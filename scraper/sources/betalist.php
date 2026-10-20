<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/atom.php';

// source_name = 'betalist' — w NiFi gałąź BetaList nie miała ustawionego
// source_name_override, więc jej wpisy trafiały do scrape_queue jako 'hacker_news'.
function run_betalist(string $runId): array {
    return run_atom_feed_source($runId, 'https://feeds.feedburner.com/BetaList', 'betalist', 'resolve_betalist_url');
}

// Link wpisu w feedzie wskazuje na stronę startupu na betalist.com
// (…/startups/slug), nie na domenę produktu. Od 20.10.2026 rozwiązujemy
// przekierowanie …/startups/slug/visit (302 → domena produktu, czasem z
// ?ref=betalist — obcinane w run_atom_feed_source). Dzięki temu source_url, a za
// nim website_url i favicon liczone przez trigger promote_scrape_to_tools(),
// wskazują na produkt. Przy błędzie zostaje adres katalogu (jak przed 20.10).
function resolve_betalist_url(string $listingUrl): string {
    return http_resolve_redirect_off_host(rtrim($listingUrl, '/') . '/visit', 'betalist.com', 5);
}
