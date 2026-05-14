<?php

namespace App\Scrapers;

use App\Models\ScrapedProduct;

trait ProductResponseFormatter
{
    public function formatProduct(array $params, string $sourceSite = null, string $source_type = null): array
    {
        return [
            'title' => trim($params['title'] ?? ''),
            'description' => $params['description'] ?? null,
            'keywords' => $params['keywords'] ?? null,
            'rating' => isset($params['rating']) ? formatNumber($params['rating']) : null,
            'url' => trim(ensure_https_url($params['url']) ?? '', '/'),
            'image' => trim($params['image'] ?? ''),
            'original_price' => isset($params['original_price']) ? formatNumber($params['original_price']) : null,
            'price' => isset($params['price']) ? formatNumber($params['price']) : 0,
            'currency' => $params['currency'] ?? '৳',
            'in_stock' => $params['in_stock'] ?? null,
            'product_id' => $params['product_id'] ?? null,
            'sku_id' => $params['sku_id'] ?? '',
            'source_site' => $this->checkSourceSite($sourceSite) ?? $params['source_site'] ?? null,
            'source_url' => trim(ensure_https_url($params['url']) ?? '', '/'),
            'brand_name' => $params['brand_name'] ?? null,
            'seller_name' => $params['seller_name'] ?? '',
            'location' => $params['location'] ?? '',
            'source_type' => $source_type,
        ];

    }


    private function checkSourceSite(string $sourceSite): string
    {
        if ($sourceSite == "shop.shajgoj.com") {
            return "shajgoj.com";
        }
        return $sourceSite;
    }


    public function updateOrCreateProduct(array $data)
    {
        try {
            $data['source_type'] = 'scraped';
            $data['last_verified_at'] = now();
            ScrapedProduct::updateOrCreate(
                ['source_url' => $data['source_url']],
                $data
            );
        } catch (\Throwable $e) {
            return;
        }
    }


}
