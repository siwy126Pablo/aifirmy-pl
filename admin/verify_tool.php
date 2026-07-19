<?php
declare(strict_types=1);

/**
 * Weryfikacja narzędzia przez AI — live fetch strony + OpenAI, zwraca porównanie
 * starych i zasugerowanych danych. Nie zapisuje niczego — PATCH robi frontend
 * (admin/index.php) bezpośrednio na Supabase REST API dla zaznaczonych pól.
 *
 * Dodaj w private_html/config/openai.php:
 *   define('OPENAI_API_KEY', 'sk-...');
 *
 * Debug: ścieżka pliku error_log na Cyberfolks/LiteSpeed nie jest nigdzie
 * zdefiniowana w tym repo (brak php.ini/.user.ini/ini_set) — zależy od
 * konfiguracji hostingu. Dlatego oprócz error_log() piszemy też jawnie do
 * private_html/logs/verify_debug.log (poza webrootem, jak config/).
 */

$verify_debug_start = microtime(true);
error_log("verify_tool: start " . microtime(true));

function verify_write_log(string $line): void {
    error_log($line);

    $logDir = '/home/siwy126/domains/aifirmy.pl/private_html/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logDir . '/verify_debug.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function verify_debug(string $label): void {
    global $verify_debug_start;
    $elapsedMs = round((microtime(true) - $verify_debug_start) * 1000);
    verify_write_log(sprintf('verify_tool: %s (+%dms od startu, %s)', $label, $elapsedMs, microtime(true)));
}

// Siatka bezpieczeństwa na fatal errory, których zwykły try/catch NIE łapie
// (np. wyczerpanie pamięci, przekroczenie limitu czasu, błędy parsowania).
// try/catch niżej łapie TypeError/Error przy przetwarzaniu odpowiedzi AI —
// to tutaj jest na wypadek, gdyby coś umknęło i try/catch.
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err === null) {
        return;
    }
    if (!in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }
    verify_write_log(sprintf(
        'verify_tool FATAL (shutdown/error_get_last): [type=%d] %s w %s:%d',
        $err['type'],
        $err['message'],
        $err['file'],
        $err['line']
    ));
});

// Typowy limit czasu wykonania na hostingu współdzielonym to 30s — kończymy
// wcześniej, żeby zdążyć zwrócić czytelny błąd JSON zamiast 502 od LiteSpeed.
set_time_limit(25);

session_start();
require_once '/home/siwy126/domains/aifirmy.pl/private_html/config/db.php';
require_once '/home/siwy126/domains/aifirmy.pl/private_html/config/openai.php';

define('SUPABASE_URL', 'https://szassqzvivdgvpkciyif.supabase.co');
define('SUPABASE_KEY', SUPABASE_ANON_KEY);

header('Content-Type: application/json; charset=utf-8');

$logged_in = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
if (!$logged_in) {
    http_response_code(401);
    echo json_encode(['error' => 'Brak autoryzacji.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['tool_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Brak tool_id.']);
    exit;
}

$toolId = $_POST['tool_id'];

// ---------- helpers Supabase REST ----------

function sb_get(string $path): array {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
        ],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

// ---------- 1. Pobierz bieżące dane narzędzia ----------

$tools = sb_get(
    'tools' .
    '?id=eq.' . rawurlencode($toolId) .
    '&select=id,name,website_url,description_pl,pricing_model,rodo_compliant,ai_act_risk,logo_url,best_for_pl,category_id,categories(name_pl)'
);

if (empty($tools)) {
    http_response_code(404);
    echo json_encode(['error' => 'Nie znaleziono narzędzia.']);
    exit;
}

$tool = $tools[0];
$categories = sb_get('categories?order=sort_order&select=id,name_pl');

verify_debug('po pobraniu narzędzia i kategorii z Supabase');

// ---------- 2. Live fetch strony narzędzia ----------

$ch = curl_init($tool['website_url']);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 6,
    CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => ['Accept: text/html'],
]);
$html      = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

verify_debug('po curl strony');

