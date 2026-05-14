<?php

namespace App\Scrapers;

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Http;
use function Laravel\Prompts\text;

class GadgetnMusicScraper implements ScraperInterface
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
            $baseUrl = "https://gadgetnmusic.com/page/$page/";

            // First request to get total number of pages
            $response = Http::get($baseUrl, [
                's' => $query,
                'post_type' => "product",
                'product_cat' => 0,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            $this->setProduct($crawler, $query);

            $pageLinks = $crawler->filter('.woocommerce-pagination .page-numbers li')
                ->each(function ($node) {
                    if ($node->filter('a.page-numbers')->count()) {
                        return (int)$node->filter('a')->text();
                    } elseif ($node->filter('span.current')->count()) {
                        return (int)$node->filter('span')->text();
                    }
                    return null;
                });

            $this->totalPages = max(array_filter($pageLinks)) ?? 1;
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
            $crawler->filter('.products.wd-products .wd-product')->each(function (Crawler $node) use ($query) {
                $title = $node->filter('.wd-entities-title')->text('') ?? '';
                $productUrl = $node->filter('.product-image-link')->attr('href') ?? '';
                $image = $node->filter('.product-image-link img')->attr('src') ?? '';

                $originalPrice = 0;
                // Get original price
                $currentNode = $node->filter('span.price del .woocommerce-Price-amount')->first();
                if ($currentNode->count()) {
                    $originalPrice = (int)preg_replace('/[^0-9]/', '', $currentNode->text());
                }

                $currentPrice = 0;
                // Get current price
                $currentNode = $node->filter('span.price ins')->text();
//                if ($currentNode->count()) {
//                    $currentPrice = (int)preg_replace('/[^0-9]/', '', $currentNode->text());
//                }

                dd($currentNode);

                // If no discount, current price might be the only price
//                if (is_null($priceData['current']) {
//                    $mainPrice = $node->filter('span.price .woocommerce-Price-amount')->first();
//                if ($mainPrice->count()) {
//                    $priceData['current'] = (int)preg_replace('/[^0-9]/', '', $mainPrice->text());
//                }


                $originalPrice = $node->filter('span.price del .woocommerce-Price-amount bdi')
                    ->first()
                    ->text();


                $currentPrice = (int)trim(str_replace('৳', '', $currentPrice));
                $originalPrice = (int)trim(str_replace('৳', '', $originalPrice));

                $originalPrice = $originalPrice ?? '';
                $price = $currentPrice ?? $originalPrice ?? '';

                $stockStatus = $node->filter('.out-of-stock')->count() > 0 ? 0 : 1;

                dd($stockStatus);


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
                    'sku_id' => '',
                    'source_url' => trim($productUrl, '/'),
                    'brand_name' => '',
                    'seller_name' => '',
                    'location' => '',
                ];

                dd($data);

                $this->products[] = $this->formatProduct($data, 'gadgetnmusic.com');
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
