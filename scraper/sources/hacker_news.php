<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/filters.php';
require_once __DIR__ . '/../lib/pipeline.php';
require_once __DIR__ . '/../lib/logging.php';

const HN_API = 'https://hacker-news.firebaseio.com/v0';

// Dokładna lista z RouteOnAttribute "przepusc" w NiFi. Dopasowanie jako zwykły
// substring URL-a (jak hn_url:contains() w NiFi), nie po hoście — więc np.
// "dev.to" pasuje też do "dev.tools", a "bbc.com" do "abbc.com".
const HN_BLOCKED_DOMAINS = [
    'github.com', 'medium.com', 'substack.com', 'dev.to', 'gist.github',
    'twitter.com', 'wsj.com', 'computerworld.com', 'reuters.com', 'cnbc.com',
    'theguardian.com', 'nytimes.com', 'bbc.com', 'kennethpayne.uk',
    'buttondown.com', 'reclaimthenet.org',
];

// Po ilu kolejnych nieudanych pobraniach itemu przerywamy (HN nie działa —
// nie ma sensu czekać 500 x 5 s i zaśmiecać activity_log).
const HN_MAX_CONSECUTIVE_FETCH_ERRORS = 10;

// Filtry w kolejności z gałęzi HN w NiFi: typ+url → domeny → data → Show/Launch
// HN lub batch YC → słowa kluczowe. true = item jest kandydatem.
function hn_item_passes_filters(array $item, int $now): bool {
    // Wstępny: tylko story z url (Ask HN i dyskusje go nie mają)
    if (($item['type'] ?? null) !== 'story') {
        return false;
    }
    $url   = $item['url'] ?? null;
    $title = $item['title'] ?? null;
    if (!is_string($url) || $url === '' || !is_string($title) || $title === '') {
        return false;
    }

    $urlLower = strtolower($url);
    foreach (HN_BLOCKED_DOMAINS as $domain) {
        if (str_contains($urlLower, $domain)) {
            return false;
        }
    }

    $time = $item['time'] ?? null;
    if (!is_int($time) || $time < $now - CANDIDATE_WINDOW_SECONDS) {
        return false;
    }

    $titleLower = strtolower($title);
    $isShowOrLaunch = str_starts_with($titleLower, 'show hn:')
        || str_starts_with($titleLower, 'launch hn:')
        || preg_match('/\(yc [a-z][0-9]{2}\)/i', $title) === 1;
    if (!$isShowOrLaunch) {
        return false;
    }

    return matches_keyword_filter($title);
}

function run_hacker_news(string $runId): array {
    $stats = [
        'fetched'    => 0,
        'filtered'   => 0,
        'duplicates' => 0,
        'inserted'   => 0,
        'rejected'   => 0,
        'errors'     => 0,
    ];

    try {
        $ids = json_decode(http_get(HN_API . '/topstories.json', 15), true);
    } catch (\Throwable $e) {
        $stats['errors']++;
        scraper_log('error', 'Nie udało się pobrać topstories HN: ' . $e->getMessage(), [], $runId);
        return $stats;
    }
    if (!is_array($ids)) {
        $stats['errors']++;
        scraper_log('error', 'topstories HN zwróciło niepoprawny JSON', [], $runId);
        return $stats;
    }

    $now = time();
    $consecutiveFetchErrors = 0;

    foreach ($ids as $id) {
        if (!is_int($id)) {
            continue;
        }
        $stats['fetched']++;

        try {
            $item = json_decode(http_get(HN_API . '/item/' . $id . '.json', 5), true);
            $consecutiveFetchErrors = 0;
        } catch (\Throwable $e) {
            $stats['errors']++;
            $consecutiveFetchErrors++;
            scraper_log('error', 'Błąd pobrania itemu HN: ' . $e->getMessage(), ['hn_id' => $id], $runId);
            if ($consecutiveFetchErrors >= HN_MAX_CONSECUTIVE_FETCH_ERRORS) {
                scraper_log('error', 'Przerwano: ' . HN_MAX_CONSECUTIVE_FETCH_ERRORS . ' kolejnych błędów pobrania itemów HN', [], $runId);
                break;
            }
            continue;
        }

        // json "null" (item usunięty) też ląduje tutaj — nie jest błędem
        if (!is_array($item) || !hn_item_passes_filters($item, $now)) {
            $stats['filtered']++;
            continue;
        }

        enqueue_candidate($item['title'], $item['url'], 'hacker_news', $item, null, $runId, $stats);
    }

    return $stats;
}
