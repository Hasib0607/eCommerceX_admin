<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class DrmScraper extends BaseScraper
{
    /**
     * This function get product response and call store products function
     *
     * @param string $query
     * @param int $page
     * @return void|null
     */
    protected function getProductResponse(string $query, int $page): void
    {
        try {
            $baseUrl = "https://www.drm.com.bd/index.php";

            // First request to get total number of pages
            $response = Http::get($baseUrl, [
                'route' => "product/search",
                'search' => $query,
                'page' => $page,
            ]);

            if (!$response->successful()) {
                return;
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            $this->setProduct($crawler, $query);

        } catch (\Throwable $e) {
            return;
        }

    }

    /**
     * This function received product response and store products in array
     *
     * @param Crawler $crawler
     * @return void
     */
    private function setProduct(Crawler $crawler, string $query = NULL)
    {
        try {
            $paginationText = $crawler->filter('.pagination-results .text-right')->text(null, false);
            preg_match('/\((\d+)\s+Pages\)/i', $paginationText, $matches);
            $lastPage = $matches[1] ?? 1;
            $this->totalPages = $lastPage;

            $crawler->filter('.main-products .product-layout')->each(function (Crawler $node) use ($query) {
                $title = $node->filter('.product-thumb .caption .name a')->text('') ?? '';
                $productUrl = $node->filter('.product-thumb .caption .name a')->attr('href') ?? '';
                $image = $node->filter('.product-thumb .image img')->attr('data-src') ?? '';

                $brandName = $node->filter('.product-thumb .caption .stats a')->count() ? trim($node->filter('.product-thumb .caption .stats a')->text()) : '';

                $features = $node->filter('.caption .description .module-features-description li')
                    ->each(function ($node) {
                        return trim($node->text());
                    });

                $description = implode(' | ', $features);

                $originalPrice = 0;
                // Get original price
                $originalNode = $node->filter('.caption .price .price-old');
                if ($originalNode->count()) {
                    $originalPrice = (float)str_replace([',', '৳'], '', $originalNode->text());
                }

                $currentPrice = 0;
                // Get current price
                $currentNode = $node->filter('.caption .price .price-new');
                if ($currentNode->count()) {
                    $currentPrice = (float)str_replace([',', '৳'], '', $currentNode->text());
                } else {
                    $currentNode = $node->filter('.caption .price .price-normal');
                    if ($currentNode->count()) {
                        $currentPrice = (float)str_replace([',', '৳'], '', $currentNode->text());
                    }
                }

                $originalPrice = $originalPrice ?? '';
                $price = $currentPrice ?? $originalPrice ?? '';

                $stockStatus = cleanPrice(trim($price)) > 0 ? 1 : 0;
                $productId = $node->filter('input[name="product_id"]')->count() > 0 ? $node->filter('input[name="product_id"]')->attr('value') : "";

                $data = [
                    'title' => trim($title),
                    'description' => $description ?? NULL,
                    'keywords' => $query ?? NULL,
                    'url' => trim($productUrl, '/'),
                    'image' => trim($image),
                    'original_price' => $originalPrice ? cleanPrice(trim($originalPrice)) : null,
                    'price' => cleanPrice(trim($price)),
                    'currency' => '৳',
                    'in_stock' => $stockStatus,
                    'product_id' => $productId,
                    'sku_id' => '',
                    'source_url' => trim($productUrl, '/'),
                    'brand_name' => $brandName ?? '',
                    'seller_name' => '',
                    'location' => '',
                ];

                $this->products[] = $this->formatProduct($data, $this->getDomain());
            });
        } catch (\Throwable $e) {
            return;
        }
    }

    /**
     * Scrape product details from a direct product URL
     */
    public function scrape(string $url): array
    {
        $crawler = $this->client->request('GET', $url);
        $title = $crawler->filter('#product-product .product-stats-left h1')->count() ? trim($crawler->filter('#product-product .product-stats-left h1')->text()) : '';

        $originalPrice = 0;
        // Get original price
        $originalNode = $crawler->filter('.product-price-old');
        if ($originalNode->count()) {
            $originalPrice = (int)preg_replace('/[^0-9]/', '', $originalNode->text());
        }

        $currentPrice = 0;
        // Get current price
        $currentNode = $crawler->filter('.product-price-new');
        if ($currentNode->count()) {
            $currentPrice = (int)preg_replace('/[^0-9]/', '', $currentNode->text());
        } else {
            $currentNode = $crawler->filter('.product-price');
            if ($currentNode->count()) {
                $currentPrice = (int)preg_replace('/[^0-9]/', '', $currentNode->text());
            }
        }

        $originalPrice = $originalPrice ?? '';
        $price = $currentPrice ?? $originalPrice ?? '';

        $features = $crawler->filter('.module-features-description li')
            ->each(function ($node) {
                return trim($node->text());
            });

        $description = implode(' | ', $features);

        $image = $crawler->filter('.swiper-wrapper .swiper-slide')->first()->filter("img")->count() ? $crawler->filter('.swiper-wrapper .swiper-slide')->first()->filter("img")->attr("data-largeimg") : null;

        $productId = $crawler->filter('.p_id span')->count() ? trim($crawler->filter('.p_id span')->text()) : '';
        $sku = $crawler->filter('.product-sku span')->count() ? trim($crawler->filter('.product-sku span')->text()) : '';
        $stockStatus = $crawler->filter('.product-stock span')->count() ? trim($crawler->filter('.product-stock span')->text()) : '';
        $stockStatus = $stockStatus == "In Stock" ? 1 : 0;


        $data = [
            'title' => trim($title),
            'description' => $description ?? "",
            'keywords' => $query ?? NULL,
            'url' => trim($url, '/'),
            'image' => trim($image),
            'original_price' => $originalPrice ? cleanPrice(trim($originalPrice)) : null,
            'price' => cleanPrice(trim($price)),
            'currency' => '৳',
            'in_stock' => $stockStatus,
            'product_id' => $productId ?? '',
            'sku_id' => $sku ?? '',
            'source_url' => trim($url, '/'),
            'brand_name' => '',
            'seller_name' => '',
            'location' => '',
        ];

        return $this->formatProduct($data, $this->getDomain());
    }


}
