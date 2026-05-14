<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use function Laravel\Prompts\text;

class CatseyeScraper extends BaseScraper
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
            $baseUrl = "https://catseye.com.bd/catalogsearch/result/index/";

            // First request to get total number of pages
            $response = Http::get($baseUrl, [
                'q' => $query,
                'p' => $page,
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
            $pageLinks = $crawler->filter('ul.pagination li.item')
                ->each(function ($node) {
                    if ($node->filter('a')->count()) {
                        return (int)$node->filter('a')->text();
                    }
                    return null;
                });

            $this->totalPages = max(array_filter($pageLinks)) ?? 1;

            $crawler->filter('ol.products li.item')->each(function (Crawler $node) use ($query) {
                $title = $node->filter('.product-item-link')->text('') ?? '';
                $productUrl = $node->filter('.product-item-link')->attr('href') ?? '';

                $firstImg = $node->filter('.product-item-photo img:first-child');
                if ($firstImg->count() > 0) {
                    $image = $firstImg->attr('data-src');
                } else {
                    // Fallback to second image
                    $secondImg = $node->filter('.product-item-photo img.img-hover-show');
                    $image = $secondImg->count() > 0 ? $secondImg->attr('data-src') : "";
                }

                $originalPrice = 0;

                $currentPrice = 0;
                // Get current price
                $currentNode = $node->filter('.price');
                if ($currentNode->count()) {
                    $currentPrice = (float)str_replace([',', '৳'], '', $currentNode->text());
                }

                $originalPrice = $originalPrice ?? '';
                $price = $currentPrice ?? $originalPrice ?? '';

                $skuId = "";
                $brandName = "";

                $extra = $node->filter('.price-final_price');
                if ($extra->count()) {
                    $productId = $extra->attr('data-product-id') ?? '';
                }

                $stockStatus = "";

                $data = [
                    'title' => trim($title),
                    'description' => NULL,
                    'keywords' => $query ?? NULL,
                    'url' => trim($productUrl, '/'),
                    'image' => trim($image),
                    'original_price' => $originalPrice ? cleanPrice(trim($originalPrice)) : null,
                    'price' => cleanPrice(trim($price)),
                    'currency' => '৳',
                    'in_stock' => $stockStatus,
                    'product_id' => $productId ?? '',
                    'sku_id' => $skuId,
                    'source_url' => trim($productUrl, '/'),
                    'brand_name' => $brandName ?? "",
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
        return [];
    }


}
