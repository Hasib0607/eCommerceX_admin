<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use function Laravel\Prompts\text;

class GentleparkScraper extends BaseScraper
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

            if ($page > 1) return;

            $baseUrl = "https://www.gentlepark.com/product-search.php";

            // First request to get total number of pages
            $response = Http::get($baseUrl, [
                'searchkeyword' => $query,
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

            $crawler->filter('.grid-products div.item')->each(function (Crawler $node) use ($query) {
                $baseUrl = "https://www.gentlepark.com/";
                $title = $node->filter('.product-name a')->text('') ?? '';

                $productUrl = "";
                $productUrlNode = $node->filter('.product-name a');
                if ($productUrlNode->count() > 0) {
                    $productUrl = $baseUrl . $productUrlNode->attr('href');
                }

                $image = "";
                $firstImg = $node->filter('.primarys');
                if ($firstImg->count() > 0) {
                    $image = $baseUrl . $firstImg->attr('src');
                }

                $originalPrice = 0;
                // Get original price
                $originalNode = $node->filter('.old-price');
                if ($originalNode->count()) {
                    $originalPrice = (int)preg_replace('/[^0-9]/', '', $originalNode->text());
                }

                $currentPrice = 0;
                // Get current price
                $currentNode = $node->filter('.price');
                if ($currentNode->count()) {
                    $currentPrice = (int)preg_replace('/[^0-9]/', '', $currentNode->text());
                }

                $originalPrice = $originalPrice ?? '';
                $price = $currentPrice ?? $originalPrice ?? '';

                $skuId = "";
                $brandName = "";

                $form = $node->filter('form.variants.add');
                if ($form->count()) {
                    $quantity = $form->filter('input[name="quantity"]')->attr('value') ?? '';
                    $skuId = $form->filter('input[name="hidden_id"]')->attr('value');
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
