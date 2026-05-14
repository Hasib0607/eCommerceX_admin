<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class ToffparkScraper extends BaseScraper
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
            $baseUrl = "https://toffpark.com/page/$page/";

            // First request to get total number of pages
            $response = Http::get($baseUrl, [
                's' => $query
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
            $pageLinks = $crawler->filter('.oceanwp-pagination .page-numbers li')
                ->each(function ($node) {
                    if ($node->filter('a.page-numbers')->count()) {
                        return (int)$node->filter('a')->text();
                    } elseif ($node->filter('span.current')->count()) {
                        return (int)$node->filter('span')->text();
                    }
                    return null;
                });

            $this->totalPages = max(array_filter($pageLinks)) ?? 1;


            $crawler->filter('#content article')->each(function (Crawler $node) use ($query) {
                $title = $node->filter('h2.search-entry-title a')->text('') ?? '';
                $productUrl = $node->filter('h2.search-entry-title a')->attr('href') ?? '';
                $image = $node->filter('.thumbnail a img')->attr('src') ?? '';
                $originalPrice = '';
                $price = '';
                $stockStatus = 1;

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
                    'sku_id' => '',
                    'source_url' => trim($productUrl, '/'),
                    'brand_name' => '',
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
        $title = $crawler->filter('h2.single-post-title.product_title')->count() ? trim($crawler->filter('h2.single-post-title.product_title')->text()) : '';

        $originalPrice = 0;
        // Get original price
        $originalNode = $crawler->filter('p.price del span.woocommerce-Price-amount bdi');
        if ($originalNode->count()) {
            $originalPrice = (float)str_replace([',', '৳'], '', $originalNode->text());
        }

        $currentPrice = 0;
        // Get current price
        $currentNode = $crawler->filter('p.price ins span.woocommerce-Price-amount bdi');
        if ($currentNode->count()) {
            $currentPrice = (float)str_replace([',', '৳'], '', $currentNode->text());
        } else {
            $currentNode = $crawler->filter('p.price span.woocommerce-Price-amount bdi');
            if ($currentNode->count()) {
                $currentPrice = (float)str_replace([',', '৳'], '', $currentNode->text());
            }
        }


        $originalPrice = $originalPrice ?? '';
        $price = $currentPrice ?? $originalPrice ?? '';

        $stockStatus = $crawler->filter('p.stock.out-of-stock')->count() ? 0 : 1;
        $skuId = $crawler->filter('.sku_wrapper .sku')->count() ? trim($crawler->filter('.sku_wrapper .sku')->text()) : "";

        $image = $crawler->filter('.woocommerce-product-gallery__wrapper a')->count() ? $crawler->filter('.woocommerce-product-gallery__wrapper a')->attr('href') : null;

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
