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

// Podąża za przekierowaniami ręcznie (bez pobierania strony docelowej) i zwraca
// pierwszy URL, którego host NIE pasuje do $stayOnHost (np. 'betalist.com').
// Dzięki temu nie odpytujemy samej strony produktu — część z nich odrzuca boty
// (403/Cloudflare) albo długo odpowiada. Rzuca RuntimeException, gdy łańcuch się
// urwie (brak Location, błąd sieci, HTTP >= 400) albo przekroczy $maxHops.
function http_resolve_redirect_off_host(string $url, string $stayOnHost, int $timeout = 5, int $maxHops = 5): string {
    $current = $url;
    for ($hop = 0; $hop < $maxHops; $hop++) {
        $ch = curl_init($current);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOBODY         => false,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; aifirmy-scraper/1.0; +https://aifirmy.pl)',
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $location = (string) (curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: '');
        curl_close($ch);

        if ($res === false) {
            throw new RuntimeException('http_resolve curl error (' . $current . '): ' . $err);
        }
        if ($httpCode < 300 || $httpCode >= 400 || $location === '') {
            throw new RuntimeException('http_resolve: brak przekierowania, HTTP ' . $httpCode . ' (' . $current . ')');
        }

        $host = strtolower((string) parse_url($location, PHP_URL_HOST));
        $stay = strtolower($stayOnHost);
        if ($host !== '' && $host !== $stay && !str_ends_with($host, '.' . $stay)) {
            return $location;
        }
        $current = $location;
    }
    throw new RuntimeException('http_resolve: za dużo przekierowań w obrębie ' . $stayOnHost . ' (' . $url . ')');
}

// Usuwa query string i fragment (?ref=betalist, utm_*, via=… itd.). Dla adresów
// stron głównych produktów parametry są w praktyce zawsze śledzące; ta sama
// reguła co dotychczasowa normalizacja URL-i z feedów Atom (część przed '?').
function strip_url_query(string $url): string {
    $url = strstr($url, '#', true) ?: $url;
    return strstr($url, '?', true) ?: $url;
}
