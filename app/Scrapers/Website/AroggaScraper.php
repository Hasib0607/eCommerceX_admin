<?php

namespace App\Scrapers\Website;

use App\Scrapers\BaseScraper;
use Symfony\Component\HttpClient\HttpClient;

class AroggaScraper extends BaseScraper
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
        $url = "https://api.arogga.com/general/v3/search";
        $client = HttpClient::create();

        try {
            $response = $client->request('GET', $url, [
                'query' => [
                    '_perPage' => $this->perPage,
                    '_search' => $query, // Do not urlencode here
                    '_page' => $page,
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
                $total = $data['total'] ?? 0;
                $totalItems = max(0, (int)$total); // Force positive integer
                $perPage = max(1, (int)$this->perPage);  // Avoid division by zero
                $this->totalPages = $totalItems > 0 ? ceil($totalItems / $perPage) : 1;

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
            $pv = $item['pv'][0] ?? [];
            $pv_id = $pv['pv_id'] ?? "";
            $id = $item['id'] ?? "";

            $name = $item["p_name"] ?? "";
            $form = $item["p_form"] ?? "";
            $strength = $item["p_strength"] ?? "";

            $slug = $this->generateProductSlug($name, $form, $strength);

            $url = "https://www.arogga.com/product/$id/$slug?pv_id=$pv_id";

            $image = $item['attachedFiles_p_images'][0]['src'] ?? "";
            if (!$image) {
                $image = $item['POSTER'] ?? "";
            }
            $description = $item['p_description'] ?? "";
            $description = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $original_price = $pv['pv_b2c_mrp'] ?? null;
            $price = $pv['pv_b2c_price'] ?? 0;

            $rating = $pv['pv_average_rating'] ?? 0;
            $brands = $item['p_brand'];

            $data = [
                'title' => trim($name) ?? '',
                'description' => $description ?? NULL,
                'keywords' => NULL,
                'rating' => $rating ?? NULL,
                'url' => trim($url, '/') ?? '',
                'image' => trim($image, '/') ?? '',
                'original_price' => $original_price,
                'price' => $price,
                'currency' => '৳',
                'in_stock' => $pv['pv_stock_status'] > 0 ? 1 : 0,
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

    private function generateProductSlug(string $name, string $form, string $strength): string
    {
        // Clean each component separately
        $clean_name = trim(preg_replace('/[^a-zA-Z0-9\s-]/', '', $name ?? ''));
        $clean_form = trim(preg_replace('/[^a-zA-Z0-9\s-]/', '', $form ?? ''));
        $clean_strength = preg_replace('/[^a-zA-Z0-9]/', '', $strength ?? '');

        // Prepare slug parts (only include non-empty components)
        $slug_parts = [];

        if (!empty($clean_name)) {
            $slug_parts[] = strtolower(str_replace(' ', '-', $clean_name));
        }

        if (!empty($clean_form)) {
            $slug_parts[] = strtolower(str_replace(' ', '-', $clean_form));
        }

        if (!empty($clean_strength)) {
            $slug_parts[] = strtolower($clean_strength);
        }

        // If all components are empty, return a default slug
        if (empty($slug_parts)) {
            return 'product';
        }

        // Combine and clean the final slug
        $slug = implode('-', $slug_parts);
        $slug = preg_replace('/-+/', '-', $slug); // Remove duplicate hyphens
        $slug = trim($slug, '-'); // Trim leading/trailing hyphens

        return $slug;
    }

    private function extractProductId(string $url): int|null
    {
        // Parse URL components
        $query = parse_url($url, PHP_URL_QUERY);
        $path = parse_url($url, PHP_URL_PATH);

        // Check query parameter first
        if ($query) {
            parse_str($query, $params);
            if (!empty($params['pv_id']) && is_numeric($params['pv_id'])) {
                return (int)$params['pv_id'];
            }
        }

        // Fallback to path segment
        $segments = explode('/', trim($path, '/'));
        if (count($segments) >= 3 && is_numeric($segments[2])) {
            return (int)$segments[2];
        }

        return null; // No valid ID found
    }

    /**
     * Scrape product details from a direct product URL
     */
    public function scrape(string $url): array|null
    {
        $productId = $this->extractProductId($url);

        $baseUrl = "https://api.arogga.com/general/v1/globalSingleProductAction/$productId";
        $client = HttpClient::create();

        $response = $client->request('POST', $baseUrl);

        $content = $response->getContent(false);
        $data = json_decode($content, true);

        $price = $data['data']['price'] ?? [];
        $originalPrice = $price['b2cMrp'] ?? null;
        $price = $price['b2cPrice'] ?? 0;

        $crawler = $this->client->request('GET', $url);

        $title = $crawler->filter('h1.text-capitalize.text-24.fw-600')->count() ? trim($crawler->filter('h1.text-capitalize.text-24.fw-600')->text()) : '';
        $brandName = $crawler->filter('div.product_company_warp__vWamg div.text-primary')->count() ? trim($crawler->filter('div.product_company_warp__vWamg div.text-primary')->text()) : '';

        $stockStatus = null;

        $image = $crawler->filter('.brand-slider_brand_slider__Llzl6 img')->count() ? $crawler->filter('.brand-slider_brand_slider__Llzl6 img')->attr('src') : null;
        $limitedText = "";

        $data = [
            'title' => trim($title),
            'description' => $limitedText ?? NULL,
            'keywords' => trim($title) ?? NULL,
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
