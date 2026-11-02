<?php
/**
 * Logika statystyk źródeł scrapowania (admin/stats.php) — czyste funkcje, bez I/O
 * i bez zależności od db.php, żeby dało się je testować na gołych tablicach.
 *
 * Dane: linie "Zakończono uruchomienie (…)" i "Start uruchomienia (…)" z activity_log
 * (scraper/lib/logging.php, source = 'scraper:<źródło>'). Licznik w context:
 * fetched, filtered, duplicates, inserted, rejected, errors. YC-OSS nie ma "filtered",
 * tylko "in_window" (liczba rekordów z okna świeżości), więc filtered = fetched - in_window.
 *
 * Składnia zgodna z PHP 7.4 (jak session_init.php).
 */

// Plik tylko do include — bezpośrednie wywołanie przez URL nic nie zwraca.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

const STATS_TZ = 'Europe/Warsaw';

/** Po ilu sekundach od ostatniego zakończonego przebiegu źródło uznajemy za "stoi". */
const STATS_STALE_SECONDS = 129600; // 36 h (GitHub Actions bywa spóźniony o kilka godzin)

/** Okna wybierane w panelu (dni). Dane pobieramy zawsze dla największego. */
const STATS_WINDOWS = [7, 14, 30, 60];

/** Klucz = activity_log.source. 'tools' = wartości tools.source przypisane do źródła. */
const STATS_SOURCES = [
    'scraper:yc_ai_pilot' => ['label' => 'YC-OSS',       'tools' => ['yc_ai_pilot', 'yc_ai']],
    'scraper:hacker_news' => ['label' => 'Hacker News',  'tools' => ['hacker_news']],
    'scraper:betalist'    => ['label' => 'BetaList',     'tools' => ['betalist']],
    'scraper:product_hunt' => ['label' => 'Product Hunt', 'tools' => ['product_hunt']],
];

function stats_ctx($context): array {
    if (is_string($context)) {
        $context = json_decode($context, true);
    }
    return is_array($context) ? $context : [];
}

/** Liczniki jednego przebiegu, ujednolicone między źródłami. */
function stats_run_numbers(array $c): array {
    $fetched = (int) ($c['fetched'] ?? 0);
    if (isset($c['filtered'])) {
        $filtered = (int) $c['filtered'];
    } elseif (isset($c['in_window'])) {
        $filtered = max(0, $fetched - (int) $c['in_window']);
    } else {
        $filtered = 0;
    }
    return [
        'fetched'    => $fetched,
        'filtered'   => $filtered,
        'duplicates' => (int) ($c['duplicates'] ?? 0),
        'inserted'   => (int) ($c['inserted'] ?? 0),
        'rejected'   => (int) ($c['rejected'] ?? 0),
        'errors'     => (int) ($c['errors'] ?? 0),
    ];
}

function stats_zero(): array {
    return ['runs' => 0, 'fetched' => 0, 'filtered' => 0, 'duplicates' => 0, 'inserted' => 0, 'rejected' => 0, 'errors' => 0];
}

/** Dni (Y-m-d, czas warszawski) od najstarszego do dzisiejszego, $days sztuk. */
function stats_days(int $now, int $days): array {
    $d = new DateTime('@' . $now);
    $d->setTimezone(new DateTimeZone(STATS_TZ));
    $d->setTime(12, 0, 0); // południe omija krawędzie zmiany czasu
    $out = [];
    for ($i = 0; $i < $days; $i++) {
        array_unshift($out, $d->format('Y-m-d'));
        $d->modify('-1 day');
    }
    return $out;
}

function stats_day_of(int $ts): string {
    $d = new DateTime('@' . $ts);
    $d->setTimezone(new DateTimeZone(STATS_TZ));
    return $d->format('Y-m-d');
}

/**
 * @param array $runRows   wiersze activity_log (created_at, source, message, context), dowolna kolejność
 * @param array $startRows wiersze "Start uruchomienia" (created_at, source, message)
 * @return array{days:string[], sources:array}
 */
