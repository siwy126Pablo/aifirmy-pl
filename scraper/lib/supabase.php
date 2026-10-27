<?php
declare(strict_types=1);

// RLS, etap 2: klucz service_role (sekret GitHub SUPABASE_SERVICE_ROLE_KEY, mapowany
// w env: workflowów) omija RLS. Gdy sekretu brak lub jest pusty — fallback na anon,
// więc dodanie sekretu i włączenie RLS są osobnymi krokami. Klucz tylko w Actions,
// nigdy w repo ani w logach.
function sb_key(): string {
    $service = getenv('SUPABASE_SERVICE_ROLE_KEY');
    return ($service !== false && $service !== '') ? $service : (string) getenv('SUPABASE_ANON_KEY');
}

function sb_get(string $path): array {
    $ch = curl_init(getenv('SUPABASE_URL') . '/rest/v1/' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . sb_key(),
            'Authorization: Bearer ' . sb_key(),
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
            'apikey: ' . sb_key(),
            'Authorization: Bearer ' . sb_key(),
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
