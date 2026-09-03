<?php

namespace Thevps\Vault\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches a service favicon for a credential's `url`. Once, synchronously, on create / url
 * change — best-effort: a network error must never break saving the credential itself. The
 * result is cached via media library and served from the host's own domain thereafter, so
 * viewers' browsers never ping the external aggregator with the company's account domains.
 *
 * SSRF guard (the URL is admin-entered, but still): http/https only; the host is DNS-resolved
 * and private/loopback/reserved addresses are rejected BEFORE the request. Redirect chains are
 * not re-validated — acceptable for an internal tool.
 */
class FaviconFetcher
{
    private const TIMEOUT_SECONDS = 5;

    public static function fetch(string $url): ?string
    {
        $host = self::hostFrom($url);
        if ($host === null || ! self::hostIsPublic($host)) {
            return null;
        }

        // The aggregator endpoint itself is from config (admin-trusted), not user input — we
        // never fetch the credential's URL directly, only pass its domain to the aggregator.
        $endpoint = str_replace(
            ['{domain}', '{size}'],
            [rawurlencode($host), (string) config('vault.favicon_size', 128)],
            config('vault.favicon_endpoint', 'https://www.google.com/s2/favicons?domain={domain}&sz={size}'),
        );

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get($endpoint);

            if ($response->successful() && str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                return $response->body();
            }
        } catch (\Throwable $e) {
            Log::warning('Vault favicon fetch failed', ['url' => $url, 'message' => $e->getMessage()]);
        }

        return null;
    }

    private static function hostFrom(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: parse_url('https://'.ltrim($url, '/'), PHP_URL_HOST);

        return $host ?: null;
    }

    /** Every IP the host resolves to must be public. */
    private static function hostIsPublic(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        $lower = strtolower($host);
        if ($lower === 'localhost' || str_ends_with($lower, '.localhost') || str_ends_with($lower, '.local') || str_ends_with($lower, '.internal')) {
            return false;
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip !== null && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
