<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/atom.php';

// source_name = 'product_hunt' — jak w BetaList: w NiFi brak source_name_override
// oznaczał wpisy jako 'hacker_news'.
//
// Filtr "type=story" z NiFi pominięty świadomie: feed PH nie rozróżnia typów
// wpisów (same <entry> z produktami), a w NiFi hn_type=story było stałą
// wpisywaną w UpdateAttribute "(BetaList normalize)", więc ten warunek nigdy
// niczego nie odrzucał.
function run_product_hunt(string $runId): array {
    // matchSummary: nazwy PH ("Floot", "Revix") rzadko mają słowo kluczowe, a tagline w opisie je ma.
    return run_atom_feed_source($runId, 'https://www.producthunt.com/feed', 'product_hunt', 'resolve_product_hunt_url', true);
}

// Link wpisu PH (rel="alternate") wskazuje na stronę produktu na producthunt.com/products/…,
// nie na domenę produktu. Domena jest za przekierowaniem z linku „Link” w treści wpisu
// (https://www.producthunt.com/r/p/<ID>?app_id=…, 301 → https://domena?ref=producthunt;
// parametry usuwa strip_url_query w run_atom_feed_source). Podobnie jak w BetaList: bez
// pobierania strony produktu, a przy błędzie zostaje adres katalogu + warning w logu.
function resolve_product_hunt_url(string $listingUrl, array $entry = []): string {
    $redirect = (string) ($entry['redirect_url'] ?? '');
    if ($redirect === '') {
        throw new RuntimeException('brak linku /r/p/ w treści wpisu');
    }
    // Obrona przed adresem spoza PH w treści feedu — odpytujemy tylko producthunt.com.
    $host = strtolower((string) parse_url($redirect, PHP_URL_HOST));
    if ($host !== 'producthunt.com' && !str_ends_with($host, '.producthunt.com')) {
        throw new RuntimeException('link przekierowania poza producthunt.com: ' . $redirect);
    }
    return http_resolve_redirect_off_host($redirect, 'producthunt.com', 5);
}
