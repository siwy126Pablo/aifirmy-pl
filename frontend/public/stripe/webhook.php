<?php
declare(strict_types=1);

/**
 * Stripe webhook handler.
 *
 * Dodaj w private_html/config/db.php:
 *   define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
 *
 * Zarejestruj endpoint w Stripe Dashboard:
 *   https://aifirmy.pl/stripe/webhook.php
 *   Eventy: checkout.session.completed
 */

// ---- Raw body — przed jakimkolwiek echo/output ----
$payload    = (string) file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// ---- Autoload Stripe SDK ----
$vendor_paths = [
    '/home/siwy126/domains/aifirmy.pl/private_html/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];
foreach ($vendor_paths as $path) {
    if (file_exists($path)) { require_once $path; break; }
}

require_once '/home/siwy126/domains/aifirmy.pl/private_html/config/db.php';

// ---- Stałe Supabase (SUPABASE_ANON_KEY pochodzi z db.php) ----
if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', 'https://szassqzvivdgvpkciyif.supabase.co');
}
if (!defined('SUPABASE_KEY')) {
    define('SUPABASE_KEY', SUPABASE_ANON_KEY);
}

// ---- Walidacja obecności danych ----
if (empty($payload) || empty($sig_header)) {
    http_response_code(400);
    exit;
}

// ---- Weryfikacja podpisu Stripe ----
$webhook_secret = defined('STRIPE_WEBHOOK_SECRET')
    ? STRIPE_WEBHOOK_SECRET
    : (getenv('STRIPE_WEBHOOK_SECRET') ?: '');

if (empty($webhook_secret)) {
    error_log('webhook.php: brak STRIPE_WEBHOOK_SECRET');
    http_response_code(500);
    exit;
}

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    error_log('webhook.php: błąd weryfikacji podpisu — ' . $e->getMessage());
    http_response_code(400);
    exit;
}

// ---- Routing eventów ----
if ($event->type === 'checkout.session.completed') {

    $session = $event->data->object;

    // line_items nie są rozwinięte w webhooku — pobieramy sesję ponownie
    $secret_key = defined('STRIPE_SECRET_KEY')
        ? STRIPE_SECRET_KEY
        : (getenv('STRIPE_SECRET_KEY') ?: '');

    \Stripe\Stripe::setApiKey($secret_key);

    try {
        $full_session = \Stripe\Checkout\Session::retrieve(
            $session->id,
            ['expand' => ['line_items']]
        );
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('webhook.php: błąd pobierania sesji — ' . $e->getMessage());
        http_response_code(500);
        exit;
    }

    $subscription_id = $full_session->subscription ?? null;
    $price_id        = $full_session->line_items->data[0]->price->id ?? null;

    // Mapowanie price_id → plan i cena
    $price_map = [
        'price_1ThXE6CDrs3IUxTR9cyQCgvy' => ['plan' => 'logo',            'price_pln' => 49],
        'price_1ThXERCDrs3IUxTRRgrGP30C' => ['plan' => 'featured',        'price_pln' => 99],
        'price_1ThXEmCDrs3IUxTRTVCpbeL9' => ['plan' => 'dofollow',        'price_pln' => 199],
        'price_1ThXFECDrs3IUxTR8cK0JZF8' => ['plan' => 'top_of_category', 'price_pln' => 299],
    ];

    if ($price_id === null || !isset($price_map[$price_id])) {
        error_log('webhook.php: nieznany price_id — ' . $price_id);
        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
    }

    $plan      = $price_map[$price_id]['plan'];
    $price_pln = $price_map[$price_id]['price_pln'];
    $now       = gmdate('Y-m-d\TH:i:s\Z');
    $ends_at   = gmdate('Y-m-d\TH:i:s\Z', strtotime('+30 days'));

    $body = json_encode([
        'tool_id'    => null,
        'plan'       => $plan,
        'price_pln'  => $price_pln,
        'payment_id' => $subscription_id,
        'starts_at'  => $now,
        'ends_at'    => $ends_at,
        'paid_at'    => $now,
    ]);

    $ch = curl_init(SUPABASE_URL . '/rest/v1/premium_listings');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'apikey: '               . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ],
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 400) {
        error_log('webhook.php: błąd Supabase INSERT — HTTP ' . $http_code . ' — ' . $response);
    }
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['received' => true]);
