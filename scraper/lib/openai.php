<?php
declare(strict_types=1);

function call_openai_description(string $title, string $url, string $context): array {
    // Dosłowna treść system promptu z NiFi ReplaceText (potwierdzona przez Pabla,
    // stan 2026-09-13) — 10 kategorii, te same zasady dla wszystkich pól.
    $systemPrompt = <<<'PROMPT'
Opisz narzedzie/firme w 2 zdaniach. Ton: neutralny, informacyjny, SEO-friendly. Jezyk: polski. Format odpowiedzi tylko JSON bez markdown: { name, description, category, tags, segment, pricing_model, best_for_pl, is_real_product } Zasady dla pola name: - Krotka nazwa produktu lub narzedzia (max 50 znakow) - Bez prefiksu Show HN: i podobnych - Bez podtytulu po myslniku lub dwukropku - Przyklad: Inbox-beam, KVarN, Lathe Zasady dla pola pricing_model: - Wybierz JEDNA wartosc: free, freemium, paid, open_source - open_source: projekt na GitHub bez platnego SaaS - free: narzedzie bez zadnych platnych planow - freemium: darmowy tier + platne plany - paid: tylko platne plany, brak darmowej wersji Zasady dla pola category: - Wybierz JEDNA kategorie z tej listy (pisownia musi byc identyczna): Automatyzacja procesów, Analityka i BI, Finanse i księgowość, HR i rekrutacja, Marketing i content, Obsługa klienta, Prawo i compliance, Sprzedaż i CRM, Zarządzanie projektami, Cyberbezpieczeństwo AI - Jesli narzedzie nie pasuje do zadnej - wybierz najblizszą Zasady dla pola best_for_pl: - Jedno krotkie zdanie po polsku, max 60 znakow, bez kropki na koncu - Opisuje dla jakiego typu firmy lub zespolu narzedzie jest najlepsze - Przyklad: Male zespoly sprzedazy w SMB, Dzialy HR w srednich firmach Zasady dla pola is_real_product: - Wartosc boolowska true lub false, bez cudzyslowow - true TYLKO jesli tytul i url opisuja faktyczny, konkretny produkt lub narzedzie ktore mozna odwiedzic i wyprobowac (SaaS, aplikacja, API, biblioteka, platforma) - false jesli to artykul, esej, badanie naukowe, wpis blogowy, dyskusja, ogloszenie lub tresc niezwiazana z konkretnym istniejacym produktem - W razie watpliwosci wybierz false Jesli podano Kontekst inny niz brak, oprzyj opis WYLACZNIE na tym kontekscie, nie zgaduj z samej nazwy/URL.
PROMPT;

    $userContent = sprintf(
        'Nazwa: %s URL: %s Kontekst: %s',
        $title,
        $url,
        $context === '' ? 'brak' : $context
    );

    $payload = [
        'model'           => 'gpt-4o-mini',
        'max_tokens'      => 500,
        'response_format' => ['type' => 'json_object'],
        'messages'        => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userContent],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . getenv('OPENAI_API_KEY'),
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) {
        throw new RuntimeException('OpenAI curl error: ' . $err);
    }
    if ($httpCode >= 400) {
        throw new RuntimeException('OpenAI HTTP ' . $httpCode . ': ' . $res);
    }

    $data = json_decode($res, true);
    $content = $data['choices'][0]['message']['content'] ?? null;
    if ($content === null) {
        throw new RuntimeException('Brak choices[0].message.content w odpowiedzi OpenAI: ' . $res);
    }

    // Zabezpieczenie na wypadek, gdyby model jednak owinął odpowiedź w ```json ... ```
    // mimo response_format=json_object i instrukcji "bez markdown" — pas
    // bezpieczeństwa, nie główna linia obrony.
    $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($content)));

    $parsed = json_decode($content, true);
    if (!is_array($parsed)) {
        throw new RuntimeException('Odpowiedź OpenAI nie jest poprawnym JSON: ' . $content);
    }

    return $parsed;
}
