<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Symfony\Component\HttpClient\HttpClient;

class PickabooScraper extends BaseScraper
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
        $url = "https://searchserverapi.com/getresults";
        $client = HttpClient::create();
        $startIndex = (int)(($page - 1) * $this->perPage);

        try {
            $response = $client->request('GET', $url, [
                'query' => [
                    'api_key' => "6W7Z0N7U0T",
                    'queryCorrection' => "true",
                    'maxResults' => $this->perPage,
                    'restrictBy[visibility]' => "3|4",
                    'restrictBy[status]' => 1,
//                    'facets' => "true",
                    'startIndex' => $startIndex,
                    'q' => $query,
//                    'restrictBy[price]' => ",",
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

            if (isset($data["items"]) && count($data["items"]) > 0) {
                $total = $data['totalItems'] ?? 0;
                $perPage = $data['itemsPerPage'] ?? 0;
                $totalItems = max(0, (int)$total); // Force positive integer
                $perPage = max(1, (int)$perPage);  // Avoid division by zero
                $this->totalPages = $totalItems > 0 ? ceil($totalItems / $perPage) : 1;

                $productData = $data["items"] ?? [];

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
            $id = $item['product_id'] ?? "";
            $name = $item["title"] ?? "";
            $url = $item["link"] ?? "";
            $image = $item['image_link'] ?? "";
            $description = $item['description'] ?? "";

            $original_price = $item['list_price'] ?? null;
            $price = $item['price'] ?? 0;

            $rating = $item['reviews_average_score'] ?? NULL;
            $skuI = $item['product_code'] ?? NULL;
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
                'in_stock' => $item['quantity'] > 0 ? 1 : 0,
                'product_id' => $id,
                'sku_id' => $skuI ?? '',
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
