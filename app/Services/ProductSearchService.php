<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ScrapedProduct;

use App\Scrapers\ProductResponseFormatter;
use App\Scrapers\ScraperWebsiteList;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProductSearchService
{
    use ProductResponseFormatter;

    protected array $scrapers;
    protected array $minScrapers;
    protected $scrapeProductValidDay = 5;

    public function __construct()
    {
        $this->scrapers = ScraperWebsiteList::get();
        $this->minScrapers = ScraperWebsiteList::getShortList();
    }

    /**
     * @return int
     */
    private function minTotalProduct(): int
    {
        $totalProductCount = 0;
        foreach ($this->scrapers as $scraper) {
            if (isset($scraper['perPage']) && $scraper['perPage'] > 0) {
                $totalProductCount += $scraper['perPage'];
            }
        }

        return $totalProductCount > 0 ? $totalProductCount : 100;
    }


    public function search(string $query, int $page, string $category, bool $minSearch): array
    {


        $cahceKey = "search:$query:page:$page";
        if ($minSearch) {
            $cahceKey = "search:$query:page:$page:min:search";
        }

        if (!env('SEARCH_CACHE_STATUS', false)) {
            Cache::forget($cahceKey); // clear the cache
        }

        return Cache::remember($cahceKey, now()->addMinutes(10), function () use ($query, $page, $category, $minSearch) {
            $perPage = $this->minTotalProduct(); // assume this return 100

            // Step 1: Paginated promoted products
            $promotedProducts = $this->getPromotedProducts($query, $perPage, $page, $category);

            // Step 2: Paginated own products from DB
            $ownProducts = $this->getOwnProducts($query, $perPage, $page, $category);

            // Step 3: Paginated cached scraped products
            $cachedScrapedProducts = $this->getCachedScrapedProducts($query, $perPage, $page);

            $pages = $page;
            $cachedResult = true;

            $cachedScrapedProductsCount = $cachedScrapedProducts->count();
//            $maxGet = $this->minTotalProduct();

            $maxGet = 50;
            if ($minSearch) {
                $maxGet = 10;
            }

            // If not enough results, scrape external
            if ($cachedScrapedProductsCount < $maxGet) {
                $externalData = $this->scrapeExternal($query, $page, $minSearch);
                $products = $externalData["products"];
                $pages = $externalData["page"] ?? $page;
                $cachedResult = false;

                $alreadyInsertedUrls = ScrapedProduct::whereIn('source_url', collect($products)->pluck('source_url'))->pluck('source_url')->all();

                foreach ($products as $data) {
                    $data['source_type'] = 'scraped';
                    $data['last_verified_at'] = now();
                    $existing = ScrapedProduct::firstWhere('source_url', $data['source_url']);

                    if ($existing) {
                        $existingKeywords = array_filter(array_map('trim', explode(',', $existing->keywords)));
                        $newKeywords = array_filter(array_map('trim', explode(',', $data['keywords'] ?? '')));
                        $merged = array_unique(array_merge($existingKeywords, $newKeywords));
                        $data['keywords'] = implode(',', $merged);
                        $existing->update($data);
                    } else {
                        ScrapedProduct::create($data);
                    }

                    if (!in_array($data['source_url'], $alreadyInsertedUrls) &&
                        !$cachedScrapedProducts->contains('source_url', $data['source_url'])) {
                        unset($data['last_verified_at']);
                        $cachedScrapedProducts->push((object)$data);
                    }
                }
            }

            if ($cachedResult) {
                $totalScrapedProducts = $this->getCachedScrapedProductCount($query);
                $pages = (int)ceil($totalScrapedProducts / $perPage); // Calculate total pages
            }

            $finalResults = $this->mixResultsPromotingOwnAndSponsors(
                $promotedProducts,
                $ownProducts,
                $cachedScrapedProducts
            );

            // Controlled mixed function
//            $finalResults = $this->mixResultsPromotingOwnAndSponsors(
//                $promotedProducts,
//                $ownProducts,
//                $cachedScrapedProducts,
//                20,
//                ['promoted' => 2, 'own' => 5, 'scraped' => 13]
//            );


            return [
                "products" => $finalResults,
                "page" => $page,
                "totalPage" => $pages,
                "totalProduct" => count($finalResults),
                "resultType" => $cachedResult,
            ];
        });

    }


    protected function getPromotedProducts(string $query, int $perPage, int $page, string $category)
    {
//        $products = PromotedProduct::where('is_active', true)
//            ->where(function ($q) use ($query) {
//                $q->where('title', 'like', "%$query%")
//                    ->orWhere('keywords', 'like', "%$query%");
//            })
//            ->where(function ($q) {
//                $q->whereNull('start_at')->orWhere('start_at', '<=', now());
//            })
//            ->where(function ($q) {
//                $q->whereNull('end_at')->orWhere('end_at', '>=', now());
//            })
//            ->skip(($page - 1) * $perPage)
//            ->take($perPage)
//            ->get()
//            ->map(function ($item) {
//                $data = $item->toArray();
//                $data['source_type'] = 'promoted';
//                return $data;
//            });
//
//        return $products ?? [];

        return [];
    }

    protected function getPromotedProductCount(string $query): int
    {
//        return PromotedProduct::where('is_active', true)
//            ->where(function ($q) use ($query) {
//                $q->where('title', 'like', "%$query%")
//                    ->orWhere('keywords', 'like', "%$query%");
//            })
//            ->where(function ($q) {
//                $q->whereNull('start_at')->orWhere('start_at', '<=', now());
//            })
//            ->where(function ($q) {
//                $q->whereNull('end_at')->orWhere('end_at', '>=', now());
//            })
//            ->count();

        return 0;
    }

    protected function getOwnProducts(string $query, int $perPage, int $page, string $category)
    {
        $products = (new OwnProductSearchService())->getProduct($query, $category, $perPage, $page);
        return $products ?? [];
    }

    protected function getOwnProductCount(string $query): int
    {
        return Product::query()
            ->where('title', 'like', "%{$query}%")
            ->count();
    }

    protected function getCachedScrapedProducts(string $query, int $perPage, int $page, int $ttl = 1800)
    {
        $cacheKey = $this->buildScrapedProductsCacheKey($query, $page);

        $ttl = strlen($query) > 20 ? 300 : $ttl; // 10 min for long queries

        // Add this when scraped products change
        if (!env('SEARCH_CACHE_STATUS', false)) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $ttl, function () use ($query, $perPage, $page) {
            if (empty($query)) {
                return collect();
            }

            $scraped = ScrapedProduct::query()
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%$query%")
                        ->orWhere('keywords', 'like', "%$query%");
                })
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            if (!isset($scraped)) {
                return [];
            }

            return $scraped->filter(function ($item) {
                if ($this->isStillValid($item)) {
                    return true;
                } else {
                    $item->delete(); // Clean up expired
                    return false;
                }
            })->map(function ($item) {
                $data = $item->toArray();

                return $this->formatProduct($data, $data['source_site'], "cachedScraped");
            });
        });
    }

    public function getCachedScrapedProductCount(string $query, int $ttl = 600): int
    {
        $cacheKey = $this->buildCountCacheKey($query);

        $ttl = strlen($query) > 20 ? 300 : $ttl; // 10 min for long queries

        // Add this when scraped products change
        if (!env('SEARCH_CACHE_STATUS', false)) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $ttl, function () use ($query) {
            if (empty($query)) {
                return 0;
            }

            return ScrapedProduct::query()
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%$query%")
                        ->orWhere('keywords', 'like', "%$query%");
                })
                ->count();
        });
    }

    protected function buildScrapedProductsCacheKey(string $query, int $page): string
    {
        $query = md5(strtolower(trim($query)));
        return "scraped_products:search:$query:page:$page";
    }

    protected function buildCountCacheKey(string $query): string
    {
        return 'scraped_count:' . md5(strtolower(trim($query)));
    }


    /**
     * Mixing Own + Scraped (Random, Boost Own)
     *
     * @param $ownProducts
     * @param $scrapedProducts
     * @param $interval
     * @return mixed
     */
    protected function mixOwnAndScrapedRandomly($ownProducts, $scrapedProducts, $interval = 3)
    {
        $own = $ownProducts->values();
        $scraped = $scrapedProducts->shuffle()->values();

        $final = [];
        $ownIndex = 0;
        $scrapedIndex = 0;

        while ($scrapedIndex < $scraped->count() || $ownIndex < $own->count()) {
            // Inject one own product every N positions (interval)
            if (($scrapedIndex + count($final)) % $interval === 0 && $ownIndex < $own->count()) {
                $final[] = $own[$ownIndex++];
            }

            if ($scrapedIndex < $scraped->count()) {
                $final[] = $scraped[$scrapedIndex++];
            }
        }

        return collect($final)->shuffle()->values(); // Shuffle again for more randomness if needed
    }

    /**
     * Mixing Own + Promoted + Scraped (Prioritized Inject)
     *
     * @param array|Collection $promoted
     * @param array|Collection $own
     * @param array|Collection $scraped
     * @param int $limit
     * @param array $distribution
     * @return array
     */
    protected function mixResultsPromotingOwnAndSponsors(
        array|Collection $promoted,
        array|Collection $own,
        array|Collection $scraped,
        int              $limit = 20,
        array            $distribution = []
    ): array
    {
        // Normalize input to arrays
        $promoted = $promoted instanceof Collection ? $promoted->all() : $promoted;
        $own = $own instanceof Collection ? $own->all() : $own;
        $scraped = $scraped instanceof Collection ? $scraped->all() : $scraped;

        $final = [];

        // Default distribution: 25% promoted, 25% own, 50% scraped
        if (empty($distribution)) {
            $distribution = [
                'promoted' => (int)floor($limit * 0.25),
                'own' => (int)floor($limit * 0.25),
            ];
            $distribution['scraped'] = $limit - $distribution['promoted'] - $distribution['own'];
        } else {
            // Normalize to sum = $limit
            $total = array_sum($distribution);
            if ($total < $limit) {
                $scalingFactor = $limit / $total;
                $distribution = array_map(
                    fn($v) => (int)floor($v * $scalingFactor),
                    $distribution
                );
                // Fix rounding issue
                $difference = $limit - array_sum($distribution);
                if ($difference > 0) {
                    $distribution['scraped'] += $difference;
                }
            }
        }

        while (!empty($promoted) || !empty($own) || !empty($scraped)) {
            // Promoted
            $count = 0;
            while ($count < $distribution['promoted']) {
                if (!empty($promoted)) {
                    $final[] = array_shift($promoted);
                } elseif (!empty($own)) {
                    $final[] = array_shift($own);
                } elseif (!empty($scraped)) {
                    $final[] = array_shift($scraped);
                } else {
                    break;
                }
                $count++;
            }

            // Own
            $count = 0;
            while ($count < $distribution['own']) {
                if (!empty($own)) {
                    $final[] = array_shift($own);
                } elseif (!empty($scraped)) {
                    $final[] = array_shift($scraped);
                } else {
                    break;
                }
                $count++;
            }

            // Scraped
            $count = 0;
            while ($count < $distribution['scraped']) {
                if (!empty($scraped)) {
                    $final[] = array_shift($scraped);
                } else {
                    break;
                }
                $count++;
            }
        }

        return $final;
    }


    public function fetchByUrl(string $url): array
    {
        $scraperEntry = null;
        $domain = null;

        foreach ($this->scrapers as $key => $config) {
            if (Str::contains($url, $key)) {
                $scraperEntry = $config;
                $domain = $key;
                break;
            }
        }

        if (!$scraperEntry || !isset($scraperEntry['class'])) {
            return [
                "products" => null,
                "page" => 1,
                "totalPage" => 1,
                "totalProduct" => 0,
            ];
        }

        $perPage = $scraperEntry['perPage'] ?? 20;
        // Dynamically create an instance of the scraper
        $scraper = app($scraperEntry['class'], ['domain' => $domain, 'perPage' => $perPage]); // Or: new $scraperEntry['class']($domain)

        $data = $scraper->scrape(ensure_https_url($url));
        $products[] = $data;

        $this->updateOrCreateProduct($data);

        return [
            "products" => $products,
            "page" => 1,
            "totalPage" => 1,
            "totalProduct" => count($products),
        ];
    }

    private function isStillValid($product): bool
    {
        return !Carbon::parse($product->last_verified_at)
            ->addDays($this->scrapeProductValidDay)
            ->isPast();
    }

    private function scrapeExternal(string $query, int $page, bool $minSearch): array
    {
        // Optional: Run scrapers in parallel with Laravel Jobs or sequentially
        $scrapers = $this->scrapers;
        if ($minSearch) {
            $scrapers = $this->minScrapers;
        }

        $results = [];
        $maxPage = $page;

        foreach ($scrapers as $key => $config) {
            if (!isset($config['class'])) {
                continue; // skip if class is not defined
            }

            $perPage = $config['perPage'] ?? 20;
            // Instantiate the scraper class
            $scraper = app($config['class'], ['domain' => $key, 'perPage' => $perPage]); // or: new $config['class']($key)

            // Call the method
            $scrapProduct = $scraper->searchByKeyword($query, $page);
            $products = $scrapProduct['products'] ?? [];

            // Update the page if needed
            $maxPage = isset($scrapProduct['page']) && $scrapProduct['page'] > $maxPage
                ? $scrapProduct['page']
                : $maxPage;

            // Merge results
            $results = array_merge($results, $products);
        }

        shuffle($results);

        return [
            "page" => $maxPage,
            "products" => $results,
        ];
    }

}
