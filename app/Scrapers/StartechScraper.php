<?php

namespace App\Scrapers;

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Http;

class StartechScraper implements ScraperInterface
{
    use ProductResponseFormatter;

    protected $client;
    protected $products = [];
    protected $totalPages = 1;

    public function __construct()
    {
        $this->client = new HttpBrowser(HttpClient::create());
    }


    /**
     * Search products on this website by keyword
     */
    public function searchByKeyword(string $query, int $page): array
    {
        $this->getProductResponse($query, $page);

        return [
            "products" => $this->products,
            "page" => $this->totalPages,
        ];
    }


    /**
     * This function get product response and call store products function
     *
     * @param string $query
     * @param int $page
     * @return void|null
     */
    private function getProductResponse(string $query, int $page)
    {
        try {
            $baseUrl = 'https://www.startech.com.bd/product/search';

            // First request to get total number of pages
            $response = Http::get($baseUrl, [
                'search' => $query,
                'page' => $page,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            $this->setProduct($crawler, $query);

            // Get total pages from "Showing 41 to 60 of 1000 (50 Pages)"
            $paginationText = $crawler->filter('.text-right p')->text(null, false);
            preg_match('/\((\d+)\s+Pages\)/i', $paginationText, $matches);
            $lastPage = $matches[1] ?? 1;
            $this->totalPages = $lastPage;
        } catch (\Throwable $e) {
            return null;
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
            $crawler->filter('.p-items-wrap .p-item')->each(function (Crawler $node) use ($query) {
                $titleNode = $node->filter('.p-item-name a');
                $imageNode = $node->filter('.p-item-img img');
                $priceNode = $node->filter('.p-item-price span');
                $newPriceNode = $node->filter('.p-item-price span.price-new');
                $originalPriceNode = $node->filter('.p-item-price span.price-old');
                $stockNode = $node->filter('.actions span.btn-add-cart');

                $descriptionItems = $node->filter('.short-description li')->each(function ($liNode) {
                    return trim(preg_replace('/\s+/', ' ', $liNode->text()));
                });
                $description = implode('|', $descriptionItems);

                $title = $titleNode->text('') ?? '';
                $productUrl = $titleNode->attr('href') ?? '';
                $image = $imageNode->attr('src') ?? '';
                $price = $priceNode->text('') ?? '';
                $priceNew = $newPriceNode->text($price) ?? '';
                $priceNew = preg_replace('/[^\d]/', '', $priceNew);
                $stockStatus = $stockNode->count() > 0 ? 1 : 0;

                $originalPrice = $originalPriceNode->text($priceNew) ?? "";
                $originalPrice = preg_replace('/[^\d]/', '', $originalPrice);

                $data = [
                    'title' => trim($title),
                    'description' => $description ?? NULL,
                    'keywords' => $query ?? NULL,
                    'url' => trim($productUrl, '/'),
                    'image' => trim($image),
                    'original_price' => $originalPrice ? cleanPrice(trim($originalPrice)) : null,
                    'price' => cleanPrice(trim($priceNew)),
                    'currency' => $item['currency'] ?? '৳',
                    'in_stock' => $stockStatus,
                    'product_id' => '',
                    'sku_id' => '',
                    'source_url' => trim($productUrl, '/'),
                    'brand_name' => '',
                    'seller_name' => '',
                    'location' => '',
                ];

                $this->products[] = $this->formatProduct($data, 'startech.com.bd');
            });
        } catch (\Throwable $e) {
//            return null;
        }
    }

    /**
     * Scrape product details from a direct product URL
     */
    public function scrape(string $url): array
    {
        $crawler = $this->client->request('GET', $url);

        $title = $crawler->filter('.product-name')->count() ? trim($crawler->filter('.product-name')->text()) : '';

        $originalPrice = $crawler->filter('td.product-info-data.product-regular-price')->count() ? $crawler->filter('td.product-info-data.product-regular-price')->text() : '';
        $originalPrice = preg_replace('/[^\d]/', '', $originalPrice);

        $price = $crawler->filter('td.product-info-data.product-price')->count() ? $crawler->filter('td.product-info-data.product-price')->text() : '';
        $price = preg_replace('/[^\d]/', '', $price);

        $stockText = $crawler->filter('td.product-info-data.product-status')->count() ? trim($crawler->filter('td.product-info-data.product-status')->text()) : '';
        $stockStatus = isset($stockText) && $stockText == "In Stock" ? 1 : 0;

        $productCode = $crawler->filter('td.product-info-data.product-code')->count() ? trim($crawler->filter('td.product-info-data.product-code')->text()) : '';
        $productId = preg_replace('/[^\d]/', '', $productCode);

        $brandName = $crawler->filter('td.product-info-data.product-brand')->count() ? trim($crawler->filter('td.product-info-data.product-brand')->text()) : '';

        $image = $crawler->filter('.thumbnail img')->count() ? $crawler->filter('.thumbnail img')->attr('src') : null;

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

        return $this->formatProduct($data, 'startech.com.bd');
    }


}
