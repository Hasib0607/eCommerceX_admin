<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Symfony\Component\HttpClient\HttpClient;

class DarazScraper extends BaseScraper
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
        $url = "https://www.daraz.com.bd/catalog/";
        $client = HttpClient::create();

        try {
            $response = $client->request('GET', $url, [
                'query' => [
                    'ajax' => 'true',
                    'q' => $query, // Do not urlencode here
                    'page' => $page,
                ],
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

            $totalResults = $data['mainInfo']['totalResults'] ?? 0;
            $pageSize = $data['mainInfo']['pageSize'] ?? 1;
            $this->totalPages = $totalResults > 0 ? ceil($totalResults / $pageSize) : 1;

            $productData = $data['mods']['listItems'] ?? [];
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
            $description = implode("|", array_slice($item['description'] ?? [], 0, 10));

            $price = isset($item['price']) ? cleanPrice(trim($item['price'])) : null;
            $original_price = isset($item['originalPrice']) ? cleanPrice(trim($item['originalPrice'])) : $price;

            $data = [
                'title' => $item['name'] ?? '',
                'description' => $description ?? NULL,
                'keywords' => $query ?? NULL,
                'url' => trim($item['itemUrl'], '/') ?? '',
                'image' => trim($item['image'], '/') ?? '',
                'original_price' => $original_price,
                'price' => $price,
                'currency' => $item['currency'] ?? '৳',
                'in_stock' => $item['inStock'] == true ? 1 : 0,
                'product_id' => $item['itemId'] ?? '',
                'sku_id' => $item['skuId'] ?? '',
                'source_url' => trim($item['itemUrl'], '/') ?? '',
                'brand_name' => $item['brandName'] ?? '',
                'seller_name' => $item['sellerName'] ?? '',
                'location' => $item['location'] ?? '',
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
        $title = $crawler->filter('h1.pdp-mod-product-badge-title')->count() ? trim($crawler->filter('h1.pdp-mod-product-badge-title')->text()) : '';

        $originalPrice = $crawler->filter('.pdp-product-price span.pdp-price_type_deleted')->count() ? $crawler->filter('.pdp-product-price span.pdp-price_type_deleted')->text() : '';
        $originalPrice = preg_replace('/[^\d]/', '', $originalPrice);

        $price = $crawler->filter('.pdp-product-price span.pdp-price_type_normal')->count() ? $crawler->filter('.pdp-product-price span.pdp-price_type_normal')->text() : '';
        $price = preg_replace('/[^\d]/', '', $price);

        $stockStatus = isset($title) && $title != "" ? 1 : 0;

        $productCode = $crawler->filter('td.product-info-data.product-code')->count() ? trim($crawler->filter('td.product-info-data.product-code')->text()) : '';
        $productId = preg_replace('/[^\d]/', '', $productCode);

        $brandName = $crawler->filter('td.product-info-data.product-brand')->count() ? trim($crawler->filter('td.product-info-data.product-brand')->text()) : '';

        $image = $crawler->filter('.gallery-preview-panel__content img')->count() ? $crawler->filter('.gallery-preview-panel__content img')->attr('src') : null;

        $fullText = $crawler->filter('.full-description p')->count() ? $crawler->filter('.full-description p')->text() : '';
        $cleanText = html_entity_decode($fullText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Extract first 150 words while trying to avoid cutting mid-sentence
        $words = explode(' ', $cleanText);
        $limitedWords = array_slice($words, 0, 250);
        $limitedText = implode(' ', $limitedWords);

        // Find last punctuation mark and trim to there
        $lastPunctuation = max(
            strrpos($limitedText, '.'),
            strrpos($limitedText, '!'),
            strrpos($limitedText, '?')
        );

        if ($lastPunctuation !== false) {
            $limitedText = substr($limitedText, 0, $lastPunctuation + 1);
        }

        $data = [
            'title' => trim($title),
            'description' => $limitedText ?? NULL,
            'keywords' => $query ?? NULL,
            'url' => trim($url, '/'),
            'image' => trim($image),
            'original_price' => $originalPrice ? cleanPrice(trim($originalPrice)) : null,
            'price' => cleanPrice(trim($price)),
            'currency' => $item['currency'] ?? '৳',
            'in_stock' => $stockStatus,
            'product_id' => $productId ?? NULL,
            'sku_id' => '',
            'source_url' => trim($url, '/'),
            'brand_name' => $brandName ?? NULL,
            'seller_name' => '',
            'location' => '',
        ];

        return $this->formatProduct($data, $this->getDomain());
    }

}
