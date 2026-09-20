<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/supabase.php';
require_once __DIR__ . '/lib/logging.php';
require_once __DIR__ . '/sources/yc_oss.php';
require_once __DIR__ . '/sources/hacker_news.php';
require_once __DIR__ . '/sources/betalist.php';
require_once __DIR__ . '/sources/product_hunt.php';

$source = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--source=')) {
        $source = substr($arg, strlen('--source='));
    }
}

if ($source === null) {
    fwrite(STDERR, "Użycie: php run.php --source=yc_oss|hacker_news|betalist|product_hunt\n");
    exit(1);
}

// Źródło rozstrzygane przed pierwszym logiem, żeby już wpis "Start" trafił do
// activity_log z właściwym `source` i etykietą.
switch ($source) {
    case 'yc_oss':
        $runner    = 'run_yc_oss_pilot';
        $logSource = 'scraper:yc_ai_pilot';
        $label     = 'pilot YC-OSS';
        break;
    case 'hacker_news':
        $runner    = 'run_hacker_news';
        $logSource = 'scraper:hacker_news';
        $label     = 'Hacker News';
        break;
    case 'betalist':
        $runner    = 'run_betalist';
        $logSource = 'scraper:betalist';
        $label     = 'BetaList';
        break;
    case 'product_hunt':
        $runner    = 'run_product_hunt';
        $logSource = 'scraper:product_hunt';
        $label     = 'Product Hunt';
        break;
    default:
        fwrite(STDERR, "Nieznane źródło: $source\n");
        exit(1);
}

scraper_log_source($logSource);
$runId = generate_uuid_v4();
scraper_log('info', "Start uruchomienia ($label)", [], $runId);

// Owinięte w try/catch, żeby również nieoczekiwany wyjątek spoza pętli
// per-rekord (a nie tylko błędy pojedynczych rekordów, już obsłużone wewnątrz
// runnera) trafił do activity_log, a nie zniknął wyłącznie w logu
// joba GitHub Actions.
try {
    $stats = $runner($runId);
} catch (\Throwable $e) {
    scraper_log('error', 'Nieobsłużony wyjątek podczas uruchomienia: ' . $e->getMessage(), [
        'source' => $source,
    ], $runId);
    fwrite(STDERR, 'Nieobsłużony wyjątek: ' . $e->getMessage() . "\n");
    exit(1);
}

$summaryLevel = $stats['errors'] > 0 ? 'warning' : 'info';
scraper_log($summaryLevel, "Zakończono uruchomienie ($label)", $stats, $runId);
echo "Podsumowanie: " . json_encode($stats, JSON_UNESCAPED_UNICODE) . "\n";