if ($html === false || $curlError !== '') {
    http_response_code(502);
    echo json_encode(['error' => 'Nie udało się pobrać strony narzędzia: ' . $curlError]);
    exit;
}

if ($httpCode >= 400) {
    http_response_code(502);
    echo json_encode(['error' => 'Strona narzędzia zwróciła błąd HTTP ' . $httpCode . '.']);
    exit;
}

// ---------- 3. Wyciągnij czysty tekst ze strony ----------

function extract_page_text(string $html): string {
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1])));
    }

    $metaDescription = '';
    if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/is', $html, $m)) {
        $metaDescription = trim(html_entity_decode(strip_tags($m[1])));
    }

    $bodyHtml = $html;
    if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $m)) {
        $bodyHtml = $m[1];
    }
    $bodyHtml = preg_replace('/<(script|style|nav|footer|noscript)\b[^>]*>.*?<\/\1>/is', ' ', $bodyHtml);
    $bodyText = trim(html_entity_decode(strip_tags($bodyHtml)));
    $bodyText = preg_replace('/\s+/', ' ', $bodyText);

    $parts = array_filter([
        $title !== '' ? "Tytuł: {$title}" : '',
        $metaDescription !== '' ? "Meta opis: {$metaDescription}" : '',
        $bodyText,
    ], fn($p) => $p !== '');

    return mb_substr(implode("\n", $parts), 0, 3000);
}

function extract_logo_hint(string $html, string $baseUrl): ?string {
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\'](.*?)["\']/is', $html, $m)) {
        return html_entity_decode($m[1]);
    }
    if (preg_match('/<link[^>]+rel=["\'](?:shortcut )?icon["\'][^>]+href=["\'](.*?)["\']/is', $html, $m)) {
        $href = html_entity_decode($m[1]);
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $parts  = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        return $href !== '' && $href[0] === '/' ? $origin . $href : $origin . '/' . $href;
    }
    return null;
}

$pageText         = extract_page_text($html);
$logoHintFromHtml = extract_logo_hint($html, $tool['website_url']);

if ($pageText === '') {
    http_response_code(502);
    echo json_encode(['error' => 'Nie udało się wyciągnąć treści ze strony narzędzia.']);
    exit;
}

// ---------- 4. OpenAI ----------

$categoryNames = array_map(fn($c) => $c['name_pl'], $categories);

$systemPrompt = 'Na podstawie treści strony narzędzia/firmy zweryfikuj i zaktualizuj dane. '
    . 'Zwróć TYLKO JSON: { description, category, pricing_model, best_for_pl, ai_act_risk_suggestion, logo_hint }. '
    . 'Zasady: description — 2 zdania po polsku, neutralne, SEO-friendly. '
    . 'category — jedna z: ' . implode(', ', $categoryNames) . '. '
    . 'pricing_model — jedna wartość: free, freemium, paid, open_source. '
    . 'best_for_pl — jedno krótkie zdanie po polsku, max 60 znaków. '
    . 'ai_act_risk_suggestion — TYLKO jeśli strona explicite wspomina o zgodności z AI Act, inaczej null '
    . '(to pole nigdy nie jest pewne, tylko sugestia do ręcznej weryfikacji). '
    . 'logo_hint — URL do favicon lub OG image ze strony jeśli widoczny w HTML head, inaczej null.';

$payload = [
    'model'           => 'gpt-4o-mini',
    'max_tokens'      => 400,
    'response_format' => ['type' => 'json_object'],
    'messages'        => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $pageText],
    ],
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ],
]);
$openaiRes   = curl_exec($ch);
$openaiHttp  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$openaiError = curl_error($ch);
curl_close($ch);

verify_debug('po OpenAI (HTTP ' . $openaiHttp . ')');

if ($openaiRes === false || $openaiError !== '') {
    http_response_code(502);
    echo json_encode(['error' => 'Błąd zapytania do OpenAI: ' . $openaiError]);
    exit;
}

