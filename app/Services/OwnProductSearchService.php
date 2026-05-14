<?php

namespace App\Services;

use App\Models\AcceptedPseProductRequest;
use App\Models\Category;
use App\Scrapers\ProductResponseFormatter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OwnProductSearchService
{
    use ProductResponseFormatter;

    private $imageHostURL;

    public function __construct()
    {
        $this->imageHostURL = rtrim(env('IMAGE_HOST', 'https://admin.ebitans.com'), '/');
    }

    public function getProductOld(string $name, string $slug, int $perPage, int $page)
    {
        try {
            $category = Category::where('slug', $slug)->first(['id']);
            if (!$category) {
                return [];
            }

//            $products = AcceptedPseProductRequest::query()
//                ->select([
//                    'accepted_pse_product_requests.id',
//                    'accepted_pse_product_requests.product_id',
//                    'accepted_pse_product_requests.category_id',
//                    'products.name',
//                    'products.description',
//                    'products.images as productImage',
//                    'products.discount_type',
//                    'products.SKU',
//                    'products.quantity',
//                    'products.tags',
//                    'products.store_id',
//                    'products.regular_price',
//                    'products.promotional_price',
//                    'currencies.symbol',
//                    'stores.url as store_url',
//                    DB::raw('COUNT(pse_visitor_counters.product_id) as totalVisitor'),
//                    DB::raw('(SELECT COUNT(*) FROM reviews WHERE reviews.product_id = products.id) as reviews_count'),
//                    DB::raw('(SELECT SUM(rating) FROM reviews WHERE reviews.product_id = products.id) as reviews_sum_rating')
//                ])
//                ->join('products', 'products.id', '=', 'accepted_pse_product_requests.product_id')
//                ->join('stores', 'stores.id', '=', 'products.store_id')
//                ->leftJoin('pse_visitor_counters', 'pse_visitor_counters.product_id', '=', 'accepted_pse_product_requests.product_id')
//                ->leftJoin('currencies', 'currencies.id', '=', 'products.currency_id')
//                ->whereRaw("JSON_CONTAINS(accepted_pse_product_requests.category_id, ?)", [json_encode((string)$category->id)])
//                ->where('stores.expiry_date', '>=', Carbon::now())
//                ->when($name, fn($query) => $query->where('products.name', 'LIKE', '%' . $name . '%'))
//                ->groupBy(
//                    'accepted_pse_product_requests.id',
//                    'accepted_pse_product_requests.product_id',
//                    'accepted_pse_product_requests.category_id',
//                    'products.name',
//                    'products.images',
//                    'products.discount_type',
//                    'products.store_id',
//                    'products.regular_price',
//                    'products.promotional_price',
//                    'stores.url'
//                )
//                ->orderByDesc('totalVisitor')
//                ->forPage($page, $perPage)
//                ->get();

            $products = AcceptedPseProductRequest::query()
                ->select([
                    'accepted_pse_product_requests.id',
                    'accepted_pse_product_requests.product_id',
                    'accepted_pse_product_requests.category_id',
                    'products.name',
                    'products.description',
                    'products.images as productImage',
                    'products.discount_type',
                    'products.SKU',
                    'products.quantity',
                    'products.tags',
                    'products.store_id',
                    'products.regular_price',
                    'products.promotional_price',
                    'currencies.symbol',
                    'stores.url as store_url',
                    DB::raw('COUNT(pse_visitor_counters.product_id) as totalVisitor'),
                    DB::raw('(SELECT COUNT(*) FROM reviews WHERE reviews.product_id = products.id) as reviews_count'),
                    DB::raw('(SELECT SUM(rating) FROM reviews WHERE reviews.product_id = products.id) as reviews_sum_rating'),
                ])
                ->join('products', 'products.id', '=', 'accepted_pse_product_requests.product_id')
                ->join('stores', 'stores.id', '=', 'products.store_id')
                ->leftJoin('pse_visitor_counters', 'pse_visitor_counters.product_id', '=', 'accepted_pse_product_requests.product_id')
                ->leftJoin('currencies', 'currencies.id', '=', 'products.currency_id')
                ->whereRaw(
                    'JSON_CONTAINS(accepted_pse_product_requests.category_id, ?)',
                    [json_encode((string)$category->id)]
                )
                ->where('stores.expiry_date', '>=', Carbon::now())
                ->when(!empty($name), function ($query) use ($name) {
                    $query->whereRaw('products.name COLLATE utf8mb4_unicode_ci LIKE ?', ['%' . $name . '%']);
                })
                ->groupBy(
                    'accepted_pse_product_requests.id',
                    'accepted_pse_product_requests.product_id',
                    'accepted_pse_product_requests.category_id',
                    'products.name',
                    'products.description',
                    'products.images',
                    'products.discount_type',
                    'products.SKU',
                    'products.quantity',
                    'products.tags',
                    'products.store_id',
                    'products.regular_price',
                    'products.promotional_price',
                    'currencies.symbol',
                    'stores.url'
                )
                ->orderByDesc('totalVisitor')
                ->forPage($page, $perPage)
                ->get();
            $products = AcceptedPseProductRequest::select(
                'accepted_pse_product_requests.id',
                'accepted_pse_product_requests.product_id',
                'accepted_pse_product_requests.category_id',
                'products.name',
                'products.images as productImage',
                'products.discount_type',
                'products.store_id',
                'products.regular_price',
                'products.promotional_price as discount_price',
                'stores.url as store_url',
                DB::raw('COUNT(pse_visitor_counters.product_id) as totalVisitor')
            )
                ->leftJoin('products', 'products.id', '=', 'accepted_pse_product_requests.product_id')
                ->leftJoin('stores', 'stores.id', '=', 'products.store_id')
                ->leftJoin('pse_visitor_counters', 'pse_visitor_counters.product_id', '=', 'accepted_pse_product_requests.product_id')
                ->where('accepted_pse_product_requests.category_id', 'LIKE', '%' . $category->id . '%')
                ->whereDate('stores.expiry_date', '>=', Carbon::now())
                ->when($name, function ($query, $name) {
                    return $query->where('products.name', 'LIKE', '%' . $name . '%');
                })
                ->groupBy(
                    'accepted_pse_product_requests.id',
                    'accepted_pse_product_requests.product_id',
                    'accepted_pse_product_requests.category_id',
                    'products.name',
                    'products.images',
                    'products.discount_type',
                    'products.store_id',
                    'products.regular_price',
                    'products.promotional_price',
                    'stores.url'
                )
                ->orderBy('totalVisitor');

            $this->addSlugToProducts($products);

            if (!$products || count($products) == 0) return [];

            return $products->map(function ($product) {
                $source_site = "https://" . trim($product->store_url, '/');
                $productUrl = "https://" . trim($product->store_url, '/') . "/product/" . $product->product_id . "/" . $product->slug;
                $inStock = $product->quantity > 0 ? 1 : 0;

                $discount_price = $product->regular_price <= $product->promotional_price ? "0" : $product->promotional_price;
                $calculate_regular_price = getPrice($product->regular_price, $discount_price, $product->discount_type);

                $averageRating = $product->reviews_count > 0 ? round($product->reviews_sum_rating / $product->reviews_count, 2) : null;

                $cleanText = html_entity_decode(strip_tags($product->description), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                // Extract first 150 words while trying to avoid cutting mid-sentence
                $words = explode(' ', $cleanText);
                $limitedWords = array_slice($words, 0, 250);
                $limitedText = implode(' ', $limitedWords);

                // Find last punctuation mark and trim to there
                $lastPunctuation = max(
                    strrpos($limitedText, '.'),
                    strrpos($limitedText, '!'),
                    strrpos($limitedText, '?')
                );

                if ($lastPunctuation !== false) {
                    $limitedText = substr($limitedText, 0, $lastPunctuation + 1);
                }

                $data = [
                    'title' => trim($product->name),
                    'description' => $limitedText, // or add logic for limited description if available
                    'keywords' => $product->tags ?? null,    // or you can derive this from name or tags
                    'rating' => $averageRating,    // or you can derive this from name or tags
                    'url' => $productUrl,
                    'image' => $this->getFirstImage($product->productImage),
                    'original_price' => (float)$product->regular_price,
                    'price' => (float)$calculate_regular_price ?? (float)$product->regular_price ?? "",
                    'currency' => $product->symbol ?? '৳', // you can update this dynamically if needed
                    'in_stock' => $inStock, // or use actual stock status if available
                    'product_id' => $product->product_id,
                    'sku_id' => $product->SKU ?? null,
                    'source_url' => $productUrl,
                    'brand_name' => null, // use actual brand if available
                    'seller_name' => '',
                    'location' => '',
                ];

                return $this->formatProduct($data, $source_site);

            });

        } catch (\Exception $e) {
            return [];
        }
    }

    protected function withCollation($column, $alias = null)
    {
        $expression = "{$column} COLLATE utf8mb4_unicode_ci";
        return $alias ? DB::raw("{$expression} as {$alias}") : DB::raw($expression);
    }

    public function getProduct(string $name, string $slug, int $perPage, int $page)
    {
        try {
            $category = Category::where('slug', $slug)->first(['id']);
            if (!$category) {
                return [];
            }

            $products = AcceptedPseProductRequest::query()
                ->select([
                    'accepted_pse_product_requests.id',
                    'accepted_pse_product_requests.product_id',
                    'accepted_pse_product_requests.category_id',
                    'products.name',
                    'products.description',
                    'products.images as productImage',
                    'products.discount_type',
                    'products.SKU',
                    'products.quantity',
                    'products.tags',
                    'products.store_id',
                    'products.regular_price',
                    'products.promotional_price',
                    'currencies.symbol',
                    'stores.url as store_url',
                    DB::raw('COUNT(pse_visitor_counters.product_id) as totalVisitor'),
                    DB::raw('(SELECT COUNT(*) FROM reviews WHERE reviews.product_id = products.id) as reviews_count'),
                    DB::raw('(SELECT SUM(rating) FROM reviews WHERE reviews.product_id = products.id) as reviews_sum_rating')
                ])
                ->join('products', 'products.id', '=', 'accepted_pse_product_requests.product_id')
                ->join('stores', 'stores.id', '=', 'products.store_id')
                ->leftJoin('pse_visitor_counters', function ($join) {
                    $join->on(DB::raw('pse_visitor_counters.product_id COLLATE utf8mb4_unicode_ci'),
                        '=',
                        DB::raw('accepted_pse_product_requests.product_id COLLATE utf8mb4_unicode_ci'));
                })
                ->leftJoin('currencies', 'currencies.id', '=', 'products.currency_id')
                ->whereRaw("JSON_CONTAINS(accepted_pse_product_requests.category_id, ?)", [json_encode((string)$category->id)])
                ->where('stores.expiry_date', '>', Carbon::now())
                ->when(!empty($name), fn($query) => $query->where('products.name', 'LIKE', '%' . $name . '%'))
                ->groupBy(
                    'accepted_pse_product_requests.id',
                    'accepted_pse_product_requests.product_id',
                    'accepted_pse_product_requests.category_id',
                    'products.name',
                    'products.images',
                    'products.discount_type',
                    'products.store_id',
                    'products.regular_price',
                    'products.promotional_price',
                    'stores.url'
                )
                ->orderByDesc('totalVisitor')
                ->forPage($page, $perPage)
                ->get();

            $this->addSlugToProducts($products);

            if (!$products || count($products) == 0) return [];

            return $products->map(function ($product) {
                $source_site = "https://" . trim($product->store_url, '/');
                $productUrl = "https://" . trim($product->store_url, '/') . "/product/" . $product->product_id . "/" . $product->slug;
                $inStock = $product->quantity > 0 ? 1 : 0;

                $discount_price = $product->regular_price <= $product->promotional_price ? "0" : $product->promotional_price;
                $calculate_regular_price = getPrice($product->regular_price, $discount_price, $product->discount_type);

                $averageRating = $product->reviews_count > 0 ? round($product->reviews_sum_rating / $product->reviews_count, 2) : null;

                $cleanText = html_entity_decode(strip_tags($product->description), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                // Extract first 150 words while trying to avoid cutting mid-sentence
                $words = explode(' ', $cleanText);
                $limitedWords = array_slice($words, 0, 250);
                $limitedText = implode(' ', $limitedWords);

                // Find last punctuation mark and trim to there
                $lastPunctuation = max(
                    strrpos($limitedText, '.'),
                    strrpos($limitedText, '!'),
                    strrpos($limitedText, '?')
                );

                if ($lastPunctuation !== false) {
                    $limitedText = substr($limitedText, 0, $lastPunctuation + 1);
                }

                $data = [
                    'title' => trim($product->name),
                    'description' => $limitedText, // or add logic for limited description if available
                    'keywords' => $product->tags ?? null,    // or you can derive this from name or tags
                    'rating' => $averageRating,    // or you can derive this from name or tags
                    'url' => $productUrl,
                    'image' => $this->getFirstImage($product->productImage),
                    'original_price' => (float)$product->regular_price,
                    'price' => (float)$calculate_regular_price ?? (float)$product->regular_price ?? "",
                    'currency' => $product->symbol ?? '৳', // you can update this dynamically if needed
                    'in_stock' => $inStock, // or use actual stock status if available
                    'product_id' => $product->product_id,
                    'sku_id' => $product->SKU ?? null,
                    'source_url' => $productUrl,
                    'brand_name' => null, // use actual brand if available
                    'seller_name' => '',
                    'location' => '',
                ];

                return $this->formatProduct($data, $source_site);

            });

        } catch (\Exception $e) {
            return [];
        }
    }


    private function getFirstImage($images)
    {
        if (is_array($images) && count($images)) {
            return $images[0];
        }
        return $this->imageHostURL . '/assets/images/eBitans_store.jpg';
    }


    private function addSlugToProducts($products)
    {
        $products->transform(function ($product) {
            $product->productImage = $this->prependImageURLs($product->productImage);
            $product->slug = Str::slug($product->name);
            return $product;
        });
    }


    private function prependImageURLs($imageString)
    {
        if (empty($imageString)) {
            return [$this->imageHostURL . '/assets/images/eBitans_store.jpg'];
        }

        return collect(explode(',', $imageString))
            ->filter()
            ->map(function ($image) {
                return $this->imageHostURL . '/assets/images/product/' . trim($image, '/');
            })
            ->whenEmpty(function ($collection) {
                return $collection->push($this->imageHostURL . '/assets/images/eBitans_store.jpg');
            })
            ->all();
    }


}
