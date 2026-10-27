<?php
declare(strict_types=1);

/**
 * Endpoint zapisów panelu admina (RLS, etap 2).
 *
 * Do tej pory JS w przeglądarce pisał do Supabase REST bezpośrednio, kluczem anon
 * wstrzykniętym do HTML-a. Teraz przeglądarka woła tylko ten plik; klucz
 * (service_role) żyje wyłącznie po stronie serwera — patrz admin/supabase.php.
 *
 * Zabezpieczenia: sesja admina (jak verify_tool.php), token CSRF w nagłówku
 * X-CSRF-Token, tylko POST z JSON-em, whitelista operacji i pól z walidacją typów.
 * Zapisy, które nic nie zmieniły (0 wierszy), zwracają błąd zamiast cichego "ok".
 *
 * Operacje (pole "op"):
 *   tool_update      {id, fields{...}, mark_verified?}  — PATCH tools
 *   soft_delete      {table: tools|scrape_queue, id}    — status/stage = 'rejected'
 *   affiliate_active {id, active}                       — PATCH affiliate_links
 *
 * Składnia celowo zgodna z PHP 7.4 (wersja PHP na hostingu nie jest w repo).
 */

session_start();
require_once '/home/siwy126/domains/aifirmy.pl/private_html/config/db.php';
require_once __DIR__ . '/supabase.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_fail(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function api_uuid($v): ?string {
    return (is_string($v) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v)) ? $v : null;
}

// ---------- autoryzacja ----------

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    api_fail(401, 'Brak autoryzacji — zaloguj się ponownie.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_fail(405, 'Dozwolone tylko POST.');
}
$csrfSession = (string) ($_SESSION['csrf'] ?? '');
$csrfHeader  = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrfSession === '' || !hash_equals($csrfSession, $csrfHeader)) {
    api_fail(403, 'Nieprawidłowy token — odśwież stronę panelu.');
}

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in) || !isset($in['op']) || !is_string($in['op'])) {
    api_fail(400, 'Nieprawidłowe żądanie.');
}

// ---------- walidacja pól tools ----------

const TOOL_PRICING     = ['free', 'freemium', 'paid', 'open_source'];
const TOOL_AI_ACT_RISK = ['minimal', 'limited', 'high', 'unacceptable'];

/** Tekst: null/pusty → NULL (gdy pole opcjonalne), inaczej przycięty string do $max bajtów. */
function tool_text($v, int $max, bool $required): array {
    if ($v === null) return $required ? ['ok' => false] : ['ok' => true, 'value' => null];
    if (!is_string($v) || strlen($v) > $max) return ['ok' => false];
    $t = trim($v);
    if ($t === '') return $required ? ['ok' => false] : ['ok' => true, 'value' => null];
    return ['ok' => true, 'value' => $t];
}

/** @return array{ok:bool, value?:mixed} */
function tool_field(string $key, $v): array {
    switch ($key) {
        case 'name':            return tool_text($v, 400, true);
        case 'website_url':     return tool_text($v, 1000, true);
        case 'logo_url':        return tool_text($v, 2000, false);
        case 'description_pl':  return tool_text($v, 10000, false);
        case 'best_for_pl':     return tool_text($v, 2000, false);
        case 'category_id':
            if ($v === null || $v === '') return ['ok' => true, 'value' => null];
            $u = api_uuid($v);
            return $u === null ? ['ok' => false] : ['ok' => true, 'value' => $u];
        case 'pricing_model':
            return (is_string($v) && in_array($v, TOOL_PRICING, true)) ? ['ok' => true, 'value' => $v] : ['ok' => false];
        case 'ai_act_risk':
            if ($v === null || $v === '') return ['ok' => true, 'value' => null];
            return (is_string($v) && in_array($v, TOOL_AI_ACT_RISK, true)) ? ['ok' => true, 'value' => $v] : ['ok' => false];
        case 'rodo_compliant':
        case 'dpa_available':
        case 'eu_data_hosting':
        case 'has_pl_ui':
        case 'has_pl_support':
            return ($v === null || is_bool($v)) ? ['ok' => true, 'value' => $v] : ['ok' => false];
        default:
            return ['ok' => false];
    }
}

// ---------- operacje ----------

try {
    switch ($in['op']) {

        case 'tool_update':
            $id = api_uuid($in['id'] ?? null);
            if ($id === null) api_fail(400, 'Nieprawidłowe id narzędzia.');
            $fields = $in['fields'] ?? null;
            if (!is_array($fields)) api_fail(400, 'Brak pól do zapisu.');

            $patch = [];
            foreach ($fields as $key => $value) {
                $r = tool_field((string) $key, $value);
                if (!$r['ok']) api_fail(400, 'Niedozwolone lub nieprawidłowe pole: ' . $key);
                $patch[$key] = $r['value'];
            }

            $verifiedAt = null;
            if (!empty($in['mark_verified'])) {
                $verifiedAt = gmdate('Y-m-d\TH:i:s\Z');
                $patch['ai_verified_at'] = $verifiedAt;
            }
            if ($patch === []) api_fail(400, 'Brak pól do zapisu.');

            sb_patch('tools', $id, $patch);
            echo json_encode(['ok' => true, 'ai_verified_at' => $verifiedAt]);
            break;

        case 'soft_delete':
            $id = api_uuid($in['id'] ?? null);
            if ($id === null) api_fail(400, 'Nieprawidłowe id.');
            // Miękkie usunięcie: tools.status / scrape_queue.stage = 'rejected'
            $columns = ['tools' => 'status', 'scrape_queue' => 'stage'];
            $table = is_string($in['table'] ?? null) ? $in['table'] : '';
            if (!isset($columns[$table])) api_fail(400, 'Niedozwolona tabela.');

            sb_patch($table, $id, [$columns[$table] => 'rejected']);
            echo json_encode(['ok' => true]);
            break;

        case 'affiliate_active':
            $id = api_uuid($in['id'] ?? null);
            if ($id === null) api_fail(400, 'Nieprawidłowe id.');
            if (!isset($in['active']) || !is_bool($in['active'])) api_fail(400, 'Brak wartości active.');

            sb_patch('affiliate_links', $id, ['active' => $in['active']]);
            echo json_encode(['ok' => true]);
            break;

        default:
            api_fail(400, 'Nieznana operacja.');
    }
} catch (RuntimeException $e) {
    error_log('admin/api.php [' . $in['op'] . ']: ' . $e->getMessage());
    api_fail(502, $e->getMessage());
}
