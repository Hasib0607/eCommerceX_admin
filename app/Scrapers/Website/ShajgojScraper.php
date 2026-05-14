<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Symfony\Component\HttpClient\HttpClient;

class ShajgojScraper extends BaseScraper
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
        $url = "https://khoj.shajgoj.com/products/1/indexes/*/queries?defaultFacet=shop";
        $client = HttpClient::create();

        $requestBody = [
            [
                "indexName" => "products",
                "params" => [
                    "facets" => [],
                    "highlightPostTag" => "</ais-highlight-0000000000>",
                    "highlightPreTag" => "<ais-highlight-0000000000>",
                    "maxValuesPerFacet" => 500,
                    "page" => $page,
                    "query" => $query, // Fixed typo from "face was" to "face wash"
                    "tagFilters" => ""
                ]
            ]
        ];

        try {
            $response = $client->request('POST', $url, [
                'json' => $requestBody,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                ],
            ]);


            // Dump raw content to verify it returns JSON
            $content = $response->getContent(false); // returns content even if not JSON
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return;
            }

            $this->totalPages = isset($data['results'][0]['nbPages']) && $data['results'][0]['nbPages'] > 1 ? $data['results'][0]['nbPages'] : 1;
            $productData = $data['results'][0]['hits'] ?? [];

            $this->setProduct($productData, $query);
        } catch (\Throwable $e) {
            return;
        }
    }

    /**
     * This function received product response and store products in array
     *
     * @param array $productData
     * @return void
     */
    private function setProduct(array $productData, string $query = NULL)
    {
        foreach ($productData as $item) {
            $name = $item['name'] ?? "";

            if (!$this->matchTitle($name, $query)) {
                continue; // Skip this item if none of the words matched
            }

            $url = "https://shop.shajgoj.com/product/" . $item['slug'];
            $image = $item['thumbnail'] ?? "";
            $original_price = $item['price'] ?? null;
            $price = $item['sale_price'] ?? 0;
            if (isset($item['tags']) && count($item['tags'])) {
                $keywords = implode(',', $item['tags']);
            }
            $rating = $item['average_rating'] ?? 0;
            if (isset($item['brand']) && count($item['brand'])) {
                $brands = implode(',', $item['brand']);
            }

            $data = [
                'title' => trim($name) ?? '',
                'description' => NULL,
                'keywords' => $keywords ?? NULL,
                'rating' => $rating ?? NULL,
                'url' => trim($url, '/') ?? '',
                'image' => trim($image, '/') ?? '',
                'original_price' => $original_price,
                'price' => $price,
                'currency' => '৳',
                'in_stock' => $item['stock'] > 0 ? 1 : 0,
                'product_id' => $item['pid'] ?? '',
                'sku_id' => $item['product_sku'] ?? '',
                'source_url' => trim($url, '/') ?? '',
                'brand_name' => $brands ?? '',
                'seller_name' => '',
                'location' => '',
            ];

            $this->products[] = $this->formatProduct($data, $this->getDomain());
        }
    }


    /**
     * Scrape product details from a direct product URL
     */
    public function scrape(string $url): array|null
    {
        $crawler = $this->client->request('GET', $url);

        $title = $crawler->filter('.product-detail-col h2.text-lg')->count() ? trim($crawler->filter('.product-detail-col h2.text-lg')->text()) : '';


        $originalPrice = $crawler->filter('.product-detail-col .cut-price')->count() ? $crawler->filter('.product-detail-col .cut-price')->text() : '';
        $originalPrice = (float)str_replace([',', '৳'], '', $originalPrice);

        $price = $crawler->filter('.product-detail-col .just-price')->count() ? $crawler->filter('.product-detail-col .just-price')->text() : '';
        $price = (float)str_replace([',', '৳'], '', $price);

        // Look for the specific stock status div structure
        $stockStatus = null;

        $description = $crawler->filter('.text-sg-quartz .html-content')->html();
        $limitedText = trim(strip_tags($description));
        // Find last punctuation mark and trim to there
        $lastPunctuation = max(
            strrpos($limitedText, '.'),
            strrpos($limitedText, '!'),
            strrpos($limitedText, '?')
        );

        if ($lastPunctuation !== false) {
            $limitedText = substr($limitedText, 0, $lastPunctuation + 1);
        }

        $image = null;

        // Get the entire text content
        $ratingText = $crawler->filter('.product-detail-col div.flex.items-center.bg-green-600')->count() ? trim($crawler->filter('.product-detail-col div.flex.items-center.bg-green-600')->text()) : '';
        preg_match('/^(\d+\.\d+)/', $ratingText, $matches);
        $rating = $matches[1] ?? null;

        // Extract SKU
        $sku = trim($crawler->filter('.text-sg-quartz div.flex h4:contains("SKU") + p')->text());

        // Extract Tags
        $tags = implode('', array_filter(array_map('trim',
            $crawler->filter('.text-sg-quartz div.flex h4:contains("Tags") + p a')
                ->each(function ($node) {
                    return trim($node->text());
                })
        )));


        // Extract Brands
        $brandName = implode('', array_filter(array_map('trim',
            $crawler->filter('.text-sg-quartz div.flex h4:contains("Brands") + p a')
                ->each(function ($node) {
                    return trim($node->text());
                })
        )));


        $data = [
            'title' => trim($title),
            'description' => $limitedText ?? NULL,
            'keywords' => $tags ?? NULL,
            'rating' => $rating ?? NULL,
            'url' => trim($url, '/'),
            'image' => trim($image),
            'original_price' => $originalPrice ? cleanPrice(trim($originalPrice)) : null,
            'price' => cleanPrice(trim($price)),
            'currency' => '৳',
            'in_stock' => $stockStatus,
            'product_id' => $productId ?? NULL,
            'sku_id' => $sku ?? '',
            'source_url' => trim($url, '/'),
            'brand_name' => $brandName ?? NULL,
            'seller_name' => '',
            'location' => '',
        ];

        return $this->formatProduct($data, $this->getDomain());
    }

}
