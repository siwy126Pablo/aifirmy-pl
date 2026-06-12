<?php
declare(strict_types=1);

/**
 * Obsługa przekierowania do Stripe Checkout.
 *
 * Przed użyciem:
 *   1. Zainstaluj Stripe PHP SDK:
 *        SSH na Cyberfolks → cd ~/domains/aifirmy.pl/private_html && composer require stripe/stripe-php
 *      Lub bez composera:
 *        Pobierz https://github.com/stripe/stripe-php/releases/latest → rozpack do private_html/stripe-php/
 *   2. Dodaj w private_html/config/db.php:
 *        define('STRIPE_SECRET_KEY', 'sk_live_...');
 */

// ---- Autoload — composer (prod) lub local dev ----
$vendor_paths = [
    '/home/siwy126/domains/aifirmy.pl/private_html/vendor/autoload.php', // Cyberfolks (poza webroot)
    __DIR__ . '/../vendor/autoload.php',                                   // dev lokalny
    '/home/siwy126/domains/aifirmy.pl/private_html/stripe-php/init.php',  // manual SDK
];
$loaded = false;
foreach ($vendor_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}
if (!$loaded) {
    error_log('checkout.php: brak Stripe PHP SDK');
    http_response_code(500);
    exit('Błąd serwera — brak biblioteki płatności.');
}

require_once '/home/siwy126/domains/aifirmy.pl/private_html/config/db.php';

// ---- Tylko POST ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: /premium');
    exit;
}

// ---- Whitelist price_id ----
const ALLOWED_PRICES = [
    'price_1ThXE6CDrs3IUxTR9cyQCgvy', // 49 zł
    'price_1ThXERCDrs3IUxTRRgrGP30C', // 99 zł
    'price_1ThXEmCDrs3IUxTRTVCpbeL9', // 199 zł
    'price_1ThXFECDrs3IUxTR8cK0JZF8', // 299 zł
];

$price_id = trim($_POST['price_id'] ?? '');
if (!in_array($price_id, ALLOWED_PRICES, true)) {
    http_response_code(400);
    header('Location: /premium?error=invalid_plan');
    exit;
}

// ---- Secret key — stała z db.php lub zmienna środowiskowa ----
$secret_key = defined('STRIPE_SECRET_KEY')
    ? STRIPE_SECRET_KEY
    : (getenv('STRIPE_SECRET_KEY') ?: '');

if (empty($secret_key)) {
    error_log('checkout.php: brak STRIPE_SECRET_KEY');
    http_response_code(500);
    exit('Błąd konfiguracji serwera.');
}

\Stripe\Stripe::setApiKey($secret_key);

// ---- Stripe Checkout Session ----
// Uwaga: currency jest osadzone w obiekcie Price po stronie Stripe —
// przy mode=subscription nie przekazujemy go do Session::create().
try {
    $session = \Stripe\Checkout\Session::create([
        'mode'        => 'subscription',
        'line_items'  => [[
            'price'    => $price_id,
            'quantity' => 1,
        ]],
        'success_url' => 'https://aifirmy.pl/dziekujemy?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => 'https://aifirmy.pl/premium',
    ]);

    header('Location: ' . $session->url, true, 303);
    exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Stripe API error: ' . $e->getMessage());
    http_response_code(502);
    header('Location: /premium?error=payment_failed');
    exit;
}
