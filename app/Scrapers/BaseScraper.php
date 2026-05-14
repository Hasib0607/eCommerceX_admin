<?php

namespace App\Scrapers;

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;

abstract class BaseScraper implements ScraperInterface
{
    use ProductResponseFormatter;

    protected string $domain;
    protected $client;
    protected $products = [];
    protected $totalPages = 1;
    protected $perPage = 20;

    public function __construct(string $domain, int $perPage = 20)
    {
        $this->client = new HttpBrowser(HttpClient::create());
        $this->domain = $domain;
        $this->perPage = $perPage;
    }

    public function getDomain(): string
    {
        return $this->domain;
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


    public function matchTitle(string $title, string $query): bool
    {
        $name = strtolower($title ?? "");
        $searchQuery = strtolower($query); // Replace this with your actual dynamic search input

        // Split query into individual words
        $searchWords = explode(' ', $searchQuery);

        // Flag to track if any word matches
        $matched = false;

        foreach ($searchWords as $word) {
            if (stripos($name, $word) !== false) {
                $matched = true;
                break;
            }
        }

        return $matched;
    }

    // Require child class to implement this
    abstract protected function getProductResponse(string $query, int $page): void;

}
