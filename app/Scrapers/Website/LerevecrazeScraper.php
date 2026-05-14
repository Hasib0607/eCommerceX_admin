<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use function Laravel\Prompts\text;

class LerevecrazeScraper extends BaseScraper
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
            $baseUrl = "https://www.lerevecraze.com/page/$page/";

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

            $total = $crawler->filter('.found')->count() ? $crawler->filter('.found')->text() : "";
            $totalItems = max(0, (int)$total); // Force positive integer
            $perPage = max(1, (int)$this->perPage);  // Avoid division by zero
            $this->totalPages = $totalItems > 0 ? ceil($totalItems / $perPage) : 1;

            $crawler->filter('ul.products li.product')->each(function (Crawler $node) use ($query) {

                $title = $node->filter('.un-product-title a')->text('') ?? '';
                $productUrl = $node->filter('.un-product-title a')->attr('href') ?? '';

                $firstImg = $node->filter('.un-loop-thumbnail img:first-child');
                if ($firstImg->count() > 0) {
                    $image = $firstImg->attr('src');
                } else {
                    // Fallback to second image
                    $secondImg = $node->filter('.un-loop-thumbnail img.image-hover');
                    $image = $secondImg->count() > 0 ? $secondImg->attr('src') : "";
                }

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

                $skuId = "";
                $brandName = "";

                $extra = $node->filter('.add_to_cart_button')->first();
                if ($extra->count()) {
                    $skuId = $extra->attr("data-product_sku");
                    $product_id = $extra->attr("data-product_id");
                    $quantity = $extra->attr("data-quantity");
                }

                $stockStatus = isset($quantity) && !empty($quantity) && $quantity >= 1 ? 1 : 0;

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
                    'product_id' => $product_id ?? '',
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
