<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/supabase.php';
require_once __DIR__ . '/lib/logging.php';
require_once __DIR__ . '/sources/yc_oss.php';

$source = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--source=')) {
        $source = substr($arg, strlen('--source='));
    }
}

if ($source === null) {
    fwrite(STDERR, "Użycie: php run.php --source=yc_oss\n");
    exit(1);
}

$runId = generate_uuid_v4();
scraper_log('info', 'Start uruchomienia (pilot YC-OSS)', [], $runId);

// Owinięte w try/catch, żeby również nieoczekiwany wyjątek spoza pętli
// per-firma (a nie tylko błędy pojedynczych rekordów, już obsłużone wewnątrz
// run_yc_oss_pilot()) trafił do activity_log, a nie zniknął wyłącznie w logu
// joba GitHub Actions.
try {
    switch ($source) {
        case 'yc_oss':
            $stats = run_yc_oss_pilot($runId);
            break;
        default:
            fwrite(STDERR, "Nieznane źródło: $source\n");
            exit(1);
    }
} catch (\Throwable $e) {
    scraper_log('error', 'Nieobsłużony wyjątek podczas uruchomienia: ' . $e->getMessage(), [
        'source' => $source,
    ], $runId);
    fwrite(STDERR, 'Nieobsłużony wyjątek: ' . $e->getMessage() . "\n");
    exit(1);
}

$summaryLevel = $stats['errors'] > 0 ? 'warning' : 'info';
scraper_log($summaryLevel, 'Zakończono uruchomienie (pilot YC-OSS)', $stats, $runId);
echo "Podsumowanie: " . json_encode($stats, JSON_UNESCAPED_UNICODE) . "\n";
