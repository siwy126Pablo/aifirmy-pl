<?php
declare(strict_types=1);

/**
 * Dowody weryfikacji pól RODO / DPA / hosting UE (tools.compliance_evidence,
 * migracja db/migrations/012_compliance_evidence.sql; reguły: docs/COMPLIANCE-EVIDENCE.md).
 *
 * Zasada: ZMIANA wartości rodo_compliant / dpa_available / eu_data_hosting nie przechodzi
 * bez notatki z dowodem (co strona producenta mówi o tym polu). Wpis dowodu powstaje w tej
 * samej operacji co zmiana pola, więc dowód nie może się rozjechać z kolumną. Logika jest
 * w jednym miejscu, bo korzystają z niej dwie ścieżki zapisu: admin/api.php (edycja)
 * i admin/index.php (formularz "Dodaj wpis").
 *
 * ai_act_risk (ADR-015) działa „miękko": dowód zapisuje się tak samo, ale zmiana bez
 * dowodu nie jest odrzucana — zapis przechodzi z ostrzeżeniem (COMPLIANCE_SOFT_FIELDS).
 *
 * Składnia celowo zgodna z PHP 7.4 (wersja PHP na hostingu nie jest w repo).
 * Plik tylko do include — bezpośrednie wywołanie przez URL nic nie zwraca.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

const COMPLIANCE_FIELDS = ['rodo_compliant', 'dpa_available', 'eu_data_hosting'];

/**
 * Pola „miękkie" (ADR-015): dowód jest przyjmowany i zapisywany tak samo jak dla pól wyżej,
 * ale zmiana wartości BEZ dowodu nie blokuje zapisu — compliance_apply() zwraca wtedy
 * ostrzeżenie (klucz 'warnings'), a wpis zostaje w kolejce przeglądu (wartość bez dowodu).
 * Powód: ai_act_risk to ocena redakcji, a część jej zapisów (sugestia weryfikacji AI)
 * trafia do bazy z okna weryfikacji bez miejsca na uzasadnienie.
 */
const COMPLIANCE_SOFT_FIELDS = ['ai_act_risk'];

const COMPLIANCE_LABELS = [
    'rodo_compliant'  => 'RODO zgodny',
    'dpa_available'   => 'Umowa DPA',
    'eu_data_hosting' => 'Hosting UE',
    'ai_act_risk'     => 'Ryzyko AI Act',
];

/** Wszystkie pola objęte dowodami: twarde (wymagany dowód) + miękkie (ostrzeżenie). */
function compliance_all_fields(): array {
    return array_merge(COMPLIANCE_FIELDS, COMPLIANCE_SOFT_FIELDS);
}

/** Dzisiejsza data (RRRR-MM-DD) w strefie projektu, nie serwera. */
function compliance_today(): string {
    return (new DateTime('now', new DateTimeZone('Europe/Warsaw')))->format('Y-m-d');
}

/**
 * Waliduje i czyści wejście dowodu dla jednego pola.
 * @param mixed $raw tablica {url?, note?} lub null
 * @return array{ok:bool, provided:bool, url?:?string, note?:string, error?:string}
 */
function compliance_clean_input($raw, string $field): array {
    $label = COMPLIANCE_LABELS[$field] ?? $field;
    if (!is_array($raw)) return ['ok' => true, 'provided' => false];

    $urlRaw  = $raw['url']  ?? null;
    $noteRaw = $raw['note'] ?? null;
    $url  = is_string($urlRaw)  ? trim($urlRaw)  : '';
    $note = is_string($noteRaw) ? trim($noteRaw) : '';
    if ($url === '' && $note === '') return ['ok' => true, 'provided' => false];

    if ($note === '') {
        return ['ok' => false, 'provided' => true, 'error' => $label . ': podaj notatkę z dowodem (co mówi strona producenta).'];
    }
    if (strlen($note) > 1000) {
        return ['ok' => false, 'provided' => true, 'error' => $label . ': notatka dowodu jest za długa (maks. 1000 znaków).'];
    }
    if ($url !== '') {
        if (strlen($url) > 2000 || !preg_match('#^https?://[^\s]+$#i', $url)) {
            return ['ok' => false, 'provided' => true, 'error' => $label . ': adres dowodu musi zaczynać się od http:// lub https://.'];
        }
    }
    return ['ok' => true, 'provided' => true, 'url' => $url === '' ? null : $url, 'note' => $note];
}

