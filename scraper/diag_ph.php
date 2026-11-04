<?php
declare(strict_types=1);

// TYMCZASOWE (04.11.2026): diagnostyka 403 na producthunt.com/r/p/ID z adresów GitHub Actions.
// Do usunięcia razem z .github/workflows/diag-producthunt.yml po zebraniu wyników.
// Nic nie zapisuje do scrape_queue; wyniki idą do activity_log (source scraper:diag_producthunt).
// Wysyła ten sam User-Agent co scraper (nie udaje przeglądarki).

require_once __DIR__ . '/lib/supabase.php';
require_once __DIR__ . '/lib/logging.php';
require_once __DIR__ . '/lib/atom.php';

const DIAG_UA = 'Mozilla/5.0 (compatible; aifirmy-scraper/1.0; +https://aifirmy.pl)';

// Jedno żądanie: kod, wybrane nagłówki odpowiedzi, długość ciała i (opcjonalnie) ciało.
function diag_request(string $url, bool $follow): array {
    $headers = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => DIAG_UA,
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
            $p = explode(':', $line, 2);
            if (count($p) === 2) {
                $headers[strtolower(trim($p[0]))] = trim($p[1]);
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    $pick = [];
    foreach (['server', 'cf-ray', 'cf-mitigated', 'content-type', 'location', 'retry-after'] as $k) {
        if (isset($headers[$k])) {
            $pick[$k] = $headers[$k];
        }
    }
    return [
        'code'    => $code,
        'err'     => $err !== '' ? $err : null,
        'final'   => $final,
        'headers' => $pick,
        'len'     => is_string($body) ? strlen($body) : null,
        'body'    => is_string($body) ? $body : '',
    ];
}

scraper_log_source('scraper:diag_producthunt');
$runId = generate_uuid_v4();
scraper_log('info', 'Start diagnostyki PH', ['runner' => getenv('RUNNER_NAME') ?: null], $runId);

// 1) robots.txt — tylko reguły dotyczące /r/ i /products oraz grupy User-agent
$robots = diag_request('https://www.producthunt.com/robots.txt', true);
$lines = [];
foreach (preg_split('/\R/', $robots['body']) ?: [] as $l) {
    if (preg_match('~^(User-agent|Disallow|Allow|Crawl-delay)\b.*(\*|aifirmy|/r/|/r$|/products|Crawl)~i', $l)
        || preg_match('~^User-agent~i', $l)) {
        $lines[] = trim($l);
    }
}
scraper_log('info', 'PH robots.txt', [
    'code' => $robots['code'], 'len' => $robots['len'], 'lines' => array_slice($lines, 0, 40),
], $runId);

// 2) feed
$feed = diag_request('https://www.producthunt.com/feed', true);
scraper_log('info', 'PH feed', ['code' => $feed['code'], 'len' => $feed['len'], 'headers' => $feed['headers']], $runId);
$entries = parse_atom_entries($feed['body']) ?? [];

// 3) 3 wpisy: obecny sposób (/r/p/ID bez podążania) i strona produktu
$n = 0;
foreach ($entries as $e) {
    if ($e['redirect_url'] === null) {
        continue;
    }
    if (++$n > 3) {
        break;
    }
    $r = diag_request($e['redirect_url'], false);
    $p = diag_request($e['url'], true);

    // Na stronie produktu (jeśli 200): ślady adresu strony produktu — tylko liczby i krótkie wycinki.
    $hints = [];
    if ($p['code'] === 200) {
        foreach (['~"websiteUrl"\s*:\s*"([^"]{1,200})"~', '~"website"\s*:\s*"([^"]{1,200})"~', '~href="([^"]*/r/p/\d+[^"]{0,80})"~'] as $re) {
            if (preg_match($re, $p['body'], $m)) {
                $hints[] = $m[0] === $m[1] ? $m[0] : substr($m[0], 0, 160);
            }
        }
        $hints = array_slice($hints, 0, 3);
    }

    scraper_log('info', 'PH probe: ' . $e['title'], [
        'listing_url'  => $e['url'],
        'redirect_url' => $e['redirect_url'],
        'r_p'          => ['code' => $r['code'], 'err' => $r['err'], 'headers' => $r['headers'], 'len' => $r['len']],
        'product_page' => ['code' => $p['code'], 'err' => $p['err'], 'final' => $p['final'], 'headers' => $p['headers'], 'len' => $p['len'], 'hints' => $hints],
    ], $runId);
    usleep(500000);
}

scraper_log('info', 'Koniec diagnostyki PH', ['probed' => min($n, 3)], $runId);
echo "Gotowe — wyniki w activity_log (source scraper:diag_producthunt, run_id $runId)\n";
