<?php

namespace App\Services;

use App\Models\AiSeedBatch;
use App\Models\AiSeedImageLibrary;
use App\Models\AiSeedProduct;
use App\Models\Banner;
use App\Models\BusinessCategory;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Slider;
use App\Models\Store;
use App\Models\User;
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
            $categoryMap = $this->createCategories($blueprint, $store, $customer, $user);
            $this->createProducts($blueprint, $batch, $store, $customer, $user, $businessCategory, $categoryMap);
            $this->createSliderBanners($blueprint, $store, $customer, $user, $businessCategory);
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
        foreach (($blueprint['catalog_blueprint']['slider_banners'] ?? []) as $index => $item) {
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

        $query = AiSeedImageLibrary::query()
            ->where('usage_type', $usageType)
            ->where('status', true);
        if ($businessCategory) {
            $query->where(function ($q) use ($businessCategory) {
                $q->where('business_category_id', $businessCategory->id)->orWhereNull('business_category_id');
            });
        }
        if ($categorySlug !== '') {
            $query->orderByRaw('CASE WHEN category_slug = ? THEN 0 ELSE 1 END', [$categorySlug]);
        }
        if ($subcategorySlug !== '') {
            $query->orderByRaw('CASE WHEN subcategory_slug = ? THEN 0 ELSE 1 END', [$subcategorySlug]);
        }
        foreach (array_slice(array_filter($tags), 0, 3) as $tag) {
            $query->orderByRaw('CASE WHEN tags LIKE ? THEN 0 ELSE 1 END', ['%' . $tag . '%']);
        }

        return $query->inRandomOrder()->first();
    }

    private function copySeedImage(AiSeedImageLibrary $image, Store $store, string $folder, int $width, int $height, string $name): ?string
    {
        $disk = Storage::disk('public');
        if (!$disk->exists($image->path)) {
            return null;
        }

        $source = $disk->path($image->path);
        $targetDir = "stores/{$store->id}/ai-seed/{$folder}";
        $targetPath = "{$targetDir}/{$name}_" . Str::lower(Str::random(6)) . '.jpg';
        $disk->makeDirectory($targetDir);
        $target = $disk->path($targetPath);

        if ($this->resizeCover($source, $target, $width, $height)) {
            return 'storage/' . $targetPath;
        }

        $fallbackPath = "{$targetDir}/{$name}_" . Str::lower(Str::random(6)) . '.' . pathinfo($image->path, PATHINFO_EXTENSION);
        $disk->copy($image->path, $fallbackPath);
        return 'storage/' . $fallbackPath;
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
        $saved = imagejpeg($dst, $target, 86);
        imagedestroy($src);
        imagedestroy($dst);
        return (bool) $saved;
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
