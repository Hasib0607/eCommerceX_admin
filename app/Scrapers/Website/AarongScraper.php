<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use function Laravel\Prompts\text;

class AarongScraper extends BaseScraper
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
            $baseUrl = "https://www.aarong.com/catalogsearch/result/";

            // First request to get total number of pages
            $response = Http::get($baseUrl, [
                'q' => $query,
                'p' => $this->perPage,
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

            $total = $crawler->filter('.toolbar-number')->count() ? $crawler->filter('.toolbar-number')->text() : "";
            $totalItems = max(0, (int)$total); // Force positive integer
            $perPage = max(1, (int)$this->perPage);  // Avoid division by zero
            $this->totalPages = $totalItems > 0 ? ceil($totalItems / $perPage) : 1;

            $crawler->filter('ol.product-items li.product-item')->each(function (Crawler $node) use ($query) {
                $title = $node->filter('.product-item-link')->text('') ?? '';
                $productUrl = $node->filter('.product-item-link')->attr('href') ?? '';
                $image = $node->filter('.product-image-photo')->attr('src') ?? '';

                $currentPrice = $node->filter('.price-wrapper')->attr('data-price-amount') ?? '';
                if (!isset($currentPrice) && empty($currentPrice)) {
                    $currentNode = $node->filter('span.price');
                    if ($currentNode->count()) {
                        $currentPrice = (int)preg_replace('/[^0-9]/', '', $currentNode->text());
                    }
                }

                $originalPrice = NULL;
                $price = $currentPrice ?? $originalPrice ?? '';

                $stockStatus = null;
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
        return [];
    }


}
