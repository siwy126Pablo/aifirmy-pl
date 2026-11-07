<?php
/**
 * Wspólny start sesji panelu admina — wołać zamiast session_start().
 *
 * Flagi ciasteczka sesji ustawiamy jawnie, PRZED session_start(). Wcześniej nie
 * było ich w repo (brak session_set_cookie_params / ini_set), więc zależały od
 * konfiguracji PHP na hostingu. Stan produkcji z 31.10.2026: PHPSESSID miał
 * SameSite=Lax i Secure, ale NIE miał HttpOnly — był czytelny w document.cookie
 * dla każdego skryptu na aifirmy.pl (ciasteczko stoi na całym hoście, path=/).
 *
 *  - httponly: ciasteczko niedostępne dla JS (osłona przed kradzieżą sesji przez XSS
 *              albo przejęty skrypt zewnętrzny, np. GA4 na publicznej stronie)
 *  - secure:   tylko po HTTPS (produkcja: Cloudflare, SSL Full strict)
 *  - samesite: Lax, jawnie. Celowo NIE Strict: wejście na panel z zewnętrznego linku
 *              (np. z Notiona) zrzucałoby wtedy ciasteczko i wymagało ponownego logowania
 *  - path:     '/' jak dotąd. Zawężenie do /admin/ zostawiłoby obok starego ciasteczka
 *              (path=/) drugie PHPSESSID, a PHP mógłby odczytać niewłaściwe
 *  - domain:   pominięte (host-only, bez zmian)
 *
 * Składnia zgodna z PHP 7.4 (tablicowy session_set_cookie_params od 7.3).
 */
function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Token CSRF panelu (ADR-012, krok 2) — jeden token na sesję, ten sam co dla api.php
 * ($_SESSION['csrf'], nagłówek X-CSRF-Token). Formularze POST niosą go w polu ukrytym
 * "csrf_token", fetch do verify_tool.php w nagłówku X-CSRF-Token.
 */
function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf'];
}

/** Pole ukryte do wstawienia w <form method="POST"> (tylko dla zalogowanego admina). */
function admin_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(admin_csrf_token(), ENT_QUOTES) . '">';
}

/** Porównanie w stałym czasie; pusty token sesji nigdy nie przechodzi. */
function admin_csrf_valid($given): bool
{
    $session = (string) ($_SESSION['csrf'] ?? '');
    return $session !== '' && is_string($given) && hash_equals($session, $given);
}

/** Formularz POST z nieprawidłowym tokenem: 403 i koniec, zanim cokolwiek się zapisze. */
function admin_csrf_require_form(): void
{
    if (admin_csrf_valid($_POST['csrf_token'] ?? '')) {
        return;
    }
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>403</title>'
       . '<p style="font-family:sans-serif;padding:24px">Nieprawidłowy token formularza — nic nie zapisano. '
       . 'Odśwież stronę panelu i spróbuj ponownie. <a href="/admin/index.php">Wróć do panelu</a></p>';
    exit;
}
