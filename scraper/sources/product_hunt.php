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
    return run_atom_feed_source($runId, 'https://www.producthunt.com/feed', 'product_hunt', null, true);
}

// Uwaga: link wpisu PH wskazuje na stronę produktu na producthunt.com/products/…,
// nie na domenę produktu (realny URL jest za przekierowaniem /r/p/ID) — ta sama
// znana luka "website_url" co w BetaList/NiFi.
//
// Resolver jak w BetaList NIE działa z GitHub Actions (próba 03–04.11.2026, CHANGELOG v0.38/v0.40):
// zarówno /r/p/ID, jak i strona produktu zwracają 403 z cf-mitigated: challenge (Cloudflare
// dla automatów). Z domowego IP /r/p/ID daje 301, ale potok nie może zależeć od komputera Pawła.
// Nie obchodzić wyzwania (udawanie przeglądarki, proxy). Link produktu poprawia się przy
// moderacji. Oficjalne API PH wymaga zgody na użycie komercyjne (hello@producthunt.com).
