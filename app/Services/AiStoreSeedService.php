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
            $categoryMap = $this->createCategories($blueprint, $store, $customer, $user);
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
        if (is_array($previewBlueprint) && isset($previewBlueprint['catalog_blueprint'])) {
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
                    return $this->applyCatalogSelection($blueprint, $aiPreferences);
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $this->applyCatalogSelection($this->fallbackBlueprint($payload), $aiPreferences);
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
        $key = str_contains($categoryName, 'grocery') ? 'grocery'
            : (str_contains($categoryName, 'electronic') ? 'electronics'
                : (str_contains($categoryName, 'pharmacy') ? 'pharmacy' : 'fashion'));
        $profile = $key === 'fashion'
            ? ['ratio' => '4:5', 'width' => 900, 'height' => 1125, 'fit' => 'cover']
            : ['ratio' => '1:1', 'width' => 900, 'height' => 900, 'fit' => 'cover'];

        $catalogs = [
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
        $products = collect(range(1, 12))->map(function ($index) use ($name, $key, $subcategories, $source) {
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

        $template = $this->chooseStoreTemplate($blueprint, $businessCategory, $aiPreferences);
        $sectionValues = $template ? $this->templateSectionValues($template) : [];
        $sectionValues = $this->fillMissingSectionValuesFromDesignlists($sectionValues, $blueprint, $businessCategory, $aiPreferences, $template);

        if (!$template && empty(array_filter($sectionValues))) {
            return;
        }

        $design = Design::query()->where('store_id', $store->id)->first() ?: new Design();
        $this->setIfColumn($design, 'store_id', (string) $store->id);
        $this->setIfColumn($design, 'uid', (string) $user->id);
        $this->setIfColumn($design, 'customer_id', (string) $customer->id);
        $this->setIfColumn($design, 'creator', (string) $user->id);
        $this->setIfColumn($design, 'editor', (string) $user->id);
        $this->setIfColumn($design, 'header_color', '#ffffff');
        $this->setIfColumn($design, 'text_color', '#000000');
        if ($template) {
            $this->setIfColumn($design, 'template_id', (string) $template->id);
        }

        foreach ($this->designSectionColumnMap() as $section => $column) {
            if (array_key_exists($section, $sectionValues)) {
                $this->setIfColumn($design, $column, $sectionValues[$section]);
            }
        }

        $design->save();

        if ($template) {
            $this->setIfColumn($store, 'template_id', (string) $template->id);
            $store->save();
            $this->setIfColumn($customer, 'template_id', (string) $template->id);
            $customer->save();
        }

        $this->syncTemplatePositionsToStore((int) $store->id, $template);
    }

    private function chooseStoreTemplate(array $blueprint, ?BusinessCategory $businessCategory, ?array $aiPreferences): ?Template
    {
        if (!Schema::hasTable('templates')) {
            return null;
        }

        $requested = $blueprint['design_blueprint']['template_id']
            ?? $blueprint['design']['template_id']
            ?? $aiPreferences['template_id']
            ?? null;
        if ($requested && ($template = Template::query()->find((int) $requested)) && $this->isActiveStatus($template->status ?? 'active')) {
            return $template;
        }

        $requestedValue = trim((string) (
            $blueprint['design_blueprint']['template_value']
            ?? $blueprint['design']['template_value']
            ?? $aiPreferences['template_value']
            ?? ''
        ));
        if ($requestedValue !== '') {
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

        return $ranked->first()['template'] ?? $templates->first();
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

        return $score;
    }

    private function fillMissingSectionValuesFromDesignlists(array $sectionValues, array $blueprint, ?BusinessCategory $businessCategory, ?array $aiPreferences, ?Template $template = null): array
    {
        if (!Schema::hasTable('designlists')) {
            return $sectionValues;
        }

        foreach ($this->designSectionTypeMap() as $section => $type) {
            if ($this->sectionValueIsUsable($sectionValues[$section] ?? '')) {
                continue;
            }
            $row = $this->chooseDesignlistForType($type, $blueprint, $businessCategory, $aiPreferences, $template);
            if ($row && trim((string) ($row->value ?? '')) !== '') {
                $sectionValues[$section] = (string) $row->value;
            }
        }

        return $sectionValues;
    }

    private function chooseDesignlistForType(string $type, array $blueprint, ?BusinessCategory $businessCategory, ?array $aiPreferences, ?Template $template = null): ?Designlist
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

        return $ranked->first()['row'] ?? $rows->first();
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

    private function createCategories(array $blueprint, Store $store, Customer $customer, User $user): array
    {
        $map = [];
        $catalog = $blueprint['catalog_blueprint'] ?? [];
        foreach (($catalog['categories'] ?? []) as $index => $item) {
            $slug = (string) ($item['slug'] ?? Str::slug((string) ($item['name'] ?? 'Category')));
            $row = $this->firstOrNewCategory((string) ($item['name'] ?? $slug), null, $store, $customer);
            $this->fillCategoryMeta($row, $store, $customer, $user, $index);
            $this->setIfColumn($row, 'parent', '0');
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

            $sourceImage = $this->pickImage('product', $businessCategory, (array) ($item['image_tags'] ?? []), $categorySlug, $subcategorySlug);
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
            $sourceImage = $this->pickImage($usageType, $businessCategory, (array) ($item['image_tags'] ?? []), '', '');
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

                if ($image = $this->firstSeedImageCandidate($query, $tags, $categorySlug, $subcategorySlug)) {
                    return $image;
                }
            }

            $globalQuery = $this->baseSeedImageQuery($candidateUsageType)
                ->whereNull('business_category_id');
            if ($image = $this->firstSeedImageCandidate($globalQuery, $tags, $categorySlug, $subcategorySlug)) {
                return $image;
            }

            if ($image = $this->firstSeedImageCandidate($this->baseSeedImageQuery($candidateUsageType), $tags, $categorySlug, $subcategorySlug)) {
                return $image;
            }
        }

        return null;
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

    private function firstSeedImageCandidate($query, array $tags, string $categorySlug, string $subcategorySlug): ?AiSeedImageLibrary
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

    private function copySeedImage(AiSeedImageLibrary $image, Store $store, string $folder, int $width, int $height, string $name): ?string
    {
        $disk = Storage::disk('public');
        $sourcePath = $this->normalizeSeedImageDiskPath((string) $image->path);
        $source = $this->seedImageSourcePath($sourcePath);
        if (!$source) {
            return null;
        }

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

    private function storeSeedMediaLibraryDirectory(Store $store, string $folder): string
    {
        $storeId = (string) ($store->id ?? '0');
        $slug = Str::slug((string) ($store->slug ?? $store->name ?? 'store')) ?: 'store';

        return "image-library/admin/{$slug}-{$storeId}/ai-seed/" . trim($folder, '/');
    }

    private function repairMissingStoreSeedImages(AiSeedBatch $batch, Store $store, ?BusinessCategory $businessCategory): void
    {
        $productImage = $this->pickImage('product', $businessCategory, [], '', '');
        $sliderImage = $this->pickImage('slider', $businessCategory, [], '', '') ?: $productImage;
        $bannerImage = $this->pickImage('banner', $businessCategory, [], '', '') ?: $productImage;

        if ($productImage && Schema::hasTable('products')) {
            Product::query()
                ->where('store_id', (string) $store->id)
                ->where(function ($query) {
                    $query->whereNull('images')->orWhere('images', '');
                })
                ->orderBy('id')
                ->get()
                ->each(function (Product $product, int $index) use ($batch, $store, $productImage) {
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

        if ($sliderImage && Schema::hasTable('sliders')) {
            Slider::query()
                ->where('store_id', (string) $store->id)
                ->where(function ($query) {
                    $query->whereNull('image')->orWhere('image', '');
                })
                ->orderBy('id')
                ->get()
                ->each(function (Slider $slider, int $index) use ($store, $sliderImage) {
                    $generatedImage = $this->copySeedImage($sliderImage, $store, 'sliders', 1600, 600, 'slider_repair_' . ($index + 1));
                    if ($generatedImage) {
                        $this->setIfColumn($slider, 'image', $generatedImage);
                        $slider->save();
                    }
                });
        }

        if ($bannerImage && Schema::hasTable('banners')) {
            Banner::query()
                ->where('store_id', (string) $store->id)
                ->where(function ($query) {
                    $query->whereNull('image')->orWhere('image', '');
                })
                ->orderBy('id')
                ->get()
                ->each(function (Banner $banner, int $index) use ($store, $bannerImage) {
                    $generatedImage = $this->copySeedImage($bannerImage, $store, 'banners', 1200, 500, 'banner_repair_' . ($index + 1));
                    if ($generatedImage) {
                        $this->setIfColumn($banner, 'image', $generatedImage);
                        $banner->save();
                    }
                });
        }
    }

    private function normalizeSeedImageDiskPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://[^/]+/(.+)$#i', $path, $matches)) {
            $path = (string) ($matches[1] ?? $path);
        }

        $path = ltrim($path, '/');
        foreach (['storage/', 'public/storage/', 'app/public/'] as $prefix) {
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