function stats_aggregate(array $runRows, array $startRows, int $now, int $days): array {
    $dayList = stats_days($now, $days);
    $inWindow = array_flip($dayList);

    $sources = [];
    foreach (STATS_SOURCES as $key => $meta) {
        $perDay = [];
        foreach ($dayList as $day) {
            $perDay[$day] = stats_zero();
        }
        $sources[$key] = [
            'key'           => $key,
            'label'         => $meta['label'],
            'perDay'        => $perDay,
            'totals'        => stats_zero(),
            'days_with_new' => 0,
            'avg_new'       => 0.0,
            'last_end'      => null,
            'last_end_errs' => 0,
            'last_start'    => null,
        ];
    }

    foreach ($runRows as $row) {
        $src = (string) ($row['source'] ?? '');
        if (!isset($sources[$src]) || strpos((string) ($row['message'] ?? ''), 'Zako') !== 0) {
            continue;
        }
        $c = stats_ctx($row['context'] ?? null);
        if (!array_key_exists('fetched', $c)) {
            continue; // inna linia zaczynająca się od "Zako…", nie podsumowanie przebiegu
        }
        $ts = strtotime((string) ($row['created_at'] ?? ''));
        if ($ts === false) {
            continue;
        }
        $n = stats_run_numbers($c);

        if ($sources[$src]['last_end'] === null || $ts > $sources[$src]['last_end']) {
            $sources[$src]['last_end'] = $ts;
            $sources[$src]['last_end_errs'] = $n['errors'];
        }

        $day = stats_day_of($ts);
        if (!isset($inWindow[$day])) {
            continue;
        }
        $sources[$src]['perDay'][$day]['runs']++;
        $sources[$src]['totals']['runs']++;
        foreach (['fetched', 'filtered', 'duplicates', 'inserted', 'rejected', 'errors'] as $f) {
            $sources[$src]['perDay'][$day][$f] += $n[$f];
            $sources[$src]['totals'][$f] += $n[$f];
        }
    }

    foreach ($startRows as $row) {
        $src = (string) ($row['source'] ?? '');
        if (!isset($sources[$src]) || strpos((string) ($row['message'] ?? ''), 'Start') !== 0) {
            continue;
        }
        $ts = strtotime((string) ($row['created_at'] ?? ''));
        if ($ts !== false && ($sources[$src]['last_start'] === null || $ts > $sources[$src]['last_start'])) {
            $sources[$src]['last_start'] = $ts;
        }
    }

    foreach ($sources as $key => $s) {
        $withNew = 0;
        foreach ($s['perDay'] as $d) {
            if ($d['inserted'] > 0) {
                $withNew++;
            }
        }
        $sources[$key]['days_with_new'] = $withNew;
        $sources[$key]['avg_new'] = $days > 0 ? $s['totals']['inserted'] / $days : 0.0;
        $sources[$key]['status'] = stats_status($sources[$key], $now);
    }

    return ['days' => $dayList, 'sources' => $sources];
}

/**
 * Stan źródła: ['ok'|'warn', tekst]. Ikona i opis zawsze razem — kolor sam niczego nie niesie.
 * @return array{0:string,1:string}
 */
function stats_status(array $s, int $now): array {
    if ($s['last_end'] === null) {
        return ['warn', '⚠ brak przebiegów w ostatnich 60 dniach'];
    }
    $age = $now - $s['last_end'];
    if ($age > STATS_STALE_SECONDS) {
        return ['warn', '⚠ ostatni przebieg ' . (int) floor($age / 3600) . ' h temu'];
    }
    if ($s['last_start'] !== null && $s['last_start'] > $s['last_end'] + 900) {
        return ['warn', '⚠ ostatni start bez zakończenia'];
    }
    if ($s['last_end_errs'] > 0) {
        return ['warn', '⚠ błędy w ostatnim przebiegu (' . $s['last_end_errs'] . ')'];
    }
    $d = new DateTime('@' . $s['last_end']);
    $d->setTimezone(new DateTimeZone(STATS_TZ));
    return ['ok', '✓ OK, ' . $d->format('d.m H:i')];
}

/**
 * Liczba zatwierdzonych narzędzi wg źródła (tools.source). Wartości spoza
 * STATS_SOURCES: 'manual' → "Ręcznie", reszta → "Inne".
 * @param array $toolRows wiersze tools z samym polem source
 * @return array<string,int> etykieta => liczba, w kolejności STATS_SOURCES, potem Ręcznie, Inne
 */
function stats_catalog_counts(array $toolRows): array {
    $byTool = [];
    foreach (STATS_SOURCES as $meta) {
        foreach ($meta['tools'] as $t) {
            $byTool[$t] = $meta['label'];
        }
    }
    $out = [];
    foreach (STATS_SOURCES as $meta) {
        $out[$meta['label']] = 0;
    }
    $out['Ręcznie'] = 0;
    $out['Inne'] = 0;
    foreach ($toolRows as $row) {
        $src = (string) ($row['source'] ?? '');
        if (isset($byTool[$src])) {
            $out[$byTool[$src]]++;
        } elseif ($src === 'manual') {
            $out['Ręcznie']++;
        } else {
            $out['Inne']++;
        }
    }
    return $out;
}

/** Dane dla wykresów JS (stats.php osadza je jako JSON). */
function stats_chart_payload(array $agg, int $days): array {
    $series = [];
    foreach ($agg['sources'] as $s) {
        $cols = ['runs' => [], 'inserted' => [], 'fetched' => [], 'filtered' => [], 'duplicates' => [], 'rejected' => [], 'errors' => []];
        foreach ($agg['days'] as $day) {
            foreach ($cols as $k => $_) {
                $cols[$k][] = $s['perDay'][$day][$k];
            }
        }
        $series[] = ['key' => $s['key'], 'label' => $s['label'], 'totals' => $s['totals']] + $cols;
    }
    return ['days' => $agg['days'], 'windowDays' => $days, 'series' => $series];
}
