<?php
declare(strict_types=1);

// Jednorazowy skrypt weryfikacyjny: sprawdza website_url wszystkich narzędzi
// status='approved' w tabeli tools. Tylko zbiera dane (kod HTTP, finalny URL
// po przekierowaniach, <title>) do RĘCZNEGO przeglądu — nie ocenia, czy
// treść strony faktycznie pasuje do narzędzia (to wymaga osądu, nie regexa),
// i nie modyfikuje/usuwa żadnych wierszy w tools.
//
// Użycie (zmienne środowiskowe jak w scraper/run.php — patrz
// .github/workflows/scrape-yc-pilot.yml dla nazw sekretów):
//   PowerShell:
//     $env:SUPABASE_URL="https://<project>.supabase.co"
//     $env:SUPABASE_ANON_KEY="..."
//     php scraper/url_audit.php
//
// Wynik: CSV w scraper/reports/url_audit_<timestamp>.csv + wpisy
// w activity_log (source='url_audit') dla każdego flagowanego wpisu oraz
// jedno podsumowanie na końcu.

require_once __DIR__ . '/lib/supabase.php';

function url_audit_uuid_v4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    $hex = bin2hex($data);
    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function url_audit_log(string $level, string $message, array $context, string $runId): void {
    try {
        sb_post('activity_log', [
            'source'  => 'url_audit',
            'level'   => $level,
            'message' => $message,
            'context' => $context,
            'run_id'  => $runId,
        ]);
    } catch (\Throwable $e) {
        fwrite(STDERR, "url_audit_log: nie udało się zapisać do activity_log: " . $e->getMessage() . "\n");
    }
}

// www./bez www. i http/https to normalny wariant tego samego hosta — nie ma
// być flagowany jako podejrzana zmiana hosta.
function url_audit_normalize_host(?string $url): ?string {
    if ($url === null || trim($url) === '') {
        return null;
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        // website_url bez schematu (nie powinno się zdarzyć przy poprawnych
        // danych, ale nie zakładamy tego bezkrytycznie) — spróbuj dodać https://
        $host = parse_url('https://' . ltrim($url, '/'), PHP_URL_HOST);
    }
    if (!$host) {
        return null;
    }
    $host = strtolower($host);
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }
    return $host;
}

function url_audit_extract_title(string $html): ?string {
    if (!preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        return null;
    }
    $title = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = trim(preg_replace('/\s+/', ' ', $title));
    if ($title === '') {
        return null;
    }
    return mb_substr($title, 0, 300);
}

/**
 * @return array{http_code:int, final_url:string, title:?string, error:?string}
 */
function url_audit_fetch(string $url): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => '',
        // Zwykła przeglądarka, żeby uniknąć 403 od WAF-ów blokujących boty
        // (jak przy Cloudflare Workers IO w pilocie YC-OSS).
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: pl,en;q=0.8',
        ],
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if ($body === false) {
        $reason = $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : ('connection_error: ' . $error);
        return ['http_code' => $httpCode, 'final_url' => $finalUrl ?: $url, 'title' => null, 'error' => $reason];
    }

    return [
        'http_code' => $httpCode,
        'final_url' => $finalUrl ?: $url,
        'title'     => url_audit_extract_title($body),
        'error'     => null,
    ];
}

$runId = url_audit_uuid_v4();
url_audit_log('info', 'Start audytu website_url dla tools (status=approved)', [], $runId);

try {
    $tools = sb_get('tools?select=id,slug,name,website_url&status=eq.approved&order=name.asc&limit=5000');
} catch (\Throwable $e) {
    url_audit_log('error', 'Nie udało się pobrać listy tools z Supabase: ' . $e->getMessage(), [], $runId);
    fwrite(STDERR, 'Błąd pobierania tools: ' . $e->getMessage() . "\n");
    exit(1);
}

$reportsDir = __DIR__ . '/reports';
if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0777, true);
}
$csvPath = $reportsDir . '/url_audit_' . date('Ymd_His') . '.csv';
$csv = fopen($csvPath, 'w');
fputcsv($csv, ['id', 'slug', 'name', 'website_url', 'http_code', 'final_url', 'host_mismatch', 'title', 'flag_reason', 'error']);

$stats = ['checked' => 0, 'flagged' => 0, 'skipped_no_url' => 0];

foreach ($tools as $tool) {
    $websiteUrl = trim((string) ($tool['website_url'] ?? ''));
    $id = $tool['id'] ?? '';
    $slug = $tool['slug'] ?? '';
    $name = $tool['name'] ?? '';

    if ($websiteUrl === '') {
        $stats['skipped_no_url']++;
        $stats['flagged']++;
        $reasons = ['brak website_url'];
        fputcsv($csv, [$id, $slug, $name, '', '', '', '', '', implode('; ', $reasons), '']);
        url_audit_log('warning', 'Brak website_url dla zatwierdzonego narzędzia', [
            'tool_id' => $id,
            'slug'    => $slug,
            'name'    => $name,
        ], $runId);
        continue;
    }

    $stats['checked']++;
    $result = url_audit_fetch($websiteUrl);

    $originalHost = url_audit_normalize_host($websiteUrl);
    $finalHost = url_audit_normalize_host($result['final_url']);
    $hostMismatch = ($originalHost !== null && $finalHost !== null && $originalHost !== $finalHost);

    $reasons = [];
    if ($result['error'] !== null) {
        $reasons[] = $result['error'] === 'timeout' ? 'timeout' : 'błąd połączenia';
    } elseif ($result['http_code'] !== 200) {
        $reasons[] = 'HTTP ' . $result['http_code'];
    }
    if ($hostMismatch) {
        $reasons[] = 'zmiana hosta: ' . $originalHost . ' -> ' . $finalHost;
    }

    $flagged = !empty($reasons);
    if ($flagged) {
        $stats['flagged']++;
    }

    fputcsv($csv, [
        $id,
        $slug,
        $name,
        $websiteUrl,
        $result['http_code'] ?: '',
        $result['final_url'],
        $hostMismatch ? 'tak' : 'nie',
        $result['title'] ?? '',
        implode('; ', $reasons),
        $result['error'] ?? '',
    ]);

    if ($flagged) {
        url_audit_log('warning', 'website_url wymaga ręcznego przeglądu: ' . implode('; ', $reasons), [
            'tool_id'       => $id,
            'slug'          => $slug,
            'name'          => $name,
            'website_url'   => $websiteUrl,
            'http_code'     => $result['http_code'],
            'final_url'     => $result['final_url'],
            'host_mismatch' => $hostMismatch,
            'error'         => $result['error'],
        ], $runId);
    }

    // Krótka przerwa, żeby nie walić ~260 requestami pod rząd w te same hosty
    // bez żadnego odstępu.
    usleep(200_000);
}

fclose($csv);

$summaryLevel = $stats['flagged'] > 0 ? 'warning' : 'info';
url_audit_log($summaryLevel, 'Zakończono audyt website_url', $stats + ['csv' => basename($csvPath)], $runId);

echo "Podsumowanie: " . json_encode($stats, JSON_UNESCAPED_UNICODE) . "\n";
echo "Raport CSV: $csvPath\n";
