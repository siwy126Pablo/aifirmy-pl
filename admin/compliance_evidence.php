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
 * Składnia celowo zgodna z PHP 7.4 (wersja PHP na hostingu nie jest w repo).
 * Plik tylko do include — bezpośrednie wywołanie przez URL nic nie zwraca.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

const COMPLIANCE_FIELDS = ['rodo_compliant', 'dpa_available', 'eu_data_hosting'];

const COMPLIANCE_LABELS = [
    'rodo_compliant'  => 'RODO zgodny',
    'dpa_available'   => 'Umowa DPA',
    'eu_data_hosting' => 'Hosting UE',
];

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
 *                         compliance_evidence (dla nowego narzędzia: []).
 * @param array $newValues nowe wartości pól (field => ?bool), tylko pola przesyłane w zapisie.
 * @param array $evidenceIn dowody z żądania (field => {url, note}).
 * @return array{ok:bool, error?:string, touched?:bool, evidence?:array, checked_at?:string}
 */
function compliance_apply(array $current, array $newValues, array $evidenceIn): array {
    $evidence = (isset($current['compliance_evidence']) && is_array($current['compliance_evidence']))
        ? $current['compliance_evidence'] : [];
    $touched = false;
    $today = compliance_today();

    // Dowód dla pola spoza listy to błąd klienta, nie ciche pominięcie.
    foreach ($evidenceIn as $k => $_) {
        if (!in_array((string) $k, COMPLIANCE_FIELDS, true)) {
            return ['ok' => false, 'error' => 'Niedozwolone pole dowodu: ' . $k];
        }
    }

    foreach (COMPLIANCE_FIELDS as $field) {
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

    if (!$touched) return ['ok' => true, 'touched' => false];
    return ['ok' => true, 'touched' => true, 'evidence' => $evidence, 'checked_at' => $today];
}

/** jsonb musi być obiektem (CHECK w bazie); pusta tablica PHP kodowałaby się jako []. */
function compliance_evidence_for_json(array $evidence) {
    return $evidence === [] ? new stdClass() : $evidence;
}
