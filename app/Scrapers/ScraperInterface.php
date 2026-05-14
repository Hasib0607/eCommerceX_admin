<?php

namespace App\Scrapers;

interface ScraperInterface
{
    public function scrape(string $url): array|null;

    public function searchByKeyword(string $query, int $page): array;
}
