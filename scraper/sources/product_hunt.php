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
    return run_atom_feed_source($runId, 'https://www.producthunt.com/feed', 'product_hunt');
}

// Uwaga: link wpisu PH wskazuje na stronę produktu na producthunt.com/products/…,
// nie na domenę produktu (realny URL jest za przekierowaniem /r/p/ID) — ta sama
// znana luka "website_url" co w BetaList/NiFi.