/**
 * Składa nową wartość compliance_evidence dla zapisu zmiany pól.
 *
 * @param array $current   bieżący wiersz: rodo_compliant / dpa_available / eu_data_hosting /
 *                         ai_act_risk / compliance_evidence (dla nowego narzędzia: []).
 * @param array $newValues nowe wartości pól (field => ?bool, dla ai_act_risk ?string),
 *                         tylko pola przesyłane w zapisie.
 * @param array $evidenceIn dowody z żądania (field => {url, note}).
 * @return array{ok:bool, error?:string, touched?:bool, evidence?:array, checked_at?:string, warnings?:string[]}
 *         'warnings' — zmiany pól miękkich bez dowodu (zapis dozwolony, do uzupełnienia).
 */
function compliance_apply(array $current, array $newValues, array $evidenceIn): array {
    $evidence = (isset($current['compliance_evidence']) && is_array($current['compliance_evidence']))
        ? $current['compliance_evidence'] : [];
    $touched = false;
    $warnings = [];
    $today = compliance_today();

    // Dowód dla pola spoza listy to błąd klienta, nie ciche pominięcie.
    foreach ($evidenceIn as $k => $_) {
        if (!in_array((string) $k, compliance_all_fields(), true)) {
            return ['ok' => false, 'error' => 'Niedozwolone pole dowodu: ' . $k];
        }
    }

    foreach (compliance_all_fields() as $field) {
        $soft = in_array($field, COMPLIANCE_SOFT_FIELDS, true);
        if (!array_key_exists($field, $newValues)) {
            if (isset($evidenceIn[$field])) {
                return ['ok' => false, 'error' => 'Dowód dla pola, którego nie zapisujesz: ' . $field];
            }
            continue;
        }
        $new = $newValues[$field];
        $old = array_key_exists($field, $current) ? $current[$field] : null;
        $changed = $new !== $old;

        $in = compliance_clean_input($evidenceIn[$field] ?? null, $field);
        if (!$in['ok']) return ['ok' => false, 'error' => $in['error']];

        if ($changed && !$in['provided']) {
            if ($soft) {
                // Miękko: zapis przechodzi, ostrzeżenie tylko przy ustawieniu wartości
                // (zdjęcie oceny do NULL nie wymaga uzasadnienia).
                if ($new !== null) {
                    $warnings[] = (COMPLIANCE_LABELS[$field] ?? $field)
                        . ': zapisano bez uzasadnienia — wpis zostaje w kolejce przeglądu. Uzupełnij dowód (adres strony producenta i notatka) w przeglądzie.';
                }
                continue;
            }
            return ['ok' => false, 'error' => (COMPLIANCE_LABELS[$field] ?? $field)
                . ': zmiana wartości wymaga dowodu — podaj adres strony producenta i notatkę (co mówi o tym polu; przy „Nie zweryfikowano" dlaczego cofasz).'];
        }
        if ($in['provided']) {
            $evidence[$field] = [
                'value'   => $new,
                'url'     => $in['url'],
                'note'    => $in['note'],
                'checked' => $today,
            ];
            $touched = true;
        }
    }

    if (!$touched) return ['ok' => true, 'touched' => false, 'warnings' => $warnings];
    return ['ok' => true, 'touched' => true, 'evidence' => $evidence, 'checked_at' => $today, 'warnings' => $warnings];
}

/** jsonb musi być obiektem (CHECK w bazie); pusta tablica PHP kodowałaby się jako []. */
function compliance_evidence_for_json(array $evidence) {
    return $evidence === [] ? new stdClass() : $evidence;
}
