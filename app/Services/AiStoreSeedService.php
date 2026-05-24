<?php

namespace App\Services;

use App\Models\AiSeedBatch;
use App\Models\AiSeedImageLibrary;
use App\Models\AiSeedProduct;
use App\Models\Banner;
use App\Models\BusinessCategory;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Design;
use App\Models\Designlist;
use App\Models\Product;
use App\Models\Slider;
use App\Models\Store;
use App\Models\Template;
use App\Models\Temposition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AiStoreSeedService
{
    private array $usedSeedImageIds = [];

    public function seed(Store $store, Customer $customer, User $user, string $launchMode = 'auto', ?array $aiPreferences = null): ?AiSeedBatch
    {
        if (!Schema::hasTable('ai_seed_batches') || !Schema::hasTable('ai_seed_products') || !Schema::hasTable('products') || !Schema::hasTable('categories')) {
            return null;
        }

        if (AiSeedBatch::query()->where('store_id', $store->id)->whereIn('status', ['pending', 'running', 'done'])->exists()) {
            return null;
        }

        $businessCategory = $this->businessCategoryForStore($store);
        $blueprint = $this->blueprint($store, $businessCategory, $launchMode, $aiPreferences);
        $imageProfile = $blueprint['product_image_profile'] ?? ['ratio' => '1:1', 'width' => 900, 'height' => 900];
        $this->usedSeedImageIds = [];

        $batch = AiSeedBatch::query()->create([
            'store_id' => $store->id,
            'mode' => $launchMode ?: 'auto',
            'business_category_id' => $businessCategory?->id,
            'image_ratio' => (string) ($imageProfile['ratio'] ?? '1:1'),
            'image_width' => (int) ($imageProfile['width'] ?? 900),
            'image_height' => (int) ($imageProfile['height'] ?? 900),
            'status' => 'running',
            'blueprint' => $blueprint,
        ]);

        try {
            $this->assignStorefrontDesign($blueprint, $store, $customer, $user, $businessCategory, $aiPreferences);
            $categoryMap = $this->createCategories($blueprint, $store, $customer, $user, $businessCategory);
            $this->createProducts($blueprint, $batch, $store, $customer, $user, $businessCategory, $categoryMap);
            $this->createSliderBanners($blueprint, $store, $customer, $user, $businessCategory);
            $this->repairMissingStoreSeedImages($batch, $store, $businessCategory);
            $batch->status = 'done';
            $batch->save();
        } catch (Throwable $e) {
            $batch->status = 'failed';
            $batch->save();
            report($e);
        }

        return $batch;
    }

    public function preview(string $storeName, ?int $businessCategoryId, int $currencyId = 1, string $launchMode = 'ai', ?array $aiPreferences = null): array
    {
        $businessCategory = $businessCategoryId
            ? BusinessCategory::query()->find($businessCategoryId)
            : null;

        $store = new Store();
        $store->id = 0;
        $store->name = $storeName ?: 'Store';
        $store->type = $businessCategory?->id ?: ($businessCategory?->name ?? '');
        $store->category_id = $businessCategory?->id;
        $store->currency = $currencyId ?: 1;

        return $this->blueprint($store, $businessCategory, $launchMode ?: 'ai', $aiPreferences);
    }

    public function generateFieldCopy(array $payload): array
    {
        $fields = collect((array) ($payload['fields'] ?? []))
            ->map(function ($field, $index) {
                $rawValue = (string) ($field['value'] ?? '');
                $constraints = $this->extractFieldWordConstraints(array_merge((array) $field, ['value' => $rawValue]));
                $normalized = [
                    'key' => (string) ($field['key'] ?? "field_{$index}"),
                    'label' => (string) ($field['label'] ?? $field['key'] ?? "Field {$index}"),
                    'value' => $this->stripFieldWordCommands($rawValue),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'placeholder' => (string) ($field['placeholder'] ?? ''),
                    'name' => (string) ($field['name'] ?? ''),
                    'section' => (string) ($field['section'] ?? ''),
                    'nearby_text' => (string) ($field['nearby_text'] ?? ''),
                ];
                if ($constraints['min']) {
                    $normalized['word_min'] = $constraints['min'];
                }
                if ($constraints['max']) {
                    $normalized['word_limit'] = $constraints['max'];
                }
                return $normalized;
            })
            ->filter(fn ($field) => trim($field['label'] . $field['value']) !== '')
            ->take(30)
            ->values()
            ->all();

        if (empty($fields)) {
            return [];
        }

        $requestPayload = [
            'mode' => (string) ($payload['mode'] ?? 'field'),
            'route' => (string) ($payload['route'] ?? ''),
            'page_title' => (string) ($payload['page_title'] ?? ''),
            'context' => (array) ($payload['context'] ?? []),
            'fields' => $fields,
        ];

        foreach ($this->botBaseUrls() as $baseUrl) {
            try {
                $response = Http::connectTimeout(15)
                    ->timeout(min(120, max(30, (int) config('services.store_create_ai_bot.timeout', 240))))
                    ->acceptJson()
                    ->post($baseUrl . '/generate-field-copy', $requestPayload);

                if ($response->successful()) {
                    $generated = (array) $response->json('fields', []);
                    $cleaned = collect($generated)
                        ->map(function ($value, $key) use ($fields) {
                            $field = collect($fields)->firstWhere('key', (string) $key) ?: [];
                            return is_scalar($value) ? $this->normalizeGeneratedFieldCopy((string) $value, $field) : '';
                        })
                        ->filter(fn ($value) => $value !== '')
                        ->all();
                    if (!empty($cleaned)) {
                        return $cleaned;
                    }
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $this->generateFieldCopyWithOpenAi($requestPayload);
    }

    private function generateFieldCopyWithOpenAi(array $payload): array
    {
        $apiKey = (string) config('services.openai.api_key');
        if ($apiKey === '') {
            return [];
        }

        $systemPrompt = implode("\n", [
            'You are the eCommerceX store creation and admin copy assistant.',
            'Generate concise, production-ready ecommerce copy for admin form fields.',
            'Understand the current page/route, page headings, section tabs, active section, nearby field text, and visible form values before writing.',
            'If a current value exists, treat it as the user hint and generate only the improved final field value.',
            'Keep button labels short. Keep titles clear. Keep descriptions/subtitles useful and natural.',
            'Do not generate unrelated generic text; match the page purpose and the field section.',
            'Never include field labels, instruction text, examples like e.g., explanations, prefixes, or quotation marks in generated values.',
            'For a label such as "Briefly describe the plan (e.g., Ideal for beginners)", return only a final value like "Ideal for beginners".',
            'If a field payload includes word_min, return at least that many words. If it includes word_limit, stay at or below that many words.',
            'If both word_min and word_limit are the same, return exactly that many words.',
            'Return only valid JSON with this exact shape: {"fields":{"field_key":"generated text"}}.',
            'Do not include markdown, explanations, or extra keys.',
        ]);

        $payload['fields'] = collect((array) ($payload['fields'] ?? []))
            ->map(function ($field) {
                $constraints = $this->extractFieldWordConstraints((array) $field);
                if ($constraints['min']) {
                    $field['word_min'] = $constraints['min'];
                }
                if ($constraints['max']) {
                    $field['word_limit'] = $constraints['max'];
                }
                return $field;
            })
            ->all();

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config('services.openai.timeout', 45))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.72,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ],
                ]);
        } catch (Throwable $e) {
            report($e);
            return [];
        }

        if (!$response->successful()) {
            return [];
        }

        $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content', ''), true);
        return collect((array) ($decoded['fields'] ?? []))
            ->map(function ($value, $key) use ($payload) {
                $field = collect((array) ($payload['fields'] ?? []))->firstWhere('key', (string) $key) ?: [];
                return is_scalar($value) ? $this->normalizeGeneratedFieldCopy((string) $value, (array) $field) : '';
            })
            ->filter(fn ($value) => $value !== '')
            ->all();
    }

    private function normalizeGeneratedFieldCopy(string $value, array $field): string
    {
        $text = trim($value);
        $label = trim((string) ($field['label'] ?? ''));
        $instruction = trim(implode(' ', array_filter([
            $label,
            (string) ($field['placeholder'] ?? ''),
            (string) ($field['nearby_text'] ?? ''),
        ])));
        $example = $this->extractFieldInstructionExample($instruction);
        $wordConstraints = $this->extractFieldWordConstraints($field);
        $lowerText = strtolower($text);

        if ($example !== '' && (
            $lowerText === strtolower($instruction)
            || str_contains($lowerText, 'e.g.')
            || str_contains($lowerText, 'example')
            || ($label !== '' && str_starts_with($lowerText, strtolower($label)))
        )) {
            return $this->enforceGeneratedFieldWords($example, $wordConstraints, $field);
        }

        if ($label !== '' && str_starts_with($lowerText, strtolower($label))) {
            $text = ltrim(substr($text, strlen($label)), " :-—–\t\n\r\0\x0B");
        }

        return $this->enforceGeneratedFieldWords(trim($text, " \t\n\r\0\x0B\"'"), $wordConstraints, $field);
    }

    private function extractFieldInstructionExample(string $instruction): string
    {
        foreach ([
            "/e\\.g\\.,?\\s*['\"]([^'\"]+)['\"]/i",
            "/e\\.g\\.,?\\s*([^\\)；;\\.\\n\\r]+)/i",
            "/example[:\\s]+['\"]([^'\"]+)['\"]/i",
            "/for example[:\\s]+['\"]([^'\"]+)['\"]/i",
        ] as $pattern) {
            if (preg_match($pattern, $instruction, $matches)) {
                return trim((string) ($matches[1] ?? ''));
            }
        }

        return '';
    }

    private function extractFieldWordLimit(array $field): ?int
    {
        return $this->extractFieldWordConstraints($field)['max'];
    }

    private function extractFieldWordConstraints(array $field): array
    {
        $explicitMin = (int) ($field['word_min'] ?? 0);
        $explicitMax = (int) ($field['word_limit'] ?? $field['word_max'] ?? 0);
        $text = strtolower(implode(' ', array_filter([
            (string) ($field['value'] ?? ''),
            (string) ($field['label'] ?? ''),
            (string) ($field['placeholder'] ?? ''),
            (string) ($field['nearby_text'] ?? ''),
        ])));

        $min = $explicitMin > 0 ? max(1, min(80, $explicitMin)) : null;
        $max = $explicitMax > 0 ? max(1, min(80, $explicitMax)) : null;

        if (!$min) {
            foreach ([
                '/\bmin(?:imum)?\s*[-:]?\s*(\d{1,3})\s*:?\b/i',
                '/\bmin-(\d{1,3})\s*:/i',
                '/(?:more than|at least|minimum|min)\s+(\d{1,3})\s+words?/i',
                '/(\d{1,3})\s+words?\s*(?:min|minimum|at least|or more)/i',
            ] as $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $min = max(1, min(80, (int) ($matches[1] ?? 0)));
                    break;
                }
            }
        }

        if (!$max) {
            foreach ([
                '/\bmax(?:imum)?\s*[-:]?\s*(\d{1,3})\s*:?\b/i',
                '/\bmax-(\d{1,3})\s*:/i',
                '/(?:within|under|max(?:imum)?|limit|up to)\s+(\d{1,3})\s+words?/i',
                '/(\d{1,3})\s+words?\s*(?:max|maximum|limit|within|under|er moddhe|er majhe|er vitore|moddhe|majhe|vitore)/i',
                '/(\d{1,3})\s*(?:টি|ta)?\s*(?:শব্দ|word)\s*(?:এর মধ্যে|এর মাঝে|er moddhe|er majhe|moddhe|majhe)?/iu',
            ] as $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $max = max(1, min(80, (int) ($matches[1] ?? 0)));
                    break;
                }
            }
        }

        if ($min && $max && $min > $max) {
            $max = $min;
        }

        return ['min' => $min, 'max' => $max];
    }

    private function limitGeneratedFieldWords(string $value, ?int $limit): string
    {
        if (!$limit) {
            return $value;
        }

        $words = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) <= $limit) {
            return $value;
        }

        return rtrim(implode(' ', array_slice($words, 0, $limit)), " ,.;:-");
    }

    private function enforceGeneratedFieldWords(string $value, array $constraints, array $field): string
    {
        $min = $constraints['min'] ?? null;
        $max = $constraints['max'] ?? null;
        $text = $this->limitGeneratedFieldWords($value, $max);
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (!$min || count($words) >= $min) {
            return $text;
        }

        $expanded = $this->expandGeneratedFieldWords($text, $min, $max, $field);
        return $this->limitGeneratedFieldWords($expanded, $max);
    }

    private function expandGeneratedFieldWords(string $value, int $min, ?int $max, array $field): string
    {
        $limit = $max;
        $text = trim($value);
        $context = trim(implode(' ', array_filter([
            (string) ($field['section'] ?? ''),
            (string) ($field['label'] ?? ''),
            (string) ($field['placeholder'] ?? ''),
        ])));
        $addons = [
            'It keeps setup simple, gives users the essentials they need, and leaves enough flexibility to grow when their business is ready.',
            'This option works well for practical ecommerce teams that want clear value, reliable features, and an easy path to upgrade later.',
            'It is designed to feel approachable, useful, and focused on helping customers start confidently without unnecessary complexity.',
        ];

        foreach ($addons as $addon) {
            $candidate = trim($text . ' ' . $addon);
            if ($context !== '' && count(preg_split('/\s+/u', $candidate, -1, PREG_SPLIT_NO_EMPTY) ?: []) < $min) {
                $candidate .= ' The copy should fit the ' . $context . ' context naturally.';
            }
            $text = $this->limitGeneratedFieldWords($candidate, $limit);
            if (count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []) >= $min) {
                return $text;
            }
        }

        return $text;
    }

    private function stripFieldWordCommands(string $value): string
    {
        $text = preg_replace('/\b(?:min|max)-\s*\d{1,3}\s*:\s*/i', '', $value) ?? $value;
        $text = preg_replace('/\b(?:more than|at least|minimum|max(?:imum)?|up to|within|under|limit)\s+\d{1,3}\s+words?\s*:\s*/i', '', $text) ?? $text;
        return trim($text);
    }

    private function blueprint(Store $store, ?BusinessCategory $businessCategory, string $launchMode, ?array $aiPreferences): array
    {
        $previewBlueprint = $aiPreferences['preview_blueprint'] ?? null;
        if (
            is_array($previewBlueprint)
            && isset($previewBlueprint['catalog_blueprint'])
            && $this->blueprintMatchesBusinessCategory($previewBlueprint, $businessCategory)
        ) {
            return $this->applyCatalogSelection($previewBlueprint, $aiPreferences);
        }

        $payload = [
            'store_id' => (int) $store->id,
            'store_name' => (string) ($store->name ?? 'Store'),
            'business_category_id' => $businessCategory?->id,
            'business_category_name' => (string) ($businessCategory?->name ?? $store->type ?? 'Fashion'),
            'package_type' => 'ecw',
            'currency' => (int) ($store->currency ?? 1),
            'launch_mode' => $launchMode ?: 'auto',
            'ai_preferences' => $aiPreferences,
            'seed_image_candidates' => $this->seedImageCandidatesForBot($businessCategory),
        ];

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }
            @ini_set('max_execution_time', '300');
            @ini_set('default_socket_timeout', '300');

            foreach ($this->botBaseUrls() as $baseUrl) {
                $response = Http::connectTimeout(25)
                    ->timeout((int) config('services.store_create_ai_bot.timeout', 240))
                    ->acceptJson()
                    ->post($baseUrl . '/generate-store-blueprint', $payload);
                if ($response->successful() && is_array($response->json())) {
                    $blueprint = $response->json();
                    $blueprint['source'] = $blueprint['source'] ?? 'bot';
                    $blueprint = $this->attachBlueprintBusinessCategoryMeta($blueprint, $businessCategory);
                    return $this->applyCatalogSelection($blueprint, $aiPreferences);
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $this->applyCatalogSelection($this->attachBlueprintBusinessCategoryMeta($this->fallbackBlueprint($payload), $businessCategory), $aiPreferences);
    }

    private function attachBlueprintBusinessCategoryMeta(array $blueprint, ?BusinessCategory $businessCategory): array
    {
        $blueprint['business_category'] = [
            'id' => $businessCategory?->id ? (int) $businessCategory->id : null,
            'name' => (string) ($businessCategory?->name ?? ''),
            'slug' => (string) ($businessCategory?->slug ?? Str::slug((string) ($businessCategory?->name ?? ''))),
            'catalog_key' => $this->catalogKeyForBusinessCategory((string) ($businessCategory?->name ?? $businessCategory?->slug ?? '')),
        ];

        return $blueprint;
    }

    private function blueprintMatchesBusinessCategory(array $blueprint, ?BusinessCategory $businessCategory): bool
    {
        if (!$businessCategory) {
            return true;
        }

        $meta = (array) ($blueprint['business_category'] ?? []);
        $expectedId = (int) $businessCategory->id;
        if ($expectedId > 0 && (int) ($meta['id'] ?? 0) === $expectedId) {
            return true;
        }

        $expectedKey = $this->catalogKeyForBusinessCategory((string) ($businessCategory->name ?? $businessCategory->slug ?? ''));
        $actualKey = trim((string) ($meta['catalog_key'] ?? ''));
        if ($actualKey !== '' && $actualKey === $expectedKey) {
            return true;
        }

        return false;
    }

    private function catalogKeyForBusinessCategory(string $categoryName): string
    {
        $categoryName = strtolower(trim($categoryName));

        return match (true) {
            str_contains($categoryName, 'ator'), str_contains($categoryName, 'attar'), str_contains($categoryName, 'itr'), str_contains($categoryName, 'perfume'), str_contains($categoryName, 'fragrance'), str_contains($categoryName, 'oud'), str_contains($categoryName, 'bakhoor'), str_contains($categoryName, 'scent') => 'fragrance',
            str_contains($categoryName, 'flower'), str_contains($categoryName, 'florist'), str_contains($categoryName, 'bouquet'), str_contains($categoryName, 'plant') => 'flowers',
            str_contains($categoryName, 'sweet'), str_contains($categoryName, 'mithai'), str_contains($categoryName, 'mishti'), str_contains($categoryName, 'dessert'), str_contains($categoryName, 'bakery'), str_contains($categoryName, 'cake') => 'sweets',
            str_contains($categoryName, 'pet'), str_contains($categoryName, 'animal'), str_contains($categoryName, 'cat'), str_contains($categoryName, 'dog'), str_contains($categoryName, 'bird'), str_contains($categoryName, 'fish') => 'pet',
            str_contains($categoryName, 'grocery'), str_contains($categoryName, 'food'), str_contains($categoryName, 'supershop') => 'grocery',
            str_contains($categoryName, 'electronic'), str_contains($categoryName, 'gadget'), str_contains($categoryName, 'mobile'), str_contains($categoryName, 'computer') => 'electronics',
            str_contains($categoryName, 'pharmacy'), str_contains($categoryName, 'medicine'), str_contains($categoryName, 'health') => 'pharmacy',
            str_contains($categoryName, 'fashion'), str_contains($categoryName, 'clothing'), str_contains($categoryName, 'apparel'), str_contains($categoryName, 'boutique') => 'fashion',
            default => 'general',
        };
    }

    private function botBaseUrls(): array
    {
        $urls = [];
        $configured = $this->normalizeBotBaseUrl((string) config('services.store_create_ai_bot.base_url'));
        if ($configured !== '') {
            $urls[] = $configured;
        }

        if (app()->environment('local')) {
            $urls[] = 'http://127.0.0.1:8091';
        }

        return array_values(array_unique(array_filter($urls)));
    }

    private function normalizeBotBaseUrl(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }

        return rtrim($baseUrl, '/');
    }

    private function applyCatalogSelection(array $blueprint, ?array $aiPreferences): array
    {
        $selectedCategories = array_values(array_filter(array_map('strval', (array) ($aiPreferences['selected_category_slugs'] ?? []))));
        $selectedSubcategories = array_values(array_filter(array_map('strval', (array) ($aiPreferences['selected_subcategory_slugs'] ?? []))));
        $selectedProducts = array_values(array_filter(array_map('strval', (array) ($aiPreferences['selected_product_keys'] ?? []))));

        if (empty($selectedCategories) && empty($selectedSubcategories) && empty($selectedProducts)) {
            return $blueprint;
        }

        $originalBlueprint = $blueprint;
        $catalog = $blueprint['catalog_blueprint'] ?? [];
        $categories = collect((array) ($catalog['categories'] ?? []));
        $subcategories = collect((array) ($catalog['subcategories'] ?? []));
        $products = collect((array) ($catalog['products'] ?? []));

        if (!empty($selectedCategories)) {
            $categories = $categories->filter(fn($item) => in_array((string) ($item['slug'] ?? ''), $selectedCategories, true))->values();
            $subcategories = $subcategories->filter(fn($item) => in_array((string) ($item['category_slug'] ?? ''), $selectedCategories, true))->values();
            $products = $products->filter(fn($item) => in_array((string) ($item['category_slug'] ?? ''), $selectedCategories, true))->values();
        }

        if (!empty($selectedSubcategories)) {
            $subcategories = $subcategories->filter(fn($item) => in_array((string) ($item['slug'] ?? ''), $selectedSubcategories, true))->values();
            $products = $products->filter(fn($item) => in_array((string) ($item['subcategory_slug'] ?? ''), $selectedSubcategories, true))->values();
        }

        if (!empty($selectedProducts)) {
            $products = $products->filter(function ($item) use ($selectedProducts) {
                $key = $this->productSelectionKey((array) $item);
                return in_array($key, $selectedProducts, true);
            })->values();
        }

        $catalog['categories'] = $categories->values()->all();
        $catalog['subcategories'] = $subcategories->values()->all();
        $catalog['products'] = $products->values()->all();
        if (empty($catalog['categories']) || empty($catalog['products'])) {
            return $originalBlueprint;
        }
        $blueprint['catalog_blueprint'] = $catalog;

        return $blueprint;
    }

    private function productSelectionKey(array $item): string
    {
        return Str::slug(
            (string) ($item['category_slug'] ?? 'category') . '-' .
            (string) ($item['subcategory_slug'] ?? 'subcategory') . '-' .
            (string) ($item['name'] ?? 'product')
        );
    }

    private function fallbackBlueprint(array $payload): array
    {
        $name = (string) ($payload['store_name'] ?? 'Store');
        $categoryName = strtolower((string) ($payload['business_category_name'] ?? 'fashion'));
        $key = $this->catalogKeyForBusinessCategory($categoryName);
        $profile = $key === 'fashion'
            ? ['ratio' => '4:5', 'width' => 900, 'height' => 1125, 'fit' => 'cover']
            : ['ratio' => '1:1', 'width' => 900, 'height' => 900, 'fit' => 'cover'];

        $catalogs = [
            'general' => [
                'categories' => ['Featured Products', 'Best Sellers', 'New Arrivals', 'Gift Items', 'Daily Essentials'],
                'subcategories' => ['Top Picks', 'Popular Items', 'Latest Items', 'Gift Sets', 'Everyday Use', 'Combo Packs'],
                'variant_type' => 'unit',
                'variant_values' => ['1 item', '2 items', 'Combo pack'],
            ],
            'fragrance' => [
                'categories' => ['Premium Attar', 'Oud Collection', 'Daily Fragrances', 'Gift Perfumes', 'Bakhoor & Incense', 'Musk Blends', 'Floral Scents', 'Citrus Fresh', 'Luxury Oils', 'Travel Roll-Ons'],
                'subcategories' => ['Classic Attar', 'Imported Attar', 'Arabic Oud', 'Cambodi Oud', 'Fresh Day Scents', 'Office Wear', 'Perfume Sets', 'Mini Gift Packs', 'Luxury Bakhoor', 'Incense Sticks', 'White Musk', 'Black Musk', 'Rose Attar', 'Jasmine Attar', 'Lemon Fresh', 'Aqua Fresh', 'Concentrated Oil', 'Signature Blend', '3ml Roll-On', 'Pocket Perfume'],
                'variant_type' => 'unit',
                'variant_values' => ['3 ml', '6 ml', '12 ml'],
            ],
            'flowers' => [
                'categories' => ['Fresh Flowers', 'Flower Bouquets', 'Roses', 'Gift Hampers', 'Indoor Plants'],
                'subcategories' => ['Daily Fresh Mix', 'Birthday Bouquets', 'Red Roses', 'Flower & Chocolate', 'Desk Plants', 'Decor Plants'],
                'variant_type' => 'unit',
                'variant_values' => ['Single', 'Small Bouquet', 'Large Bouquet'],
            ],
            'sweets' => [
                'categories' => ['Traditional Sweets', 'Premium Mithai', 'Cakes & Bakery', 'Sweet Boxes', 'Festival Specials'],
                'subcategories' => ['Rasgulla', 'Sandesh', 'Cakes', 'Assorted Boxes', 'Eid Specials', 'Sweet Hampers'],
                'variant_type' => 'unit',
                'variant_values' => ['250g', '500g', '1kg'],
            ],
            'pet' => [
                'categories' => ['Pet Food', 'Pet Treats', 'Cat Care', 'Dog Care', 'Pet Grooming'],
                'subcategories' => ['Dry Food', 'Training Treats', 'Cat Litter', 'Leashes & Collars', 'Shampoo', 'Pet Toys'],
                'variant_type' => 'unit',
                'variant_values' => ['Small', 'Medium', 'Large'],
            ],
            'fashion' => [
                'categories' => ['Women Clothing', 'Men Clothing', 'Accessories'],
                'subcategories' => ['Dresses', 'Tops', 'Shirts', 'Panjabi', 'Bags', 'Sunglasses'],
                'variant_type' => 'color_size',
                'variant_values' => ['Black / M', 'Pink / L', 'White / S'],
            ],
            'electronics' => [
                'categories' => ['Mobile Accessories', 'Audio', 'Smart Gadgets'],
                'subcategories' => ['Cases', 'Chargers', 'Earbuds', 'Headphones', 'Smart Watch', 'Power Bank'],
                'variant_type' => 'color',
                'variant_values' => ['Black', 'White', 'Blue'],
            ],
            'grocery' => [
                'categories' => ['Fresh Food', 'Pantry', 'Household'],
                'subcategories' => ['Fruits', 'Vegetables', 'Rice & Dal', 'Spices', 'Cleaning', 'Kitchen'],
                'variant_type' => 'unit',
                'variant_values' => ['500g', '1kg', '2kg'],
            ],
            'pharmacy' => [
                'categories' => ['Health Care', 'Personal Care', 'Baby Care'],
                'subcategories' => ['Supplements', 'First Aid', 'Skin Care', 'Hair Care', 'Diapers', 'Baby Food'],
                'variant_type' => 'unit',
                'variant_values' => ['1 pack', '2 pack', 'Family pack'],
            ],
        ];
        $source = $catalogs[$key];

        $categories = collect($source['categories'])->map(fn($label) => [
            'name' => $label,
            'slug' => Str::slug($label),
            'reason' => 'Fallback matched category',
        ])->values()->all();
        $subcategories = collect($source['subcategories'])->map(function ($label, $index) use ($categories) {
            $parent = $categories[$index % count($categories)];
            return ['category_slug' => $parent['slug'], 'name' => $label, 'slug' => Str::slug($label)];
        })->values()->all();
        $products = collect(range(1, 25))->map(function ($index) use ($name, $key, $subcategories, $source) {
            $sub = $subcategories[($index - 1) % count($subcategories)];
            $productName = 'Starter ' . $sub['name'] . ' ' . $index;
            $regular = 500 + ($index * 170);
            return [
                'name' => $productName,
                'category_slug' => $sub['category_slug'],
                'subcategory_slug' => $sub['slug'],
                'short_description' => "{$productName} prepared for {$name}.",
                'description' => "{$productName} is a clean starter product generated for {$name}.",
                'regular_price' => $regular,
                'promotional_price' => max(0, $regular - 100),
                'stock' => 20 + $index,
                'variant_type' => $source['variant_type'],
                'variant_values' => $source['variant_values'],
                'image_tags' => [$key, $sub['category_slug'], $sub['slug']],
            ];
        })->values()->all();

        return [
            'source' => 'static',
            'style_profile' => $key === 'fashion' ? 'premium-brand' : 'modern-clean',
            'product_image_profile' => $profile,
            'catalog_blueprint' => [
                'categories' => $categories,
                'subcategories' => $subcategories,
                'products' => $products,
                'slider_banners' => [
                    ['usage_type' => 'slider', 'image_tags' => [$key, 'slider'], 'headline' => "{$name} Collection", 'subheadline' => 'Ready storefront for your business.', 'cta' => 'Shop Now'],
                    ['usage_type' => 'banner', 'image_tags' => [$key, 'banner'], 'headline' => 'Fresh arrivals', 'subheadline' => 'Curated starter products.', 'cta' => 'Explore'],
                ],
            ],
            'notes' => ['mode' => (string) ($payload['launch_mode'] ?? 'auto'), 'reason' => 'Laravel fallback blueprint'],
        ];
    }

    private function assignStorefrontDesign(array $blueprint, Store $store, Customer $customer, User $user, ?BusinessCategory $businessCategory, ?array $aiPreferences): void
    {
        if (!Schema::hasTable('designs')) {
            return;
        }

        $template = $this->chooseStoreTemplate($blueprint, $store, $businessCategory, $aiPreferences);
        $sectionValues = $template ? $this->templateSectionValues($template) : [];
        $sectionValues = $this->varyTemplateSectionValues($sectionValues, $blueprint, $store, $businessCategory, $aiPreferences, $template);
        $sectionValues = $this->fillMissingSectionValuesFromDesignlists($sectionValues, $blueprint, $store, $businessCategory, $aiPreferences, $template);

        if (!$template && empty(array_filter($sectionValues))) {
            return;
        }

        $design = Design::query()->where('store_id', $store->id)->first() ?: new Design();
        $this->setIfColumn($design, 'store_id', (string) $store->id);
        $this->setIfColumn($design, 'uid', (string) $user->id);
        $this->setIfColumn($design, 'customer_id', (string) $customer->id);
        $this->setIfColumn($design, 'creator', (string) $user->id);
        $this->setIfColumn($design, 'editor', (string) $user->id);
        $colors = $this->storefrontHeaderColors($blueprint, $store, $businessCategory, $aiPreferences);
        $this->setIfColumn($design, 'header_color', $colors['header_color']);
        $this->setIfColumn($design, 'text_color', $colors['text_color']);
        $this->setHeaderSectionColors($design, $colors);
        if ($template) {
            $this->setIfColumn($design, 'template_id', (string) $template->id);
        }

        foreach ($this->designSectionColumnMap() as $section => $column) {
            if (array_key_exists($section, $sectionValues)) {
                $this->setIfColumn($design, $column, $sectionValues[$section]);
            }
        }
        $this->setGeneratedSectionSettings($design, $blueprint, $store, $businessCategory, $aiPreferences, $sectionValues);

        $design->save();

        if ($template) {
            $this->setIfColumn($store, 'template_id', (string) $template->id);
            $store->save();
            $this->setIfColumn($customer, 'template_id', (string) $template->id);
            $customer->save();
        }

        $this->syncTemplatePositionsToStore((int) $store->id, $template);
    }

    private function storefrontHeaderColors(array $blueprint, Store $store, ?BusinessCategory $businessCategory, ?array $aiPreferences): array
    {
        $seedHeaderColor = $this->normalizeHexColor(
            (string) (
                $blueprint['header_color']
                ?? data_get($blueprint, 'design_blueprint.header_color')
                ?? data_get($blueprint, 'design.header_color')
                ?? data_get($aiPreferences, 'header_color')
                ?? ''
            )
        );
        $seedTextColor = $this->normalizeHexColor(
            (string) (
                $blueprint['text_color']
                ?? data_get($blueprint, 'design_blueprint.text_color')
                ?? data_get($blueprint, 'design.text_color')
                ?? data_get($aiPreferences, 'text_color')
                ?? ''
            )
        );

        $generated = $this->generateStorefrontHeaderColorsWithOpenAi(
            $blueprint,
            $store,
            $businessCategory,
            $aiPreferences,
            ['header_color' => $seedHeaderColor, 'text_color' => $seedTextColor]
        );
        $headerColor = $this->customHeaderPaletteColor((string) ($generated['header_color'] ?? ''))
            ?: $this->customHeaderPaletteColor($seedHeaderColor);
        $textColor = $this->normalizeHexColor((string) ($generated['text_color'] ?? '')) ?: $seedTextColor;

        if ($headerColor === '') {
            $headerColor = $this->fallbackHeaderColor($blueprint, $businessCategory, $aiPreferences);
        }
        $headerColor = $this->categorySafeHeaderColor($headerColor, $businessCategory, $blueprint, $aiPreferences);

        if ($textColor === '' || $this->contrastRatio($headerColor, $textColor) < 4.5) {
            $textColor = $this->bestReadableTextColor($headerColor);
        }

        return [
            'header_color' => $headerColor,
            'text_color' => $textColor,
        ];
    }

    private function generateStorefrontHeaderColorsWithOpenAi(array $blueprint, Store $store, ?BusinessCategory $businessCategory, ?array $aiPreferences, array $seedColors = []): array
    {
        $apiKey = (string) config('services.openai.api_key');
        if ($apiKey === '') {
            return [];
        }

        $payload = [
            'store_name' => (string) ($store->name ?? 'Store'),
            'business_category' => [
                'id' => $businessCategory?->id ? (int) $businessCategory->id : null,
                'name' => (string) ($businessCategory?->name ?? $store->type ?? ''),
                'slug' => (string) ($businessCategory?->slug ?? ''),
            ],
            'style_profile' => (string) ($blueprint['style_profile'] ?? data_get($blueprint, 'design_blueprint.style_profile', 'modern-clean')),
            'tone_preset' => (string) data_get($aiPreferences, 'tone_preset', ''),
            'primary_goal' => (string) data_get($aiPreferences, 'primary_goal', ''),
            'brand_colors_hint' => (string) data_get($aiPreferences, 'brand_colors', ''),
            'allowed_header_colors' => $this->customHeaderColorPalette(),
            'recommended_header_colors_for_category' => $this->categoryHeaderColorPalette($businessCategory),
            'suggested_header_color' => (string) ($seedColors['header_color'] ?? ''),
            'suggested_text_color' => (string) ($seedColors['text_color'] ?? ''),
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config('services.openai.timeout', 45))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.75,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => implode("\n", [
                                'You generate ecommerce storefront header colors.',
                                'Return only JSON with header_color and text_color.',
                                'Both values must be valid 6-digit hex colors.',
                                'header_color must be one of the allowed_header_colors from the user custom palette.',
                                'Prefer recommended_header_colors_for_category when present.',
                                'Choose a distinctive brand-appropriate header color from the store name, business category, style, tone, and goal.',
                                'Do not return plain white or plain black as header_color unless explicitly requested by a brand color hint.',
                                'text_color must have at least WCAG AA contrast on header_color.',
                            ]),
                        ],
                        ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ],
                ]);
        } catch (Throwable $e) {
            report($e);
            return [];
        }

        if (!$response->successful()) {
            return [];
        }

        $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content', ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function fallbackHeaderColor(array $blueprint, ?BusinessCategory $businessCategory, ?array $aiPreferences): string
    {
        $hint = strtolower(trim(implode(' ', array_filter([
            (string) data_get($aiPreferences, 'brand_colors', ''),
            (string) ($businessCategory?->name ?? ''),
            (string) ($businessCategory?->slug ?? ''),
            (string) ($blueprint['style_profile'] ?? ''),
        ]))));

        if (preg_match('/#(?:[0-9a-f]{3}){1,2}\b/i', $hint, $matches)) {
            $color = $this->customHeaderPaletteColor($matches[0]);
            if ($color !== '') {
                return $color;
            }
        }

        $named = [
            'perfume' => '#7C3AED',
            'fragrance' => '#7C3AED',
            'attar' => '#7C3AED',
            'flower' => '#F43F5E',
            'sweet' => '#F59E0B',
            'bakery' => '#F59E0B',
            'pet' => '#10B981',
            'grocery' => '#10B981',
            'food' => '#10B981',
            'electronic' => '#4F7BD9',
            'gadget' => '#4F7BD9',
            'pharmacy' => '#10B981',
            'health' => '#10B981',
            'fashion' => '#F43F5E',
            'boutique' => '#F43F5E',
            'premium' => '#7C3AED',
            'bold' => '#F59E0B',
            'minimal' => '#334155',
        ];

        foreach ($named as $needle => $color) {
            if (str_contains($hint, $needle)) {
                return $this->customHeaderPaletteColor($color) ?: '#4F7BD9';
            }
        }

        $palette = $this->customHeaderColorPalette();
        $seed = (string) ($businessCategory?->id ?? '') . '|' . (string) data_get($aiPreferences, 'style_preset', '') . '|' . (string) data_get($aiPreferences, 'primary_goal', '');
        return $palette[(int) (abs(crc32($seed)) % count($palette))] ?? '#4F7BD9';
    }

    private function customHeaderColorPalette(): array
    {
        return ['#4F7BD9', '#10B981', '#F43F5E', '#F59E0B', '#7C3AED', '#334155'];
    }

    private function customHeaderPaletteColor(string $color): string
    {
        $color = $this->normalizeHexColor($color);
        if ($color === '') {
            return '';
        }

        return in_array($color, $this->customHeaderColorPalette(), true) ? $color : '';
    }

    private function categoryHeaderColorPalette(?BusinessCategory $businessCategory): array
    {
        $categoryKey = $this->catalogKeyForBusinessCategory((string) ($businessCategory?->name ?? $businessCategory?->slug ?? ''));
        return match ($categoryKey) {
            'pharmacy', 'grocery', 'pet' => ['#10B981', '#334155', '#4F7BD9'],
            'sweets' => ['#F59E0B', '#F43F5E', '#7C3AED'],
            'flowers', 'fashion' => ['#F43F5E', '#7C3AED', '#4F7BD9'],
            'electronics' => ['#4F7BD9', '#334155', '#7C3AED'],
            'fragrance' => ['#7C3AED', '#334155', '#F59E0B'],
            default => $this->customHeaderColorPalette(),
        };
    }

    private function categorySafeHeaderColor(string $color, ?BusinessCategory $businessCategory, array $blueprint, ?array $aiPreferences): string
    {
        $allowed = $this->categoryHeaderColorPalette($businessCategory);
        if (in_array($color, $allowed, true)) {
            return $color;
        }

        $fallback = $this->fallbackHeaderColor($blueprint, $businessCategory, $aiPreferences);
        return in_array($fallback, $allowed, true) ? $fallback : ($allowed[0] ?? '#4F7BD9');
    }

    private function setHeaderSectionColors(Design $design, array $colors): void
    {
        if (!Schema::hasColumn('designs', 'section_settings')) {
            return;
        }

        $settings = [];
        $raw = $design->section_settings ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $settings = is_array($decoded) ? $decoded : [];
        } elseif (is_array($raw)) {
            $settings = $raw;
        }

        $header = is_array($settings['header'] ?? null) ? $settings['header'] : [];
        $header['header_color'] = $colors['header_color'];
        $header['text_color'] = $colors['text_color'];
        $settings['header'] = $header;
        $design->section_settings = json_encode($settings);
    }

    private function setGeneratedSectionSettings(Design $design, array $blueprint, Store $store, ?BusinessCategory $businessCategory, ?array $aiPreferences, array $sectionValues): void
    {
        if (!Schema::hasColumn('designs', 'section_settings')) {
            return;
        }

        $settings = $this->decodeSectionSettings($design->section_settings ?? null);
        $generated = $this->storefrontSectionCopy($blueprint, $store, $businessCategory, $aiPreferences);

        foreach ($this->sectionSettingsTargets() as $frontendKey => $target) {
            $section = is_array($settings[$frontendKey] ?? null) ? $settings[$frontendKey] : [];
            $copy = is_array($generated[$frontendKey] ?? null) ? $generated[$frontendKey] : [];
            $backendKey = (string) ($target['backend_key'] ?? $frontendKey);
            $group = (string) ($target['group'] ?? 'content');

            $section['show_heading'] = array_key_exists('show_heading', $section) ? (bool) $section['show_heading'] : true;
            $section['show_on_mobile'] = array_key_exists('show_on_mobile', $section) ? (bool) $section['show_on_mobile'] : true;
            $section['boxed'] = array_key_exists('boxed', $section) ? (bool) $section['boxed'] : false;
            $section['compact_mode'] = array_key_exists('compact_mode', $section) ? (bool) $section['compact_mode'] : false;
            $section['headline'] = $this->cleanSectionCopy((string) ($copy['headline'] ?? ''), 64)
                ?: (string) ($target['fallback_headline'] ?? Str::headline($frontendKey));
            $section['helper_text'] = $this->cleanSectionCopy((string) ($copy['helper_text'] ?? ''), 140)
                ?: (string) ($target['fallback_helper'] ?? '');

            if ($this->sectionValueIsUsable($sectionValues[$backendKey] ?? null)) {
                $section['template'] = (string) $sectionValues[$backendKey];
            }

            if ($group === 'product') {
                $section['columns'] = (string) ($section['columns'] ?? '4');
                $section['card_style'] = (string) ($section['card_style'] ?? 'grid');
                $section['section_theme'] = (string) ($section['section_theme'] ?? 'soft');
                $section['show_view_all'] = array_key_exists('show_view_all', $section) ? (bool) $section['show_view_all'] : true;
                $section['view_all_label'] = $this->cleanSectionCopy((string) ($copy['view_all_label'] ?? ''), 24) ?: 'View all';
            } elseif ($group === 'promo') {
                $section['columns'] = (string) ($section['columns'] ?? '3');
                $section['content_align'] = (string) ($section['content_align'] ?? 'left');
                $section['section_theme'] = (string) ($section['section_theme'] ?? 'soft');
                $section['show_badge'] = array_key_exists('show_badge', $section) ? (bool) $section['show_badge'] : true;
                $section['primary_button_label'] = $this->cleanSectionCopy((string) ($copy['primary_button_label'] ?? ''), 24) ?: 'Explore';
            } else {
                $section['content_align'] = (string) ($section['content_align'] ?? 'left');
                $section['section_theme'] = (string) ($section['section_theme'] ?? 'minimal');
                if (in_array($frontendKey, ['about', 'blog', 'youtube', 'footer'], true)) {
                    $section['primary_button_label'] = $this->cleanSectionCopy((string) ($copy['primary_button_label'] ?? ''), 24) ?: 'Learn more';
                }
            }

            $settings[$frontendKey] = $section;
        }

        $design->section_settings = json_encode($settings);
    }

    private function decodeSectionSettings($raw): array
    {
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    private function storefrontSectionCopy(array $blueprint, Store $store, ?BusinessCategory $businessCategory, ?array $aiPreferences): array
    {
        $fallback = $this->fallbackSectionCopy($store, $businessCategory);
        $generated = $this->generateStorefrontSectionCopyWithOpenAi($blueprint, $store, $businessCategory, $aiPreferences);

        foreach ($generated as $key => $copy) {
            if (!isset($fallback[$key]) || !is_array($copy)) {
                continue;
            }
            $headline = $this->cleanSectionCopy((string) ($copy['headline'] ?? ''), 64);
            if ($headline !== '' && !$this->sectionCopyIsCategorySafe($headline, $businessCategory)) {
                $headline = '';
            }
            $fallback[$key] = array_merge($fallback[$key], array_filter([
                'headline' => $headline,
                'helper_text' => $this->cleanSectionCopy((string) ($copy['helper_text'] ?? ''), 140),
                'view_all_label' => $this->cleanSectionCopy((string) ($copy['view_all_label'] ?? ''), 24),
                'primary_button_label' => $this->cleanSectionCopy((string) ($copy['primary_button_label'] ?? ''), 24),
            ], fn ($value) => $value !== ''));
        }

        return $fallback;
    }

    private function generateStorefrontSectionCopyWithOpenAi(array $blueprint, Store $store, ?BusinessCategory $businessCategory, ?array $aiPreferences): array
    {
        $apiKey = (string) config('services.openai.api_key');
        if ($apiKey === '') {
            return [];
        }

        $catalog = (array) ($blueprint['catalog_blueprint'] ?? []);
        $targets = collect($this->sectionSettingsTargets())
            ->map(fn ($target, $key) => [
                'key' => $key,
                'label' => (string) ($target['label'] ?? Str::headline((string) $key)),
                'purpose' => (string) ($target['purpose'] ?? ''),
            ])
            ->values()
            ->all();
        $payload = [
            'store_name' => (string) ($store->name ?? 'Store'),
            'business_category' => [
                'name' => (string) ($businessCategory?->name ?? $store->type ?? ''),
                'slug' => (string) ($businessCategory?->slug ?? ''),
                'catalog_key' => $this->catalogKeyForBusinessCategory((string) ($businessCategory?->name ?? $businessCategory?->slug ?? $store->type ?? '')),
            ],
            'category_copy_rules' => $this->sectionCopyCategoryRules($businessCategory, $store),
            'style_profile' => (string) ($blueprint['style_profile'] ?? data_get($blueprint, 'design_blueprint.style_profile', 'modern-clean')),
            'tone_preset' => (string) data_get($aiPreferences, 'tone_preset', ''),
            'primary_goal' => (string) data_get($aiPreferences, 'primary_goal', ''),
            'categories' => collect((array) ($catalog['categories'] ?? []))->pluck('name')->filter()->take(8)->values()->all(),
            'products' => collect((array) ($catalog['products'] ?? []))->pluck('name')->filter()->take(10)->values()->all(),
            'sections' => $targets,
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config('services.openai.timeout', 45))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
                    'temperature' => 0.78,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => implode("\n", [
                                'You generate ecommerce storefront section copy.',
                                'Return only JSON with this shape: {"sections":{"section_key":{"headline":"...","helper_text":"...","view_all_label":"...","primary_button_label":"..."}}}.',
                                'Generate every section_key from the payload and do not invent extra keys.',
                                'Each headline must be unique, customer-facing, interactive, and 2 to 6 words.',
                                'Write headlines like inviting storefront copy shoppers would click, not admin section names.',
                                'Prefer active, benefit-led words such as discover, find, shop, explore, trusted, fresh, ready, selected, or recommended.',
                                'Each helper_text must be unique, natural, and 8 to 16 words with a clear shopper benefit.',
                                'Strictly match the store name, business category, product catalog, style, tone, goal, and category_copy_rules.',
                                'Avoid generic repeated titles such as Featured Products, Product Details, Latest Stories, or Best Sellers unless the category makes them feel specific.',
                                'For medicine, pharmacy, health, wellness, supplement, medical, or care stores, avoid romantic, gift-like, playful, or emotional wording such as love, loved, favorites, perfect match, treat yourself, or picked for you.',
                                'For medicine/pharmacy/health stores, use professional shopping language around health essentials, trusted care, wellness needs, pharmacy picks, daily care, and safe checkout.',
                                'Button labels must be 1 to 3 words and action-oriented.',
                            ]),
                        ],
                        ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ],
                ]);
        } catch (Throwable $e) {
            report($e);
            return [];
        }

        if (!$response->successful()) {
            return [];
        }

        $decoded = json_decode((string) data_get($response->json(), 'choices.0.message.content', ''), true);
        return is_array($decoded['sections'] ?? null) ? $decoded['sections'] : [];
    }

    private function fallbackSectionCopy(Store $store, ?BusinessCategory $businessCategory): array
    {
        $category = trim((string) ($businessCategory?->name ?? $store->type ?? 'products'));
        $categoryLabel = $category !== '' ? Str::headline($category) : 'Products';
        $categoryKey = $this->catalogKeyForBusinessCategory((string) ($businessCategory?->name ?? $businessCategory?->slug ?? $store->type ?? ''));

        if ($categoryKey === 'pharmacy') {
            return [
                'slider' => ['headline' => "Shop {$store->name}", 'helper_text' => 'Find health essentials, daily care items, and trusted pharmacy products.', 'primary_button_label' => 'Shop now'],
                'banner' => ['headline' => 'Care Deals Today', 'helper_text' => 'Explore timely savings on everyday wellness and care products.', 'primary_button_label' => 'Explore'],
                'banner-bottom' => ['headline' => 'Find Daily Care', 'helper_text' => 'Browse practical health needs grouped for quick, confident shopping.', 'primary_button_label' => 'Browse'],
                'announcement' => ['headline' => 'Health Store Updates', 'helper_text' => 'Check important notices, availability updates, and active care offers.', 'primary_button_label' => 'View'],
                'feature-category' => ['headline' => 'Shop Health Needs', 'helper_text' => 'Move through wellness, personal care, and pharmacy categories faster.', 'view_all_label' => 'View all'],
                'product' => ['headline' => 'Health Essentials', 'helper_text' => 'Browse practical products selected for everyday care and wellness needs.', 'view_all_label' => 'View all'],
                'feature-product' => ['headline' => 'Trusted Care Picks', 'helper_text' => 'Review highlighted items for daily health, comfort, and personal care.', 'view_all_label' => 'Explore'],
                'best-sell-product' => ['headline' => 'Most Chosen Care', 'helper_text' => 'See frequently selected wellness products shoppers rely on regularly.', 'view_all_label' => 'Shop care'],
                'new-arrival' => ['headline' => 'New Health Arrivals', 'helper_text' => 'Check newly added care products, wellness items, and pharmacy essentials.', 'view_all_label' => 'See new'],
                'testimonial' => ['headline' => 'Trusted by Shoppers', 'helper_text' => 'Read feedback that helps health shoppers buy with confidence.'],
                'youtube' => ['headline' => 'Learn Before You Buy', 'helper_text' => 'Watch helpful product explainers, care tips, and store updates.', 'primary_button_label' => 'Watch'],
                'about' => ['headline' => 'Know This Pharmacy', 'helper_text' => 'Learn what care products we provide and how we serve shoppers.', 'primary_button_label' => 'Read more'],
                'newsletter' => ['headline' => 'Get Care Updates', 'helper_text' => 'Hear about health arrivals, stock updates, and useful wellness offers.', 'primary_button_label' => 'Subscribe'],
                'brand' => ['headline' => 'Trusted Health Brands', 'helper_text' => 'Browse reliable brands and care collections chosen for everyday needs.'],
                'blog' => ['headline' => 'Read Wellness Guides', 'helper_text' => 'Find practical care tips and product guidance before shopping.', 'primary_button_label' => 'Read'],
                'offer' => ['headline' => 'Save on Care', 'helper_text' => 'Catch timely savings on daily wellness and personal care products.', 'primary_button_label' => 'Claim offer'],
                'footer' => ['headline' => 'Need Store Help?', 'helper_text' => 'Find support, policies, and useful links for your health order.', 'primary_button_label' => 'Contact'],
                'shop-page' => ['headline' => 'Browse Health Products', 'helper_text' => 'Filter and choose care products with clearer shopping context.'],
                'single-product-page' => ['headline' => 'Review Care Details', 'helper_text' => 'Check key product information before adding this item to cart.'],
                'checkout-page' => ['headline' => 'Complete Secure Checkout', 'helper_text' => 'Finish your health order through a clear and focused checkout.'],
                'login-page' => ['headline' => 'Access Your Account', 'helper_text' => 'Review orders, addresses, and saved details without slowing down.'],
                'product-card' => ['headline' => 'Quick Care Preview', 'helper_text' => 'Scan prices, images, and product actions while browsing.'],
            ];
        }

        return [
            'slider' => ['headline' => "Discover {$store->name}", 'helper_text' => "Start with curated {$categoryLabel} picks made for easy shopping.", 'primary_button_label' => 'Shop now'],
            'banner' => ['headline' => 'Grab Fresh Deals', 'helper_text' => "Explore timely offers across the {$categoryLabel} picks shoppers ask for most.", 'primary_button_label' => 'Explore'],
            'banner-bottom' => ['headline' => 'Find More Favorites', 'helper_text' => 'Browse useful picks grouped for quick decisions and easy checkout.', 'primary_button_label' => 'Browse'],
            'announcement' => ['headline' => 'Do Not Miss This', 'helper_text' => 'Catch important launches, notices, and active offers before they end.', 'primary_button_label' => 'View'],
            'feature-category' => ['headline' => 'Choose Your Category', 'helper_text' => "Jump into curated {$categoryLabel} collections and find the right fit faster.", 'view_all_label' => 'View all'],
            'product' => ['headline' => 'Pick What You Love', 'helper_text' => 'Discover reliable products selected to make shopping quicker and easier.', 'view_all_label' => 'View all'],
            'feature-product' => ['headline' => 'Explore Our Favorites', 'helper_text' => 'Handpicked highlights that help shoppers spot the strongest catalog choices.', 'view_all_label' => 'Explore'],
            'best-sell-product' => ['headline' => 'Shop Customer Favorites', 'helper_text' => 'Trusted choices picked often by shoppers who know what works.', 'view_all_label' => 'Shop best'],
            'new-arrival' => ['headline' => 'See What Is New', 'helper_text' => 'Freshly added items ready to make every visit feel worth checking.', 'view_all_label' => 'See new'],
            'testimonial' => ['headline' => 'See Why They Trust Us', 'helper_text' => 'Read real feedback that helps new shoppers buy with confidence.'],
            'youtube' => ['headline' => 'Watch Before You Shop', 'helper_text' => 'Explore product stories, demos, and brand moments in a richer format.', 'primary_button_label' => 'Watch'],
            'about' => ['headline' => 'Know Your Store', 'helper_text' => 'Learn what we sell, why it matters, and how we serve you.', 'primary_button_label' => 'Read more'],
            'newsletter' => ['headline' => 'Get First Updates', 'helper_text' => 'Hear about launches, offers, and useful product news before others.', 'primary_button_label' => 'Subscribe'],
            'brand' => ['headline' => 'Shop Trusted Brands', 'helper_text' => 'Browse trusted names and collections chosen to make buying easier.'],
            'blog' => ['headline' => 'Read Helpful Guides', 'helper_text' => 'Find buying tips, updates, and inspiration before choosing your next item.', 'primary_button_label' => 'Read'],
            'offer' => ['headline' => 'Claim Today’s Offer', 'helper_text' => 'Catch timely savings and bundles before you move to checkout.', 'primary_button_label' => 'Claim offer'],
            'footer' => ['headline' => 'Need Help Shopping?', 'helper_text' => 'Find support, policies, and helpful links whenever you need them.', 'primary_button_label' => 'Contact'],
            'shop-page' => ['headline' => 'Find Your Next Pick', 'helper_text' => 'Filter, compare, and choose the right product with less effort.'],
            'single-product-page' => ['headline' => 'Ready to Buy?', 'helper_text' => 'Review the details that matter before adding this item to cart.'],
            'checkout-page' => ['headline' => 'Finish Your Order', 'helper_text' => 'Complete your purchase through a clear, focused, and secure checkout.'],
            'login-page' => ['headline' => 'Welcome Back Shopper', 'helper_text' => 'Access orders, addresses, and saved details without slowing down.'],
            'product-card' => ['headline' => 'Quick Product Preview', 'helper_text' => 'Scan prices, images, and actions quickly while browsing the store.'],
        ];
    }

    private function sectionCopyCategoryRules(?BusinessCategory $businessCategory, Store $store): array
    {
        $categoryKey = $this->catalogKeyForBusinessCategory((string) ($businessCategory?->name ?? $businessCategory?->slug ?? $store->type ?? ''));
        if ($categoryKey === 'pharmacy') {
            return [
                'voice' => 'professional, clear, trust-first, health-shopping focused',
                'use_words' => ['health essentials', 'trusted care', 'wellness needs', 'daily care', 'pharmacy products', 'personal care'],
                'avoid_words' => ['love', 'loved', 'favorites', 'perfect match', 'treat yourself', 'picked for you', 'gift', 'romantic'],
            ];
        }

        return [
            'voice' => 'customer-facing, category-specific, natural ecommerce copy',
            'use_words' => ['discover', 'find', 'shop', 'explore', 'trusted', 'selected', 'new'],
            'avoid_words' => ['generic section names', 'duplicate titles', 'admin labels'],
        ];
    }

    private function sectionCopyIsCategorySafe(string $headline, ?BusinessCategory $businessCategory): bool
    {
        $categoryKey = $this->catalogKeyForBusinessCategory((string) ($businessCategory?->name ?? $businessCategory?->slug ?? ''));
        if ($categoryKey !== 'pharmacy') {
            return true;
        }

        $normalized = strtolower($headline);
        foreach (['love', 'loved', 'favorite', 'favourite', 'perfect match', 'treat yourself', 'picked for you', 'gift'] as $blocked) {
            if (str_contains($normalized, $blocked)) {
                return false;
            }
        }

        return true;
    }

    private function sectionSettingsTargets(): array
    {
        return [
            'slider' => ['backend_key' => 'hero_slider', 'group' => 'promo', 'label' => 'Hero slider', 'purpose' => 'top storefront campaign and primary shopping CTA'],
            'banner' => ['backend_key' => 'banner', 'group' => 'promo', 'label' => 'Top banner', 'purpose' => 'promotional merchandising strip'],
            'banner-bottom' => ['backend_key' => 'banner_bottom', 'group' => 'promo', 'label' => 'Bottom banner', 'purpose' => 'secondary promotional area'],
            'announcement' => ['backend_key' => 'announcement', 'group' => 'promo', 'label' => 'Announcement', 'purpose' => 'short storewide announcement or notice'],
            'feature-category' => ['backend_key' => 'feature_category', 'group' => 'product', 'label' => 'Featured categories', 'purpose' => 'category discovery section'],
            'product' => ['backend_key' => 'product', 'group' => 'product', 'label' => 'Product section', 'purpose' => 'main all-products merchandising section'],
            'feature-product' => ['backend_key' => 'feature_product', 'group' => 'product', 'label' => 'Featured products', 'purpose' => 'curated highlighted products'],
            'best-sell-product' => ['backend_key' => 'best_sell_product', 'group' => 'product', 'label' => 'Best-selling products', 'purpose' => 'trust-building top sellers'],
            'new-arrival' => ['backend_key' => 'new_arrival', 'group' => 'product', 'label' => 'New arrivals', 'purpose' => 'fresh catalog additions'],
            'testimonial' => ['backend_key' => 'testimonial', 'group' => 'content', 'label' => 'Testimonials', 'purpose' => 'customer proof and credibility'],
            'youtube' => ['backend_key' => 'youtube', 'group' => 'content', 'label' => 'YouTube video', 'purpose' => 'video storytelling and product education'],
            'about' => ['backend_key' => 'about', 'group' => 'content', 'label' => 'About section', 'purpose' => 'brand and store introduction'],
            'newsletter' => ['backend_key' => 'newsletter', 'group' => 'content', 'label' => 'Newsletter', 'purpose' => 'email signup section'],
            'brand' => ['backend_key' => 'brand', 'group' => 'content', 'label' => 'Brand section', 'purpose' => 'brand logo or supplier trust section'],
            'blog' => ['backend_key' => 'blog', 'group' => 'content', 'label' => 'Blog section', 'purpose' => 'content and buying guide section'],
            'offer' => ['backend_key' => 'offer', 'group' => 'promo', 'label' => 'Offer section', 'purpose' => 'special deals and conversion pushes'],
            'footer' => ['backend_key' => 'footer', 'group' => 'content', 'label' => 'Footer', 'purpose' => 'support and closing navigation'],
            'shop-page' => ['backend_key' => 'shop_page', 'group' => 'content', 'label' => 'Shop page', 'purpose' => 'product listing page heading'],
            'single-product-page' => ['backend_key' => 'single_product_page', 'group' => 'content', 'label' => 'Single product page', 'purpose' => 'product detail page heading'],
            'checkout-page' => ['backend_key' => 'checkout_page', 'group' => 'content', 'label' => 'Checkout page', 'purpose' => 'checkout page heading'],
            'login-page' => ['backend_key' => 'login_page', 'group' => 'content', 'label' => 'Login page', 'purpose' => 'customer account login heading'],
            'product-card' => ['backend_key' => 'product_card', 'group' => 'content', 'label' => 'Product card', 'purpose' => 'product card copy settings'],
        ];
    }

    private function cleanSectionCopy(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?: '');
        $text = trim($text, "\"'`“”‘’");
        if ($text === '') {
            return '';
        }

        return Str::limit($text, $limit, '');
    }

    private function normalizeHexColor(string $color): string
    {
        $color = trim($color);
        if ($color === '') {
            return '';
        }
        if (!str_starts_with($color, '#')) {
            $color = '#' . $color;
        }
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $color, $matches)) {
            $short = $matches[1];
            return '#' . strtoupper($short[0] . $short[0] . $short[1] . $short[1] . $short[2] . $short[2]);
        }
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtoupper($color);
        }

        return '';
    }

    private function bestReadableTextColor(string $background): string
    {
        $blackContrast = $this->contrastRatio($background, '#111827');
        $whiteContrast = $this->contrastRatio($background, '#FFFFFF');
        return $blackContrast >= $whiteContrast ? '#111827' : '#FFFFFF';
    }

    private function contrastRatio(string $first, string $second): float
    {
        $firstLum = $this->relativeLuminance($first);
        $secondLum = $this->relativeLuminance($second);
        $lighter = max($firstLum, $secondLum);
        $darker = min($firstLum, $secondLum);
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $color): float
    {
        $rgb = $this->hexToRgb($color);
        $channels = array_map(function ($channel) {
            $value = $channel / 255;
            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }

    private function hexToRgb(string $color): array
    {
        $color = $this->normalizeHexColor($color) ?: '#000000';
        return [
            hexdec(substr($color, 1, 2)),
            hexdec(substr($color, 3, 2)),
            hexdec(substr($color, 5, 2)),
        ];
    }

    private function chooseStoreTemplate(array $blueprint, Store $store, ?BusinessCategory $businessCategory, ?array $aiPreferences): ?Template
    {
        if (!Schema::hasTable('templates')) {
            return null;
        }

        $requested = $blueprint['design_blueprint']['template_id']
            ?? $blueprint['design']['template_id']
            ?? $aiPreferences['template_id']
            ?? null;
        if ($this->templateRequestIsLocked($aiPreferences) && $requested && ($template = Template::query()->find((int) $requested)) && $this->isActiveStatus($template->status ?? 'active')) {
            return $template;
        }

        $requestedValue = trim((string) (
            $blueprint['design_blueprint']['template_value']
            ?? $blueprint['design']['template_value']
            ?? $aiPreferences['template_value']
            ?? ''
        ));
        if ($this->templateRequestIsLocked($aiPreferences) && $requestedValue !== '') {
            $template = Template::query()
                ->where(function ($query) use ($requestedValue) {
                    $query->where('value', $requestedValue)->orWhere('name', $requestedValue);
                })
                ->get()
                ->first(fn ($row) => $this->isActiveStatus($row->status ?? 'active'));
            if ($template) {
                return $template;
            }
        }

        $templates = Template::query()
            ->orderBy('position', 'asc')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Template $template) => $this->isActiveStatus($template->status ?? 'active'))
            ->values();

        if ($templates->isEmpty()) {
            return null;
        }

        $ranked = $templates
            ->map(fn (Template $template) => [
                'template' => $template,
                'score' => $this->templateMatchScore($template, $blueprint, $businessCategory, $aiPreferences),
            ])
            ->sortByDesc('score')
            ->values();

        $maxScore = (int) ($ranked->max('score') ?? 0);
        $pool = $ranked
            ->filter(fn ($item) => (int) $item['score'] >= $maxScore - 24)
            ->values();
        $recentTemplateIds = $this->recentTemplateIdsForCategory($store, $businessCategory);
        $freshPool = $pool
            ->reject(fn ($item) => in_array((int) ($item['template']->id ?? 0), $recentTemplateIds, true))
            ->values();

        $selectedPool = $freshPool->isNotEmpty() ? $freshPool : $pool;
        $selected = $this->spreadPick($selectedPool->all(), 'template', $store, $businessCategory);

        return $selected['template'] ?? $ranked->first()['template'] ?? $templates->first();
    }

    private function templateMatchScore(Template $template, array $blueprint, ?BusinessCategory $businessCategory, ?array $aiPreferences): int
    {
        $score = 0;
        $categoryId = $businessCategory?->id ? (int) $businessCategory->id : null;
        if ($categoryId && $this->modelCategoryMatches($template, $categoryId)) {
            $score += 100;
        }

        $position = (int) ($template->position ?? 0);
        if ($position > 0) {
            $score += max(0, 30 - min(30, $position));
        }

        $haystack = strtolower(trim(implode(' ', array_filter([
            (string) ($template->name ?? ''),
            (string) ($template->value ?? ''),
            (string) ($template->short_description ?? ''),
            (string) ($template->reviewer ?? ''),
        ]))));

        foreach ($this->designIntentKeywords($blueprint, $businessCategory, $aiPreferences) as $keyword) {
            if ($keyword !== '' && str_contains($haystack, $keyword)) {
                $score += 12;
            }
        }

        $requestedId = (int) (
            $blueprint['design_blueprint']['template_id']
            ?? $blueprint['design']['template_id']
            ?? $aiPreferences['template_id']
            ?? 0
        );
        if ($requestedId > 0 && (int) ($template->id ?? 0) === $requestedId) {
            $score += 18;
        }

        $requestedValue = strtolower(trim((string) (
            $blueprint['design_blueprint']['template_value']
            ?? $blueprint['design']['template_value']
            ?? $aiPreferences['template_value']
            ?? ''
        )));
        if ($requestedValue !== '' && in_array($requestedValue, [
            strtolower(trim((string) ($template->value ?? ''))),
            strtolower(trim((string) ($template->name ?? ''))),
        ], true)) {
            $score += 18;
        }

        return $score;
    }

    private function templateRequestIsLocked(?array $aiPreferences): bool
    {
        $aiPreferences = (array) $aiPreferences;
        foreach (['template_locked', 'template_lock', 'lock_template', 'manual_template'] as $key) {
            if (filter_var($aiPreferences[$key] ?? false, FILTER_VALIDATE_BOOL)) {
                return true;
            }
        }

        return false;
    }

    private function recentTemplateIdsForCategory(Store $store, ?BusinessCategory $businessCategory): array
    {
        if (!Schema::hasTable('stores') || !Schema::hasColumn('stores', 'template_id')) {
            return [];
        }

        $query = Store::query()
            ->where('id', '<>', (int) $store->id)
            ->whereNotNull('template_id')
            ->where('template_id', '<>', '')
            ->where('template_id', '<>', '0')
            ->orderByDesc('id')
            ->limit(12);

        $categoryId = $businessCategory?->id ? (string) $businessCategory->id : '';
        if ($categoryId !== '') {
            $hasCategoryColumn = Schema::hasColumn('stores', 'category_id');
            $hasTypeColumn = Schema::hasColumn('stores', 'type');
            if ($hasCategoryColumn || $hasTypeColumn) {
                $query->where(function ($subQuery) use ($categoryId, $hasCategoryColumn, $hasTypeColumn) {
                    if ($hasCategoryColumn) {
                        $subQuery->where('category_id', $categoryId);
                    }
                    if ($hasTypeColumn) {
                        $hasCategoryColumn ? $subQuery->orWhere('type', $categoryId) : $subQuery->where('type', $categoryId);
                    }
                });
            }
        }

        return $query->pluck('template_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function recentDesignValuesForCategory(string $section, Store $store, ?BusinessCategory $businessCategory): array
    {
        $column = $this->designSectionColumnMap()[$section] ?? null;
        if (!$column || !Schema::hasTable('designs') || !Schema::hasColumn('designs', 'store_id') || !Schema::hasColumn('designs', $column)) {
            return [];
        }

        $query = DB::table('designs')
            ->where('designs.store_id', '<>', (int) $store->id)
            ->whereNotNull("designs.{$column}")
            ->where("designs.{$column}", '<>', '')
            ->where("designs.{$column}", '<>', '0')
            ->where("designs.{$column}", '<>', 'none')
            ->orderByDesc('designs.id')
            ->limit(16);

        $categoryId = $businessCategory?->id ? (string) $businessCategory->id : '';
        if ($categoryId !== '' && Schema::hasTable('stores')) {
            $storeCategoryColumns = array_values(array_filter(['category_id', 'type'], fn ($column) => Schema::hasColumn('stores', $column)));
            if (!empty($storeCategoryColumns)) {
                $query->join('stores', 'stores.id', '=', 'designs.store_id')
                    ->where(function ($subQuery) use ($storeCategoryColumns, $categoryId) {
                        foreach ($storeCategoryColumns as $index => $column) {
                            $index === 0
                                ? $subQuery->where("stores.{$column}", $categoryId)
                                : $subQuery->orWhere("stores.{$column}", $categoryId);
                        }
                    });
            }
        }

        return $query->pluck("designs.{$column}")
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $this->sectionValueIsUsable($value))
            ->unique()
            ->values()
            ->all();
    }

    private function spreadPick(array $items, string $salt, Store $store, ?BusinessCategory $businessCategory): ?array
    {
        if (empty($items)) {
            return null;
        }

        $seed = implode('|', [
            $salt,
            (string) ($store->id ?? ''),
            (string) ($store->name ?? ''),
            (string) ($store->slug ?? ''),
            (string) ($businessCategory?->id ?? ''),
        ]);
        $index = (int) (abs(crc32($seed)) % count($items));

        return $items[$index] ?? $items[0];
    }

    private function varyTemplateSectionValues(array $sectionValues, array $blueprint, Store $store, ?BusinessCategory $businessCategory, ?array $aiPreferences, ?Template $template = null): array
    {
        if (!Schema::hasTable('designlists') || $this->templateRequestIsLocked($aiPreferences)) {
            return $sectionValues;
        }

        $varied = 0;
        foreach ($this->designSectionTypeMap() as $section => $type) {
            if (!$this->sectionCanAutoVary($section) || !$this->sectionValueIsUsable($sectionValues[$section] ?? '')) {
                continue;
            }

            $shouldVary = $this->spreadPick([['use' => false], ['use' => true], ['use' => true]], 'vary:' . $section, $store, $businessCategory);
            if (!($shouldVary['use'] ?? false) && $varied > 0) {
                continue;
            }

            $row = $this->chooseDesignlistForType($section, $type, $blueprint, $store, $businessCategory, $aiPreferences, $template);
            $value = trim((string) ($row->value ?? ''));
            if ($value !== '' && $value !== trim((string) ($sectionValues[$section] ?? ''))) {
                $sectionValues[$section] = $value;
                $varied++;
            }
        }

        return $sectionValues;
    }

    private function sectionCanAutoVary(string $section): bool
    {
        return in_array($section, [
            'header',
            'hero_slider',
            'banner',
            'banner_bottom',
            'feature_category',
            'product',
            'feature_product',
            'best_sell_product',
            'new_arrival',
            'testimonial',
            'footer',
            'product_card',
            'mobile_bottom_menu',
        ], true);
    }

    private function fillMissingSectionValuesFromDesignlists(array $sectionValues, array $blueprint, Store $store, ?BusinessCategory $businessCategory, ?array $aiPreferences, ?Template $template = null): array
    {
        if (!Schema::hasTable('designlists')) {
            return $sectionValues;
        }

        foreach ($this->designSectionTypeMap() as $section => $type) {
            if ($this->sectionValueIsUsable($sectionValues[$section] ?? '')) {
                continue;
            }
            $row = $this->chooseDesignlistForType($section, $type, $blueprint, $store, $businessCategory, $aiPreferences, $template);
            if ($row && trim((string) ($row->value ?? '')) !== '') {
                $sectionValues[$section] = (string) $row->value;
            }
        }

        return $sectionValues;
    }

    private function chooseDesignlistForType(string $section, string $type, array $blueprint, Store $store, ?BusinessCategory $businessCategory, ?array $aiPreferences, ?Template $template = null): ?Designlist
    {
        $rows = Designlist::query()
            ->where('type', $type)
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Designlist $row) => $this->isActiveStatus($row->status ?? 'active'))
            ->values();
        if ($rows->isEmpty()) {
            return null;
        }

        $ranked = $rows
            ->map(fn (Designlist $row) => [
                'row' => $row,
                'score' => $this->designlistMatchScore($row, $blueprint, $businessCategory, $aiPreferences, $template),
            ])
            ->sortByDesc('score')
            ->values();

        $maxScore = (int) ($ranked->max('score') ?? 0);
        $pool = $ranked
            ->filter(fn ($item) => (int) $item['score'] >= $maxScore - 24)
            ->values();
        $recentValues = $this->recentDesignValuesForCategory($section, $store, $businessCategory);
        $freshPool = $pool
            ->reject(fn ($item) => in_array(trim((string) ($item['row']->value ?? '')), $recentValues, true))
            ->values();

        $selectedPool = $freshPool->isNotEmpty() ? $freshPool : $pool;
        $selected = $this->spreadPick($selectedPool->all(), $section . ':' . $type, $store, $businessCategory);

        return $selected['row'] ?? $ranked->first()['row'] ?? $rows->first();
    }

    private function designlistMatchScore(Designlist $row, array $blueprint, ?BusinessCategory $businessCategory, ?array $aiPreferences, ?Template $template = null): int
    {
        $score = 0;
        $categoryId = $businessCategory?->id ? (int) $businessCategory->id : null;
        if ($categoryId && $this->modelCategoryMatches($row, $categoryId)) {
            $score += 100;
        }

        if ($template && $this->modelsShareAnyCategory($row, $template)) {
            $score += 60;
        }

        $haystack = strtolower(trim(implode(' ', array_filter([
            (string) ($row->name ?? ''),
            (string) ($row->value ?? ''),
            (string) ($row->type ?? ''),
            (string) ($row->title ?? ''),
            (string) ($row->subtitle ?? ''),
            (string) ($row->image_description ?? ''),
            is_string($row->ai_preferences ?? null) ? (string) $row->ai_preferences : '',
        ]))));

        foreach ($this->designIntentKeywords($blueprint, $businessCategory, $aiPreferences) as $keyword) {
            if ($keyword !== '' && str_contains($haystack, $keyword)) {
                $score += 12;
            }
        }

        return $score;
    }

    private function templateSectionValues(Template $template): array
    {
        $values = [];
        foreach ($this->templateToDesignColumnMap() as $templateColumn => $designSection) {
            $values[$designSection] = $this->sectionValueIsUsable($template->{$templateColumn} ?? null)
                ? (string) $template->{$templateColumn}
                : '';
        }
        return $values;
    }

    private function sectionValueIsUsable($value): bool
    {
        $normalized = strtolower(trim((string) $value));
        return !in_array($normalized, ['', '0', 'null', 'select', 'select design', 'select template', 'none'], true);
    }

    private function syncTemplatePositionsToStore(int $storeId, ?Template $template): void
    {
        if (!$template || !Schema::hasTable('tempositions') || !Schema::hasTable('design_positions')) {
            return;
        }

        $positions = Temposition::query()
            ->where('template_id', $template->id)
            ->get(['name', 'position']);

        foreach ($positions as $position) {
            $name = trim((string) ($position->name ?? ''));
            if ($name === '') {
                continue;
            }

            DB::table('design_positions')->updateOrInsert(
                ['store_id' => $storeId, 'name' => $name],
                ['position' => (int) ($position->position ?? 0), 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function designIntentKeywords(array $blueprint, ?BusinessCategory $businessCategory, ?array $aiPreferences): array
    {
        $raw = [
            (string) ($blueprint['style_profile'] ?? ''),
            (string) data_get($blueprint, 'design_blueprint.style_profile', ''),
            (string) data_get($blueprint, 'design_blueprint.visual_style', ''),
            (string) ($aiPreferences['style_preset'] ?? ''),
            (string) ($aiPreferences['tone_preset'] ?? ''),
            (string) ($aiPreferences['primary_goal'] ?? ''),
            (string) ($businessCategory?->name ?? ''),
            (string) ($businessCategory?->slug ?? ''),
        ];

        $expanded = [];
        foreach ($raw as $value) {
            $normalized = strtolower(str_replace(['_', '-'], ' ', trim($value)));
            foreach (preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                if (strlen($word) >= 3) {
                    $expanded[] = $word;
                }
            }
            if ($normalized !== '') {
                $expanded[] = $normalized;
            }
        }

        return array_values(array_unique($expanded));
    }

    private function csvContains(string $csv, string $needle): bool
    {
        $needle = trim($needle);
        if ($needle === '') {
            return false;
        }

        $parts = array_map('trim', explode(',', $csv));
        return in_array($needle, $parts, true);
    }

    private function modelCategoryIds($model): array
    {
        $rawValues = [];
        foreach (['business_category_ids', 'category_ids', 'category', 'theme_category', 'type'] as $column) {
            if (isset($model->{$column})) {
                $rawValues[] = $model->{$column};
            }
        }

        return collect($rawValues)
            ->flatMap(function ($value) {
                if (is_array($value)) {
                    return $value;
                }
                $text = trim((string) $value);
                if ($text === '') {
                    return [];
                }
                $decoded = json_decode($text, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
                return preg_split('/[,|]/', $text) ?: [];
            })
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '' && ctype_digit($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function modelCategoryMatches($model, int $categoryId): bool
    {
        return in_array($categoryId, $this->modelCategoryIds($model), true);
    }

    private function modelsShareAnyCategory($first, $second): bool
    {
        $left = $this->modelCategoryIds($first);
        $right = $this->modelCategoryIds($second);
        if (empty($left) || empty($right)) {
            return false;
        }

        return !empty(array_intersect($left, $right));
    }

    private function isActiveStatus($status): bool
    {
        return in_array(strtolower(trim((string) $status)), ['active', 'on', '1', 'true', 'yes', ''], true);
    }

    private function templateToDesignColumnMap(): array
    {
        return [
            'header' => 'header',
            'slider' => 'hero_slider',
            'banner' => 'banner',
            'banner_bottom' => 'banner_bottom',
            'feature_category' => 'feature_category',
            'product' => 'product',
            'feature_product' => 'feature_product',
            'best_sell_product' => 'best_sell_product',
            'new_arrival' => 'new_arrival',
            'testimonial' => 'testimonial',
            'youtube' => 'youtube',
            'footer' => 'footer',
            'single_product_page' => 'single_product_page',
            'shop_page' => 'shop_page',
            'checkout_page' => 'checkout_page',
            'login_page' => 'login_page',
            'profile_page' => 'profile_page',
            'invoice' => 'invoice',
            'product_card' => 'product_card',
            'product_modal' => 'product_modal',
            'preloader' => 'preloader',
            'mobile_bottom_menu' => 'mobile_bottom_menu',
            'blog' => 'blog',
            'contact' => 'contact',
            'offer' => 'offer',
            'auth' => 'auth',
        ];
    }

    private function designSectionColumnMap(): array
    {
        return [
            'header' => 'header',
            'hero_slider' => 'hero_slider',
            'banner' => 'banner',
            'banner_bottom' => 'banner_bottom',
            'feature_category' => 'feature_category',
            'product' => 'product',
            'feature_product' => 'feature_product',
            'best_sell_product' => 'best_sell_product',
            'new_arrival' => 'new_arrival',
            'testimonial' => 'testimonial',
            'youtube' => 'youtube',
            'footer' => 'footer',
            'single_product_page' => 'single_product_page',
            'shop_page' => 'shop_page',
            'checkout_page' => 'checkout_page',
            'login_page' => 'login_page',
            'profile_page' => 'profile_page',
            'invoice' => 'invoice',
            'product_card' => 'product_card',
            'product_modal' => 'product_modal',
            'preloader' => 'preloader',
            'mobile_bottom_menu' => 'mobile_bottom_menu',
            'blog' => 'blog',
            'contact' => 'contact',
            'offer' => 'offer',
            'auth' => 'auth',
        ];
    }

    private function designSectionTypeMap(): array
    {
        return [
            'header' => 'header',
            'hero_slider' => 'slider',
            'banner' => 'banner',
            'banner_bottom' => 'banner_bottom',
            'feature_category' => 'feature_category',
            'product' => 'product',
            'feature_product' => 'feature_product',
            'best_sell_product' => 'best_sell_product',
            'new_arrival' => 'new_arrival',
            'testimonial' => 'testimonial',
            'youtube' => 'youtube',
            'footer' => 'footer',
            'single_product_page' => 'single_product_page',
            'shop_page' => 'shop_page',
            'checkout_page' => 'checkout_page',
            'login_page' => 'login_page',
            'product_card' => 'product_card',
            'preloader' => 'preloader',
            'mobile_bottom_menu' => 'mobile_bottom_menu',
            'blog' => 'blog',
            'contact' => 'contact',
            'offer' => 'offer',
            'auth' => 'auth',
        ];
    }

    private function createCategories(array $blueprint, Store $store, Customer $customer, User $user, ?BusinessCategory $businessCategory): array
    {
        $map = [];
        $catalog = $blueprint['catalog_blueprint'] ?? [];
        foreach (($catalog['categories'] ?? []) as $index => $item) {
            $slug = (string) ($item['slug'] ?? Str::slug((string) ($item['name'] ?? 'Category')));
            $row = $this->firstOrNewCategory((string) ($item['name'] ?? $slug), null, $store, $customer);
            $this->fillCategoryMeta($row, $store, $customer, $user, $index);
            $this->setIfColumn($row, 'parent', '0');
            $this->assignCategorySeedImages($row, $store, $businessCategory, $slug, '', $index + 1, $item['image_seed_id'] ?? null);
            $row->save();
            $map[$slug] = ['id' => $row->id, 'subcategories' => []];
        }

        foreach (($catalog['subcategories'] ?? []) as $index => $item) {
            $parentSlug = (string) ($item['category_slug'] ?? '');
            $parentId = $map[$parentSlug]['id'] ?? null;
            if (!$parentId) {
                continue;
            }
            $slug = (string) ($item['slug'] ?? Str::slug((string) ($item['name'] ?? 'Subcategory')));
            $row = $this->firstOrNewCategory((string) ($item['name'] ?? $slug), (string) $parentId, $store, $customer);
            $this->fillCategoryMeta($row, $store, $customer, $user, $index);
            $row->parent = (string) $parentId;
            $this->assignCategorySeedImages($row, $store, $businessCategory, $parentSlug, $slug, $index + 1, $item['image_seed_id'] ?? null);
            $row->save();
            $map[$parentSlug]['subcategories'][$slug] = $row->id;
        }

        return $map;
    }

    private function createProducts(array $blueprint, AiSeedBatch $batch, Store $store, Customer $customer, User $user, ?BusinessCategory $businessCategory, array $categoryMap): void
    {
        $profile = $blueprint['product_image_profile'] ?? ['width' => 900, 'height' => 900, 'ratio' => '1:1'];
        foreach (($blueprint['catalog_blueprint']['products'] ?? []) as $index => $item) {
            $categorySlug = (string) ($item['category_slug'] ?? '');
            $subcategorySlug = (string) ($item['subcategory_slug'] ?? '');
            $categoryId = $categoryMap[$categorySlug]['id'] ?? null;
            $subcategoryId = $categoryMap[$categorySlug]['subcategories'][$subcategorySlug] ?? null;
            if (!$categoryId) {
                continue;
            }

            $sourceImage = $this->seedImageByIdForUse($item['image_seed_id'] ?? null, 'product')
                ?: $this->pickImage('product', $businessCategory, (array) ($item['image_tags'] ?? []), $categorySlug, $subcategorySlug);
            $this->rememberSeedImage($sourceImage, 'product');
            $generatedImage = $sourceImage
                ? $this->copySeedImage($sourceImage, $store, 'products', (int) ($profile['width'] ?? 900), (int) ($profile['height'] ?? 900), 'product_' . ($index + 1))
                : null;

            $product = new Product();
            $this->setIfColumn($product, 'name', (string) ($item['name'] ?? 'Generated Product'));
            $this->setIfColumn($product, 'description', (string) ($item['description'] ?? ''));
            $this->setIfColumn($product, 'short_description', (string) ($item['short_description'] ?? ''));
            $this->setIfColumn($product, 'regular_price', (string) ((float) ($item['regular_price'] ?? 0)));
            $this->setIfColumn($product, 'promotional_price', (string) ((float) ($item['promotional_price'] ?? 0)));
            $this->setIfColumn($product, 'discount_type', !empty($item['promotional_price']) ? 'fixed' : 'no_discount');
            $this->setIfColumn($product, 'quantity', (string) ((int) ($item['stock'] ?? 20)));
            $this->setIfColumn($product, 'category', (string) $categoryId);
            $this->setIfColumn($product, 'subcategory', $subcategoryId ? (string) $subcategoryId : '');
            $this->setIfColumn($product, 'images', $generatedImage ?: '');
            $this->setIfColumn($product, 'tags', implode(',', array_filter((array) ($item['image_tags'] ?? []))));
            $this->setIfColumn($product, 'status', 'active');
            $this->setIfColumn($product, 'SKU', 'AI-' . strtoupper(Str::random(8)));
            $this->setIfColumn($product, 'currency_id', (string) ($store->currency ?? 1));
            $this->setIfColumn($product, 'feature', $index < 8 ? 1 : 0);
            $this->setIfColumn($product, 'best_sell', $index >= 4 && $index < 10 ? 1 : 0);
            $this->setIfColumn($product, 'storefront', 1);
            $this->setIfColumn($product, 'position', $index + 1);
            $this->setIfColumn($product, 'variant_payload', json_encode($this->variantPayload($item)));
            $this->setIfColumn($product, 'uid', (string) $user->id);
            $this->setIfColumn($product, 'customer_id', (string) $customer->id);
            $this->setIfColumn($product, 'store_id', (string) $store->id);
            $this->setIfColumn($product, 'creator', (string) $user->id);
            $this->setIfColumn($product, 'editor', (string) $user->id);
            $product->save();

            AiSeedProduct::query()->create([
                'batch_id' => $batch->id,
                'store_id' => $store->id,
                'product_id' => $product->id,
                'source_image_id' => $sourceImage?->id,
                'generated_image_path' => $generatedImage,
                'is_demo' => true,
            ]);
        }
    }

    private function createSliderBanners(array $blueprint, Store $store, Customer $customer, User $user, ?BusinessCategory $businessCategory): void
    {
        $sliderBanners = (array) ($blueprint['catalog_blueprint']['slider_banners'] ?? []);
        if (empty($sliderBanners)) {
            $businessKey = Str::slug((string) ($businessCategory?->name ?? $store->type ?? 'store'));
            $storeName = (string) ($store->name ?? 'Store');
            $sliderBanners = [
                ['usage_type' => 'slider', 'image_tags' => [$businessKey, 'slider', 'hero'], 'headline' => "{$storeName} Collection", 'subheadline' => 'Fresh picks ready for your customers.', 'cta' => 'Shop Now'],
                ['usage_type' => 'banner', 'image_tags' => [$businessKey, 'banner', 'offer'], 'headline' => 'New arrivals', 'subheadline' => 'Curated products for a strong launch.', 'cta' => 'Explore'],
            ];
        }

        foreach ($sliderBanners as $index => $item) {
            $usageType = (string) ($item['usage_type'] ?? 'banner');
            $sourceImage = $this->seedImageByIdForUse($item['image_seed_id'] ?? null, $usageType)
                ?: $this->pickImage($usageType, $businessCategory, (array) ($item['image_tags'] ?? []), '', '');
            $this->rememberSeedImage($sourceImage, $usageType);
            $generatedImage = $sourceImage
                ? $this->copySeedImage($sourceImage, $store, $usageType === 'slider' ? 'sliders' : 'banners', $usageType === 'slider' ? 1600 : 1200, $usageType === 'slider' ? 600 : 500, $usageType . '_' . ($index + 1))
                : null;

            if ($usageType === 'slider') {
                $row = new Slider();
                $this->setIfColumn($row, 'image', $generatedImage ?: '');
                $this->setIfColumn($row, 'title', (string) ($item['headline'] ?? 'New Collection'));
                $this->setIfColumn($row, 'subtitle', (string) ($item['subheadline'] ?? ''));
                $this->setIfColumn($row, 'button', (string) ($item['cta'] ?? 'Shop Now'));
                $this->setIfColumn($row, 'status', 'active');
                $this->setIfColumn($row, 'position', $index + 1);
            } else {
                $row = new Banner();
                $this->setIfColumn($row, 'image', $generatedImage ?: '');
                $this->setIfColumn($row, 'status', 'active');
                $this->setIfColumn($row, 'type', 0);
            }
            $this->setIfColumn($row, 'uid', (string) $user->id);
            $this->setIfColumn($row, 'customer_id', (string) $customer->id);
            $this->setIfColumn($row, 'store_id', (string) $store->id);
            $this->setIfColumn($row, 'creator', (string) $user->id);
            $this->setIfColumn($row, 'editor', (string) $user->id);
            $row->save();
        }
    }

    private function pickImage(string $usageType, ?BusinessCategory $businessCategory, array $tags, string $categorySlug, string $subcategorySlug): ?AiSeedImageLibrary
    {
        if (!Schema::hasTable('ai_seed_image_libraries')) {
            return null;
        }

        $usageTypes = $this->seedImageUsageFallbacks($usageType);
        $categoryId = $businessCategory?->id ? (int) $businessCategory->id : null;

        foreach ($usageTypes as $candidateUsageType) {
            if ($categoryId) {
                $query = $this->baseSeedImageQuery($candidateUsageType)
                    ->where(function ($q) use ($businessCategory, $categoryId) {
                        $q->where('business_category_id', $categoryId);
                        if (Schema::hasColumn('ai_seed_image_libraries', 'business_category_ids')) {
                            $q->orWhereJsonContains('business_category_ids', $categoryId)
                                ->orWhereJsonContains('business_category_ids', (string) $categoryId);
                        }
                        if (trim((string) $businessCategory->name) !== '') {
                            $q->orWhere('business_category_name', $businessCategory->name);
                        }
                    });

                if ($image = $this->firstSeedImageCandidate($query, $tags, $categorySlug, $subcategorySlug, $usageType)) {
                    return $image;
                }
            }

            $globalQuery = $this->baseSeedImageQuery($candidateUsageType)
                ->whereNull('business_category_id');
            if ($image = $this->firstSeedImageCandidate($globalQuery, $tags, $categorySlug, $subcategorySlug, $usageType)) {
                return $image;
            }

            if ($image = $this->firstSeedImageCandidate($this->baseSeedImageQuery($candidateUsageType), $tags, $categorySlug, $subcategorySlug, $usageType)) {
                return $image;
            }
        }

        return $this->firstActiveSeedImage();
    }

    private function firstActiveSeedImage(): ?AiSeedImageLibrary
    {
        if (!Schema::hasTable('ai_seed_image_libraries')) {
            return null;
        }

        return AiSeedImageLibrary::query()
            ->where('status', true)
            ->latest('id')
            ->first();
    }

    private function seedImageById($id): ?AiSeedImageLibrary
    {
        if (!Schema::hasTable('ai_seed_image_libraries') || !is_numeric($id) || (int) $id <= 0) {
            return null;
        }

        return AiSeedImageLibrary::query()
            ->where('id', (int) $id)
            ->where('status', true)
            ->first();
    }

    private function seedImageByIdForUse($id, string $bucket): ?AiSeedImageLibrary
    {
        $image = $this->seedImageById($id);
        if (!$image || $this->seedImageAlreadyUsed($image, $bucket)) {
            return null;
        }

        return $image;
    }

    private function seedImageCandidatesForBot(?BusinessCategory $businessCategory): array
    {
        if (!Schema::hasTable('ai_seed_image_libraries')) {
            return [];
        }

        $categoryId = $businessCategory?->id ? (int) $businessCategory->id : null;
        $query = AiSeedImageLibrary::query()->where('status', true)->latest('id');
        if ($categoryId) {
            $query->where(function ($q) use ($businessCategory, $categoryId) {
                $q->where('business_category_id', $categoryId)
                    ->orWhereNull('business_category_id');
                if (Schema::hasColumn('ai_seed_image_libraries', 'business_category_ids')) {
                    $q->orWhereJsonContains('business_category_ids', $categoryId)
                        ->orWhereJsonContains('business_category_ids', (string) $categoryId);
                }
                if (trim((string) $businessCategory->name) !== '') {
                    $q->orWhere('business_category_name', $businessCategory->name);
                }
            });
        }

        return $query->limit(200)->get()->map(static fn (AiSeedImageLibrary $row) => [
            'id' => (int) $row->id,
            'usage_type' => (string) ($row->usage_type ?? ''),
            'business_category_name' => (string) ($row->business_category_name ?? ''),
            'category_slug' => (string) ($row->category_slug ?? ''),
            'subcategory_slug' => (string) ($row->subcategory_slug ?? ''),
            'tags' => (string) ($row->tags ?? ''),
            'alt_text' => (string) ($row->alt_text ?? ''),
            'original_name' => (string) ($row->original_name ?? ''),
        ])->values()->all();
    }

    private function seedImageUsageFallbacks(string $usageType): array
    {
        $usageType = trim($usageType) ?: 'product';
        $fallbacks = match ($usageType) {
            'slider' => ['slider', 'banner', 'product', 'category'],
            'banner' => ['banner', 'slider', 'product', 'category'],
            'category' => ['category', 'product', 'banner', 'slider'],
            default => ['product', 'category', 'banner', 'slider'],
        };

        return array_values(array_unique($fallbacks));
    }

    private function baseSeedImageQuery(string $usageType)
    {
        return AiSeedImageLibrary::query()
            ->where('usage_type', $usageType)
            ->where('status', true);
    }

    private function firstSeedImageCandidate($query, array $tags, string $categorySlug, string $subcategorySlug, ?string $bucket = null): ?AiSeedImageLibrary
    {
        $usedIds = $this->usedSeedImageIdsFor($bucket);
        if (!empty($usedIds)) {
            $unusedQuery = clone $query;
            $unusedQuery->whereNotIn('id', $usedIds);
            if ($image = $this->orderedSeedImageCandidate($unusedQuery, $tags, $categorySlug, $subcategorySlug)) {
                return $image;
            }
        }

        return $this->orderedSeedImageCandidate($query, $tags, $categorySlug, $subcategorySlug);
    }

    private function orderedSeedImageCandidate($query, array $tags, string $categorySlug, string $subcategorySlug): ?AiSeedImageLibrary
    {
        if ($categorySlug !== '') {
            $query->orderByRaw('CASE WHEN category_slug = ? THEN 0 ELSE 1 END', [$categorySlug]);
        }
        if ($subcategorySlug !== '') {
            $query->orderByRaw('CASE WHEN subcategory_slug = ? THEN 0 ELSE 1 END', [$subcategorySlug]);
        }
        foreach (array_slice(array_filter(array_map('strval', $tags)), 0, 5) as $tag) {
            $query->orderByRaw('CASE WHEN tags LIKE ? THEN 0 ELSE 1 END', ['%' . $tag . '%']);
        }

        return $query->orderByDesc('id')->first();
    }

    private function usedSeedImageIdsFor(?string $bucket): array
    {
        $bucket = trim((string) $bucket);
        if ($bucket === '') {
            return [];
        }

        return array_values(array_unique(array_map('intval', $this->usedSeedImageIds[$bucket] ?? [])));
    }

    private function seedImageAlreadyUsed(AiSeedImageLibrary $image, string $bucket): bool
    {
        $id = (int) $image->id;
        if ($id <= 0) {
            return false;
        }

        return in_array($id, $this->usedSeedImageIdsFor($bucket), true);
    }

    private function rememberSeedImage(?AiSeedImageLibrary $image, string $bucket): void
    {
        if (!$image || (int) $image->id <= 0) {
            return;
        }

        $bucket = trim($bucket) ?: 'product';
        $this->usedSeedImageIds[$bucket] ??= [];
        $this->usedSeedImageIds[$bucket][] = (int) $image->id;
    }

    private function copySeedImage(AiSeedImageLibrary $image, Store $store, string $folder, int $width, int $height, string $name): ?string
    {
        $disk = Storage::disk('public');
        $sourcePath = $this->normalizeSeedImageDiskPath((string) $image->path);
        $source = $this->seedImageSourcePath($sourcePath);
        if (!$source) {
            return null;
        }

        $legacy = $this->copySeedImageToLegacyAssetDirectory($source, $sourcePath ?: (string) $image->path, $folder, $width, $height, $name);

        $targetDir = $this->storeSeedMediaLibraryDirectory($store, $folder);
        $targetPath = "{$targetDir}/{$name}_" . Str::lower(Str::random(6)) . '.jpg';
        $disk->makeDirectory($targetDir);
        $target = $disk->path($targetPath);

        if ($this->resizeCover($source, $target, $width, $height)) {
            return 'storage/' . $targetPath;
        }

        $fallbackExtension = strtolower((string) pathinfo($sourcePath ?: (string) $image->path, PATHINFO_EXTENSION));
        $fallbackExtension = preg_replace('/[^a-z0-9]/', '', $fallbackExtension) ?: 'jpg';
        $fallbackPath = "{$targetDir}/{$name}_" . Str::lower(Str::random(6)) . '.' . $fallbackExtension;
        if ($sourcePath !== '' && $disk->exists($sourcePath)) {
            $disk->copy($sourcePath, $fallbackPath);
        } else {
            $disk->put($fallbackPath, (string) @file_get_contents($source));
        }
        return 'storage/' . $fallbackPath;
    }

    private function copySeedImageToLegacyAssetDirectory(string $source, string $sourcePath, string $folder, int $width, int $height, string $name): ?string
    {
        $directory = $this->legacySeedAssetDirectory($folder, $name);
        if ($directory === null) {
            return null;
        }

        $targetDir = public_path($directory);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return null;
        }

        $baseName = Str::slug($name) ?: 'ai-seed';
        $targetName = $baseName . '_' . Str::lower(Str::random(6)) . '.jpg';
        $target = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $targetName;

        if ($this->resizeCover($source, $target, $width, $height)) {
            return $targetName;
        }

        $extension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'jpg';
        $targetName = $baseName . '_' . Str::lower(Str::random(6)) . '.' . $extension;
        $target = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $targetName;

        return @copy($source, $target) ? $targetName : null;
    }

    private function legacySeedAssetDirectory(string $folder, string $name): ?string
    {
        return match ($folder) {
            'products' => 'assets/images/product',
            'sliders' => 'assets/images/slider',
            'banners' => 'assets/images/banner',
            'categories' => Str::startsWith($name, 'category_icon_') ? 'assets/images/icon' : 'assets/images/category',
            default => null,
        };
    }

    private function assignCategorySeedImages(Category $row, Store $store, ?BusinessCategory $businessCategory, string $categorySlug, string $subcategorySlug, int $index, $seedImageId = null): void
    {
        $sourceImage = $this->seedImageByIdForUse($seedImageId, 'category')
            ?: $this->pickImage('category', $businessCategory, [], $categorySlug, $subcategorySlug)
            ?: $this->firstActiveSeedImage();
        if (!$sourceImage) {
            return;
        }
        $this->rememberSeedImage($sourceImage, 'category');

        if (Schema::hasColumn($row->getTable(), 'banner') && empty((string) ($row->banner ?? ''))) {
            $bannerImage = $this->copySeedImage($sourceImage, $store, 'categories', 1200, 500, 'category_banner_' . $index);
            if ($bannerImage) {
                $row->banner = $bannerImage;
            }
        }

        if (Schema::hasColumn($row->getTable(), 'icon') && empty((string) ($row->icon ?? ''))) {
            $iconImage = $this->copySeedImage($sourceImage, $store, 'categories', 512, 512, 'category_icon_' . $index);
            if ($iconImage) {
                $row->icon = $iconImage;
            }
        }
    }

    private function storeSeedMediaLibraryDirectory(Store $store, string $folder): string
    {
        $storeId = (string) ($store->id ?? '0');
        $slug = Str::slug((string) ($store->slug ?? $store->name ?? 'store')) ?: 'store';

        return "image-library/admin/{$slug}-{$storeId}/ai-seed/" . trim($folder, '/');
    }

    private function repairMissingStoreSeedImages(AiSeedBatch $batch, Store $store, ?BusinessCategory $businessCategory): void
    {
        if (Schema::hasTable('products')) {
            Product::query()
                ->where('store_id', (string) $store->id)
                ->where(function ($query) {
                    $query->whereNull('images')->orWhere('images', '');
                })
                ->orderBy('id')
                ->get()
                ->each(function (Product $product, int $index) use ($batch, $store, $businessCategory) {
                    $productImage = $this->pickImage('product', $businessCategory, [], '', '');
                    if (!$productImage) {
                        return;
                    }
                    $this->rememberSeedImage($productImage, 'product');
                    $generatedImage = $this->copySeedImage($productImage, $store, 'products', 900, 900, 'product_repair_' . ($index + 1));
                    if (!$generatedImage) {
                        return;
                    }

                    $this->setIfColumn($product, 'images', $generatedImage);
                    $product->save();

                    if (Schema::hasTable('ai_seed_products')) {
                        AiSeedProduct::query()->updateOrCreate(
                            ['store_id' => $store->id, 'product_id' => $product->id],
                            [
                                'batch_id' => $batch->id,
                                'source_image_id' => $productImage->id,
                                'generated_image_path' => $generatedImage,
                                'is_demo' => true,
                            ]
                        );
                    }
                });
        }

        if (Schema::hasTable('sliders')) {
            Slider::query()
                ->where('store_id', (string) $store->id)
                ->where(function ($query) {
                    $query->whereNull('image')->orWhere('image', '');
                })
                ->orderBy('id')
                ->get()
                ->each(function (Slider $slider, int $index) use ($store, $businessCategory) {
                    $sliderImage = $this->pickImage('slider', $businessCategory, [], '', '');
                    if (!$sliderImage) {
                        return;
                    }
                    $this->rememberSeedImage($sliderImage, 'slider');
                    $generatedImage = $this->copySeedImage($sliderImage, $store, 'sliders', 1600, 600, 'slider_repair_' . ($index + 1));
                    if ($generatedImage) {
                        $this->setIfColumn($slider, 'image', $generatedImage);
                        $slider->save();
                    }
                });
        }

        if (Schema::hasTable('banners')) {
            Banner::query()
                ->where('store_id', (string) $store->id)
                ->where(function ($query) {
                    $query->whereNull('image')->orWhere('image', '');
                })
                ->orderBy('id')
                ->get()
                ->each(function (Banner $banner, int $index) use ($store, $businessCategory) {
                    $bannerImage = $this->pickImage('banner', $businessCategory, [], '', '');
                    if (!$bannerImage) {
                        return;
                    }
                    $this->rememberSeedImage($bannerImage, 'banner');
                    $generatedImage = $this->copySeedImage($bannerImage, $store, 'banners', 1200, 500, 'banner_repair_' . ($index + 1));
                    if ($generatedImage) {
                        $this->setIfColumn($banner, 'image', $generatedImage);
                        $banner->save();
                    }
                });
        }

        if (Schema::hasTable('categories')) {
            Category::query()
                ->where('store_id', (string) $store->id)
                ->orderBy('id')
                ->get()
                ->each(function (Category $category, int $index) use ($store, $businessCategory) {
                    $categorySlug = Str::slug((string) ($category->name ?? 'category')) ?: 'category';
                    $this->assignCategorySeedImages($category, $store, $businessCategory, $categorySlug, '', $index + 1);
                    $category->save();
                });
        }
    }

    private function normalizeSeedImageDiskPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return '';
        }

        if (str_contains($path, 'media-library/file?')) {
            $query = parse_url($path, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                parse_str($query, $params);
                $path = (string) ($params['path'] ?? $path);
            }
        }

        if (preg_match('#^https?://[^/]+/(.+)$#i', $path, $matches)) {
            $path = (string) ($matches[1] ?? $path);
        }

        $path = ltrim($path, '/');
        foreach (['react-admin-api/public/media-library/file?path=', 'storage/', 'public/storage/', 'storage/app/public/', 'app/public/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }

        return ltrim(urldecode($path), '/');
    }

    private function seedImageSourcePath(string $diskPath): ?string
    {
        if ($diskPath === '' || str_contains($diskPath, '..')) {
            return null;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($diskPath)) {
            return $disk->path($diskPath);
        }

        foreach ([public_path($diskPath), public_path('storage/' . $diskPath), storage_path('app/public/' . $diskPath)] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resizeCover(string $source, string $target, int $width, int $height): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }
        $info = @getimagesize($source);
        if (!$info) {
            return false;
        }
        $mime = $info['mime'] ?? '';
        $create = match ($mime) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
            default => null,
        };
        if (!$create || !function_exists($create)) {
            return false;
        }

        $src = @$create($source);
        if (!$src) {
            return false;
        }
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $scale = max($width / max(1, $srcW), $height / max(1, $srcH));
        $newW = (int) ceil($srcW * $scale);
        $newH = (int) ceil($srcH * $scale);
        $dst = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, (int) (($width - $newW) / 2), (int) (($height - $newH) / 2), 0, 0, $newW, $newH, $srcW, $srcH);
        return (bool) imagejpeg($dst, $target, 86);
    }

    private function variantPayload(array $item): array
    {
        $type = (string) ($item['variant_type'] ?? '');
        $values = array_values(array_filter((array) ($item['variant_values'] ?? [])));
        if ($type === '' || empty($values)) {
            return [];
        }

        return [
            'source' => 'ai_seed',
            'type' => $type,
            'values' => $values,
        ];
    }

    private function businessCategoryForStore(Store $store): ?BusinessCategory
    {
        $type = trim((string) ($store->category_id ?? $store->type ?? ''));
        if ($type !== '' && ctype_digit($type)) {
            return BusinessCategory::query()->find((int) $type);
        }
        if ($type !== '') {
            return BusinessCategory::query()->where('name', $type)->orWhere('slug', Str::slug($type))->first();
        }
        return null;
    }

    private function firstOrNewCategory(string $name, ?string $parent, Store $store, Customer $customer): Category
    {
        $query = Category::query()->where('name', $name);
        if (Schema::hasColumn('categories', 'store_id')) {
            $query->where('store_id', (string) $store->id);
        }
        if (Schema::hasColumn('categories', 'customer_id')) {
            $query->where('customer_id', (string) $customer->id);
        }
        if ($parent !== null) {
            $query->where('parent', $parent);
        } else {
            $query->where(function ($q) {
                $q->whereNull('parent')->orWhere('parent', '')->orWhere('parent', '0');
            });
        }

        return $query->first() ?: new Category(['name' => $name]);
    }

    private function fillCategoryMeta(Category $row, Store $store, Customer $customer, User $user, int $position): void
    {
        $row->status = 'active';
        $row->position = (string) ($position + 1);
        $this->setIfColumn($row, 'uid', (string) $user->id);
        $this->setIfColumn($row, 'customer_id', (string) $customer->id);
        $this->setIfColumn($row, 'store_id', (string) $store->id);
        $this->setIfColumn($row, 'creator', (string) $user->id);
        $this->setIfColumn($row, 'editor', (string) $user->id);
    }

    private function setIfColumn($model, string $column, $value): void
    {
        if (Schema::hasColumn($model->getTable(), $column)) {
            $model->{$column} = $value;
        }
    }
}
