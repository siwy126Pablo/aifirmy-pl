<?php
declare(strict_types=1);

function sb_get(string $path): array {
    $ch = curl_init(getenv('SUPABASE_URL') . '/rest/v1/' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . getenv('SUPABASE_ANON_KEY'),
            'Authorization: Bearer ' . getenv('SUPABASE_ANON_KEY'),
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) {
        throw new RuntimeException('sb_get curl error: ' . $err);
    }
    if ($httpCode >= 400) {
        throw new RuntimeException('sb_get HTTP ' . $httpCode . ': ' . $res);
    }
    return json_decode($res, true) ?? [];
}

function sb_post(string $table, array $data): void {
    $ch = curl_init(getenv('SUPABASE_URL') . '/rest/v1/' . $table);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . getenv('SUPABASE_ANON_KEY'),
            'Authorization: Bearer ' . getenv('SUPABASE_ANON_KEY'),
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) {
        throw new RuntimeException('sb_post curl error: ' . $err);
    }
    if ($httpCode >= 400) {
        throw new RuntimeException('sb_post HTTP ' . $httpCode . ' do ' . $table . ': ' . $res);
    }
}
