<?php
declare(strict_types=1);

/**
 * Wspólna warstwa Supabase REST dla panelu admina (index.php, affiliate.php,
 * verify_tool.php, api.php). Wymaga wcześniejszego require db.php.
 *
 * KLUCZ SERWEROWY (RLS, etap 2):
 *   W private_html/config/db.php (poza repo) dodaj:
 *     define('SUPABASE_SERVICE_ROLE_KEY', 'eyJ...');   // Supabase → Settings → API → service_role
 *   Klucz service_role omija RLS, więc NIGDY nie trafia do HTML/JS ani do repo —
 *   przeglądarka pisze do bazy wyłącznie przez admin/api.php (sesja + token CSRF).
 *   Dopóki stałej nie ma, wszystko działa jak wcześniej na kluczu anon (fallback),
 *   żeby wdrożenie kodu i dodanie klucza mogły być osobnymi krokami.
 *
 * Zapisy NIE giną po cichu: sb_patch/sb_post/sb_delete rzucają RuntimeException
 * przy błędzie HTTP oraz — dla PATCH/DELETE — gdy żaden wiersz nie został
 * zmieniony (tak zachowuje się PostgREST po włączeniu RLS bez pasującej polityki:
 * 200 i zero wierszy, bez błędu).
 */

// Plik tylko do include — bezpośrednie wywołanie przez URL nic nie zwraca.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', 'https://szassqzvivdgvpkciyif.supabase.co');
}

if (!defined('SUPABASE_KEY')) {
    $sbServiceKey = defined('SUPABASE_SERVICE_ROLE_KEY') ? (string) SUPABASE_SERVICE_ROLE_KEY : '';
    define('SUPABASE_USING_SERVICE_ROLE', $sbServiceKey !== '');
    define('SUPABASE_KEY', $sbServiceKey !== '' ? $sbServiceKey : SUPABASE_ANON_KEY);
    unset($sbServiceKey);
}

/** Ostatni błąd odczytu (sb_get/sb_count) — panel pokazuje go w bannerze. */
$GLOBALS['sb_last_error'] = null;

/**
 * Niskopoziomowe wywołanie PostgREST.
 * @return array{code:int, body:string, error:string, headers:string[]}
 */
function sb_request(string $method, string $path, ?array $body = null, array $headers = []): array {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/' . $path);
    $respHeaders = [];
    $reqHeaders = array_merge([
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
    ], $headers);

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HEADERFUNCTION => function ($ch, $h) use (&$respHeaders) {
            $respHeaders[] = $h;
            return strlen($h);
        },
    ];
    if ($body !== null) {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new RuntimeException('Nie udało się zakodować danych do JSON.');
        }
        $opts[CURLOPT_POSTFIELDS] = $json;
        $reqHeaders[] = 'Content-Type: application/json';
    }
    $opts[CURLOPT_HTTPHEADER] = $reqHeaders;
    curl_setopt_array($ch, $opts);

    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code'    => $code,
        'body'    => $res === false ? '' : (string) $res,
        'error'   => $res === false ? ($err !== '' ? $err : 'brak odpowiedzi') : '',
        'headers' => $respHeaders,
    ];
}

/** Czytelny opis błędu z odpowiedzi PostgREST (bez zrzucania całego body). */
function sb_error_text(array $r): string {
    if ($r['error'] !== '') {
        return 'brak połączenia z Supabase (' . $r['error'] . ')';
    }
    $decoded = json_decode($r['body'], true);
    $msg = is_array($decoded) ? ($decoded['message'] ?? $decoded['hint'] ?? '') : '';
    return 'HTTP ' . $r['code'] . ($msg !== '' ? ': ' . $msg : '');
}

function sb_note_read_error(string $what, array $r): void {
    $GLOBALS['sb_last_error'] = $what . ' — ' . sb_error_text($r);
    error_log('admin/supabase.php: ' . $GLOBALS['sb_last_error']);
}

/** Odczyt. Przy błędzie zwraca [] i ustawia $GLOBALS['sb_last_error']. */
function sb_get(string $path): array {
    $r = sb_request('GET', $path);
    if ($r['error'] !== '' || $r['code'] >= 400) {
        sb_note_read_error('GET ' . strtok($path, '?'), $r);
        return [];
    }
    $decoded = json_decode($r['body'], true);
    return is_array($decoded) ? $decoded : [];
}

function sb_count(string $table, string $filter = ''): int {
    $r = sb_request('GET', $table . '?select=id&limit=1' . ($filter ? '&' . $filter : ''), null, ['Prefer: count=exact']);
    if ($r['error'] !== '' || $r['code'] >= 400) {
        sb_note_read_error('COUNT ' . $table, $r);
        return 0;
    }
    foreach ($r['headers'] as $h) {
        if (preg_match('/^Content-Range:\s*[^\/]*\/(\d+)/i', $h, $m)) return (int) $m[1];
    }
    return 0;
}

/** Liczy wiersze z odpowiedzi return=representation; rzuca przy błędzie lub 0 wierszy. */
function sb_expect_rows(string $what, array $r): int {
    if ($r['error'] !== '' || $r['code'] >= 400) {
        throw new RuntimeException($what . ' nie powiodło się — ' . sb_error_text($r));
    }
    $rows = json_decode($r['body'], true);
    $n = is_array($rows) ? count($rows) : 0;
    if ($n === 0) {
        throw new RuntimeException($what . ' nie zmieniło żadnego wiersza (brak rekordu albo odmowa po stronie RLS).');
    }
    return $n;
}

function sb_patch(string $table, string $id, array $data): int {
    $r = sb_request('PATCH', $table . '?id=eq.' . rawurlencode($id) . '&select=id', $data, ['Prefer: return=representation']);
    return sb_expect_rows('Zapis w ' . $table, $r);
}

function sb_post(string $table, array $data): void {
    $r = sb_request('POST', $table, $data, ['Prefer: return=minimal']);
    if ($r['error'] !== '' || $r['code'] >= 400) {
        throw new RuntimeException('Dodanie do ' . $table . ' nie powiodło się — ' . sb_error_text($r));
    }
}

function sb_delete(string $table, string $id): int {
    $r = sb_request('DELETE', $table . '?id=eq.' . rawurlencode($id) . '&select=id', null, ['Prefer: return=representation']);
    return sb_expect_rows('Usunięcie z ' . $table, $r);
}
