<?php
declare(strict_types=1);

require_once __DIR__ . '/quality_rules.php';

function call_openai_description(string $title, string $url, string $context): array {
    // Dosłowna treść system promptu z NiFi ReplaceText (potwierdzona przez Pabla,
    // stan 2026-09-13) — 10 kategorii, te same zasady dla wszystkich pól.
    // 2026-09-30: przepisany na pełne polskie diakrytyki + jawna reguła o znakach
    // (wzorzec z verify_tool.php, 19.09) — już nie dosłowna kopia z NiFi.
    // 2026-10-20: is_real_product=false także dla hardware/IoT, robotyki i uzbrojenia.
    // 2026-10-30: reguły jakości języka (diakrytyki, gramatyka, bez „Dla: Dla…”, 3. osoba, bez
    // etykiet pól) pochodzą ze wspólnej stałej AIFIRMY_COPY_QUALITY_RULES (quality_rules.php),
    // tej samej, której używa admin/verify_tool.php.
    // Prefiks „Show HN:” i kategoria spoza listy są dodatkowo pilnowane w PHP
    // (pipeline.php), bo model nie zawsze trzyma się tych reguł.
    $systemPrompt = <<<'PROMPT'
Opisz narzędzie/firmę w 2 zdaniach. Ton: neutralny, informacyjny, SEO-friendly. Język: polski. Format odpowiedzi tylko JSON bez markdown: { name, description, category, tags, segment, pricing_model, best_for_pl, is_real_product } Zasady dla pola name: - Krótka nazwa produktu lub narzędzia (max 50 znaków) - Bez prefiksu Show HN: i podobnych - Bez podtytułu po myślniku lub dwukropku - Przykład: Inbox-beam, KVarN, Lathe Zasady dla pola pricing_model: - Wybierz JEDNĄ wartość: free, freemium, paid, open_source - open_source: projekt na GitHub bez płatnego SaaS - free: narzędzie bez żadnych płatnych planów - freemium: darmowy tier + płatne plany - paid: tylko płatne plany, brak darmowej wersji Zasady dla pola category: - Wybierz JEDNĄ kategorię z tej listy (pisownia musi być identyczna): Automatyzacja procesów, Analityka i BI, Finanse i księgowość, HR i rekrutacja, Marketing i content, Obsługa klienta, Prawo i compliance, Sprzedaż i CRM, Zarządzanie projektami, Cyberbezpieczeństwo AI - Jeśli narzędzie nie pasuje do żadnej - wybierz najbliższą Zasady dla pola best_for_pl: - Jedno krótkie zdanie po polsku, max 60 znaków, bez kropki na końcu - Opisuje, dla jakiego typu firmy lub zespołu narzędzie jest najlepsze - Przykład: Małe zespoły sprzedaży w SMB, Działy HR w średnich firmach Zasady dla pola is_real_product: - Wartość boolowska true lub false, bez cudzysłowów - true TYLKO jeśli tytuł i url opisują faktyczny, konkretny produkt lub narzędzie, które można odwiedzić i wypróbować (SaaS, aplikacja, API, biblioteka, platforma) - false jeśli to artykuł, esej, badanie naukowe, wpis blogowy, dyskusja, ogłoszenie lub treść niezwiązana z konkretnym istniejącym produktem - false także wtedy, gdy to fizyczny sprzęt lub urządzenie (hardware, IoT, elektronika, gadżet), robotyka lub roboty, albo broń, uzbrojenie i systemy wojskowe — nawet jeśli wykorzystują AI - W razie wątpliwości wybierz false Jeśli podano Kontekst inny niż brak, oprzyj opis WYŁĄCZNIE na tym kontekście, nie zgaduj z samej nazwy/URL.
PROMPT;
    $systemPrompt .= "\n\n" . AIFIRMY_COPY_QUALITY_RULES;

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
