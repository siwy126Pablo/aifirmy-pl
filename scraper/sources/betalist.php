<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/atom.php';

// source_name = 'betalist' — w NiFi gałąź BetaList nie miała ustawionego
// source_name_override, więc jej wpisy trafiały do scrape_queue jako 'hacker_news'.
function run_betalist(string $runId): array {
    return run_atom_feed_source($runId, 'https://feeds.feedburner.com/BetaList', 'betalist');
}

// Uwaga: link wpisu w feedzie BetaList wskazuje na stronę startupu na betalist.com
// (…/startups/slug), nie na domenę samego produktu — więc source_url (→ website_url
// po zatwierdzeniu) wymaga ręcznej korekty, tak samo jak w NiFi (znana luka
// "website_url" z CLAUDE.md).
