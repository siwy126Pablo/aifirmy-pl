<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/supabase.php';
require_once __DIR__ . '/../lib/openai.php';
require_once __DIR__ . '/../lib/logging.php';

function run_yc_oss_pilot(string $runId): array {
    $stats = [
        'fetched'    => 0,
        'in_window'  => 0,
        'duplicates' => 0,
        'inserted'   => 0,
        'rejected'   => 0,
        'errors'     => 0,
    ];

    $json = @file_get_contents('https://yc-oss.github.io/api/tags/artificial-intelligence.json');
    if ($json === false) {
        scraper_log('error', 'Nie udało się pobrać YC-OSS API', [], $runId);
        return $stats;
    }

    $companies = json_decode($json, true);
    if (!is_array($companies)) {
        scraper_log('error', 'YC-OSS API zwróciło niepoprawny JSON', [], $runId);
        return $stats;
    }

    $stats['fetched'] = count($companies);
    $cutoff = time() - 7776000; // 90 dni, ten sam próg co RouteOnAttribute "nowa_firma" w NiFi

    foreach ($companies as $company) {
        $launchedAt = $company['launched_at'] ?? null;
        if (!is_int($launchedAt) && !is_float($launchedAt)) {
            continue; // brak/zły typ pola — pomiń, jak EvaluateJsonPath by pominęło błędny rekord
        }
        if ($launchedAt < $cutoff) {
            continue;
        }
        $stats['in_window']++;

        $name    = $company['name'] ?? null;
        $website = $company['website'] ?? null;
        if (!$name || !$website) {
            continue;
        }

        // Dedup po source_url — url_hash jest kolumną nieużywaną (potwierdzone:
        // null we wszystkich sprawdzonych wierszach produkcyjnych), więc NiFi
        // też musi w praktyce dedupować po tym polu, nie po haszu.
        try {
            $existing = sb_get('scrape_queue?source_url=eq.' . rawurlencode($website) . '&select=id&limit=1');
        } catch (\Throwable $e) {
            $stats['errors']++;
            scraper_log('error', 'Błąd sprawdzania duplikatu: ' . $e->getMessage(), [
                'source_url' => $website,
            ], $runId);
            continue;
        }
        if (!empty($existing)) {
            $stats['duplicates']++;
            continue;
        }

        $oneLiner = $company['one_liner'] ?? '';
        $longDesc = $company['long_description'] ?? '';
        $sourceContext = trim(preg_replace('/[\r\n]+/', ' ', $oneLiner . ' ' . $longDesc));
        // Cudzysłowy NIE wymagają ręcznego usuwania jak w NiFi ReplaceText —
        // json_encode() w PHP poprawnie je escapuje natywnie przy budowie
        // requestu do OpenAI. Ręczne stripowanie (jak robi NiFi) byłoby tu
        // niepotrzebną utratą informacji.

        try {
            $ai = call_openai_description($name, $website, $sourceContext);
        } catch (\Throwable $e) {
            $stats['errors']++;
            scraper_log('error', 'Błąd wywołania OpenAI: ' . $e->getMessage(), [
                'source_url' => $website,
            ], $runId);
            continue;
        }

        $isRealProduct = ($ai['is_real_product'] ?? null) === true;
        $stage = $isRealProduct ? 'ai_done' : 'ai_rejected';
        if (!$isRealProduct) {
            $stats['rejected']++;
        }

        try {
            sb_post('scrape_queue', [
                'source_name'      => 'yc_ai_pilot',
                'source_url'       => $website,
                'raw_name'         => $name,
                'raw_desc'         => $sourceContext !== '' ? $sourceContext : null,
                'raw_json'         => $company,
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
                'source_url' => $website,
            ], $runId);
        }
    }

    return $stats;
}

// Świadomie pominięte pola z odpowiedzi AI: segment — model go zwraca (jest
// w system prompcie), ale nie ma odpowiadającej kolumny w scrape_queue
// (potwierdzone pełną listą kolumn) — to nie jest błąd pilota, to jest już
// istniejący stan produkcyjnego pipeline'u (NiFi też nie ma gdzie tego
// zapisać). ai_rodo/ai_act_risk też zostają puste — prompt ich nie
// generuje, są wypełniane później, ręcznie/przez trigger.
