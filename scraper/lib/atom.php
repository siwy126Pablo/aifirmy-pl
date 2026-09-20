<?php
declare(strict_types=1);

require_once __DIR__ . '/http.php';
require_once __DIR__ . '/filters.php';
require_once __DIR__ . '/pipeline.php';
require_once __DIR__ . '/logging.php';

// Parsuje feed Atom do listy wpisów: title, url (rel="alternate"), summary
// (tekst bez HTML), published (unix timestamp albo null), raw_published (string).
// Zwraca null, gdy XML jest niepoprawny — odróżnione od [] (poprawny, pusty feed).
function parse_atom_entries(string $xml): ?array {
    $prev = libxml_use_internal_errors(true);
    // LIBXML_NONET: żadnych zewnętrznych zasobów; PHP 8 i tak nie ładuje
    // zewnętrznych encji domyślnie.
    $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if ($feed === false) {
        return null;
    }

    $entries = [];
    foreach ($feed->entry as $e) {
        $title = trim((string)$e->title);

        $url = '';
        foreach ($e->link as $link) {
            $rel = (string)$link['rel'];
            if ($rel === '' || $rel === 'alternate') {
                $url = trim((string)$link['href']);
                break;
            }
        }
        if ($title === '' || $url === '') {
            continue;
        }

        $html = (string)$e->content !== '' ? (string)$e->content : (string)$e->summary;
        $summary = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if (mb_strlen($summary) > 1000) {
            $summary = mb_substr($summary, 0, 1000);
        }

        $rawPublished = (string)$e->published !== '' ? (string)$e->published : (string)$e->updated;
        $ts = $rawPublished !== '' ? strtotime($rawPublished) : false;

        $entries[] = [
            'title'         => $title,
            'url'           => $url,
            'summary'       => $summary,
            'published'     => $ts === false ? null : $ts,
            'raw_published' => $rawPublished,
        ];
    }
    return $entries;
}

// Wspólny przebieg dla źródeł opartych o feed Atom (BetaList, Product Hunt).
// Normalizacja 1:1 z UpdateAttribute "(BetaList normalize)" w NiFi, używanego
// przez obie gałęzie: nazwa = tytuł przed pierwszym ' – ' (en dash U+2013),
// URL = część przed '?' (usuwa utm_*). Filtr słów kluczowych działa na tak
// obciętej NAZWIE, nie na pełnym tytule — tak jest w NiFi (hn_title).
function run_atom_feed_source(string $runId, string $feedUrl, string $sourceName): array {
    $stats = [
        'fetched'    => 0,
        'filtered'   => 0,
        'duplicates' => 0,
        'inserted'   => 0,
        'rejected'   => 0,
        'errors'     => 0,
    ];

    try {
        $xml = http_get($feedUrl, 15);
    } catch (\Throwable $e) {
        $stats['errors']++;
        scraper_log('error', 'Nie udało się pobrać feedu: ' . $e->getMessage(), ['feed' => $feedUrl], $runId);
        return $stats;
    }

    $entries = parse_atom_entries($xml);
    if ($entries === null) {
        $stats['errors']++;
        scraper_log('error', 'Feed zwrócił niepoprawny XML', ['feed' => $feedUrl], $runId);
        return $stats;
    }

    $stats['fetched'] = count($entries);
    $cutoff = time() - CANDIDATE_WINDOW_SECONDS;

    foreach ($entries as $entry) {
        if ($entry['published'] === null || $entry['published'] < $cutoff) {
            $stats['filtered']++;
            continue;
        }

        $name = trim(strstr($entry['title'], ' – ', true) ?: $entry['title']);
        $url  = strstr($entry['url'], '?', true) ?: $entry['url'];

        if (!matches_keyword_filter($name)) {
            $stats['filtered']++;
            continue;
        }

        enqueue_candidate($name, $url, $sourceName, $entry, $entry['summary'], $runId, $stats);
    }

    return $stats;
}
