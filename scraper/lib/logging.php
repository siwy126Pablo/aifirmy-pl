<?php
declare(strict_types=1);

require_once __DIR__ . '/supabase.php';

function generate_uuid_v4(): string {
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

function scraper_log(string $level, string $message, array $context = [], ?string $runId = null): void {
    try {
        sb_post('activity_log', [
            'source'  => 'scraper:yc_ai_pilot',
            'level'   => $level,
            'message' => $message,
            'context' => $context,
            'run_id'  => $runId,
        ]);
    } catch (\Throwable $e) {
        // Świadomie tylko STDERR — brak lokalnego pliku logu w GH Actions,
        // ten strumień trafia do logu joba w Actions UI.
        fwrite(STDERR, "scraper_log: nie udało się zapisać do activity_log: " . $e->getMessage() . "\n");
    }
}
