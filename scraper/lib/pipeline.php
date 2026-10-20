<?php
declare(strict_types=1);

require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/logging.php';

// Wspólny końcowy odcinek dla HN/BetaList/Product Hunt: dedup po source_url →
// OpenAI → insert do scrape_queue. $rawDesc (BetaList/PH: summary z feedu Atom,
// do 1000 znaków) idzie do OpenAI jako Kontekst — bez niego model zmyślał opisy
// ogólnikowych nazw (19.10: 4/9 „Platforma AI do X”). HN przekazuje null →
// Kontekst „brak”, jak wcześniej.
// Ta sama sekwencja co w yc_oss.php; $stats aktualizowane przez referencję
// (klucze: duplicates, inserted, rejected, errors).
function enqueue_candidate(
    string $name,
    string $url,
    string $sourceName,
    array $rawJson,
    ?string $rawDesc,
    string $runId,
    array &$stats
): void {
    try {
        $existing = sb_get('scrape_queue?source_url=eq.' . rawurlencode($url) . '&select=id&limit=1');
    } catch (\Throwable $e) {
        $stats['errors']++;
        scraper_log('error', 'Błąd sprawdzania duplikatu: ' . $e->getMessage(), [
            'source_url' => $url,
        ], $runId);
        return;
    }
    if (!empty($existing)) {
        $stats['duplicates']++;
        return;
    }

    try {
        $ai = call_openai_description($name, $url, $rawDesc ?? '');
    } catch (\Throwable $e) {
        $stats['errors']++;
        scraper_log('error', 'Błąd wywołania OpenAI: ' . $e->getMessage(), [
            'source_url' => $url,
        ], $runId);
        return;
    }

    $isRealProduct = ($ai['is_real_product'] ?? null) === true;
    $stage = $isRealProduct ? 'ai_done' : 'ai_rejected';
    if (!$isRealProduct) {
        $stats['rejected']++;
    }

    try {
        sb_post('scrape_queue', [
            'source_name'      => $sourceName,
            'source_url'       => $url,
            'raw_name'         => $name,
            'raw_desc'         => $rawDesc !== null && $rawDesc !== '' ? $rawDesc : null,
            'raw_json'         => $rawJson,
            'name'             => $ai['name'] ?? $name,
            'ai_description'   => $ai['description'] ?? null,
            'ai_category'      => $ai['category'] ?? null,
            'ai_tags'          => $ai['tags'] ?? null,
            'ai_pricing_model' => $ai['pricing_model'] ?? null,
            'best_for_pl'      => $ai['best_for_pl'] ?? null,
            'stage'            => $stage,
        ]);
        $stats['inserted']++;
    } catch (\Throwable $e) {
        $stats['errors']++;
        scraper_log('error', 'Błąd zapisu do scrape_queue: ' . $e->getMessage(), [
            'source_url' => $url,
        ], $runId);
    }
}