// ---------- 5. Parsowanie odpowiedzi OpenAI i budowa response ----------
//
// Od tego miejsca do zwrócenia JSON-a całość owinięta w try/catch (\Throwable,
// bo TypeError/Error nie dziedziczą po Exception) — to tutaj najczęściej
// dochodziło do fatal errora (np. gdy OpenAI zwróci coś innego niż czysty
// JSON w message.content, albo pole "category" w nieoczekiwanym kształcie).

try {
    if ($openaiHttp >= 400) {
        verify_write_log('verify_tool: OpenAI HTTP ' . $openaiHttp . ' — surowa odpowiedź: ' . mb_substr((string) $openaiRes, 0, 2000));
        throw new RuntimeException('OpenAI zwróciło błąd HTTP ' . $openaiHttp . '.');
    }

    $openaiData = json_decode($openaiRes, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        verify_write_log('verify_tool: nie udało się zdekodować odpowiedzi HTTP z OpenAI (' . json_last_error_msg() . ') — surowa odpowiedź: ' . mb_substr((string) $openaiRes, 0, 2000));
        throw new RuntimeException('Nie udało się zdekodować odpowiedzi HTTP z OpenAI: ' . json_last_error_msg());
    }

    $content = $openaiData['choices'][0]['message']['content'] ?? null;

    // Surowa treść content — logujemy PRZED próbą jej sparsowania, żeby mieć
    // dowód nawet jeśli json_decode niżej zawiedzie (np. odpowiedź owinięta
    // w markdown ```json mimo instrukcji w prompcie).
    verify_write_log('verify_tool: surowa treść content z OpenAI (pierwsze 2000 znaków): ' . mb_substr((string) $content, 0, 2000));

    if (!$content) {
        throw new RuntimeException('OpenAI nie zwróciło treści odpowiedzi (message.content puste).');
    }

    $ai = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($ai)) {
        throw new RuntimeException('Nie udało się sparsować odpowiedzi AI jako JSON: ' . json_last_error_msg());
    }

    // Dopasuj nazwę kategorii zwróconą przez AI do category_id. $ai['category']
    // teoretycznie nie musi być stringiem (np. AI zwróci tablicę) — bez tego
    // sprawdzenia trim()/mb_strtolower() rzuciłyby TypeError (strict_types=1).
    $aiCategoryRaw  = $ai['category'] ?? '';
    $aiCategoryName = is_string($aiCategoryRaw) ? trim($aiCategoryRaw) : '';

    $matchedCategoryId = null;
    foreach ($categories as $cat) {
        if ($aiCategoryName !== '' && mb_strtolower(trim((string) $cat['name_pl'])) === mb_strtolower($aiCategoryName)) {
            $matchedCategoryId = $cat['id'];
            break;
        }
    }

    $logoHint = $ai['logo_hint'] ?? $logoHintFromHtml;

    $response = [
        'old' => [
            'description'   => $tool['description_pl'],
            'category'      => $tool['categories']['name_pl'] ?? null,
            'category_id'   => $tool['category_id'],
            'pricing_model' => $tool['pricing_model'],
            'best_for_pl'   => $tool['best_for_pl'],
            'ai_act_risk'   => $tool['ai_act_risk'],
            'logo_url'      => $tool['logo_url'],
        ],
        'new' => [
            'description'            => $ai['description'] ?? null,
            'category'               => $aiCategoryName !== '' ? $aiCategoryName : null,
            'category_id'            => $matchedCategoryId,
            'pricing_model'          => $ai['pricing_model'] ?? null,
            'best_for_pl'            => $ai['best_for_pl'] ?? null,
            'ai_act_risk_suggestion' => $ai['ai_act_risk_suggestion'] ?? null,
            'logo_hint'              => $logoHint,
        ],
    ];

    echo json_encode($response);

    verify_debug('po zapisie response');
} catch (\Throwable $e) {
    verify_debug('WYJĄTEK: ' . $e->getMessage() . ' w ' . $e->getFile() . ':' . $e->getLine());
    verify_write_log('verify_tool: stack trace: ' . $e->getTraceAsString());

    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['error' => 'Błąd przetwarzania odpowiedzi AI: ' . $e->getMessage()]);
}
