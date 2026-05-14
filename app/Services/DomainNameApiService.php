<?php

namespace App\Services;

use DomainNameApi\DomainNameAPI_PHPLibrary;

class DomainNameApiService
{
    public function normalizeDomain(string $value): string
    {
        $domain = strtolower(trim($value));
        $domain = preg_replace('/^https?:\/\//i', '', $domain);
        $domain = preg_replace('/\/.*$/', '', (string) $domain);
        return trim((string) $domain, " \t\n\r\0\x0B.");
    }

    public function isValidDomain(string $domain): bool
    {
        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $domain);
    }

    public function tldFromDomain(string $domain): string
    {
        $parts = explode('.', $domain);
        return strtolower((string) end($parts));
    }

    public function priceFor(string $tld): float
    {
        $prices = $this->parsePrices((string) config('services.domainnameapi.prices_bdt', ''));
        return (float) ($prices[strtolower($tld)] ?? 0);
    }

    public function supportedTlds(): array
    {
        return array_values(array_filter(array_map(function ($item) {
            return strtolower(ltrim(trim((string) $item), '.'));
        }, explode(',', (string) config('services.domainnameapi.supported_tlds', 'com')))));
    }

    public function checkAvailability(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $tld = $this->tldFromDomain($domain);

        if (!$this->isValidDomain($domain)) {
            return [
                'configured' => $this->isConfigured(),
                'available' => false,
                'message' => 'Please enter a valid domain.',
            ];
        }

        if (!in_array($tld, $this->supportedTlds(), true)) {
            return [
                'configured' => $this->isConfigured(),
                'available' => false,
                'message' => 'This domain extension is not supported yet.',
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'configured' => false,
                'available' => false,
                'message' => 'DomainNameAPI credentials are not configured.',
            ];
        }

        $result = $this->callSdkAvailability($domain);

        return [
            'configured' => true,
            'available' => (bool) ($result['available'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'source' => (string) ($result['source'] ?? 'domainnameapi'),
        ];
    }

    private function isConfigured(): bool
    {
        $hasApiKeyAuth = (string) config('services.domainnameapi.reseller_id', '') !== ''
            && (string) config('services.domainnameapi.api_key', '') !== '';
        $hasBasicAuth = (string) config('services.domainnameapi.username', '') !== ''
            && (string) config('services.domainnameapi.password', '') !== '';

        return $hasApiKeyAuth || $hasBasicAuth;
    }

    private function callSdkAvailability(string $domain): array
    {
        $resellerId = (string) config('services.domainnameapi.reseller_id');
        $apiKey = (string) config('services.domainnameapi.api_key');
        $username = (string) config('services.domainnameapi.username');
        $password = (string) config('services.domainnameapi.password');
        $tld = $this->tldFromDomain($domain);
        $domainName = preg_replace('/\.' . preg_quote($tld, '/') . '$/i', '', $domain);
        $testMode = in_array(strtolower((string) config('services.domainnameapi.environment', 'prod')), ['ote', 'test', 'sandbox'], true);

        try {
            $this->loadSdkIfNeeded();
            $client = new DomainNameAPI_PHPLibrary(
                $resellerId !== '' ? $resellerId : $username,
                $apiKey !== '' ? $apiKey : $password,
                $testMode
            );
            $response = $client->checkAvailability([$domainName], [$tld], 1, 'create');
            return $this->normalizeAvailabilityResponse($response, $domainName, $tld);
        } catch (\Throwable $exception) {
            return ['available' => false, 'message' => 'Could not connect to DomainNameAPI: ' . $exception->getMessage()];
        }
    }

    private function normalizeAvailabilityResponse($payload, ?string $domainName = null, ?string $tld = null): array
    {
        if (is_array($payload)) {
            if (isset($payload['error'])) {
                return [
                    'available' => false,
                    'message' => (string) ($payload['error']['Message'] ?? $payload['error']['Details'] ?? 'DomainNameAPI request failed.'),
                    'source' => 'domainnameapi',
                ];
            }

            foreach ($payload as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemDomain = strtolower((string) ($item['DomainName'] ?? ''));
                $itemTld = strtolower(ltrim((string) ($item['TLD'] ?? ''), '.'));
                if (($domainName === null || $itemDomain === strtolower($domainName)) && ($tld === null || $itemTld === strtolower($tld))) {
                    $status = strtolower((string) ($item['Status'] ?? ''));
                    return [
                        'available' => $status === 'available',
                        'message' => $status === 'available' ? 'Domain is available.' : (string) ($item['Reason'] ?? 'Domain is not available.'),
                        'source' => 'domainnameapi',
                    ];
                }
            }
        }

        $encoded = strtolower(json_encode($payload));
        foreach (['"available":true', '"purchasable":true', '"isavailable":true', '"status":"available"'] as $needle) {
            if (str_contains($encoded, $needle)) {
                return ['available' => true, 'message' => 'Domain is available.', 'source' => 'domainnameapi'];
            }
        }
        foreach (['"available":false', '"purchasable":false', '"isavailable":false', '"status":"registered"', '"status":"unavailable"'] as $needle) {
            if (str_contains($encoded, $needle)) {
                return ['available' => false, 'message' => 'Domain is not available.', 'source' => 'domainnameapi'];
            }
        }

        return ['available' => false, 'message' => 'Could not read domain availability response.', 'source' => 'domainnameapi'];
    }

    private function loadSdkIfNeeded(): void
    {
        if (class_exists(DomainNameAPI_PHPLibrary::class)) {
            return;
        }

        $sdkPaths = glob(base_path('vendor/domainreseller/php-dna/*/DomainNameApi/DomainNameAPI_PHPLibrary.php')) ?: [];
        $sdkPaths[] = base_path('vendor/domainreseller/php-dna/DomainNameApi/DomainNameAPI_PHPLibrary.php');

        foreach ($sdkPaths as $sdkPath) {
            if (file_exists($sdkPath)) {
                require_once $sdkPath;
                return;
            }
        }
    }

    private function parsePrices(string $value): array
    {
        $rows = [];
        foreach (explode(',', $value) as $part) {
            [$tld, $price] = array_pad(explode(':', $part, 2), 2, null);
            $tld = strtolower(trim((string) $tld));
            if ($tld !== '') {
                $rows[$tld] = (float) $price;
            }
        }
        return $rows;
    }
}
