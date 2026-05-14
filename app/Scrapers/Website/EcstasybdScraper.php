<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use function Laravel\Prompts\text;

class EcstasybdScraper extends BaseScraper
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
            $baseUrl = "https://ecstasybd.com";

            // First request to get total number of pages
            $response = Http::get($baseUrl, [
                'page' => "search",
                'key' => $query,
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
            $pageLinks = $crawler->filter('.paginatoin-area .pagination-box li')
                ->each(function ($node) {
                    if ($node->filter('a')->count()) {
                        return (int)$node->filter('a')->text();
                    }
                    return null;
                });

            $this->totalPages = max(array_filter($pageLinks)) ?? 1;

            $crawler->filter('.shop-product-wrap.grid-view div.col-lg-3')->each(function (Crawler $node) use ($query) {
                $baseUrl = "https://ecstasybd.com/";
                $title = $node->filter('.product-name a')->text('') ?? '';
                $urlQuery = $node->filter('.product-name a')->attr('href') ?? '';

                parse_str($urlQuery, $params);
                $pid = $params['pid'] ?? null;
                $productUrl = $baseUrl . $urlQuery;

                $imageNode = $node->filter('img.pri-img');
                if ($imageNode->count()) {
                    $image = $imageNode->attr('src');
                } else {
                    $imageNode = $node->filter('img.sec-img');
                    if ($imageNode->count()) {
                        $image = $imageNode->attr('src');
                    }
                }

                $image = $image ? $baseUrl . $image : "";

                $originalPrice = 0;
                // Get current price
                $originalNode = $node->filter('.price-old del');
                if ($originalNode->count()) {
                    $originalPrice = (int)preg_replace('/[^0-9]/', '', $originalNode->text());
                }

                $currentPrice = 0;
                // Get current price
                $currentNode = $node->filter('.price-regular');
                if ($currentNode->count()) {
                    $currentPrice = (int)preg_replace('/[^0-9]/', '', $currentNode->text());
                }

                $originalPrice = $originalPrice ?? '';
                $price = $currentPrice ?? $originalPrice ?? '';

                $qtyNode = $node->filter('input[name="quantity"]');
                if ($qtyNode->count()) {
                    $quantity = $qtyNode->attr('value') ?? "";
                }
                
                $stockStatus = isset($quantity) && $quantity > 0 ? 1 : 0;
                $skuId = "";
                $brandName = "";

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
                    'product_id' => $pid ?? '',
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
