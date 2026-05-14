<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use function Laravel\Prompts\text;

class RichmanbdScraper extends BaseScraper
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
            $baseUrl = "https://richmanbd.com/page/$page/";

            // First request to get total number of pages
            $response = Http::get($baseUrl, [
                's' => $query,
                'post_type' => "product",
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
            $pageLinks = $crawler->filter('.woocommerce-pagination .page-numbers li')
                ->each(function ($node) {
                    if ($node->filter('a.page-numbers')->count()) {
                        return (int)$node->filter('a')->text();
                    } elseif ($node->filter('span.current')->count()) {
                        return (int)$node->filter('span')->text();
                    }
                    return null;
                });

            $this->totalPages = max(array_filter($pageLinks)) ?? 1;

            $crawler->filter('ul.product-grid li.product-item')->each(function (Crawler $node) use ($query) {
                $title = $node->filter('.product-title a')->text('') ?? '';
                $productUrl = $node->filter('.product-title a')->attr('href') ?? '';
                $image = $node->filter('img.fami-img.fami-lazy')->count() ? $node->filter('img.fami-img.fami-lazy')->attr('src') : '';

                $originalPrice = 0;
                // Get original price
                $originalNode = $node->filter('span.price del span.woocommerce-Price-amount bdi');
                if ($originalNode->count()) {
                    $originalPrice = (int)preg_replace('/[^0-9]/', '', $originalNode->text());
                }

                $currentPrice = 0;
                // Get current price
                $currentNode = $node->filter('span.price ins span.woocommerce-Price-amount bdi');
                if ($currentNode->count()) {
                    $currentPrice = (int)preg_replace('/[^0-9]/', '', $currentNode->text());
                } else {
                    $currentNode = $node->filter('span.price span.woocommerce-Price-amount bdi');
                    if ($currentNode->count()) {
                        $currentPrice = (int)preg_replace('/[^0-9]/', '', $currentNode->text());
                    }
                }

                $originalPrice = $originalPrice ?? '';
                $price = $currentPrice ?? $originalPrice ?? '';

                $stockStatus = $node->filter('.outofstock')->count() ? $node->filter('.outofstock')->text() : "";
                $stockStatus = !empty($stockStatus) && $stockStatus == "Sold out" ? 0 : 1;

                $skuId = "";
                $brandName = "";

                if ($node->filter('.variations_form')->count()) {
                    $itemData = json_decode($node->filter('.variations_form')->attr("data-product_variations"), true);

                    if (isset($itemData[0]['display_regular_price']) && !empty($itemData[0]['display_regular_price'])) {
                        $originalPrice = $itemData[0]['display_regular_price'] ?? "";
                    }

                    if (isset($itemData[0]['display_price']) && !empty($itemData[0]['display_price'])) {
                        $price = $itemData[0]['display_price'] ?? "";
                    }

                    if (isset($itemData[0]['is_in_stock']) && $itemData[0]['is_in_stock']) {
                        $stockStatus = 1;
                    } else {
                        $stockStatus = 0;
                    }

                    $skuId = $itemData[0]['sku'] ?? "";
                }

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
                    'product_id' => '',
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
        $crawler = $this->client->request('GET', $url);
        $title = $crawler->filter('h1.product_title')->count() ? trim($crawler->filter('h1.product_title')->text()) : '';

        $originalPrice = 0;
        // Get original price
        $originalNode = $crawler->filter('.summary-inner p.price del span.woocommerce-Price-amount bdi');
        if ($originalNode->count()) {
            $originalPrice = (int)preg_replace('/[^0-9]/', '', $originalNode->text());
        }

        $currentPrice = 0;
        // Get current price
        $currentNode = $crawler->filter('.summary-inner p.price ins span.woocommerce-Price-amount bdi');
        if ($currentNode->count()) {
            $currentPrice = (int)preg_replace('/[^0-9]/', '', $currentNode->text());
        } else {
            $currentNode = $crawler->filter('.summary-inner p.price span.woocommerce-Price-amount bdi');
            if ($currentNode->count()) {
                $currentPrice = (int)preg_replace('/[^0-9]/', '', $currentNode->text());
            }
        }

        $originalPrice = $originalPrice ?? '';
        $price = $currentPrice ?? $originalPrice ?? '';

        $stockStatus = $crawler->filter('p.stock.out-of-stock')->count() ? 0 : 1;
        $skuId = $crawler->filter('.sku_wrapper .sku')->count() ? trim($crawler->filter('.sku_wrapper .sku')->text()) : "";

        $image = $crawler->filter('.wd-carousel-wrap .wd-carousel-item a')->count() ? $crawler->filter('.wd-carousel-wrap .wd-carousel-item a')->attr('href') : null;

        $data = [
            'title' => trim($title),
            'description' => "",
            'keywords' => $query ?? NULL,
            'url' => trim($url, '/'),
            'image' => trim($image),
            'original_price' => $originalPrice ? cleanPrice(trim($originalPrice)) : null,
            'price' => cleanPrice(trim($price)),
            'currency' => '৳',
            'in_stock' => $stockStatus,
            'product_id' => '',
            'sku_id' => $skuId ?? '',
            'source_url' => trim($url, '/'),
            'brand_name' => '',
            'seller_name' => '',
            'location' => '',
        ];

        return $this->formatProduct($data, $this->getDomain());
    }


}
