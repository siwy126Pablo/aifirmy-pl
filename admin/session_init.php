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
