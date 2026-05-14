<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Symfony\Component\HttpClient\HttpClient;

class BishworangScraper extends BaseScraper
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
        $url = "https://backend.bishworang.com.bd/api/v3/products";
        $client = HttpClient::create();

        try {
            $response = $client->request('GET', $url, [
                'query' => [
                    'search' => $query,
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

            if (isset($data["data"]) && count($data["data"]) > 0) {
                $this->totalPages = isset($data['meta']['last_page']) ? (int)$data['meta']['last_page'] : 1;

                $productData = $data["data"] ?? [];

                $this->setProduct($productData, $query);
            }
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
            $id = $item['id'] ?? "";
            $name = $item["name"] ?? "";
            $slug = $item["slug"] ?? "";
            $url = "https://www.bishworang.com.bd/details/$slug";
            $image = $item['thumbnail_image'] ?? "";
            $description = "";

            $original_price = $item['base_price'] ?? null;
            $price = $item['base_discounted_price'] ?? 0;

            $rating = 0;
            $brands = "";

            $data = [
                'title' => trim($name) ?? '',
                'description' => $description ?? NULL,
                'keywords' => $query ?? NULL,
                'rating' => $rating ?? NULL,
                'url' => trim($url, '/') ?? '',
                'image' => trim($image, '/') ?? '',
                'original_price' => $original_price,
                'price' => $price,
                'currency' => '৳',
                'in_stock' => $item['current_stock'] > 0 ? 1 : 0,
                'product_id' => $id,
                'sku_id' => '',
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
        return [];
    }

}
