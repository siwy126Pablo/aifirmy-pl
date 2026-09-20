<?php
declare(strict_types=1);

// GET z timeoutem i User-Agentem. Rzuca RuntimeException (jak sb_get) przy błędzie
// sieci lub HTTP >= 400, żeby wołający mógł zalogować przyczynę.
function http_get(string $url, int $timeout = 5): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT        => $timeout,
        // Bez UA część serwisów (np. za Cloudflare) odrzuca żądanie.
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; aifirmy-scraper/1.0; +https://aifirmy.pl)',
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) {
        throw new RuntimeException('http_get curl error (' . $url . '): ' . $err);
    }
    if ($httpCode >= 400) {
        throw new RuntimeException('http_get HTTP ' . $httpCode . ' (' . $url . ')');
    }
    return $res;
}
