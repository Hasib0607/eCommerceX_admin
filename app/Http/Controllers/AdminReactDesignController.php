<?php

namespace App\Http\Controllers;

use App\Models\AdminBlog;
use App\Models\Announcement;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\BusinessCategory;
use App\Models\Category;
use App\Models\CheckoutForm;
use App\Models\Customer;
use App\Models\Design;
use App\Models\Designlist;
use App\Models\Headersetting;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Product;
use App\Models\Slider;
use App\Models\Staff;
use App\Models\Store;
use App\Models\StoreDesign;
use App\Models\Template;
use App\Models\Testimonial;
use App\Services\StorefrontBootstrapCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminReactDesignController extends Controller
{
    private const SECTION_COLUMN_MAP = [
        'header' => 'header',
        'slider' => 'hero_slider',
        'feature-category' => 'feature_category',
        'banner' => 'banner',
        'product' => 'product',
        'feature-product' => 'feature_product',
        'youtube' => 'youtube',
        'best-sell-product' => 'best_sell_product',
        'announcement' => 'announcement',
        'banner-bottom' => 'banner_bottom',
        'brand' => 'brand',
        'blog' => 'blog',
        'offer' => 'offer',
        'about' => 'about',
        'new-arrival' => 'new_arrival',
        'testimonial' => 'testimonial',
        'newsletter' => 'newsletter',
        'footer' => 'footer',
        'single-product-page' => 'single_product_page',
        'shop-page' => 'shop_page',
        'checkout-page' => 'checkout_page',
        'login-page' => 'login_page',
        'product-card' => 'product_card',
        'preloader' => 'preloader',
        'mobile-bottom-menu' => 'mobile_bottom_menu',
    ];
    private const SECTION_DESIGNLIST_TYPE_MAP = [
        'header' => 'header',
        'slider' => 'slider',
        'feature-category' => 'feature_category',
        'banner' => 'banner',
        'product' => 'product',
        'feature-product' => 'feature_product',
        'youtube' => 'youtube',
        'best-sell-product' => 'best_sell_product',
        'announcement' => 'announcement',
        'banner-bottom' => 'banner_bottom',
        'brand' => 'brand',
        'blog' => 'blog',
        'offer' => 'offer',
        'about' => 'about',
        'new-arrival' => 'new_arrival',
        'testimonial' => 'testimonial',
        'newsletter' => 'newsletter',
        'footer' => 'footer',
        'single-product-page' => 'single_product_page',
        'shop-page' => 'shop_page',
        'checkout-page' => 'checkout_page',
        'login-page' => 'login_page',
        'product-card' => 'product_card',
        'preloader' => 'preloader',
        'mobile-bottom-menu' => 'mobile_bottom_menu',
    ];
    private const LAYOUT_SECTION_META = [
        'header' => ['label' => 'Header', 'group' => 'core', 'sortable' => false, 'description' => 'Top navigation, branding, and entry experience.'],
        'slider' => ['label' => 'Slider', 'group' => 'core', 'sortable' => true, 'description' => 'Hero slider and first visual impact area.'],
        'banner' => ['label' => 'Banner', 'group' => 'core', 'sortable' => true, 'description' => 'Promotional banner block placed near the top of the homepage.'],
        'banner-bottom' => ['label' => 'Banner Bottom', 'group' => 'core', 'sortable' => true, 'description' => 'Secondary banner strip for follow-up offers and highlights.'],
        'feature-category' => ['label' => 'Feature Category', 'group' => 'core', 'sortable' => true, 'description' => 'Highlighted categories to guide browsing faster.'],
        'product' => ['label' => 'Product', 'group' => 'core', 'sortable' => true, 'description' => 'Primary product showcase block.'],
        'feature-product' => ['label' => 'Feature Product', 'group' => 'core', 'sortable' => true, 'description' => 'Curated featured products for stronger conversion focus.'],
        'best-sell-product' => ['label' => 'Best Sell Product', 'group' => 'core', 'sortable' => true, 'description' => 'Social proof driven bestseller section.'],
        'new-arrival' => ['label' => 'New Arrival', 'group' => 'core', 'sortable' => true, 'description' => 'Freshly added items shown as a discovery block.'],
        'testimonial' => ['label' => 'Testimonial', 'group' => 'core', 'sortable' => true, 'description' => 'Customer trust and review section.'],
        'youtube' => ['label' => 'YouTube', 'group' => 'core', 'sortable' => true, 'description' => 'Embedded brand video or campaign media section.'],
        'announcement' => ['label' => 'Announcement', 'group' => 'core', 'sortable' => true, 'description' => 'Campaign or notice bar section for urgent messaging.'],
        'about' => ['label' => 'About', 'group' => 'core', 'sortable' => true, 'description' => 'Short brand story or trust-building introduction section.'],
        'newsletter' => ['label' => 'Newsletter', 'group' => 'core', 'sortable' => true, 'description' => 'Email capture section for repeat marketing.'],
        'brand' => ['label' => 'Brand', 'group' => 'core', 'sortable' => true, 'description' => 'Partner or store brand showcase section.'],
        'blog' => ['label' => 'Blog', 'group' => 'core', 'sortable' => true, 'description' => 'Content and SEO support block.'],
        'footer' => ['label' => 'Footer', 'group' => 'core', 'sortable' => false, 'description' => 'Final support and store information area.'],
        'offer' => ['label' => 'Offer', 'group' => 'additional', 'sortable' => false, 'description' => 'Offer-focused landing block and quick campaign area.'],
        'single-product-page' => ['label' => 'Single Product Page', 'group' => 'additional', 'sortable' => false, 'description' => 'Detailed product page layout design.'],
        'shop-page' => ['label' => 'Shop Page', 'group' => 'additional', 'sortable' => false, 'description' => 'Catalog and product listing page layout.'],
        'checkout-page' => ['label' => 'Checkout Page', 'group' => 'additional', 'sortable' => false, 'description' => 'Checkout journey design and payment confidence layout.'],
        'login-page' => ['label' => 'Login Page', 'group' => 'additional', 'sortable' => false, 'description' => 'Customer sign-in and access experience.'],
        'product-card' => ['label' => 'Product Card', 'group' => 'additional', 'sortable' => false, 'description' => 'Shared product card design used across listings.'],
        'preloader' => ['label' => 'Preloader', 'group' => 'additional', 'sortable' => false, 'description' => 'Initial loading experience before storefront content appears.'],
        'mobile-bottom-menu' => ['label' => 'Mobile Bottom Menu', 'group' => 'additional', 'sortable' => false, 'description' => 'Persistent mobile bottom navigation shortcuts.'],
    ];

    private function resolveContext(): array
    {
        $user = auth()->user();
        $storeId = 0;
        $customerId = 0;
        if (($user->type ?? '') === 'admin' || ($user->type ?? '') === 'dropshipper') {
            $customer = Customer::where('uid', $user->id)->first();
            $storeId = (int) ($customer->active_store ?? 0);
            $customerId = (int) ($customer->id ?? 0);
        } elseif (($user->type ?? '') === 'staff') {
            $staff = Staff::where('uid', $user->id)->first();
            $storeId = (int) ($staff->store_id ?? 0);
            $customerId = (int) ($staff->customer_id ?? 0);
        }
        return ['user_id' => (int) ($user->id ?? 0), 'store_id' => $storeId, 'customer_id' => $customerId];
    }

    private function designForStore(int $storeId, int $userId, int $customerId): Design
    {
        $design = Design::where('store_id', $storeId)->first();
        if (!$design) {
            $design = new Design();
            $design->store_id = $storeId;
            $design->uid = $userId;
            $design->customer_id = $customerId;
            $design->creator = $userId;
            $design->editor = $userId;
            $design->header_color = '#ffffff';
            $design->text_color = '#000000';
            $templateId = $this->storedTemplateId($storeId, $customerId);
            if ($templateId > 0 && Schema::hasColumn('designs', 'template_id')) {
                $design->template_id = $templateId;
            }
            $design->save();
        }
        return $design;
    }

    private function storedTemplateId(int $storeId, int $customerId): int
    {
        $storeTemplateId = Schema::hasTable('stores') && Schema::hasColumn('stores', 'template_id')
            ? (int) (Store::query()->where('id', $storeId)->value('template_id') ?? 0)
            : 0;
        if ($storeTemplateId > 0) {
            return $storeTemplateId;
        }

        return Schema::hasTable('customers') && Schema::hasColumn('customers', 'template_id')
            ? (int) (Customer::query()->where('id', $customerId)->value('template_id') ?? 0)
            : 0;
    }

    private function activeTemplateIdForStore(int $storeId, int $customerId, ?Design $design = null): int
    {
        $activeTemplateId = (int) ($design->template_id ?? 0);
        if ($activeTemplateId > 0) {
            return $activeTemplateId;
        }

        $storedTemplateId = $this->storedTemplateId($storeId, $customerId);
        if ($storedTemplateId > 0 && $design && Schema::hasColumn('designs', 'template_id')) {
            $design->template_id = $storedTemplateId;
            $design->save();
        }

        return $storedTemplateId;
    }

    private function decodeSectionSettings(Design $design): array
    {
        $raw = $design->section_settings ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function sectionTemplateValues(Design $design, array $settings): array
    {
        $values = [];
        foreach (self::SECTION_COLUMN_MAP as $sectionKey => $column) {
            $template = data_get($settings, "{$sectionKey}.template");
            if (!is_string($template) || trim($template) === '') {
                $template = (string) ($design->{$column} ?? '');
            }
            $values[$sectionKey] = $template;
        }
        return $values;
    }

    private function sectionTemplateOptions(): array
    {
        $types = array_values(array_unique(array_values(self::SECTION_DESIGNLIST_TYPE_MAP)));
        $rows = Designlist::query()
            ->where('status', 'active')
            ->whereIn('type', $types)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'value', 'type', 'image']);

        $groupedByType = [];
        foreach ($rows as $row) {
            $type = (string) ($row->type ?? '');
            if ($type === '') {
                continue;
            }
            $label = trim((string) ($row->name ?? '')) ?: trim((string) ($row->value ?? ''));
            $groupedByType[$type][] = [
                'id' => (int) $row->id,
                'label' => $label,
                'value' => (string) ($row->value ?? ''),
                'type' => $type,
                'preview_image_url' => $this->previewAssetUrl($row->image ?? null, 'assets/images/design'),
            ];
        }

        $sectionOptions = [];
        foreach (self::SECTION_DESIGNLIST_TYPE_MAP as $sectionKey => $type) {
            $sectionOptions[$sectionKey] = $groupedByType[$type] ?? [];
        }

        return $sectionOptions;
    }

    private function layoutSectionsForStore(Design $design, int $storeId): array
    {
        $positionRows = DB::table('design_positions')
            ->where('store_id', $storeId)
            ->get(['name', 'position']);

        $positionMap = [];
        foreach ($positionRows as $row) {
            $positionMap[(string) ($row->name ?? '')] = (int) ($row->position ?? 0);
        }

        $sectionSettings = $this->decodeSectionSettings($design);
        $sectionSettings['checkout-page'] = array_merge(
            is_array($sectionSettings['checkout-page'] ?? null) ? $sectionSettings['checkout-page'] : [],
            $this->checkoutFieldSettingsForStore($storeId)
        );
        $templateValues = $this->sectionTemplateValues($design, $sectionSettings);
        $templateOptions = $this->sectionTemplateOptions();

        $fallback = 1;
        $items = [];
        foreach (self::LAYOUT_SECTION_META as $sectionKey => $meta) {
            $column = self::SECTION_COLUMN_MAP[$sectionKey] ?? null;
            $enabled = true;
            if ($column && Schema::hasColumn('designs', $column)) {
                $enabled = !is_null($design->{$column});
            }
            if (!$enabled) {
                continue;
            }

            $positionKey = $column ?: $sectionKey;
            $position = $positionMap[$positionKey] ?? $fallback;
            $fallback++;
            $currentTemplate = (string) ($templateValues[$sectionKey] ?? '');
            $currentTemplateImage = collect($templateOptions[$sectionKey] ?? [])
                ->first(fn ($option) => (string) ($option['value'] ?? '') === $currentTemplate);

            $items[] = [
                'key' => $sectionKey,
                'column' => $column,
                'position_key' => $positionKey,
                'label' => $meta['label'],
                'group' => $meta['group'],
                'sortable' => (bool) $meta['sortable'],
                'description' => $meta['description'],
                'position' => (int) $position,
                'template' => $currentTemplate,
                'template_preview_image_url' => $currentTemplateImage['preview_image_url'] ?? null,
                'template_options' => $templateOptions[$sectionKey] ?? [],
                'settings' => is_array($sectionSettings[$sectionKey] ?? null) ? $sectionSettings[$sectionKey] : [],
            ];
        }

        usort($items, function (array $a, array $b) {
            $groupOrder = ['core' => 0, 'additional' => 1];
            $groupSort = (($groupOrder[$a['group']] ?? 99) <=> ($groupOrder[$b['group']] ?? 99));
            if ($groupSort !== 0) {
                return $groupSort;
            }
            return ((int) $a['position']) <=> ((int) $b['position']);
        });

        return $items;
    }

    private function defaultCheckoutFieldStatuses(): array
    {
        return [
            'name' => 1,
            'phone' => 1,
            'email' => 0,
            'address' => 1,
            'note' => 0,
            'district' => 0,
            'language' => 0,
        ];
    }

    private function checkoutFieldSettingsForStore(int $storeId): array
    {
        if ($storeId <= 0 || !Schema::hasTable('checkout_forms')) {
            return [
                'show_checkout_name' => true,
                'show_checkout_phone' => true,
                'show_checkout_email' => false,
                'show_checkout_address' => true,
                'show_checkout_note' => false,
                'show_checkout_district' => false,
                'show_checkout_language' => false,
            ];
        }

        $statuses = $this->defaultCheckoutFieldStatuses();
        $rows = CheckoutForm::query()
            ->where('store_id', (string) $storeId)
            ->get(['name', 'status']);

        if ($rows->isEmpty()) {
            foreach ($statuses as $name => $status) {
                CheckoutForm::query()->updateOrCreate(
                    ['name' => $name, 'store_id' => (string) $storeId],
                    ['status' => $status]
                );
            }
        } else {
            foreach ($rows as $row) {
                $name = (string) ($row->name ?? '');
                if (array_key_exists($name, $statuses)) {
                    $statuses[$name] = (int) ($row->status ?? 0);
                }
            }
        }

        return [
            'show_checkout_name' => (bool) ($statuses['name'] ?? 0),
            'show_checkout_phone' => (bool) ($statuses['phone'] ?? 0),
            'show_checkout_email' => (bool) ($statuses['email'] ?? 0),
            'show_checkout_address' => (bool) ($statuses['address'] ?? 0),
            'show_checkout_note' => (bool) ($statuses['note'] ?? 0),
            'show_checkout_district' => (bool) ($statuses['district'] ?? 0),
            'show_checkout_language' => (bool) ($statuses['language'] ?? 0),
        ];
    }

    private function saveCheckoutFieldSettings(int $storeId, array $settings): void
    {
        if ($storeId <= 0 || !Schema::hasTable('checkout_forms')) {
            return;
        }

        $map = [
            'name' => 'show_checkout_name',
            'phone' => 'show_checkout_phone',
            'email' => 'show_checkout_email',
            'address' => 'show_checkout_address',
            'note' => 'show_checkout_note',
            'district' => 'show_checkout_district',
            'language' => 'show_checkout_language',
        ];

        foreach ($map as $name => $settingKey) {
            CheckoutForm::query()->updateOrCreate(
                ['name' => $name, 'store_id' => (string) $storeId],
                ['status' => !empty($settings[$settingKey]) ? 1 : 0]
            );
        }
    }

    private function previewAssetUrl(?string $value, string $directory = ''): ?string
    {
        $path = trim((string) $value);
        if ($path === '' || strtolower($path) === 'null') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        foreach (['public/storage/', 'storage/app/public/', 'app/public/'] as $prefix) {
            if (Str::startsWith($normalized, $prefix)) {
                $normalized = Str::after($normalized, $prefix);
                break;
            }
        }

        if (Str::startsWith($normalized, 'storage/image-library/') || Str::startsWith($normalized, 'storage/ai-seed-library/')) {
            return publicMediaLibraryUrl(Str::after($normalized, 'storage/'));
        }

        if (Str::startsWith($normalized, ['image-library/', 'ai-seed-library/'])) {
            return publicMediaLibraryUrl($normalized);
        }

        if (Str::startsWith($normalized, ['storage/', 'assets/'])) {
            return url($normalized);
        }

        if (str_contains($normalized, '/')) {
            return url($normalized);
        }

        if ($directory === '') {
            return url($normalized);
        }

        return url(trim($directory, '/') . '/' . $normalized);
    }

    private function menuLabel(Menu $menu): string
    {
        foreach (['name', 'menu_name', 'title', 'label', 'page_name', 'menu'] as $field) {
            $value = trim((string) ($menu->{$field} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Menu';
    }

    private function menuHref(Menu $menu): string
    {
        foreach (['custom_link', 'link', 'url', 'slug'] as $field) {
            $value = trim((string) ($menu->{$field} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '#';
    }

    private function sectionContentDesigns(int $storeId): array
    {
        return StoreDesign::query()
            ->where('store_id', $storeId)
            ->get()
            ->groupBy('type')
            ->map(fn ($rows) => $rows->map(function (StoreDesign $row) {
                return [
                    'id' => (int) $row->id,
                    'title' => (string) ($row->title ?? ''),
                    'title_color' => (string) ($row->title_color ?? ''),
                    'subtitle' => (string) ($row->subtitle ?? ''),
                    'subtitle_color' => (string) ($row->subtitle_color ?? ''),
                    'button' => (string) ($row->button ?? ''),
                    'button_color' => (string) ($row->button_color ?? ''),
                    'button_bg_color' => (string) ($row->button_bg_color ?? ''),
                    'button1' => (string) ($row->button1 ?? ''),
                    'button1_color' => (string) ($row->button1_color ?? ''),
                    'button1_bg_color' => (string) ($row->button1_bg_color ?? ''),
                    'link' => (string) ($row->link ?? ''),
                    'bg_image_url' => $this->previewAssetUrl($row->bg_image ?? null, 'assets/images/design'),
                    'image_description' => (string) ($row->image_description ?? ''),
                    'type' => (string) ($row->type ?? ''),
                ];
            })->values()->all())
            ->toArray();
    }

    private function previewProducts(int $storeId, string $mode = 'all', int $limit = 8): array
    {
        $query = Product::query()
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->with(['getBrand:id,name']);

        if ($mode === 'feature') {
            if (Schema::hasColumn('products', 'feature')) {
                $query->where('feature', 1);
            }
        } elseif ($mode === 'best-sell') {
            if (Schema::hasColumn('products', 'best_sell')) {
                $query->where('best_sell', 1);
            }
        } elseif ($mode === 'new-arrival') {
            $query->latest('id');
        } else {
            $query->latest('id');
        }

        return $query->limit($limit)->get()->map(function (Product $product) {
            $firstImage = collect(explode(',', (string) ($product->images ?? '')))
                ->map(fn ($item) => trim($item))
                ->filter()
                ->first();

            $regularPrice = is_numeric($product->regular_price ?? null) ? (float) $product->regular_price : null;
            $salePrice = is_numeric($product->promotional_price ?? null) ? (float) $product->promotional_price : null;

            return [
                'id' => (int) $product->id,
                'name' => (string) ($product->name ?? ''),
                'image_url' => $this->previewAssetUrl($firstImage, 'assets/images/product'),
                'brand' => (string) ($product->getBrand->name ?? ''),
                'regular_price' => $regularPrice,
                'sale_price' => $salePrice,
                'display_price' => $salePrice && $salePrice > 0 ? $salePrice : $regularPrice,
                'slug' => (string) ($product->slug ?? ''),
            ];
        })->values()->all();
    }

    private function previewSectionData(
        string $sectionKey,
        int $storeId,
        ?Store $store,
        ?Headersetting $headerSetting,
        array $storeDesigns
    ): array {
        $designBlocks = $storeDesigns[self::SECTION_COLUMN_MAP[$sectionKey] ?? $sectionKey] ?? [];

        if ($sectionKey === 'header') {
            $menuQuery = Menu::query();
            if (Schema::hasColumn('menus', 'store_id')) {
                $menuQuery->where('store_id', $storeId);
            }
            if (Schema::hasColumn('menus', 'status')) {
                $menuQuery->whereIn('status', ['1', 1, 'active']);
            }
            if (Schema::hasColumn('menus', 'sort')) {
                $menuQuery->orderBy('sort', 'asc');
            } else {
                $menuQuery->orderBy('id', 'asc');
            }

            $menus = $menuQuery->limit(8)->get()->map(fn (Menu $menu) => [
                'id' => (int) $menu->id,
                'label' => $this->menuLabel($menu),
                'href' => $this->menuHref($menu),
            ])->values()->all();

            $announcements = Announcement::query()
                ->where('store_id', $storeId)
                ->where('status', 1)
                ->latest('id')
                ->limit(3)
                ->get()
                ->map(fn (Announcement $announcement) => (string) ($announcement->announcement ?? ''))
                ->filter()
                ->values()
                ->all();

            return [
                'kind' => 'header',
                'menus' => $menus,
                'announcements' => $announcements,
                'logo_url' => $this->previewAssetUrl($headerSetting->logo ?? null, 'assets/images/setting'),
                'store_name' => (string) ($store->name ?? $store->url ?? 'Your Store'),
                'phone' => (string) ($headerSetting->phone ?? ''),
            ];
        }

        if ($sectionKey === 'slider') {
            $query = Slider::query();
            if (Schema::hasColumn('sliders', 'store_id')) {
                $query->where('store_id', $storeId);
            }
            if (Schema::hasColumn('sliders', 'status')) {
                $query->where('status', 'active');
            }
            if (Schema::hasColumn('sliders', 'position')) {
                $query->orderBy('position', 'asc');
            } else {
                $query->orderBy('id', 'desc');
            }

            $slides = $query
                ->limit(5)
                ->get()
                ->map(fn (Slider $slider) => [
                    'id' => (int) $slider->id,
                    'title' => (string) ($slider->title ?? ''),
                    'subtitle' => (string) ($slider->subtitle ?? ''),
                    'button' => (string) ($slider->button ?? ''),
                    'link' => (string) ($slider->link ?? ''),
                    'image_url' => $this->previewAssetUrl($slider->image ?? null, 'assets/images/slider'),
                    'subimage_url' => $this->previewAssetUrl($slider->subimage ?? null, 'assets/images/slider'),
                ])->values()->all();

            return [
                'kind' => 'slider',
                'slides' => $slides,
                'design_blocks' => $designBlocks,
            ];
        }

        if (in_array($sectionKey, ['banner', 'banner-bottom'], true)) {
            $query = Banner::query();
            if (Schema::hasColumn('banners', 'store_id')) {
                $query->where('store_id', $storeId);
            }
            if (Schema::hasColumn('banners', 'status')) {
                $query->where('status', 'active');
            }
            if (Schema::hasColumn('banners', 'type')) {
                $candidateTypes = $sectionKey === 'banner-bottom'
                    ? ['banner_bottom', 'bottom', '1', 1]
                    : ['banner', 'top', '0', 0];
                $query->when(true, function ($q) use ($candidateTypes, $sectionKey) {
                    if ($sectionKey === 'banner-bottom') {
                        $q->whereIn('type', $candidateTypes);
                    } else {
                        $q->where(function ($inner) use ($candidateTypes) {
                            $inner->whereIn('type', $candidateTypes)->orWhereNull('type');
                        });
                    }
                });
            }

            $banners = $query->limit(4)->get()->map(fn (Banner $banner) => [
                'id' => (int) $banner->id,
                'image_url' => $this->previewAssetUrl($banner->image ?? null, 'assets/images/banner'),
                'link' => (string) ($banner->link ?? ''),
            ])->values()->all();

            return [
                'kind' => 'banner',
                'banners' => $banners,
                'design_blocks' => $designBlocks,
            ];
        }

        if ($sectionKey === 'feature-category') {
            $categories = Category::query()
                ->when(Schema::hasColumn('categories', 'store_id'), fn ($query) => $query->where('store_id', $storeId))
                ->where('status', 'active')
                ->when(Schema::hasColumn('categories', 'parent'), fn ($query) => $query->where(function ($inner) {
                    $inner->whereNull('parent')->orWhere('parent', '0')->orWhere('parent', '');
                }))
                ->orderBy('position', 'asc')
                ->limit(8)
                ->get()
                ->map(fn (Category $category) => [
                    'id' => (int) $category->id,
                    'name' => (string) ($category->name ?? ''),
                ])->values()->all();

            return [
                'kind' => 'categories',
                'categories' => $categories,
                'design_blocks' => $designBlocks,
            ];
        }

        if (in_array($sectionKey, ['product', 'feature-product', 'best-sell-product', 'new-arrival'], true)) {
            $mode = match ($sectionKey) {
                'feature-product' => 'feature',
                'best-sell-product' => 'best-sell',
                'new-arrival' => 'new-arrival',
                default => 'all',
            };

            return [
                'kind' => 'products',
                'products' => $this->previewProducts($storeId, $mode),
                'design_blocks' => $designBlocks,
            ];
        }

        if ($sectionKey === 'testimonial') {
            $items = Testimonial::query()
                ->where('store_id', $storeId)
                ->where('status', 'active')
                ->orderBy('position', 'asc')
                ->limit(6)
                ->get()
                ->map(fn (Testimonial $testimonial) => [
                    'id' => (int) $testimonial->id,
                    'name' => (string) ($testimonial->name ?? ''),
                    'occupation' => (string) ($testimonial->occupation ?? ''),
                    'feedback' => (string) ($testimonial->feedback ?? ''),
                    'image_url' => $this->previewAssetUrl($testimonial->image ?? null, 'assets/images/testimonials'),
                ])->values()->all();

            return [
                'kind' => 'testimonial',
                'items' => $items,
                'design_blocks' => $designBlocks,
            ];
        }

        if ($sectionKey === 'announcement') {
            $items = Announcement::query()
                ->where('store_id', $storeId)
                ->where('status', 1)
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Announcement $announcement) => [
                    'id' => (int) $announcement->id,
                    'text' => (string) ($announcement->announcement ?? ''),
                ])->values()->all();

            return [
                'kind' => 'announcement',
                'items' => $items,
                'design_blocks' => $designBlocks,
            ];
        }

        if ($sectionKey === 'brand') {
            $items = Brand::query()
                ->when(Schema::hasColumn('brands', 'store_id'), fn ($query) => $query->where('store_id', $storeId))
                ->when(Schema::hasColumn('brands', 'status'), fn ($query) => $query->where('status', 'active'))
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn (Brand $brand) => [
                    'id' => (int) $brand->id,
                    'name' => (string) ($brand->name ?? ''),
                    'image_url' => $this->previewAssetUrl($brand->image ?? null, 'assets/images/brand'),
                ])->values()->all();

            return [
                'kind' => 'brand',
                'items' => $items,
                'design_blocks' => $designBlocks,
            ];
        }

        if ($sectionKey === 'blog') {
            $query = AdminBlog::query()->where('status', 'active');
            if (Schema::hasColumn('admin_blogs', 'website')) {
                $query->where('website', 1);
            }
            if (Schema::hasColumn('admin_blogs', 'store_id')) {
                $query->where('store_id', $storeId);
            }

            $items = $query
                ->latest('id')
                ->limit(4)
                ->get()
                ->map(fn (AdminBlog $blog) => [
                    'id' => (int) $blog->id,
                    'title' => (string) ($blog->title ?? ''),
                    'subtitle' => (string) ($blog->sub_title ?? ''),
                    'thumbnail_url' => $this->previewAssetUrl($blog->thumbnail ?? null, 'BlogImages'),
                ])->values()->all();

            return [
                'kind' => 'blog',
                'items' => $items,
                'design_blocks' => $designBlocks,
            ];
        }

        if ($sectionKey === 'footer') {
            $pages = Page::query()
                ->where('store_id', $storeId)
                ->where('status', 'active')
                ->latest('id')
                ->limit(6)
                ->get()
                ->map(fn (Page $page) => [
                    'id' => (int) $page->id,
                    'name' => (string) ($page->name ?? ''),
                    'link' => (string) ($page->link ?? ''),
                ])->values()->all();

            return [
                'kind' => 'footer',
                'pages' => $pages,
                'contact' => [
                    'phone' => (string) ($headerSetting->phone ?? ''),
                    'email' => (string) ($headerSetting->email ?? ''),
                    'address' => (string) ($headerSetting->address ?? ''),
                ],
                'design_blocks' => $designBlocks,
            ];
        }

        if ($sectionKey === 'youtube') {
            return [
                'kind' => 'video',
                'video_url' => (string) ($store->youtube_link ?? ''),
                'design_blocks' => $designBlocks,
            ];
        }

        if (in_array($sectionKey, ['about', 'newsletter'], true)) {
            return [
                'kind' => 'content',
                'design_blocks' => $designBlocks,
            ];
        }

        return [
            'kind' => 'generic',
            'design_blocks' => $designBlocks,
        ];
    }

    public function homepagePreview(): JsonResponse
    {
        $ctx = $this->resolveContext();
        $storeId = (int) $ctx['store_id'];
        $design = $this->designForStore($storeId, (int) $ctx['user_id'], (int) $ctx['customer_id']);
        $store = Store::query()->find($storeId);
        $headerSetting = Headersetting::query()->where('store_id', $storeId)->first();
        $storeDesigns = $this->sectionContentDesigns($storeId);

        $items = collect($this->layoutSectionsForStore($design, $storeId))
            ->map(function (array $item) use ($storeId, $store, $headerSetting, $storeDesigns) {
                $item['live_preview'] = $this->previewSectionData(
                    (string) $item['key'],
                    $storeId,
                    $store,
                    $headerSetting,
                    $storeDesigns
                );

                return $item;
            })
            ->values()
            ->all();

        return response()->json([
            'store' => [
                'id' => (int) ($store->id ?? 0),
                'name' => (string) ($store->name ?? $store->url ?? 'Your Store'),
                'url' => (string) ($store->url ?? ''),
                'logo_url' => $this->previewAssetUrl($headerSetting->logo ?? null, 'assets/images/setting'),
                'favicon_url' => $this->previewAssetUrl($headerSetting->favicon ?? null, 'assets/images/setting'),
            ],
            'header' => [
                'header_color' => (string) ($design->header_color ?? '#ffffff'),
                'text_color' => (string) ($design->text_color ?? '#111827'),
                'mobile_bottom_menu' => (string) ($design->mobile_bottom_menu ?? ''),
            ],
            'items' => $items,
        ]);
    }

    public function themes(Request $request): JsonResponse
    {
        $ctx = $this->resolveContext();
        $storeId = (int) $ctx['store_id'];
        $keyword = trim((string) $request->query('search', ''));
        $categoryFilter = trim((string) $request->query('category', ''));
        $pricingFilter = trim((string) $request->query('pricing', 'all')); // all|free|paid
        $activeDesign = Design::where('store_id', $storeId)->first();
        $activeTemplateId = $this->activeTemplateIdForStore($storeId, (int) $ctx['customer_id'], $activeDesign);

        $query = Template::query()->where('status', 'active')->orderBy('position', 'asc')->orderByDesc('id');
        if ($keyword !== '') {
            $query->where('name', 'like', "%{$keyword}%");
        }
        $columns = Schema::hasTable('templates') ? Schema::getColumnListing('templates') : [];
        $categoryColumn = collect(['category', 'theme_category', 'type'])->first(fn ($c) => in_array($c, $columns, true));
        $priceColumn = collect(['price', 'amount', 'cost'])->first(fn ($c) => in_array($c, $columns, true));
        $rawRows = $query->get();
        $categoryIds = $rawRows
            ->flatMap(function (Template $row) use ($categoryColumn) {
                $rawCategory = $categoryColumn ? trim((string) ($row->{$categoryColumn} ?? '')) : '';
                if ($rawCategory === '') {
                    return [];
                }

                return collect(explode(',', $rawCategory))
                    ->map(fn ($token) => trim((string) $token))
                    ->filter(fn (string $token) => $token !== '' && ctype_digit($token))
                    ->all();
            })
            ->unique()
            ->values();
        $businessCategoryMap = $categoryIds->isNotEmpty()
            ? BusinessCategory::query()
                ->whereIn('id', $categoryIds->map(fn (string $id) => (int) $id)->all())
                ->pluck('name', 'id')
                ->map(fn ($name) => trim((string) $name))
                ->all()
            : [];
        $legacyCategoryMap = $categoryIds->isNotEmpty()
            ? Category::query()
                ->whereIn('id', $categoryIds->map(fn (string $id) => (int) $id)->all())
                ->pluck('name', 'id')
                ->map(fn ($name) => trim((string) $name))
                ->all()
            : [];

        $allRows = $rawRows->map(function (Template $row) use ($activeTemplateId, $categoryColumn, $priceColumn, $businessCategoryMap, $legacyCategoryMap) {
            $rawCategory = $categoryColumn ? trim((string) ($row->{$categoryColumn} ?? '')) : '';
            $resolvedCategories = collect(explode(',', $rawCategory))
                ->map(fn ($token) => trim((string) $token))
                ->filter(fn (string $token) => $token !== '' && strtolower($token) !== 'select')
                ->map(function (string $token) use ($businessCategoryMap, $legacyCategoryMap) {
                    if (!ctype_digit($token)) {
                        return $token;
                    }

                    $id = (int) $token;

                    return $businessCategoryMap[$id]
                        ?? $legacyCategoryMap[$id]
                        ?? null;
                })
                ->filter(fn ($value) => filled($value))
                ->unique()
                ->values();

            $category = $resolvedCategories->isNotEmpty()
                ? $resolvedCategories->implode(', ')
                : 'General';
            $rawPrice = $priceColumn ? $row->{$priceColumn} : null;
            $price = is_numeric($rawPrice) ? (float) $rawPrice : 0.0;
            $pricingType = $price > 0 ? 'paid' : 'free';
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'short_description' => (string) ($row->short_description ?? ''),
                'liveurl' => (string) ($row->liveurl ?? ''),
                'feature_image_url' => $this->previewAssetUrl($row->feature_image ?? null, 'assets/images/template'),
                'main_image_url' => $this->previewAssetUrl($row->main_image ?? null, 'assets/images/template'),
                'is_active' => (int) $row->id === $activeTemplateId,
                'category' => $category,
                'pricing_type' => $pricingType,
                'price' => $price,
            ];
        });

        $items = $allRows->filter(function (array $row) use ($categoryFilter, $pricingFilter) {
            if ($categoryFilter !== '' && strcasecmp($row['category'], $categoryFilter) !== 0) {
                return false;
            }
            if (in_array($pricingFilter, ['free', 'paid'], true) && $row['pricing_type'] !== $pricingFilter) {
                return false;
            }
            return true;
        })->values();

        $categories = $allRows->pluck('category')->filter()->unique()->values();
        $headerSetting = Headersetting::where('store_id', $storeId)->first();
        $themeLocked = (int) ($headerSetting->theme_lock ?? 0) === 1;
        return response()->json([
            'items' => $items,
            'active_template_id' => $activeTemplateId,
            'categories' => $categories,
            'theme_locked' => $themeLocked,
        ]);
    }

    public function activateTheme(int $id): JsonResponse
    {
        $ctx = $this->resolveContext();
        $template = Template::findOrFail($id);
        $design = $this->designForStore((int) $ctx['store_id'], (int) $ctx['user_id'], (int) $ctx['customer_id']);
        $design->banner_bottom = $template->banner_bottom;
        $design->header = $template->header;
        $design->hero_slider = $template->slider;
        $design->banner = $template->banner;
        $design->feature_category = $template->feature_category;
        $design->product = $template->product;
        $design->feature_product = $template->feature_product;
        $design->best_sell_product = $template->best_sell_product;
        $design->new_arrival = $template->new_arrival;
        $design->testimonial = $template->testimonial;
        $design->footer = $template->footer;
        $design->single_product_page = $template->single_product_page;
        $design->shop_page = $template->shop_page;
        $design->checkout_page = $template->checkout_page;
        $design->login_page = $template->login_page;
        $design->profile_page = $template->profile_page;
        $design->invoice = $template->invoice;
        $design->product_card = $template->product_card;
        $design->product_modal = $template->product_modal;
        $design->preloader = $template->preloader;
        $design->mobile_bottom_menu = $template->mobile_bottom_menu;
        $design->blog = $template->blog;
        $design->contact = $template->contact;
        $design->offer = $template->offer;
        $design->auth = $template->auth;
        $design->template_id = $template->id;
        $design->editor = (int) $ctx['user_id'];
        $design->save();
        if (Schema::hasColumn('stores', 'template_id')) {
            Store::query()->where('id', (int) $ctx['store_id'])->update(['template_id' => $template->id]);
        }
        if (Schema::hasColumn('customers', 'template_id')) {
            Customer::query()->where('id', (int) $ctx['customer_id'])->update(['template_id' => $template->id]);
        }
        return response()->json(['success' => true]);
    }

    public function headerSettings(): JsonResponse
    {
        $ctx = $this->resolveContext();
        $design = $this->designForStore((int) $ctx['store_id'], (int) $ctx['user_id'], (int) $ctx['customer_id']);
        $sectionSettings = $this->decodeSectionSettings($design);
        return response()->json([
            'header' => (string) ($design->header ?? ''),
            'header_color' => (string) ($design->header_color ?? '#ffffff'),
            'text_color' => (string) ($design->text_color ?? '#000000'),
            'mobile_bottom_menu' => (string) ($design->mobile_bottom_menu ?? ''),
            'section_settings' => $sectionSettings,
            'section_templates' => $this->sectionTemplateValues($design, $sectionSettings),
            'template_options' => $this->sectionTemplateOptions(),
        ]);
    }

    public function layoutSections(): JsonResponse
    {
        $ctx = $this->resolveContext();
        $design = $this->designForStore((int) $ctx['store_id'], (int) $ctx['user_id'], (int) $ctx['customer_id']);
        return response()->json([
            'items' => $this->layoutSectionsForStore($design, (int) $ctx['store_id']),
        ]);
    }

    public function reorderLayoutSections(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.key' => ['required', 'string', 'max:100'],
            'items.*.position' => ['required', 'integer', 'min:1'],
        ]);

        $ctx = $this->resolveContext();
        $design = $this->designForStore((int) $ctx['store_id'], (int) $ctx['user_id'], (int) $ctx['customer_id']);
        $known = collect($this->layoutSectionsForStore($design, (int) $ctx['store_id']))
            ->keyBy('key');

        foreach ($payload['items'] as $item) {
            $key = (string) $item['key'];
            $knownItem = $known->get($key);
            if (!$knownItem || empty($knownItem['sortable'])) {
                continue;
            }

            DB::table('design_positions')->updateOrInsert(
                [
                    'store_id' => (int) $ctx['store_id'],
                    'name' => (string) $knownItem['position_key'],
                ],
                [
                    'position' => (int) $item['position'],
                ]
            );
        }

        return response()->json([
            'success' => true,
            'items' => $this->layoutSectionsForStore($design, (int) $ctx['store_id']),
        ]);
    }

    public function resetStorefrontBootstrapCache(): JsonResponse
    {
        $ctx = $this->resolveContext();
        $store = Store::query()->find((int) $ctx['store_id']);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found.',
            ], 404);
        }

        $cleared = StorefrontBootstrapCache::forget($store);

        return response()->json([
            'success' => true,
            'cleared' => $cleared,
            'message' => $cleared ? 'Storefront cache reset.' : 'No matching cache entry was found.',
        ]);
    }

    public function saveHeaderSettings(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'header' => ['nullable', 'string', 'max:255'],
            'header_color' => ['nullable', 'string', 'max:30'],
            'text_color' => ['nullable', 'string', 'max:30'],
            'mobile_bottom_menu' => ['nullable', 'string', 'max:255'],
            'section_key' => ['nullable', 'string', 'max:80'],
            'section_template' => ['nullable', 'string', 'max:255'],
            'menu_config' => ['nullable', 'array'],
            'section_settings' => ['nullable', 'array'],
        ]);
        $ctx = $this->resolveContext();
        $design = $this->designForStore((int) $ctx['store_id'], (int) $ctx['user_id'], (int) $ctx['customer_id']);
        $sectionSettings = $this->decodeSectionSettings($design);

        if (isset($payload['section_settings']) && is_array($payload['section_settings'])) {
            $sectionSettings = $payload['section_settings'];
        }

        $sectionKey = trim((string) ($payload['section_key'] ?? ''));
        $sectionTemplate = (string) ($payload['section_template'] ?? '');
        if ($sectionKey !== '') {
            $entry = is_array($sectionSettings[$sectionKey] ?? null) ? $sectionSettings[$sectionKey] : [];
            if (array_key_exists('header_color', $payload)) {
                $entry['header_color'] = (string) ($payload['header_color'] ?? '');
            }
            if (array_key_exists('text_color', $payload)) {
                $entry['text_color'] = (string) ($payload['text_color'] ?? '');
            }
            if (array_key_exists('mobile_bottom_menu', $payload)) {
                $entry['mobile_bottom_menu'] = (string) ($payload['mobile_bottom_menu'] ?? '');
            }
            if ($sectionTemplate !== '') {
                $entry['template'] = $sectionTemplate;
            }
            if (isset($payload['menu_config']) && is_array($payload['menu_config'])) {
                $entry['menu_config'] = $payload['menu_config'];
            }
            $sectionSettings[$sectionKey] = $entry;

            $mappedColumn = self::SECTION_COLUMN_MAP[$sectionKey] ?? null;
            if ($mappedColumn && $sectionTemplate !== '' && Schema::hasColumn('designs', $mappedColumn)) {
                $design->{$mappedColumn} = $sectionTemplate;
            }

            if ($sectionKey === 'checkout-page') {
                $this->saveCheckoutFieldSettings((int) $ctx['store_id'], $entry);
            }
        }

        $design->header = (string) ($payload['header'] ?? $sectionTemplate ?? $design->header);
        $design->header_color = (string) ($payload['header_color'] ?? $design->header_color);
        $design->text_color = (string) ($payload['text_color'] ?? $design->text_color);
        $design->mobile_bottom_menu = (string) ($payload['mobile_bottom_menu'] ?? $design->mobile_bottom_menu);
        if (Schema::hasColumn('designs', 'section_settings')) {
            $design->section_settings = json_encode($sectionSettings);
        }
        $design->editor = (int) $ctx['user_id'];
        $design->save();
        return response()->json([
            'success' => true,
            'section_settings' => $sectionSettings,
            'section_templates' => $this->sectionTemplateValues($design, $sectionSettings),
        ]);
    }

    public function testimonials(Request $request): JsonResponse
    {
        $ctx = $this->resolveContext();
        $search = trim((string) $request->query('search', ''));
        $rows = Testimonial::where('store_id', (int) $ctx['store_id'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('position', 'asc')
            ->orderByDesc('id')
            ->get();
        return response()->json([
            'items' => $rows->map(fn (Testimonial $t) => [
                'id' => (int) $t->id,
                'name' => (string) ($t->name ?? ''),
                'occupation' => (string) ($t->occupation ?? ''),
                'feedback' => (string) ($t->feedback ?? ''),
                'image' => (string) ($t->image ?? ''),
                'image_url' => $this->previewAssetUrl($t->image ?? null, 'assets/images/testimonials'),
                'position' => (int) ($t->position ?? 0),
                'status' => (string) ($t->status ?? 'inactive'),
            ])->values(),
        ]);
    }

    public function testimonialStore(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'feedback' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'image_media_path' => ['nullable', 'string', 'max:500'],
            'image_upload' => ['nullable', 'file', 'image', 'max:10240'],
            'position' => ['required', 'integer'],
            'status' => ['required', 'in:active,inactive'],
        ]);
        $ctx = $this->resolveContext();
        $row = new Testimonial();
        $row->name = $payload['name'];
        $row->occupation = $payload['occupation'] ?? null;
        $row->feedback = $payload['feedback'] ?? null;
        $row->image = $this->storeTestimonialImage($request, $payload);
        $row->position = (int) $payload['position'];
        $row->status = $payload['status'];
        $row->uid = (int) $ctx['user_id'];
        $row->customer_id = (int) $ctx['customer_id'];
        $row->store_id = (int) $ctx['store_id'];
        $row->creator = (int) $ctx['user_id'];
        $row->editor = (int) $ctx['user_id'];
        $row->save();
        return response()->json(['success' => true, 'id' => (int) $row->id]);
    }

    public function testimonialUpdate(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'feedback' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'image_media_path' => ['nullable', 'string', 'max:500'],
            'image_upload' => ['nullable', 'file', 'image', 'max:10240'],
            'position' => ['required', 'integer'],
            'status' => ['required', 'in:active,inactive'],
        ]);
        $ctx = $this->resolveContext();
        $row = Testimonial::where('store_id', (int) $ctx['store_id'])->findOrFail($id);
        $row->name = $payload['name'];
        $row->occupation = $payload['occupation'] ?? null;
        $row->feedback = $payload['feedback'] ?? null;
        $storedImage = $this->storeTestimonialImage($request, $payload);
        if ($storedImage !== null && $storedImage !== '') {
            $row->image = $storedImage;
        }
        $row->position = (int) $payload['position'];
        $row->status = $payload['status'];
        $row->editor = (int) $ctx['user_id'];
        $row->save();
        return response()->json(['success' => true]);
    }

    public function testimonialDelete(int $id): JsonResponse
    {
        $ctx = $this->resolveContext();
        $row = Testimonial::where('store_id', (int) $ctx['store_id'])->findOrFail($id);
        $row->delete();
        return response()->json(['success' => true]);
    }

    private function storeTestimonialImage(Request $request, array $payload): ?string
    {
        if ($request->hasFile('image_upload')) {
            return $this->storeUploadedTestimonialImage($request->file('image_upload'));
        }

        if (!empty($payload['image_media_path'])) {
            return $this->normalizeStoredMediaReference((string) $payload['image_media_path']);
        }

        $existing = trim((string) ($payload['image'] ?? ''));
        return $existing !== '' ? $existing : null;
    }

    private function normalizeStoredMediaReference(string $value): string
    {
        $path = trim($value);
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

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '' && str_starts_with($path, $appUrl)) {
            $path = substr($path, strlen($appUrl));
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'storage/')) {
            return $path;
        }
        if (Str::startsWith($path, ['image-library/', 'ai-seed-library/'])) {
            return 'storage/' . $path;
        }

        return getLibraryImagePath($path);
    }

    private function storeUploadedTestimonialImage($file): string
    {
        $targetDir = public_path('assets/images/testimonials');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $ext = strtolower((string) ($file->getClientOriginalExtension() ?: 'jpg'));
        $filename = 'testimonial_' . uniqid('', true) . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
        $file->move($targetDir, $filename);

        return $filename;
    }

    public function pages(Request $request): JsonResponse
    {
        $ctx = $this->resolveContext();
        $search = trim((string) $request->query('search', ''));
        $rows = Page::where('store_id', (int) $ctx['store_id'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('id')
            ->paginate(25);
        return response()->json([
            'items' => collect($rows->items())->map(fn (Page $p) => [
                'id' => (int) $p->id,
                'name' => (string) ($p->name ?? ''),
                'slug' => (string) ($p->slug ?? ''),
                'details' => (string) ($p->details ?? ''),
                'feature_image' => (string) ($p->feature_image ?? ''),
                'feature_image_url' => $this->resolvePageFeatureImageUrl($p),
                'link' => (string) ($p->link ?? ''),
                'position' => (int) ($p->position ?? 0),
                'status' => (string) ($p->status ?? 'inactive'),
                'route_path' => '/' . ltrim((string) ($p->slug ?? ''), '/'),
            ])->values(),
            'pagination' => [
                'total' => $rows->total(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
            ],
        ]);
    }

    public function pageStore(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'feature_image' => ['nullable', 'string', 'max:255'],
            'feature_image_media_path' => ['nullable', 'string', 'max:500'],
            'feature_image_upload' => ['nullable', 'file', 'image', 'max:10240'],
            'link' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);
        $ctx = $this->resolveContext();
        $exists = Page::where('store_id', (int) $ctx['store_id'])->where('name', $payload['name'])->first();
        if ($exists) {
            return response()->json(['message' => 'Name already exists.'], 422);
        }

        $slug = $this->buildPageSlug($payload['slug'] ?? $payload['name']);
        $slugTaken = Page::where('store_id', (int) $ctx['store_id'])->where('slug', $slug)->exists();
        if ($slugTaken) {
            return response()->json(['message' => 'Page route already exists.'], 422);
        }

        $row = new Page();
        $row->name = $payload['name'];
        $row->slug = $slug;
        $row->details = $payload['details'] ?? null;
        $row->feature_image = $this->storePageFeatureImage($request, $payload);
        $row->link = $this->normalizePageLink($payload['link'] ?? null);
        $row->status = $payload['status'];
        $row->uid = (int) $ctx['user_id'];
        $row->store_id = (int) $ctx['store_id'];
        $row->customer_id = (int) $ctx['customer_id'];
        $row->creator = (int) $ctx['user_id'];
        $row->editor = (int) $ctx['user_id'];
        $row->save();
        return response()->json([
            'success' => true,
            'id' => (int) $row->id,
            'route_path' => '/' . ltrim((string) $row->slug, '/'),
        ]);
    }

    public function pageUpdate(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'feature_image' => ['nullable', 'string', 'max:255'],
            'feature_image_media_path' => ['nullable', 'string', 'max:500'],
            'feature_image_upload' => ['nullable', 'file', 'image', 'max:10240'],
            'link' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);
        $ctx = $this->resolveContext();
        $row = Page::where('store_id', (int) $ctx['store_id'])->findOrFail($id);
        $dup = Page::where('store_id', (int) $ctx['store_id'])->where('name', $payload['name'])->where('id', '!=', $id)->first();
        if ($dup) {
            return response()->json(['message' => 'Name already exists.'], 422);
        }

        $slug = $this->buildPageSlug($payload['slug'] ?? $row->slug ?? $payload['name']);
        $slugTaken = Page::where('store_id', (int) $ctx['store_id'])->where('slug', $slug)->where('id', '!=', $id)->exists();
        if ($slugTaken) {
            return response()->json(['message' => 'Page route already exists.'], 422);
        }

        $row->name = $payload['name'];
        $row->slug = $slug;
        $row->details = $payload['details'] ?? null;
        $storedFeatureImage = $this->storePageFeatureImage($request, $payload);
        if ($storedFeatureImage !== null && $storedFeatureImage !== '') {
            $row->feature_image = $storedFeatureImage;
        }
        $row->link = $this->normalizePageLink($payload['link'] ?? null);
        $row->status = $payload['status'];
        $row->editor = (int) $ctx['user_id'];
        $row->save();
        return response()->json(['success' => true]);
    }

    public function pageDelete(int $id): JsonResponse
    {
        $ctx = $this->resolveContext();
        $row = Page::where('store_id', (int) $ctx['store_id'])->findOrFail($id);
        $row->delete();
        return response()->json(['success' => true]);
    }

    private function buildPageSlug(?string $value): string
    {
        $slug = trim((string) $value);
        $slug = preg_replace('~[^A-Za-z0-9_-]+~', '-', $slug);
        $slug = trim((string) $slug, " \t\n\r\0\x0B-_/");
        $slug = Str::lower(str_replace('-', '_', $slug));

        if ($slug === '') {
            return 'page_' . Str::lower(Str::random(6));
        }

        return $slug;
    }

    private function normalizePageLink(?string $value): ?string
    {
        $link = trim((string) $value);
        if ($link === '' || Str::lower($link) === 'none') {
            return null;
        }

        return $link;
    }

    private function storePageFeatureImage(Request $request, array $payload): ?string
    {
        if ($request->hasFile('feature_image_upload')) {
            return $this->storeUploadedPageImage($request->file('feature_image_upload'));
        }

        if (!empty($payload['feature_image_media_path'])) {
            return getLibraryImagePath((string) $payload['feature_image_media_path']);
        }

        $existing = trim((string) ($payload['feature_image'] ?? ''));
        return $existing !== '' ? $existing : null;
    }

    private function storeUploadedPageImage($file): string
    {
        $targetDir = public_path('assets/images/page');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $ext = strtolower((string) ($file->getClientOriginalExtension() ?: 'jpg'));
        $filename = 'page_' . uniqid('', true) . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
        $file->move($targetDir, $filename);

        return $filename;
    }

    private function resolvePageFeatureImageUrl(Page $page): ?string
    {
        $value = trim((string) ($page->feature_image ?? ''));
        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        if (str_contains($value, '/')) {
            return getPath($value, '');
        }

        return url('assets/images/page/' . ltrim($value, '/'));
    }

    public function invoiceTemplates(): JsonResponse
    {
        $ctx = $this->resolveContext();
        $design = $this->designForStore((int) $ctx['store_id'], (int) $ctx['user_id'], (int) $ctx['customer_id']);
        $activeInvoice = (string) ($design->invoice ?? '');
        $items = Designlist::where('type', 'invoice')
            ->where('status', 'active')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (Designlist $d) use ($activeInvoice) {
            return [
                'id' => (int) $d->id,
                'name' => (string) ($d->name ?? ''),
                'value' => (string) ($d->value ?? ''),
                'status' => (string) ($d->status ?? ''),
                'is_active' => (string) ($d->value ?? '') === $activeInvoice,
                'preview_image_url' => $this->previewAssetUrl($d->image ?? null, 'assets/images/design'),
            ];
        })->values();
        return response()->json(['items' => $items, 'active_invoice' => $activeInvoice]);
    }

    public function activateInvoiceTemplate(int $id): JsonResponse
    {
        $ctx = $this->resolveContext();
        $template = Designlist::findOrFail($id);
        $design = $this->designForStore((int) $ctx['store_id'], (int) $ctx['user_id'], (int) $ctx['customer_id']);
        $design->invoice = (string) ($template->value ?? '');
        $design->editor = (int) $ctx['user_id'];
        $design->save();
        return response()->json(['success' => true]);
    }
}
