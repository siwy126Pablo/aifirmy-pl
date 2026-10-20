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
// Lista kategorii z promptu (openai.php) i tabeli categories — pisownia 1:1.
const PIPELINE_CATEGORIES = [
    'Automatyzacja procesów', 'Analityka i BI', 'Finanse i księgowość',
    'HR i rekrutacja', 'Marketing i content', 'Obsługa klienta',
    'Prawo i compliance', 'Sprzedaż i CRM', 'Zarządzanie projektami',
    'Cyberbezpieczeństwo AI',
];

// Zwraca kanoniczną nazwę kategorii albo null. Dopasowanie bez wielkości liter
// i polskich znaków („Automatyzacja procesow” → „Automatyzacja procesów”), bez
// zgadywania synonimów: kategoria spoza listy dałaby w triggerze
// promote_scrape_to_tools() narzędzie z category_id = NULL, więc wpis trafia do
// „Odrzucone przez AI” z powodem w error_msg zamiast do kolejki.
function normalize_pipeline_category(?string $category): ?string {
    if ($category === null) {
        return null;
    }
    $key = static function (string $s): string {
        $s = mb_strtolower(trim($s), 'UTF-8');
        return strtr($s, ['ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
                          'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z']);
    };
    $needle = $key($category);
    foreach (PIPELINE_CATEGORIES as $c) {
        if ($key($c) === $needle) {
            return $c;
        }
    }
    return null;
}

// Usuwa prefiksy HN („Show HN:”, „Launch HN:”, „Ask HN:”) — prompt o tym mówi,
// ale model czasem je zostawia.
function strip_hn_prefix(string $name): string {
    return trim(preg_replace('/^\s*(Show|Launch|Ask)\s+HN\s*:\s*/iu', '', $name) ?? $name);
}

function enqueue_candidate(
    string $name,
    string $url,
    string $sourceName,
    array $rawJson,
    ?string $rawDesc,
    string $runId,
    array &$stats,
    array $extraDedupUrls = []
): void {
    // Dedup: exact match source_url na $url oraz ewentualnych dodatkowych adresach
    // (BetaList: adres katalogu, pod którym są zapisane wiersze sprzed 20.10).
    $dedupUrls = array_values(array_unique(array_merge([$url], $extraDedupUrls)));
    if (count($dedupUrls) === 1) {
        $filter = 'source_url=eq.' . rawurlencode($url);
    } else {
        $parts = array_map(fn($u) => 'source_url.eq."' . rawurlencode($u) . '"', $dedupUrls);
        $filter = 'or=(' . implode(',', $parts) . ')';
    }
    try {
        $existing = sb_get('scrape_queue?' . $filter . '&select=id&limit=1');
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
    $errorMsg = null;
    $rawCategory = isset($ai['category']) && is_string($ai['category']) ? $ai['category'] : null;
    $category = normalize_pipeline_category($rawCategory);
    if ($isRealProduct && $category === null) {
        $isRealProduct = false;
        $errorMsg = 'Kategoria spoza listy: ' . ($rawCategory ?? 'brak');
        scraper_log('warning', $errorMsg . ' — wpis do „Odrzucone przez AI”', [
            'source_url' => $url,
        ], $runId);
    }
    $stage = $isRealProduct ? 'ai_done' : 'ai_rejected';
    if (!$isRealProduct) {
        $stats['rejected']++;
    }
    $aiName = isset($ai['name']) && is_string($ai['name']) && trim($ai['name']) !== '' ? $ai['name'] : $name;

    try {
        sb_post('scrape_queue', [
            'source_name'      => $sourceName,
            'source_url'       => $url,
            'raw_name'         => $name,
            'raw_desc'         => $rawDesc !== null && $rawDesc !== '' ? $rawDesc : null,
            'raw_json'         => $rawJson,
            'name'             => strip_hn_prefix($aiName),
            'ai_description'   => $ai['description'] ?? null,
            'ai_category'      => $category ?? $rawCategory,
            'ai_tags'          => $ai['tags'] ?? null,
            'ai_pricing_model' => $ai['pricing_model'] ?? null,
            'best_for_pl'      => $ai['best_for_pl'] ?? null,
            'stage'            => $stage,
            'error_msg'        => $errorMsg,
        ]);
        $stats['inserted']++;
    } catch (\Throwable $e) {
        $stats['errors']++;
        scraper_log('error', 'Błąd zapisu do scrape_queue: ' . $e->getMessage(), [
            'source_url' => $url,
        ], $runId);
    }
}
