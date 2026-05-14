<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttributeResource;
use App\Http\Resources\BannerResource;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\SliderResource;
use App\Http\Resources\SubcategoryResource;
use App\Http\Resources\TestimonialResource;
use App\Models\AddonsExpired;
use App\Models\AddonsOrder;
use App\Models\AdminCoupon;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\BuyModulus;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Color;
use App\Models\Coupon;
use App\Models\Design;
use App\Models\ExpoDeviceToken;
use App\Models\Headersetting;
use App\Models\HomePae;
use App\Models\Menu;
use App\Models\Mobileapp;
use App\Models\Modulus;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Page;
use App\Models\Paymenttoken;
use App\Models\Plan;
use App\Models\Product;
use App\Models\QuickLogin;
use App\Models\Review;
use App\Models\Size;
use App\Models\Slider;
use App\Models\Store;
use App\Models\Supersetting;
use App\Models\Template;
use App\Models\Temposition;
use App\Models\Testimonial;
use App\Models\Unit;
use App\Models\User;
use App\Models\Veriant;
use Auth;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Resources\ProductLayoutResource;

class SubdomainController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $store = Store::where('expiry_date', '>=', Carbon::now())->get();
        if (isset($store)) {
            if (count($store) > 0) {
                foreach ($store as $str) {
                    $slug[] = $str->url;
                }
            }
        } else {
            $slug[] = null;
        }
        return response()->json($slug);
    }


    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function appStatus(Request $request)
    {
        $app = Mobileapp::where('store_id', $request->store_id)->where('expiry_date', '>=', Carbon::now())->first();

        if (empty($app)) {
            return response()->json(['status' => 'false']);
        } else {
            $expoDeviceInfo = ExpoDeviceToken::firstOrCreate(
                ['expo_token' => request('expo_token')],
                ['store_id' => request('store_id')]
            );

            $appurl = null;
            if ($app->status == "Download") {
                $appurl = $app->url;
            }

            return response()->json(['status' => 'true', 'expoDeviceInfo' => $expoDeviceInfo, 'appurl' => $appurl]);
        }
    }

    public function getsearch()
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://seo-keyword-research.p.rapidapi.com/keyword?keyword=email%20marketing&country=us",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "X-RapidAPI-Host: seo-keyword-research.p.rapidapi.com",
                "X-RapidAPI-Key: f0a3fa7693msh277c0ad98d4ff5bp1b0b6djsn3d943d2a6385"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return "cURL Error #:" . $err;
        } else {
            return $response;
        }
    }

    public function sendheader()
    {
        $store = Store::where('expiry_date', '>=', Carbon::now())->get();
        if (isset($store) && count($store) > 0) {
            foreach ($store as $key => $stor) {
                $data[$key]['domain'] = $stor->url;
                $data[$key]['store_id'] = $stor->id;
                $ders = Design::where('store_id', $stor->id)->first();
                $data[$key]['header'] = $ders->header ?? "default";
                $data[$key]['hero'] = $ders->hero_slider ?? "default";
                $data[$key]['product'] = $ders->product ?? "default";
                $data[$key]['testimonial'] = $ders->testimonial ?? "default";
                $data[$key]['footer'] = $ders->footer ?? "default";
            }
        }
        return response()->json($data);
    }

    public function getnotification()
    {
        $notification = Notification::all();
        return response()->json($notification);
    }

    public function storefrontBootstrap(Request $request)
    {
        try {
            $domain = trim((string) $request->query('domain', ''));
            if ($domain === '') {
                return response()->json(['status' => false, 'message' => 'Domain is required.'], 422);
            }

            $domain = preg_replace('/^www\./i', '', $domain);
            $store = Store::query()
                ->leftJoin('currencies as current_currencies', 'stores.currency', '=', 'current_currencies.id')
                ->where('stores.url', $domain)
                ->where('stores.expiry_date', '>=', Carbon::now())
                ->first([
                    'stores.id',
                    'stores.name',
                    'stores.slug',
                    'stores.url',
                    'stores.type',
                    'stores.status',
                    'stores.store_status',
                    'stores.template_id',
                    'stores.currency',
                    'stores.currency_rate',
                    'stores.expiry_date',
                    'current_currencies.id as current_currency_id',
                    'current_currencies.symbol as current_currency_symbol',
                    'current_currencies.code as current_currency_code',
                    'current_currencies.rate as current_currency_rate',
                    'current_currencies.customize_rate_status as current_currency_customize_rate_status',
                ]);

            if (!$store) {
                return response()->json(['status' => false, 'message' => 'Store not found.'], 404);
            }
            $store->setRelation('current_currency', $store->current_currency_id ? (object) [
                'id' => $store->current_currency_id,
                'symbol' => $store->current_currency_symbol,
                'code' => $store->current_currency_code,
                'rate' => $store->current_currency_rate,
                'customize_rate_status' => $store->current_currency_customize_rate_status,
            ] : null);

            if (config('cache.default') === 'file') {
                $payload = $this->buildStorefrontBootstrapPayload($store);
            } else {
                $cacheKey = "storefront_bootstrap:{$store->id}:{$store->template_id}:v2";
                $payload = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($store) {
                    return $this->buildStorefrontBootstrapPayload($store);
                });
            }

            return response()->json([
                'status' => true,
                'message' => 'Success',
                'data' => $payload,
            ]);
        } catch (\Exception $exception) {
            report($exception);
            return serverError();
        }
    }

    private function buildStorefrontBootstrapPayload(Store $store): array
    {
        $layout = $this->storefrontLayout($store);
        $design = $this->storefrontDesign($store);
        $headerSetting = $this->storefrontHeaderSetting($store);
        $menu = $this->storefrontMenu($store);
        $page = $this->storefrontPages($store);
        $category = $this->storefrontCategories($store);
        $slider = $this->storefrontSliders($store);
        $banner = $this->storefrontBanners($store);
        $products = $this->storefrontProducts($store, 'product');
        $featureProducts = $this->storefrontProducts($store, 'feature');
        $bestSellProducts = $this->storefrontProducts($store, 'best_sell');
        $newArrivalProducts = $this->storefrontProducts($store, 'new_arrival');
        $boughtModuleStatus = BuyModulus::query()
            ->where('store_id', $store->id)
            ->pluck('status', 'modulus_id')
            ->map(fn ($status) => (bool) $status)
            ->toArray();
        $globalModuleStatus = Modulus::query()
            ->whereIn('id', array_keys($boughtModuleStatus + [10 => false, 11 => false]))
            ->pluck('status', 'id')
            ->map(fn ($status) => (bool) $status)
            ->toArray();
        $isModuleActive = fn (int $id) => (bool) ($globalModuleStatus[$id] ?? false) && (bool) ($boughtModuleStatus[$id] ?? false);
        $marketingModuleStatus = [
            'facebook_pixel' => $isModuleActive(11),
            'google_analytics' => $isModuleActive(10),
        ];
        $moduleStatus = [];
        foreach ($boughtModuleStatus as $moduleId => $enabled) {
            $moduleId = (int) $moduleId;
            $moduleStatus[$moduleId] = (bool) ($enabled && ($globalModuleStatus[$moduleId] ?? false));
        }

        $payload = [
            'store' => $this->storefrontStore($store),
            'design' => $design,
            'headerSetting' => $headerSetting,
            'layout' => $layout,
            'menu' => $menu,
            'page' => $page,
            'category' => $category,
            'slider' => $slider,
            'banner' => $banner,
            'products' => $products,
            'featureProducts' => $featureProducts,
            'bestSellProducts' => $bestSellProducts,
            'newArrivalProducts' => $newArrivalProducts,
            'marketingModuleStatus' => $marketingModuleStatus,
            'moduleStatus' => $moduleStatus,
            'sections' => [
                'hero_slider' => ['design' => $design['hero_slider'] ?? null, 'items' => $slider],
                'feature_category' => ['design' => $design['feature_category'] ?? null, 'items' => $category],
                'banner' => ['design' => $design['banner'] ?? null, 'items' => array_values(array_filter($banner, fn ($item) => (int) ($item['type'] ?? 0) === 0))],
                'banner_bottom' => ['design' => $design['banner_bottom'] ?? null, 'items' => array_values(array_filter($banner, fn ($item) => (int) ($item['type'] ?? 0) === 1))],
                'product' => ['design' => $design['product'] ?? null, 'items' => $products],
                'feature_product' => ['design' => $design['feature_product'] ?? null, 'items' => $featureProducts],
                'best_sell_product' => ['design' => $design['best_sell_product'] ?? null, 'items' => $bestSellProducts],
                'new_arrival' => ['design' => $design['new_arrival'] ?? null, 'items' => $newArrivalProducts],
                'menu' => ['items' => $menu],
                'page' => ['items' => $page],
            ],
        ];

        return $payload;
    }

    private function storefrontStore(Store $store): array
    {
        return [
            'id' => (int) $store->id,
            'name' => (string) ($store->name ?? ''),
            'slug' => (string) ($store->slug ?? ''),
            'url' => (string) ($store->url ?? ''),
            'type' => (string) ($store->type ?? ''),
            'status' => (string) ($store->status ?? ''),
            'store_status' => (int) ($store->store_status ?? 0),
            'template_id' => (int) ($store->template_id ?? 0),
            'currency_id' => (int) ($store->currency ?? 0),
            'currency' => $store->current_currency ? [
                'id' => (int) $store->current_currency->id,
                'symbol' => (string) ($store->current_currency->symbol ?? ''),
                'code' => (string) ($store->current_currency->code ?? ''),
            ] : null,
        ];
    }

    private function storefrontLayout(Store $store): array
    {
        $tempPositions = DB::table('tempositions')
            ->where('template_id', $store->template_id)
            ->select('name', 'position')
            ->pluck('position', 'name')
            ->toArray();

        $designPositions = DB::table('design_positions')
            ->where('store_id', $store->id)
            ->select('name', 'position')
            ->orderBy('position', 'asc')
            ->pluck('position', 'name')
            ->toArray();

        $merged = array_merge($tempPositions, $designPositions);
        asort($merged);

        return array_values(array_keys($merged));
    }

    private function storefrontDesign(Store $store): array
    {
        $columns = [
            'header', 'hero_slider', 'banner', 'banner_bottom', 'feature_category', 'product',
            'feature_product', 'best_sell_product', 'new_arrival', 'testimonial', 'youtube',
            'announcement', 'about', 'newsletter', 'brand', 'footer', 'auth', 'shop_page',
            'single_product_page', 'checkout_page', 'login_page', 'profile_page', 'invoice',
            'product_card', 'product_modal', 'preloader', 'mobile_bottom_menu', 'offer',
            'blog', 'contact',
        ];

        $design = DB::table('designs')->where('store_id', $store->id)->first($columns);
        if (!$design) {
            return [];
        }

        $data = [];
        foreach ($columns as $column) {
            $value = $design->{$column} ?? null;
            $data[$column] = $value === 'none' || $value === 'null' ? null : $value;
        }

        return $data;
    }

    private function storefrontHeaderSetting(Store $store): ?array
    {
        $header = DB::table('headersettings')
            ->leftJoin('quick_logins as google_login', function ($join) {
                $join->on('headersettings.store_id', '=', 'google_login.store_id')
                    ->where('google_login.modulus_id', 10);
            })
            ->leftJoin('quick_logins as facebook_login', function ($join) {
                $join->on('headersettings.store_id', '=', 'facebook_login.store_id')
                    ->where('facebook_login.modulus_id', 11);
            })
            ->where('headersettings.store_id', $store->id)
            ->first([
                'headersettings.id',
                'headersettings.website_name',
                'headersettings.short_description',
                'headersettings.logo',
                'headersettings.favicon',
                'headersettings.currency_id',
                'headersettings.pagination',
                'headersettings.theme_lock',
                'headersettings.button_status',
                'headersettings.rtl_status',
                'google_login.google_tag_manager as gtm',
                'google_login.google_analytics',
                'google_login.google_search_console',
                'facebook_login.facebook_pixel',
                'facebook_login.general_access_token as facebook_access_token',
                'facebook_login.test_event_code as facebook_test_event_code',
                'facebook_login.domain_verification_code',
            ]);
        if (!$header) {
            return null;
        }

        return [
            'id' => (int) $header->id,
            'website_name' => (string) ($header->website_name ?? ''),
            'short_description' => (string) ($header->short_description ?? ''),
            'logo' => $this->storefrontImage($header->logo ?? null, 'assets/images/setting'),
            'favicon' => $this->storefrontImage($header->favicon ?? null, 'assets/images/setting'),
            'symbol' => (string) ($store->current_currency->symbol ?? ''),
            'code' => (string) ($store->current_currency->code ?? ''),
            'currency_id' => (int) ($header->currency_id ?? 0),
            'pagination' => (string) ($header->pagination ?? ''),
            'theme_lock' => (int) ($header->theme_lock ?? 0),
            'button_status' => (int) ($header->button_status ?? 0),
            'rtl_status' => (int) ($header->rtl_status ?? 0),
            'gtm' => $header->gtm ?? null,
            'google_analytics' => $header->google_analytics ?? null,
            'google_search_console' => $header->google_search_console ?? null,
            'facebook_pixel' => $header->facebook_pixel ?? null,
            'facebook_access_token' => $header->facebook_access_token ?? null,
            'facebook_test_event_code' => $header->facebook_test_event_code ?? null,
            'domain_verification_code' => $header->domain_verification_code ?? null,
        ];
    }

    private function storefrontMenu(Store $store): array
    {
        return DB::table('menus')
            ->where('store_id', $store->id)
            ->orderBy('sort', 'ASC')
            ->get(['id', 'name', 'custom_link', 'status', 'sort'])
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'name' => (string) ($item->name ?? ''),
                'url' => (string) ($item->custom_link ?? ''),
                'slug' => generateSlug($item->name ?? '', '-'),
                'status' => (string) ($item->status ?? ''),
                'sort' => (int) ($item->sort ?? 0),
            ])
            ->values()
            ->all();
    }

    private function storefrontPages(Store $store): array
    {
        return DB::table('pages')
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->get(['id', 'name', 'slug', 'status'])
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'name' => (string) ($item->name ?? ''),
                'slug' => (string) ($item->slug ?? ''),
                'status' => (string) ($item->status ?? ''),
            ])
            ->values()
            ->all();
    }

    private function storefrontCategories(Store $store): array
    {
        return DB::table('categories')
            ->where('store_id', $store->id)
            ->where('parent', 0)
            ->where('status', 'active')
            ->orderBy('position', 'ASC')
            ->limit(20)
            ->get(['id', 'name', 'slug', 'parent', 'banner', 'icon', 'status', 'position'])
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'name' => (string) ($item->name ?? ''),
                'slug' => (string) ($item->slug ?? ''),
                'parent' => (int) ($item->parent ?? 0),
                'banner' => $this->storefrontImage($item->banner ?? null, 'assets/images/category'),
                'icon' => $this->storefrontImage($item->icon ?? null, 'assets/images/icon'),
                'status' => (string) ($item->status ?? ''),
                'position' => (int) ($item->position ?? 0),
            ])
            ->values()
            ->all();
    }

    private function storefrontSliders(Store $store): array
    {
        return DB::table('sliders')
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->orderBy('position', 'ASC')
            ->limit(8)
            ->get(['id', 'image', 'subimage', 'title', 'subtitle', 'button', 'link', 'color', 'subtitle_color', 'button_color', 'position'])
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'image' => $this->storefrontImage($item->image ?? null, 'assets/images/slider'),
                'subimage' => $this->storefrontImage($item->subimage ?? null, 'assets/images/slider'),
                'title' => (string) ($item->title ?? ''),
                'subtitle' => (string) ($item->subtitle ?? ''),
                'button' => (string) ($item->button ?? ''),
                'link' => (string) ($item->link ?? ''),
                'color' => (string) ($item->color ?? ''),
                'subtitle_color' => (string) ($item->subtitle_color ?? ''),
                'button_color' => (string) ($item->button_color ?? ''),
                'position' => (int) ($item->position ?? 0),
            ])
            ->values()
            ->all();
    }

    private function storefrontBanners(Store $store): array
    {
        return DB::table('banners')
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->orderBy('id', 'ASC')
            ->limit(12)
            ->get(['id', 'image', 'link', 'type'])
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'image' => $this->storefrontImage($item->image ?? null, 'assets/images/banner'),
                'link' => (string) ($item->link ?? ''),
                'type' => (int) ($item->type ?? 0),
            ])
            ->values()
            ->all();
    }

    private function storefrontProducts(Store $store, string $mode): array
    {
        $query = Product::query()
            ->leftJoin('currencies', 'products.currency_id', '=', 'currencies.id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand')
            ->where('products.status', 'active')
            ->where('products.store_id', $store->id)
            ->select([
                'products.id',
                'products.name',
                'products.images',
                'products.gallery_image',
                'products.regular_price',
                'products.promotional_price',
                'products.discount_type',
                'products.stock_status',
                'products.quantity',
                'products.position',
                'products.brand',
                'products.currency_id',
                'products.created_at',
                'currencies.rate as product_currency_rate',
                'currencies.symbol as product_currency_symbol',
                'currencies.code as product_currency_code',
                'brands.name as brand_name',
            ]);

        if ($mode === 'feature') {
            $query->where('products.feature', 1);
        } elseif ($mode === 'best_sell') {
            $query->where('products.best_sell', 1);
        }

        $mode === 'new_arrival'
            ? $query->orderByDesc('products.created_at')
            : $query->orderBy('products.position', 'ASC');

        return $query->limit(12)
            ->get()
            ->map(fn ($product) => $this->storefrontProductCard($product, $store))
            ->values()
            ->all();
    }

    private function storefrontProductCard($product, Store $store): array
    {
        $regularPrice = $this->storefrontConvertedProductAmount((float) ($product->regular_price ?? 0), $product, $store);
        $promotionalPrice = $this->storefrontConvertedProductAmount((float) ($product->promotional_price ?? 0), $product, $store);
        $discountPrice = $regularPrice <= $promotionalPrice ? 0 : $promotionalPrice;
        $calculateRegularPrice = getPrice($regularPrice, $discountPrice, $product->discount_type);
        $currentCurrency = $store->current_currency;

        return [
            'id' => (int) $product->id,
            'name' => (string) ($product->name ?? ''),
            'slug' => generateSlug($product->name, '-'),
            'image' => $this->storefrontProductImages($product),
            'rating' => 0,
            'number_rating' => 0,
            'regular_price' => (float) $regularPrice,
            'calculate_regular_price' => (float) ($calculateRegularPrice ?? $regularPrice),
            'discount_type' => (string) ($product->discount_type ?? ''),
            'discount_price' => (float) $discountPrice,
            'stock_status' => (string) ($product->stock_status ?? ''),
            'quantity' => (float) ($product->quantity ?? 0),
            'symbol' => (string) ($currentCurrency->symbol ?? $product->product_currency_symbol ?? ''),
            'code' => (string) ($currentCurrency->code ?? $product->product_currency_code ?? ''),
            'position' => (int) ($product->position ?? 0),
            'brand_id' => (int) ($product->brand ?? 0),
            'brand_name' => (string) ($product->brand_name ?? ''),
            'created_at' => (string) ($product->created_at ?? ''),
        ];
    }

    private function storefrontConvertedProductAmount(float $amount, $product, Store $store): float
    {
        $currentCurrency = $store->current_currency;
        $productCurrencyId = (int) ($product->currency_id ?? 0);
        $storeCurrencyId = (int) ($store->currency ?? 0);
        if ($amount <= 0 || $productCurrencyId === $storeCurrencyId || !$currentCurrency) {
            return round($amount, 2);
        }

        if ((int) ($currentCurrency->customize_rate_status ?? 0) === 0) {
            $productRate = (float) ($product->product_currency_rate ?? 0);
            $currentRate = (float) ($currentCurrency->rate ?? 0);
            if ($productRate > 0 && $currentRate > 0) {
                return round(($amount / $productRate) * $currentRate, 2);
            }
        }

        $storeRate = (float) ($store->currency_rate ?? 0);
        if ($storeRate > 0) {
            return round($amount / $storeRate, 2);
        }

        return round($amount, 2);
    }

    private function storefrontProductImages($product): array
    {
        $images = $product->images ? explode(',', $product->images) : [];
        $gallery = $product->gallery_image ? explode(',', $product->gallery_image) : [];
        $merged = array_values(array_unique(array_filter(array_merge($gallery, $images))));

        return array_values(array_map(fn ($image) => $this->storefrontImage($image, 'assets/images/product'), $merged));
    }

    private function storefrontImage($value, ?string $folder = null): ?array
    {
        if (empty($value)) {
            return null;
        }

        return [
            'url' => getPath($value, $folder),
            'width' => null,
            'height' => null,
        ];
    }


    /**
     * Get store
     *
     * @param $name
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStoreOld($name)
    {
        try {
            if (empty($name) || is_null($name)) {
                return response()->json(['status' => false, 'message' => 'Domain name is required']);
            }

            $store = Store::where('url', $name)->where('expiry_date', '>=', Carbon::now())->first();

            if (isset($store)) {
                return response()->json(['status' => true, 'message' => 'Success', 'data' => $store]);
            }
            return response()->json(['status' => false, 'message' => 'Store not found!']);
        } catch (\Exception $exception) {
            return serverError();
        }
    }

    public function getStore($name)
    {
        if (empty($name)) {
            return response()->json(['status' => false, 'message' => 'Domain name is required']);
        }

        try {
            $cacheKey = "store_lookup_{$name}";

            $store = Cache::remember($cacheKey, 600, function () use ($name) {
                return Store::where('url', $name)->where('expiry_date', '>=', Carbon::now())->first();
            });

            if ($store) {
                return response()->json([
                    'status' => true,
                    'message' => 'Success',
                    'data' => $store
                ]);
            }

            return response()->json(['status' => false, 'message' => 'Store not found!']);
        } catch (\Exception $e) {
            return serverError();
        }
    }


    public function getDomainSection($name, $section)
    {
        try {
            if (empty($name) || is_null($name)) {
                return response()->json(['status' => false, 'message' => 'Domain name is required']);
            }

            if (empty($section) || is_null($section)) {
                return response()->json(['status' => false, 'message' => 'Section name is required']);
            }

            $store = Store::where('url', $name)->where('expiry_date', '>=', Carbon::now())->first();

            if (isset($store)) {
                switch ($section) {
                    case 'layout':
                        $cacheKey = "layout_positions_{$store->id}_{$store->template_id}";

                        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($store) {
                            $tempPositions = Temposition::where('template_id', $store->template_id)
                                ->select('name', 'position')
                                ->pluck('position', 'name')
                                ->toArray();

                            $designPositions = DB::table('design_positions')
                                ->where('store_id', $store->id)
                                ->select('name', 'position')
                                ->orderBy('position', 'asc')
                                ->pluck('position', 'name')
                                ->toArray();

                            $merged = array_merge($tempPositions, $designPositions);

                            // Sort by value (position)
                            asort($merged);

                            return array_keys($merged);
                        });

                        return response()->json(['status' => true, 'message' => 'Success', 'data' => $data]);

                    case 'design':
                        $columns = [
                            "header",
                            "hero_slider",
                            "banner",
                            "banner_bottom",
                            "feature_category",
                            "product",
                            "feature_product",
                            "best_sell_product",
                            "new_arrival",
                            "testimonial",
                            "youtube",
                            "announcement",
                            "about",
                            "newsletter",
                            "brand",
                            "footer",
                            "auth",
                            "single_product_page",
                            "shop_page",
                            "checkout_page",
                            "login_page",
                            "profile_page",
                            "invoice",
                            "product_card",
                            "product_modal",
                            "preloader",
                            "mobile_bottom_menu",
                            "offer",
                            "blog",
                            "contact",
                        ];

                        $cacheKey = "design_layout_store_{$store->id}";

                        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($store, $columns) {
                            $design = Design::where('store_id', $store->id)->first();

                            if ($design) {
                                foreach ($columns as $column) {
                                    if (isset($design->$column) && $design->$column === "none") {
                                        $design->$column = null;
                                    }
                                }
                            }

                            return $design;
                        });

                        return response()->json([
                            'status' => true,
                            'message' => 'Success',
                            'data' => $data,
                        ]);


                    case 'menu':
                        $menu = Menu::where('store_id', $store->id)->orderBy('sort', 'ASC')->get();
                        return response()->json(['status' => true, 'message' => 'Success', 'data' => $menu]);

                    case 'slider':
                        $slider = Slider::where('store_id', $store->id)->where('status', 'active')->orderBy('position', 'ASC')->get();
                        return response()->json(['status' => true, 'message' => 'Success', 'data' => SliderResource::collection($slider)]);

                    case 'banner':
                        $banner = Banner::where('store_id', $store->id)->where('status', 'active')->get();
                        return response()->json(['status' => true, 'message' => 'Success', 'data' => BannerResource::collection($banner)]);

                    case 'page':
                        $page = Page::where('store_id', $store->id)->where('status', 'active')->get();
                        return response()->json(['status' => true, 'message' => 'Success', 'data' => $page]);

                    case 'best_sell_product':
                        $cacheKey = "store_{$store->id}_best_sell_product";

                        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($store) {
                            return Product::convertCurrency($store->id)
                                ->where('products.status', 'active')
                                ->where('products.best_sell', 1)
                                ->with([
                                    'getBrand' => function ($query) {
                                        $query->select('id', 'name');
                                    }
                                ])
                                ->withSum('reviews', 'rating')  // Adds total_rating
                                ->withCount('reviews')
                                ->orderBy('products.position', 'ASC')
                                ->inRandomOrder()
                                ->limit(10)
                                ->get();
                        });

                        $best_sell_product = $this->getProductResponse($data, $store->id);

                        return response()->json(['status' => true, 'message' => 'Success', 'data' => $best_sell_product]);

                    case 'feature_product':
                        $cacheKey = "store_{$store->id}_feature_product";

                        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($store) {
                            return Product::convertCurrency($store->id)
                                ->where('products.status', 'active')
                                ->where('products.feature', 1)
                                ->with([
                                    'getBrand' => function ($query) {
                                        $query->select('id', 'name');
                                    }
                                ])
                                ->withSum('reviews', 'rating')  // Adds total_rating
                                ->withCount('reviews')
                                ->orderBy('products.position', 'ASC')
                                ->inRandomOrder()
                                ->limit(10)
                                ->get();
                        });

                        $feature_products = $this->getProductResponse($data, $store->id);

                        return response()->json(['status' => true, 'message' => 'Success', 'data' => $feature_products]);

                    case 'testimonial':
                        $testimonials = Testimonial::where('store_id', $store->id)->where('status', 'active')->get();
                        return response()->json(['status' => true, 'message' => 'Success', 'data' => TestimonialResource::collection($testimonials)]);

                    case 'product':
                        $cacheKey = "store_{$store->id}_products";

                        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($store) {
                            return Product::convertCurrency($store->id)
                                ->where('products.status', 'active')
                                ->with([
                                    'getBrand:id,name'
                                ])
                                ->withSum('reviews', 'rating')
                                ->withCount('reviews')
                                ->orderBy('products.position', 'ASC')
                                ->inRandomOrder()
                                ->limit(10)
                                ->get();
                        });

                        $product = $this->getProductResponse($data, $store->id);

                        return response()->json(['status' => true, 'message' => 'Success', 'store_id' => $store->id ?? "", 'data' => $product]);

                    case 'brand':
                        $brand = Brand::where('store_id', $store->id)->get(['id', 'name', 'image']);
                        return response()->json(['status' => true, 'message' => 'Success', 'data' => BrandResource::collection($brand)]);

                    case 'campaign':
                        $campaign = Campaign::convertCurrency($store->id)->where('campaigns.store_id', $store->id)->where('campaigns.status', 'active')->get();
                        return response()->json(['status' => true, 'message' => 'Success', 'data' => $campaign]);

                    case 'category':
                        $allProducts = Product::where('store_id', $store->id)
                            ->select('id', 'category', 'subcategory') // Select only what's needed
                            ->get();

                        $categories = Category::where('store_id', $store->id)
                            ->where('parent', 0)
                            ->where('status', 'active')
                            ->orderBy('position', 'ASC')
                            ->with([
                                'subcategories' => function ($query) use ($store) {
                                    $query->where('store_id', $store->id)
                                        ->where('status', 'active');
                                }
                            ])
                            ->get()
                            ->map(function ($category) use ($store, $allProducts) {
                                $category->total_products = $allProducts->filter(function ($product) use ($category) {
                                    return in_array($category->id, explode(',', $product->category));
                                })->count();

                                $category->subcategories->each(function ($subcategory) use ($allProducts) {
                                    $subcategory->total_products = $allProducts->filter(function ($product) use ($subcategory) {
                                        return in_array($subcategory->id, explode(',', $product->subcategory));
                                    })->count();
                                });

                                return $category;
                            });

                        return response()->json(['status' => true, 'message' => 'Success', 'data' => CategoryResource::collection($categories)]);

                    case 'subcategory':
                        $allProducts = Product::where('store_id', $store->id)
                            ->select('id', 'subcategory') // Only what we need
                            ->get();

                        $subcategories = Category::where('store_id', $store->id)
                            ->where('parent', '!=', '0') // Subcategories only
                            ->where('status', 'active')
                            ->orderBy('position', 'ASC')
                            ->get()
                            ->map(function ($subcategory) use ($allProducts) {
                                // Count products where subcategory ID is in comma-separated product.subcategory
                                $subcategory->total_products = $allProducts->filter(function ($product) use ($subcategory) {
                                    return in_array($subcategory->id, explode(',', $product->subcategory));
                                })->count();

                                return $subcategory;
                            });

                        return response()->json(['status' => true, 'message' => 'Success', 'data' => SubcategoryResource::collection($subcategories)]);

                    case 'offer':
                        $offer = Offer::where('store_id', $store->id)->where('status', 'active')->first();

                        $data = Product::convertCurrency($store->id)
                            ->where('products.status', 'active')
                            ->where('products.discount_type', '!=', 'no_discount')
                            ->with([
                                'getBrand' => function ($query) {
                                    $query->select('id', 'name');
                                }
                            ])
                            ->withSum('reviews', 'rating')  // Adds total_rating
                            ->withCount('reviews')
                            ->orderBy('products.position', 'ASC')
                            ->get();

                        $product = $this->getProductResponse($data, $store->id);

                        $response = [];
                        if (isset($offer)) {
                            $response = [
                                'name' => $offer->name,
                                'start_date' => $offer->start_date,
                                'end_date' => $offer->end_date,
                                'status' => $offer->status,
                                'products' => $product,
                            ];

                        }
                        return response()->json(['status' => true, 'message' => 'Success', 'data' => $response]);

                    default:
                        return response()->json(['status' => true, 'message' => 'Data not found!', 'data' => $store]);
                }
            } else {
                return response()->json(['status' => false, 'message' => 'Store not found!']);
            }
        } catch (\Exception $exception) {
            return serverError();
        }
    }

    public function getProductResponse($data, $store_id)
    {
        return $data->map(function ($product) use ($store_id) {
            return $this->getSingleProductResponse($product, $store_id);
        });
    }

    public function getSingleProductResponse($product, $store_id)
    {
        // Prepare each product's data
        $cacheKey = "product_images_{$product->id}";
        $images = Cache::remember($cacheKey, 60, function () use ($product) {
            $images = $product->images ? explode(',', $product->images) : [];
            $gallery_image = $product->gallery_image ? explode(',', $product->gallery_image) : [];
            $merged = array_filter(array_merge($gallery_image, $images));
            return array_values(array_map(fn($img) => getPath($img, 'assets/images/product'), array_unique($merged)));
        });


        $averageRating = $product->reviews_count > 0 ? $product->reviews_sum_rating / $product->reviews_count : 0;

        // Convert currency for variants
        $variants = $product->getVariantsWithConversion($store_id)->get()->map(function ($variant) {
            return [
                'id' => $variant->id,
                'pid' => $variant->pid,
                'color' => trim($variant->color ?? ''),
                'color_name' => trim($variant->getColor->name ?? ''),
                'size' => $variant->size,
                'volume' => $variant->volume,
                'unit' => $variant->unit,
                'quantity' => $variant->quantity,
                'additional_price' => $variant->additional_price,
                'image' => getPath($variant->image, 'assets/images/product'),
                'color_image' => getPath($variant->color_image, 'assets/images/product'),
                'symbol' => $variant->symbol,
                'code' => $variant->code,
            ];
        });

        $discount_price = $product->regular_price <= $product->promotional_price ? "0" : $product->promotional_price;

        $calculate_regular_price = getPrice($product->regular_price, $discount_price, $product->discount_type);
        $campaign_offer = $this->checkProductOffer($product, $calculate_regular_price, $store_id);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'image' => $images,
            'rating' => $averageRating,
            'number_rating' => $product->reviews_count,
            'slug' => generateSlug($product->name, '-'),
            'description' => mb_substr($product->description, 0, 216),
            'regular_price' => (float)$product->regular_price,
            'calculate_regular_price' => (float)$calculate_regular_price ?? (float)$product->regular_price ?? "",
            'product_offer' => $campaign_offer ?? "",
            'discount_type' => $product->discount_type,
            'discount_price' => (float)$discount_price,
            'category_id' => $product->category ?? "",
            'subcategory_id' => $product->subcategory ?? "",
            'category' => $product->get_categories ?? "",
            'subcategory' => $product->get_subcategories ?? "",
            'tax_type' => $product->tax_type,
            'tax_rate' => (float)$product->tax_rate,
            'quantity' => (float)$product->quantity,
            'stock_status' => $product->stock_status,
            'pre_order_note' => mb_substr($product->pre_order_note, 0, 216),
            'seo_keywords' => $product->seo_keywords,
            'weight' => $product->weight,
            'shipping_fee' => (float)$product->shipping_fee,
            'video_link' => $product->video_link ?? "",
            'SKU' => $product->SKU,
            'tags' => $product->tags,
            'product_link' => $product->product_link,
            'currency_id' => $product->currency_id,
            'symbol' => $product->symbol,
            'code' => $product->code,
            'position' => $product->position,
            'variant' => $variants,
            'brand_id' => $product->brand,
            'brand_name' => $product->getBrand->name ?? "",
            'supplier_id' => $product->supplier,
            'supplier_name' => $product->getSupplier->name ?? "",
            'created_at' => $product->created_at ?? ""
        ];
    }

    public function getAllBrandProducts(Request $request)
    {
        try {
            $brand = Brand::where('id', $request->id)->first();

            if (empty($brand)) {
                return response()->json([
                    'status' => '404',
                    'message' => 'Brand id not found.'
                ]);
            }

            $perPage = 10;

            $products = Product::select(
                'products.id',
                'products.name',
                'products.regular_price',
                'products.discount_type',
                'products.promotional_price',
                'products.tax_type',
                'products.tax_rate',
                'products.quantity',
                'products.seo_keywords',
                'products.weight',
                'products.video_link',
                'products.shipping_fee',
                'products.images as image',
                'products.category',
                'products.subcategory',
                'products.tags',
                'products.position',
                'products.status',
                'products.best_sell',
                'products.feature',
                'products.uid',
                'products.customer_id',
                'products.store_id',
                'products.creator',
                'products.editor',
                'products.brand',
                'products.supplier',
                'products.cost',
                'products.pse',
                'products.pse_status',
                'products.pse_cat_id',
                'products.barcode',
                'products.ask_price',
                'products.created_at',
                'products.commission',
                'products.updated_at',
                'products.SKU',
                'brands.id as brand_id',
                'brands.name as brand_name'
            )
                ->leftJoin('brands', 'brands.id', '=', 'products.brand')
                ->where('products.status', '!=', 'RecycleBin')
                ->where('brands.id', '=', $brand->id)
                ->Paginate($perPage)->onEachSide(1)->setPath('');

            if (empty($brand)) {
                return response()->json([
                    'status' => '200',
                    'message' => 'The have no product in this brand.'
                ]);
            }

            // Convert images string to array
            $products->getCollection()->transform(function ($product) {
                $product->image = [trim($product->image, '"')];

                // Check if variants exist for the product
                $variants = Veriant::convertCurrency($product->id)->get();

                // If variants exist, add them to the product, otherwise keep the "variant" array empty
                $product->variant = $variants->isEmpty() ? [] : $variants;
                return $product;
            });

            return response()->json(['data' => $products]);
        } catch (Exception $e) {
            return serverError();
        }
    }

    public function campaign($store)
    {
        try {
            if (is_null($store) || empty($store)) {
                return sendError("Store ID is required");
            }

            $data = [];
            $campaign = Campaign::convertCurrency($store)
                ->where('campaigns.store_id', $store)
                ->where('campaigns.status', 'active')
                ->get();

            foreach ($campaign as $value) {
                $ids = !empty($value->products)
                    ? explode(',', $value->products)
                    : explode(',', $value->category);

                $value['campaignProducts'] = !empty($value->products)
                    ? $this->__product($ids, $store)
                    : $this->__productCat($ids, $store);

                $data[] = $value;
            }

            return sendResponse("Success", $data);
        } catch (Exception $e) {
            return serverError();
        }
    }

    protected function __product($pids, $store_id)
    {
        // Convert $pids to an array if it's a string
        $productIds = is_string($pids) ? explode(',', $pids) : $pids;

        // Fetch all products in a single query
        $products = Product::where('store_id', $store_id)
            ->whereIn('id', $productIds)
            ->where('status', 'active')
            ->get();

        return $this->getProductResponse($products, $store_id);
    }

    protected function __productCat($catIds, $store_id)
    {
        // Convert $pids to an array if it's a string
        $categoryIds = is_string($catIds) ? explode(',', $catIds) : $catIds;

        $products = Product::where('store_id', $store_id)
            ->whereIn('category', $categoryIds)
            ->where('status', 'active')
            ->get();

        return $this->getProductResponse($products, $store_id);
    }

    function productSearch($store, $search = "")
    {
        try {
            if (is_null($store) || empty($store)) {
                return sendError("Store ID is required");
            }

            $searchResult = [];

            if (!empty($search)) {
                $data = Product::where('store_id', $store)->where('status', 'active')
                    ->where(function ($query) use ($search) {
                        $query->where('name', 'LIKE', "%$search%")
                            ->orWhere('tags', 'LIKE', "%$search%")
                            ->orWhere('SKU', 'LIKE', "%$search%");
                    })->orderBy('name', 'ASC')->limit(50)->get();

                if (isset($data) && count($data) > 0) {
                    foreach ($data as $key => $products) {
                        $discount_price = $products->regular_price <= $products->promotional_price ? "0" : $products->promotional_price;
                        $calculate_regular_price = getPrice($products->regular_price, $discount_price, $products->discount_type);
                        $campaign_offer = $this->checkProductOffer($products, $calculate_regular_price, $store);

                        $searchResult[$key]['id'] = $products->id;
                        $searchResult[$key]['store_id'] = $products->store_id;
                        $searchResult[$key]['name'] = $products->name;
                        $searchResult[$key]['slug'] = generateSlug($products->name, '-');

                        $images = array_filter(explode(',', $products->images));
                        $gallery_image = array_filter(explode(',', $products->gallery_image));
                        $mergedImages = array_unique(array_merge($gallery_image, $images));

                        $images = array_map(fn($img) => getPath($img, 'assets/images/product'), $mergedImages);
                        $searchResult[$key]['image'] = $images[0] ?? NULL;

                        $searchResult[$key]['regular_price'] = (float)$products->regular_price;
                        $searchResult[$key]['calculate_regular_price'] = (float)$calculate_regular_price ?? (float)$products->regular_price ?? "";

                        $searchResult[$key]['product_offer'] = $campaign_offer;
                        $searchResult[$key]['discount_type'] = $products->discount_type;
                        $searchResult[$key]['discount_price'] = (float)$discount_price;

                    }
                }
            }

            return sendResponse("Success", $searchResult);
        } catch (Exception $e) {
            return serverError();
        }
    }

    public function getcatproduct(Request $request, $id)
    {
        if (empty($id) || is_null($id)) {
            return response()->json(['status' => false, 'message' => 'Category id is required']);
        }

        $cat = Category::find($id);

        if (empty($cat)) {
            return response()->json(['status' => false, 'message' => 'Category not found']);
        }

        // Retrieve colors
        $colors = Color::where('store_id', $cat->store_id)->get(['name', 'code']);

        // Build query for products
        $productQuery = Product::convertCurrency($cat->store_id)
            ->where(function ($query) use ($id) {
                $query->where('products.category', "LIKE", "%$id%")
                    ->orWhere('products.subcategory', "LIKE", "%$id%");
            })
            ->where('products.status', 'active')
            ->with([
                'getBrand' => function ($query) {
                    $query->select('id', 'name');
                }
            ])
            ->withSum('reviews', 'rating')  // Adds total_rating
            ->withCount('reviews');

        // Apply sorting filter
        if ($request->filter) {
            $type = $request->filter;
            $sortOptions = [
                'az' => ['products.name', 'asc'],
                'za' => ['products.name', 'desc'],
                'lh' => ['products.regular_price', 'asc'],
                'hl' => ['products.regular_price', 'desc']
            ];
            if (ModulusStatus($cat->store_id, 9)) {
                $defaultSort = ['products.position', 'asc'];
            } else {
                $defaultSort = ['products.id', 'desc'];
            }
            $productQuery->orderBy(...($sortOptions[$type] ?? $defaultSort));
        } else {
            if (ModulusStatus($cat->store_id, 9)) {
                $productQuery->orderBy('products.position');
            } else {
                $productQuery->orderBy('products.id', 'desc');
            }
        }

        // Apply price filter
        if (!empty($request->priceFilter)) {
            $priceFilter = $request->priceFilter;
            $productQuery->where('products.regular_price', '<=', $priceFilter);
        }

        // Apply color filter
        if (!empty($request->colorFilter)) {
            $colorFilter = $request->colorFilter;
            if ($colorFilter) {
                $productQuery->join('veriants', 'products.id', '=', 'veriants.pid')
                    ->where('veriants.color', $colorFilter)
                    ->select('products.*', 'veriants.color', 'veriants.size')
                    ->groupBy('products.id');
            }
        }

        // Apply brand filter
        if (!empty($request->brandFilter)) {
            $brandFilter = explode(',', $request->brandFilter); // Convert to array
            $productQuery->whereIn('products.brand', $brandFilter);
        }

        // Paginate products
        $products = $productQuery->paginate(10)->onEachSide(1)->setPath('');
        $store_id = $cat->store_id ?? "";

        $productData = $this->getProductResponse($products, $store_id);

        $pagination = paginationResponse($products);


        $data = [
            'products' => $productData,
            'colors' => $colors,
            'pagination' => $pagination
        ];

        return sendResponse("Success", $data);
    }


    public function getsubcatproduct(Request $request, $id)
    {
        if (empty($id) || is_null($id)) {
            return response()->json(['status' => false, 'message' => 'Sub Category id is required']);
        }

        $cat = Category::find($id);

        if (empty($cat)) {
            return response()->json(['status' => false, 'message' => 'Category not found']);
        }

        $storeId = $cat->store_id;

        // Base query for products
        $productQuery = Product::convertCurrency($storeId)
            ->where('products.subcategory', "LIKE", "%$id%")
            ->where('products.status', 'active')
            ->with([
                'getBrand' => function ($query) {
                    $query->select('id', 'name');
                }
            ])
            ->withSum('reviews', 'rating')  // Adds total_rating
            ->withCount('reviews');

        // Apply sorting
        if ($request->filter) {
            $type = $request->filter;
            switch ($type) {
                case 'az':
                    $productQuery->orderBy('products.name', 'asc');
                    break;
                case 'za':
                    $productQuery->orderBy('products.name', 'desc');
                    break;
                case 'lh':
                    $productQuery->orderBy('products.regular_price', 'asc');
                    break;
                case 'hl':
                    $productQuery->orderBy('products.regular_price', 'desc');
                    break;
                default:
                    if (ModulusStatus($storeId, 9)) {
                        $productQuery->orderBy('products.position');
                    } else {
                        $productQuery->orderBy('products.id', 'desc');
                    }
                    break;
            }
        } else {
            if (ModulusStatus($storeId, 9)) {
                $productQuery->orderBy('products.position');
            } else {
                $productQuery->orderBy('products.id', 'desc');
            }
        }

        // Apply price filter if present
        if (!empty($request->priceFilter)) {
            $productQuery->where('products.regular_price', '<=', $request->priceFilter);
        }

        // Apply color filter if present
        if (!empty($request->colorFilter)) {
            $productQuery->join('veriants', 'products.id', '=', 'veriants.pid')
                ->where('veriants.color', $request->colorFilter)
                ->select('products.*', 'veriants.color', 'veriants.size')
                ->groupBy('products.id');
        }

        // Apply brand filter
        if (!empty($request->brandFilter)) {
            $brandFilter = explode(',', $request->brandFilter); // Convert to array
            $productQuery->whereIn('products.brand', $brandFilter);
        }

        // Paginate and fetch data
        $products = $productQuery->orderBy('products.id', 'desc')->paginate(8)->onEachSide(1)->setPath('');

        $colors = Color::where('store_id', $storeId)->get(['name', 'code']);

        $productData = $this->getProductResponse($products, $storeId);

        $pagination = paginationResponse($products);

        $data = [
            'products' => $productData,
            'colors' => $colors,
            'pagination' => $pagination
        ];

        return sendResponse("Success", $data);

    }

    public function getBrandProduct(Request $request, $id)
    {
        if (empty($id) || is_null($id)) {
            return response()->json(['status' => false, 'message' => 'Brand id is required']);
        }

        $brand = Brand::find($id);

        if (empty($brand)) {
            return response()->json(['status' => false, 'message' => 'Brand not found']);
        }

        // Retrieve colors
        $colors = Color::where('store_id', $brand->store_id)->get(['name', 'code']);

        $Store = Store::where('id', $brand->store_id)->first();
        if (!isset($Store)) {
            return response()->json(['status' => false, 'message' => 'Brand Store not found']);
        }

        // Build query for products
        $productQuery = Product::convertCurrency($brand->store_id)
            ->where(function ($query) use ($id) {
                $query->where('products.brand', "LIKE", "%$id%");
            })
            ->where('products.status', 'active')
            ->with([
                'getBrand' => function ($query) {
                    $query->select('id', 'name');
                }
            ])
            ->withSum('reviews', 'rating')  // Adds total_rating
            ->withCount('reviews');

        // Apply sorting filter
        if ($request->filter) {
            $type = $request->filter;
            $sortOptions = [
                'az' => ['products.name', 'asc'],
                'za' => ['products.name', 'desc'],
                'lh' => ['products.regular_price', 'asc'],
                'hl' => ['products.regular_price', 'desc']
            ];
            if (ModulusStatus($brand->store_id, 9)) {
                $defaultSort = ['products.position', 'asc'];
            } else {
                $defaultSort = ['products.id', 'desc'];
            }
            $productQuery->orderBy(...($sortOptions[$type] ?? $defaultSort));
        } else {
            if (ModulusStatus($brand->store_id, 9)) {
                $productQuery->orderBy('products.position');
            } else {
                $productQuery->orderBy('products.id', 'desc');
            }
        }

        // Apply price filter
        if (!empty($request->priceFilter)) {
            $priceFilter = $request->priceFilter;
            $productQuery->where('products.regular_price', '<=', $priceFilter);
        }

        // Apply color filter
        if (!empty($request->colorFilter)) {
            $colorFilter = $request->colorFilter;
            if ($colorFilter) {
                $productQuery->join('veriants', 'products.id', '=', 'veriants.pid')
                    ->where('veriants.color', $colorFilter)
                    ->select('products.*', 'veriants.color', 'veriants.size')
                    ->groupBy('products.id');
            }
        }

        // Paginate products
        $products = $productQuery->paginate(8)->onEachSide(1)->setPath('');
        $store_id = $brand->store_id ?? "";

        $productData = $this->getProductResponse($products, $store_id);

        $pagination = paginationResponse($products);


        $data = [
            'products' => $productData,
            'colors' => $colors,
            'pagination' => $pagination
        ];

        return sendResponse("Success", $data);
    }

    public function verifycoupon(Request $request, $store, $code)
    {
        try {
            $amount = $request->amount ?? NULL;
            $shipping = $request->shipping ?? NULL;
            $payment = $request->payment ?? NULL;

            if (is_null($store) || empty($store)) {
                return sendError("Store ID is required");
            }

            if (is_null($code) || empty($code)) {
                return sendError("Code is required");
            }

            $user_id = Auth::user()->id ?? "";
            $orderCoupon = Order::where('uid', $user_id)->where('coupon', $code)->where('store_id', $store)->count();

            $coupon = Coupon::where('store_id', $store)->where('status', 'active')->where('code', $code)->whereDate('end_date', '>=', Carbon::today()->toDateString())->first();


            if (isset($coupon)) {
                $couponStatus = true;  // Start by assuming the coupon is valid

                // Check max use condition
                if ($coupon->max_use <= $orderCoupon) {
                    $couponStatus = false;
                }

                // Check Shipping area if applicable
                if (!is_null($coupon->shipping_area) && $coupon->shipping_area != $shipping) {
                    $couponStatus = false;
                }

                // Check payment method if applicable
                if (!is_null($coupon->payment_method) && $coupon->payment_method != $payment) {
                    $couponStatus = false;
                }

                // Check min_purchase amount if applicable
                if (isset($coupon->min_purchase) && $coupon->min_purchase > $amount) {
                    $couponStatus = false;
                }

                // Check max_purchase amount if applicable
                if (isset($coupon->max_purchase) && $coupon->max_purchase > 0 && $coupon->max_purchase < $amount) {
                    $couponStatus = false;
                }


                if ($couponStatus) {
                    return sendResponse("Success", $coupon);
                }

                return sendError('Sorry! Currently we can"t accept this coupon.');
            } else {
                return sendError('Sorry! Currently we can"t accept this coupon.');
            }
        } catch (Exception $e) {
            return serverError();
        }
    }


    public function couponAutoApply(Request $request, $store, $amount)
    {
        try {
            $shipping = $request->shipping ?? NULL;
            $payment = $request->payment ?? NULL;

            if (is_null($store) || empty($store)) {
                return sendError("Store ID is required");
            }

            if (is_null($amount) || empty($amount)) {
                return sendError("Amount is required");
            }

            $coupons = Coupon::where('store_id', $store)
                ->where('status', 'active')
                ->where('auto_apply', 1)
                ->whereDate('end_date', '>=', Carbon::today()->toDateString())
                ->get();

            $user_id = Auth::user()->id ?? "";

            if ($coupons->isNotEmpty()) {
                foreach ($coupons as $coupon) {
                    // Count how many times this coupon was used by the user for the store
                    $orderCoupon = Order::where('uid', $user_id)
                        ->where('coupon', $coupon->coupon)
                        ->where('store_id', $store)
                        ->count();

                    // Check if the coupon has exceeded its max use
                    if ($coupon->max_use <= $orderCoupon) {
                        continue;  // Skip this coupon if max use is exceeded
                    }

                    // Check if the coupon is valid for the shipping area
                    if (!is_null($coupon->shipping_area) && $coupon->shipping_area != $shipping) {
                        continue;  // Skip if the shipping area does not match
                    }

                    // Check if the coupon is valid for the payment method
                    if (!is_null($coupon->payment_method) && $coupon->payment_method != $payment) {
                        continue;  // Skip if the shipping area does not match
                    }

                    // Check if the minimum purchase condition is met
                    if (isset($coupon->min_purchase) && $coupon->min_purchase > $amount) {
                        continue;  // Skip if the amount is less than the minimum purchase
                    }

                    // Check if the maximum purchase condition is met
                    if (isset($coupon->max_purchase) && $coupon->max_purchase > 0 && $coupon->max_purchase < $amount) {
                        continue;  // Skip if the shipping cost exceeds the maximum purchase limit
                    }

                    // If all checks pass, return the coupon
                    return sendResponse("Success", $coupon);
                }
            }

            return sendError('Sorry! Currently we can"t accept this coupon.');
        } catch (Exception $e) {
            return serverError();
        }
    }

    /***
     * Check store coupon available or not
     *
     * @param $store
     * @return \Illuminate\Http\JsonResponse
     */
    public function availableCoupon($store)
    {
        try {
            if (is_null($store) || empty($store)) {
                return sendError("Store ID is required");
            }

            $coupon = Coupon::where('store_id', $store)->where('status', 'active')->get();

            if (isset($coupon) && count($coupon) > 0) {
                return sendResponse('Coupon is available');
            } else {
                return sendError('Coupon is not available', '', 200);
            }
        } catch (Exception $e) {
            return serverError();
        }
    }

    public function adminVerifyCoupon(Request $request)
    {
        $store_id = $request->store_id;
        $code = $request->code;
        $tokens = Paymenttoken::where('token', $request->token)->first();
        $user_id = $tokens->uid;
        $ordersCoupon = AddonsOrder::where('user_id', $user_id)->where('coupon', $request->code)->count();
        $coupon = AdminCoupon::where('status', 'active')->where('code', $code)->whereDate('end_date', '>=',
            Carbon::today()->toDateString())->first();

        if (isset($coupon)) {

            if ($coupon->max_use > $ordersCoupon) {
                return response()->json($coupon);
            }
            return response()->json(['error' => 'Sorry!  You exit the MAXIMUM limit.']);
        } else {
            return response()->json(['error' => 'Sorry! Currently we can"t accept this coupon.']);
        }
    }


    public function getdetails($store, $id)
    {
        if (empty($store) || is_null($store)) {
            return response()->json(['status' => false, 'message' => 'Store id is required']);
        } else if (empty($id) || is_null($id)) {
            return response()->json(['status' => false, 'message' => 'Product id is required']);
        }

        $product = Product::convertCurrency($store)->where('products.id', $id)
            ->where('products.status', 'active')
            ->with([
                'getBrand' => function ($query) {
                    $query->select('id', 'name');
                },
                'layout' => function ($query) {
                    $query->orderBy('position', 'asc');
                }
            ])
            ->withSum('reviews', 'rating')  // Adds total_rating
            ->withCount('reviews')
            ->first();

        if (isset($product)) {
            // Prepare each product's data
            $images = array_filter(explode(',', $product->images));
            $gallery_image = array_filter(explode(',', $product->gallery_image));
            $mergedImages = array_unique(array_merge($gallery_image, $images));
            $images = array_map(fn($img) => getPath($img, 'assets/images/product'), $mergedImages);

            $averageRating = $product->reviews_count > 0 ? $product->reviews_sum_rating / $product->reviews_count : 0;

            // Convert currency for variants
            $variants = $product->getVariantsWithConversion($store)->get()->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'pid' => $variant->pid,
                    'color' => trim($variant->color ?? ''),
                    'color_name' => trim($variant->getColor->name ?? ''),
                    'size' => $variant->size,
                    'volume' => $variant->volume,
                    'unit' => $variant->unit,
                    'quantity' => $variant->quantity,
                    'additional_price' => $variant->additional_price,
                    'image' => getPath($variant->image, 'assets/images/product'),
                    'color_image' => getPath($variant->color_image, 'assets/images/product'),
                    'symbol' => $variant->symbol,
                    'code' => $variant->code,
                ];
            });

            $uniqueColors = collect($variants)
                ->filter(fn($vr) => isset($vr['color']) && !empty(trim($vr['color']))) // Ensure color exists and is not empty
                ->map(fn($vr) => [
                    'color' => $vr['color'],
                    'color_name' => $vr['color_name'],
                    'color_image' => $vr['color_image'],
                ])
                ->unique('color') // Get unique entries by the 'color' field
                ->values() // Re-index the resulting collection
                ->toArray();

            $discount_price = $product->regular_price <= $product->promotional_price ? "0" : $product->promotional_price;

            $calculate_regular_price = getPrice($product->regular_price, $discount_price, $product->discount_type);
            $campaign_offer = $this->checkProductOffer($product, $calculate_regular_price, $store);

            $productQuantity = ($product->stock_status === 'in_stock' || is_null($product->stock_status))
                ? (float)$product->quantity
                : 0;


            $productData = [
                'id' => $product->id,
                'name' => $product->name,
                'image' => $images,
                'rating' => $averageRating,
                'number_rating' => $product->reviews_count,
                'slug' => generateSlug($product->name, '-'),
                'description' => $product->description,
                'regular_price' => (float)$product->regular_price,
                'calculate_regular_price' => (float)$calculate_regular_price ?? (float)$product->regular_price ?? "",
                'product_offer' => $campaign_offer ?? "",
                'discount_type' => $product->discount_type,
                'discount_price' => (float)$discount_price,
                'category_id' => $product->category ?? "",
                'subcategory_id' => $product->subcategory ?? "",
                'category' => $product->get_categories ?? "",
                'subcategory' => $product->get_subcategories ?? "",
                'tax_type' => $product->tax_type,
                'tax_rate' => (float)$product->tax_rate,
                'quantity' => $productQuantity,
                'stock_status' => $product->stock_status,
                'pre_order_note' => $product->pre_order_note,
                'seo_keywords' => $product->seo_keywords,
                'weight' => $product->weight,
                'shipping_fee' => (float)$product->shipping_fee,
                'video_link' => $product->video_link ?? "",
                'SKU' => $product->SKU,
                'tags' => $product->tags,
                'product_link' => $product->product_link,
                'currency_id' => $product->currency_id,
                'symbol' => $product->symbol,
                'code' => $product->code,
                'position' => $product->position,
                'variant' => $variants,
                'variant_color' => $uniqueColors,
                'brand_id' => $product->brand,
                'brand_name' => $product->getBrand->name ?? "",
                'supplier_id' => $product->supplier,
                'supplier_name' => $product->getSupplier->name ?? "",
                'created_at' => $product->created_at ?? ""
            ];

            $customizable = ModulusStatus($store, 121);

            $productData['layout'] = $customizable
                ? $product->layout->map(fn($layout) => new ProductLayoutResource($layout, $images))
                : null;

            return sendResponse("Success", $productData);
        } else {
            return sendError("Product not found!");
        }
    }

    public function plandetails(Request $request)
    {
        $visitor = getVisitorInfo();
        $timeZone = $request->timeZone ?? "";

        $plan = Plan::with('details')
            ->whereNotIn('id', [8, 9])
            ->where('status', 'active');
        $columns = [
            'id',
            'name',
            'subtitle',
            'branch',
            'staff',
            'product',
            'category',
            'sub_category',
            'inventory',
            'google_ad',
            'order',
            'website_setup',
            'advance_report',
            'position',
            'status',
        ];
        if ((isset($visitor->countryCode) && $visitor->countryCode == 'BD') || $timeZone == "Asia/Dhaka") {
            $columns = array_merge($columns, [
                'price',
                'discount_type',
                'onedis as one_month_discount',
                'sixdis as six_month_discount',
                'twelvedis as twelve_month_discount',
                'twentyfourdis as twenty_four_month_discount',
                DB::raw("'৳' as symbol"),
            ]);
        } else {
            $columns = array_merge($columns, [
                'usd_price as price',
                'usd_discount_type as discount_type',
                'usd_1_dis as one_month_discount',
                'usd_6_dis as six_month_discount',
                'usd_12_dis as twelve_month_discount',
                'usd_24_dis as twenty_four_month_discount',
                DB::raw("'$' as symbol"),
            ]);
        }
        $plans = $plan->select($columns)
            ->orderBy('position', 'ASC')
            ->get();
//        $posplan = Posplan::where('status', 'active')->orderBy('position', 'ASC')->get();
//        $digitalplan = Digitalplan::where('status', 'active')->orderBy('position', 'ASC')->get();

        return response()->json([
            'website_Plan' => $plans,
//            'Pos_Plan' => $posplan,
//            'Digital_Plan' => $digitalplan
        ]);
    }

    public function pages($store, $slug)
    {
        try {
            if (empty($store) || is_null($store)) {
                return response()->json(['status' => false, 'message' => 'Store id is required']);
            }
            if (empty($slug) || is_null($slug)) {
                return response()->json(['status' => false, 'message' => 'Slug is required']);
            }

            $page = Page::where('slug', $slug)->where('store_id', $store)->first();
            $page->feature_image = getPath($page->feature_image, '');

            return sendResponse("Success", $page);
        } catch (\Exception $exception) {
            return serverError();
        }
    }

    public function relatedproduct($id)
    {
        try {
            if (empty($id) || is_null($id)) {
                return response()->json(['status' => false, 'message' => 'Product id is required']);
            }

            $product = Product::where("id", $id)->first();

            $store_id = $product->store_id ?? "";

            if (isset($product)) {
                $data = Product::convertCurrency($store_id)
                    ->where('products.status', 'active')
                    ->where('products.category', $product->category)
                    ->with([
                        'brand' => function ($query) {
                            $query->select('id', 'name');
                        }
                    ])
                    ->withSum('reviews', 'rating')  // Adds total_rating
                    ->withCount('reviews')
                    ->get();

                $related_product = $this->getProductResponse($data, $store_id);

                return sendResponse("Success", $related_product);
            } else {
                return sendError("Product not found");
            }

        } catch (\Exception $exception) {
            return serverError();
        }
    }

    public function getreview($id)
    {
        try {
            if (empty($id) || is_null($id)) {
                return response()->json(['status' => false, 'message' => 'Product id is required']);
            }

            $reviewss = Review::where('product_id', $id)->get();
            if (isset($reviewss) && count($reviewss) > 0) {
                foreach ($reviewss as $key => $rv) {
                    $user = User::find($rv->uid);
                    $review[$key]['id'] = $rv->id ?? '';
                    $review[$key]['name'] = $rv->name ?? '';
                    $review[$key]['image'] = getPath(($user->image ?? ''), 'assets/images/img');
                    $review[$key]['ucd'] = $user->created_at ?? '';
                    $review[$key]['comment'] = $rv->comment ?? '';
                    $review[$key]['rating'] = $rv->rating ?? '';
                    $review[$key]['cd'] = $rv->created_at ?? '';
                }

                return response()->json(['status' => true, 'message' => 'No Review Found', 'data' => $review]);
            } else {
                return response()->json(['status' => false, 'message' => 'No Review Found', 'data' => []]);
            }
        } catch (\Exception $exception) {
            return serverError();
        }
    }

    public function checkoffer($store, $id)
    {
        try {
            if (empty($store) || is_null($store)) {
                return response()->json(['status' => false, 'message' => 'Store id is required']);
            } else if (empty($id) || is_null($id)) {
                return response()->json(['status' => false, 'message' => 'Product id is required']);
            }

            $product = Product::find($id);
            if (isset($product)) {
                return $this->checkProductOffer($product, $product->regular_price, $store);
            } else {
                return response()->json(['status' => false, 'message' => 'Product not found', 'data' => []]);
            }
        } catch (\Exception $exception) {
            return serverError();
        }
    }

    public function checkProductOffer($product, $regular_price, $store_id)
    {
        $id = $product->id;
        $currentDate = Carbon::now()->format('Y-m-d');
        $currentDay = Carbon::now()->format('l');

        // Common query base for campaigns
        $baseQuery = Campaign::convertCurrency($store_id)
            ->where('campaigns.status', 'active')
            ->where('campaigns.store_id', $store_id);

        // Date Range Campaigns (Campaign 1 & 2)
        $dateRangeQuery = clone $baseQuery;
        $dateRangeQuery->where('campaigns.length_type', 'date_range')
            ->where('campaigns.start_date', '<=', $currentDate)
            ->where('campaigns.end_date', '>=', $currentDate);

        // Campaign 1: Product-specific
        $campaign1 = (clone $dateRangeQuery)->where('campaigns.campaign_type', 'product')
            ->whereRaw('FIND_IN_SET("' . $id . '", campaigns.products)')
            ->get();
        if ($response = $this->isCampaignActiveNow($campaign1, $regular_price)) {
            return $response;
        }

        // Campaign 2: Category-specific
//        $productCategories = explode(',', $product->category); // Convert product categories to an array
//        $productSubcategories = isset($product->subcategory) ? explode(',', $product->subcategory) : []; // Convert subcategories if available

        $productCategories = is_string($product->category) ? explode(',', $product->category) : (array)$product->category;
        $productSubcategories = isset($product->subcategory)
            ? (is_string($product->subcategory) ? explode(',', $product->subcategory) : (array)$product->subcategory)
            : [];

        $categoryQuery = (clone $dateRangeQuery)
            ->where('campaigns.campaign_type', 'category')
            ->where(function ($query) use ($productCategories, $productSubcategories) {
                foreach ($productCategories as $category) {
                    $query->orWhereRaw("FIND_IN_SET(?, campaigns.category)", [$category]);
                }
                foreach ($productSubcategories as $subcategory) {
                    $query->orWhereRaw("FIND_IN_SET(?, campaigns.category)", [$subcategory]);
                }
            });

        $campaign2 = $categoryQuery->get();
        if ($response = $this->isCampaignActiveNow($campaign2, $regular_price)) {
            return $response;
        }

        // Specific Date Campaigns (Campaign 3 & 4)
        $specificDateQuery = clone $baseQuery;
        $specificDateQuery->where('campaigns.length_type', 'specific_date')
            ->where('campaigns.specific_dates', $currentDate);

        // Campaign 3: Product-specific
        $campaign3 = (clone $specificDateQuery)->where('campaigns.campaign_type', 'product')
            ->whereRaw('FIND_IN_SET("' . $id . '", campaigns.products)')
            ->get();
        if ($response = $this->isCampaignActiveNow($campaign3, $regular_price)) {
            return $response;
        }

        // Campaign 4: Category-specific
//        $categoryQuery = (clone $specificDateQuery)->where('campaigns.campaign_type', 'category')
//            ->whereRaw('FIND_IN_SET("' . (int)$product->category . '", campaigns.category)');
//
//        if (isset($product->subcategory)) {
//            $categoryQuery->orWhereRaw('FIND_IN_SET("' . (int)$product->subcategory . '", campaigns.category)');
//        }
//        $productCategories = explode(',', $product->category); // Convert product categories to an array
//        $productSubcategories = isset($product->subcategory) ? explode(',', $product->subcategory) : []; // Convert subcategories if available

        $productCategories = is_string($product->category) ? explode(',', $product->category) : (array)$product->category;
        $productSubcategories = isset($product->subcategory)
            ? (is_string($product->subcategory) ? explode(',', $product->subcategory) : (array)$product->subcategory)
            : [];

        $categoryQuery = (clone $specificDateQuery)
            ->where('campaigns.campaign_type', 'category')
            ->where(function ($query) use ($productCategories, $productSubcategories) {
                foreach ($productCategories as $category) {
                    $query->orWhereRaw("FIND_IN_SET(?, campaigns.category)", [$category]);
                }
                foreach ($productSubcategories as $subcategory) {
                    $query->orWhereRaw("FIND_IN_SET(?, campaigns.category)", [$subcategory]);
                }
            });

        $campaign4 = $categoryQuery->get();
        if ($response = $this->isCampaignActiveNow($campaign4, $regular_price)) {
            return $response;
        }

        // Repeat Date Campaigns (Campaign 5 & 6)
        $repeatDateQuery = clone $baseQuery;
        $repeatDateQuery->where('campaigns.length_type', 'repeat_date')
            ->whereRaw('FIND_IN_SET("' . $currentDay . '", campaigns.repeat_dates)');

        // Campaign 5: Product-specific
        $campaign5 = (clone $repeatDateQuery)->where('campaigns.campaign_type', 'product')
            ->whereRaw('FIND_IN_SET("' . $id . '", campaigns.products)')
            ->get();
        if ($response = $this->isCampaignActiveNow($campaign5, $regular_price)) {
            return $response;
        }

        // Campaign 6: Category-specific
//        $categoryQuery = (clone $repeatDateQuery)->where('campaigns.campaign_type', 'category')
//            ->whereRaw('FIND_IN_SET("' . (int)$product->category . '", campaigns.category)');
//
//        if (isset($product->subcategory)) {
//            $categoryQuery->orWhereRaw('FIND_IN_SET("' . (int)$product->subcategory . '", campaigns.category)');
//        }

//        $productCategories = explode(',', $product->category); // Convert product categories to an array
//        $productSubcategories = isset($product->subcategory) ? explode(',', $product->subcategory) : []; // Convert subcategories if available

        $productCategories = is_string($product->category) ? explode(',', $product->category) : (array)$product->category;
        $productSubcategories = isset($product->subcategory)
            ? (is_string($product->subcategory) ? explode(',', $product->subcategory) : (array)$product->subcategory)
            : [];

        $categoryQuery = (clone $repeatDateQuery)
            ->where('campaigns.campaign_type', 'category')
            ->where(function ($query) use ($productCategories, $productSubcategories) {
                foreach ($productCategories as $category) {
                    $query->orWhereRaw("FIND_IN_SET(?, campaigns.category)", [$category]);
                }
                foreach ($productSubcategories as $subcategory) {
                    $query->orWhereRaw("FIND_IN_SET(?, campaigns.category)", [$subcategory]);
                }
            });

        $campaign6 = $categoryQuery->get();
        if ($response = $this->isCampaignActiveNow($campaign6, $regular_price)) {
            return $response;
        }

        return [
            "status" => false,
            "message" => "No active offers found",
            "offer_price" => null,
            "offer_amount" => null,
            "discount_type" => null,
            "discount_amount" => null,
            "shipping_area" => null,
        ];
    }

    /**
     * Check if a campaign is currently active based on start and end times.
     */
    public function isCampaignActiveNow($campaigns, $regular_price)
    {
        $currentTime = Carbon::now()->format('H:i');

        foreach ($campaigns as $campaign) {
            if (isset($campaign->start_time, $campaign->end_time)) {
                if ($campaign->start_time <= $currentTime && $campaign->end_time >= $currentTime) {
                    return $this->generateOfferResponse($regular_price, $campaign);
                }
            } else {
                return $this->generateOfferResponse($regular_price, $campaign);
            }
        }

        return null;
    }

    /**
     * Generate the offer response.
     */
    public function generateOfferResponse($regular_price, $campaign)
    {
        $offer_price = getPrice($regular_price, $campaign->discount_amount, $campaign->discount_type);
        $discount_amount = getDiscountAmount($regular_price, $campaign->discount_amount, $campaign->discount_type);

        return [
            "status" => true,
            "message" => "Success",
            "offer_price" => $offer_price ?? null,
            "offer_amount" => $discount_amount ?? null,
            "discount_type" => $campaign->discount_type ?? null,
            "discount_amount" => $campaign->discount_amount ?? null,
            "shipping_area" => $campaign->shipping_area ?? null,
        ];
    }

    public function getshoppageproduct(Request $request)
    {
        $name = $request->name;
        $store = Store::where('url', $name)
            ->where('expiry_date', '>=', Carbon::now())
            ->first();

        if (!$store) {
            return response()->json(['data' => [], 'colors' => []]);
        }

        $colors = Color::where('store_id', $store->id)->get(['name', 'code']);

        $productQuery = Product::convertCurrency($store->id)->where('products.status', 'active')
            ->with([
                'getBrand' => function ($query) {
                    $query->select('id', 'name');
                }
            ])
            ->withSum('reviews', 'rating')  // Adds total_rating
            ->withCount('reviews');


        if ($request->filter) {
            $type = $request->filter;
            switch ($type) {
                case 'az':
                    $productQuery->orderBy('products.name', 'asc');
                    break;
                case 'za':
                    $productQuery->orderBy('products.name', 'desc');
                    break;
                case 'lh':
                    $productQuery->orderBy('products.regular_price', 'asc');
                    break;
                case 'hl':
                    $productQuery->orderBy('products.regular_price', 'desc');
                    break;
                default:
                    if (ModulusStatus($store->id, 9)) {
                        $productQuery->orderBy('products.position');
                    } else {
                        $productQuery->orderBy('products.id', 'desc');
                    }
                    break;
            }
        } else {
            if (ModulusStatus($store->id, 9)) {
                $productQuery->orderBy('products.position');
            } else {
                $productQuery->orderBy('products.id', 'desc');
            }
        }


        if (!empty($request->priceFilter)) {
            $priceFilter = $request->priceFilter;
            $productQuery->where('products.regular_price', '<=', $priceFilter);
        }

        if (!empty($request->colorFilter)) {
            $colorFilter = $request->colorFilter;
            if ($colorFilter) {
                $productQuery->join('veriants', 'products.id', '=', 'veriants.pid')
                    ->where('veriants.color', $colorFilter)
                    ->select('products.*', 'veriants.color', 'veriants.size')
                    ->groupBy('products.id');
            }
        }

        // Apply brand filter
        if (!empty($request->brandFilter)) {
            $brandFilter = explode(',', $request->brandFilter); // Convert to array
            $productQuery->whereIn('products.brand', $brandFilter);
        }

        // Paginate the query results
//        $products = $productQuery->paginate(8)->onEachSide(1)->setPath('');
//        $productData = $this->getProductResponse($products, $store->id);
//        $pagination = paginationResponse($products);
//
//        $data = [
//            'products' => $productData,
//            'colors' => $colors,
//            'pagination' => $pagination
//        ];

        $cacheKey = 'shop_products_' . md5(json_encode($request->all()));

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($productQuery, $store, $colors) {
            $products = $productQuery->paginate(8)->onEachSide(1)->setPath('');
            $productData = $this->getProductResponse($products, $store->id);
            $pagination = paginationResponse($products);

            return [
                'products' => $productData,
                'colors' => $colors,
                'pagination' => $pagination
            ];
        });

        return sendResponse("Success", $data);
    }


    public function appsurl(Request $request)
    {
        $store = Store::where('id', $request->store_id)->whereDate('expiry_date', '>=', Carbon::now())->first();
        if (isset($store)) {
            $url = $store->url;
            $design = Design::where('store_id', $store->id)->first();
            if (isset($design)) {
                $header_color = $design->header_color;
                $text_color = $design->text_color;
            } else {
                $header_color = "#f1593a";
                $text_color = "#fff";
            }
        } else {
            $url = env('APP_URL');
            $header_color = "#f1593a";
            $text_color = "#fff";
        }
        return response()->json(['url' => $url, 'header_color' => $header_color, 'text_color' => $text_color]);
    }

    public function popupimage()
    {
        $data = Supersetting::find(1);
        return ['data' => $data];
    }

    public function digitaltimmer()
    {
        $data = Supersetting::find(1);
        return ['data' => $data];
    }

    public function templates()
    {
        $data = Template::where('status', 'active')->orderBy('position', 'asc')->get();
        return [
            'templates' => $data
        ];
    }


    /**
     * Get product by product tags
     *
     * @param $store
     * @param $tag
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTagProduct($store, $tag)
    {
        try {
            if (empty($store) || is_null($store)) {
                return response()->json(['status' => false, 'message' => 'Store id is required']);
            }

            if (empty($tag) || is_null($tag)) {
                return response()->json(['status' => false, 'message' => 'Tag is required']);
            }

            $data = Product::where('tags', 'like', "%$tag%")
                ->where('status', 'active')
                ->where('store_id', $store)
                ->inRandomOrder()
                ->limit(4)
                ->get();

            return response()->json(['status' => true, "message" => "Success", "data" => $data]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, "message" => "Something went wrong", "data" => []]);
        }
    }


    /**
     * Get attribute by store id
     *
     * @param $store
     * @param $name
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAttribute($store, $name)
    {
        try {
            if (empty($store) || is_null($store)) {
                return response()->json(['status' => false, 'message' => 'Store id is required']);
            }

            if (empty($name) || is_null($name)) {
                return response()->json(['status' => false, 'message' => 'Attribute name is required']);
            }

            switch ($name) {
                case 'size':
                    $sizeData = Size::where('store_id', $store)->orderBy('position', 'asc')->get();
                    $size = AttributeResource::collection($sizeData);

                    return response()->json(['status' => true, "message" => "Success", "data" => $size]);

                case 'color':
                    $colorData = Color::where('store_id', $store)->orderBy('position', 'asc')->get();
                    $color = AttributeResource::collection($colorData);
                    return response()->json(['status' => true, "message" => "Success", "data" => $color]);

                case 'unit':
                    $unitData = Unit::where('store_id', $store)->get();
                    $unit = AttributeResource::collection($unitData);
                    return response()->json(['status' => true, "message" => "Success", "data" => $unit]);

                default:
                    return response()->json(['status' => false, "message" => "Invalid attribute name", "data" => []]);
            }

        } catch (\Exception $e) {
            return response()->json(['status' => false, "message" => "Something went wrong", "data" => []]);
        }
    }


}
