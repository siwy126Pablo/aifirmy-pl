# COMPLIANCE-EVIDENCE.md — jak działa weryfikacja RODO / DPA / hostingu UE / ryzyka AI Act

**Dowody NIE są w tym pliku.** Źródłem prawdy jest baza: kolumny `tools.compliance_evidence` (jsonb) i `tools.compliance_checked_at` (migracja `db/migrations/012_compliance_evidence.sql`, 6.11.2026). Ten plik zawiera tylko reguły i zapytania kontrolne. Dzięki temu nie ma drugiej kopii dowodów, która mogłaby się rozjechać z bazą.

## Zasada dla każdej sesji

Przed weryfikacją pól `rodo_compliant`, `dpa_available`, `eu_data_hosting` oraz `ai_act_risk` (od 19.11.2026, ADR-015) sprawdź `compliance_evidence` danego narzędzia:

- **Jest wpis dla pola i `value` zgadza się z kolumną** → pole jest sprawdzone. Nie weryfikuj od nowa, chyba że od `checked` minął rok albo producent ogłosił zmianę.
- **`value` w dowodzie ≠ wartość w kolumnie** → ktoś zmienił pole bez aktualizacji dowodu; dowód jest nieważny, pole do ponownego sprawdzenia.
- **Wpis z `value: null`** → sprawdzono, strona nie mówi nic o tym polu (NULL jest tu wynikiem, nie brakiem sprawdzenia).
- **Wartość ≠ NULL, brak wpisu** → brak dowodu (starszy wpis ręczny, zob. „Stan").
- **Każda zmiana pola** (w panelu lub SQL) wymaga w tej samej operacji zapisu wpisu: `value`, `url`, `note`, `checked` (RRRR-MM-DD) oraz `compliance_checked_at`.

Opis tej zasady jest też w `COMMENT ON COLUMN` na kolumnach, więc widzi go każdy, kto ogląda schemat.

## Kształt wpisu

```json
{ "dpa_available":   { "value": true,  "url": "https://…", "note": "co jest na stronie", "checked": "2026-11-06" },
  "eu_data_hosting": { "value": false, "url": "https://…", "note": "…", "checked": "2026-11-06" } }
```

Klucze to nazwy kolumn. `url` może być `null`, gdy adres nie został zapisany (zaznacz to w `note`).

## Reguły znaczenia pól (ustalone 6.11.2026)

- **`dpa_available` ✓** — strona producenta (Legal / Trust / Security) zawiera własny dokument DPA. Samo „zgodni z RODO" to za mało → NULL.
- **`eu_data_hosting` (reguła A, decyzja Pabla)** — ✓, gdy region UE można wybrać przy zakładaniu konta lub jest domyślny. ✗, gdy UE to płatny dodatek / tylko enterprise albo domyślnie dane są poza UE. NULL, gdy strona nic o hostingu nie mówi.
- **`rodo_compliant` ✓ (reguła B, decyzja Pabla 7.11.2026, wariant 2)** — ✓, gdy oficjalna strona producenta (privacy policy, trust/security center, DPA) spełnia JEDNO z dwóch:
  1. producent publikuje własny dokument DPA, albo
  2. polityka prywatności / trust page jawnie opisuje przetwarzanie w kontekście RODO, czyli zawiera WSZYSTKO naraz: podstawy prawne, prawa osób, podmiot odpowiedzialny/procesor wskazany z nazwy (i adresu) oraz transfery poza EOG z mechanizmem (SCC/DPF) albo informację, że danych nie przekazuje.

  Samo „jesteśmy zgodni z RODO" / „GDPR compliant" bez powyższego → NULL. Brak informacji → NULL, nigdy ✓. ✗ tylko przy jawnej deklaracji producenta, że nie spełnia (rzadkie). W `note` dowodu wpisz, który z dwóch warunków był spełniony (DPA albo elementy polityki) i gdzie. Pole nie pokrywa się z `dpa_available`: ✓ może wynikać z warunku 2 bez DPA.

  **Przejście dla starszych wpisów:** ✓ bez dowodu (163 zatwierdzonych na 7.11) sprawdzamy partiami po 15–20 narzędzi. Sprawdzone → ✓ z dowodem albo NULL z dowodem (`value: null`, notatka co zabrakło). Niesprawdzone zostają bez zmian aż do swojej partii — nie zerujemy hurtem.
- Brak informacji = NULL, nigdy ✓. Oficjalne strony producenta (privacy, security, trust center, DPA), nie blogi ani katalogi.
- **`ai_act_risk` (ADR-014, ADR-015, 19.11.2026)** — ocena redakcji wg funkcji produktu głównego, nie kategorii:
  - `high`: ocenia/rankinguje pojedynczych pracowników lub wnioskuje o emocjach (ADR-014), albo inny obszar z załącznika III będący rdzeniem produktu. Zawsze z `ai_act_notes`.
  - `limited`: rdzeń produktu rozmawia z osobami spoza firmy użytkownika, generuje treści syntetyczne (art. 50 ust. 1, 2, 4), wykrywa emocje z tekstu albo kategoryzuje emocje/biometrycznie (art. 50 ust. 3).
  - `minimal`: reszta (asystenci, z którymi użytkownik rozmawia świadomie, narzędzia deweloperskie, infrastruktura, testowanie).
  - Precyzja ponad kompletność. Wątpliwe albo nieoceniane (platforma od minimalnych po wysokie, kandydat do odrzucenia z katalogu): NULL z dowodem `value: null` i notatką dlaczego.
  - Przy wartości ≠ NULL ustaw też `ai_act_notes` („Wstępna ocena redakcji: …"); strona pokazuje ją w FAQ tylko gdy `ai_act_risk` nie jest NULL. W `note` dowodu: co sprawdzono na stronie producenta; przy granicznych „Decyzja redakcji RRRR-MM-DD: …".
  - **Pole miękkie:** panel nie blokuje zapisu bez dowodu, tylko ostrzega (wartość bez dowodu = do przeglądu). Zapis przez SQL/MCP: dowód i `compliance_checked_at` w tej samej operacji, jak dla pozostałych pól.

## Zapytania kontrolne

```sql
-- dowód nie zgadza się z kolumną (ma być 0 wierszy)
SELECT t.slug, e.key, e.value->'value' AS dowod
FROM tools t, jsonb_each(t.compliance_evidence) e
WHERE nullif(e.value->'value','null'::jsonb) IS DISTINCT FROM
  CASE e.key WHEN 'dpa_available' THEN to_jsonb(t.dpa_available)
             WHEN 'eu_data_hosting' THEN to_jsonb(t.eu_data_hosting)
             WHEN 'rodo_compliant' THEN to_jsonb(t.rodo_compliant)
             WHEN 'ai_act_risk' THEN to_jsonb(t.ai_act_risk) END;

-- pola z wartością bez dowodu (do weryfikacji)
SELECT slug FROM tools
WHERE (dpa_available IS NOT NULL AND NOT compliance_evidence ? 'dpa_available')
   OR (eu_data_hosting IS NOT NULL AND NOT compliance_evidence ? 'eu_data_hosting');

-- ai_act_risk ≠ NULL bez dowodu (kolejka przeglądu; po przeglądzie 19.11.2026 zostaje 6 celowo niezapisanych z potoku)
SELECT slug FROM tools
WHERE status = 'approved' AND ai_act_risk IS NOT NULL AND NOT compliance_evidence ? 'ai_act_risk';

-- dowody starsze niż rok
SELECT slug, compliance_checked_at FROM tools
WHERE compliance_checked_at < now() - interval '1 year';
```

Uwaga: `value: null` w jsonb to nie SQL NULL, stąd `nullif(…,'null'::jsonb)`; porównanie bez tego zgłasza fałszywe rozjazdy.

## Stan po wdrożeniu (6.11.2026)

41 narzędzi, 57 pól z dowodem (przegląd z 6.11.2026 na stronach producentów). Bez dowodu zostają: ok. 175 × `rodo_compliant` ✓, 23 × `dpa_available` ✓ i 21 × `eu_data_hosting` ≠ NULL (m.in. Dust, ElevenLabs, tl;dv, Weaviate, UiPath, Grammarly Business, Brand24, Parloa, SentiOne, Senuto, Lex Expert AI, humai — część sprawdzana wcześniej, ale bez zapisanego adresu). Brak dowodu nie znaczy, że wartość jest błędna, tylko że nie ma jak tego odróżnić.

**Przegląd `ai_act_risk` (19.11.2026, ADR-015):** 185 wpisów z potoku z niezweryfikowanym `minimal` przejrzane partiami, każdy z dowodem `ai_act_risk`; po przeglądzie zatwierdzone: NULL 18, high 21, limited 93, minimal 238, 182 wpisy z dowodem. Sześć wpisów celowo bez zapisu (artifex, front-end-skill-pack-for-ai-agents, vehla, vim, airdows, amorphic-labs: kandydaci do odrzucenia lub wyjaśnienia).

Niedokończone sprawdzenia i partie `rodo_compliant` są w Notion (zadania P001). Definicja ✓ rozstrzygnięta 7.11 (reguła B wyżej, ADR-013).

## Panel admina

- **Edycja (`✏️ Edytuj`):** przy RODO / DPA / Hosting UE widać status dowodu (✓ sprawdzono RRRR-MM-DD + notatka i link / ⚠ wartość bez dowodu / ⚠ dowód nieważny). Zmiana wartości pola otwiera pola adresu i notatki; **bez notatki zapis jest odrzucony** (po stronie serwera: `admin/compliance_evidence.php` wywoływane z `admin/api.php`, więc obchodzi się to tylko przez bezpośredni SQL). „Zapisz nowy dowód" odświeża dowód bez zmiany wartości. Dowód zapisuje się w tej samej operacji co pole.
- **„Dodaj wpis":** RODO / DPA / Hosting inne niż „Nie zweryfikowano" wymaga notatki dowodu.
- **Ryzyko AI Act (od ADR-015):** pole miękkie. „Dodaj wpis": domyślnie „Nie oceniono" (NULL), wiersz uzasadnienia oceny jest zalecany, nie wymagany. Zapis wartości bez dowodu przechodzi z ostrzeżeniem (komunikat po dodaniu wpisu lub w oknie „Zweryfikuj przez AI"). Okno „Edytuj" nie ma jeszcze pola `ai_act_risk` ani `ai_act_notes` (zadanie w Notion).
- **Poza panelem** (SQL, MCP): sam pilnuj zapisu dowodu razem z polem (zob. „Zasada dla każdej sesji"); serwer nie ma tam jak tego wymusić.
- Dowód nie jest zapisywalny przez zwykły `fields` w `tool_update` — wyłącznie przez `evidence`.
- **Uwaga:** kolumny `compliance_evidence` i `ai_act_notes` są czytelne publicznie przez REST (`anon`, wiersze `approved`). Frontend ich nie używa w zapytaniach, ale nie wpisuj do `note` niczego, czego nie chcesz upublicznić (np. notatek wewnętrznych typu „do odrzucenia").
