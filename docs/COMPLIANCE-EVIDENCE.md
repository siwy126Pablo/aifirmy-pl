# COMPLIANCE-EVIDENCE.md — jak działa weryfikacja RODO / DPA / hostingu UE

**Dowody NIE są w tym pliku.** Źródłem prawdy jest baza: kolumny `tools.compliance_evidence` (jsonb) i `tools.compliance_checked_at` (migracja `db/migrations/012_compliance_evidence.sql`, 6.11.2026). Ten plik zawiera tylko reguły i zapytania kontrolne. Dzięki temu nie ma drugiej kopii dowodów, która mogłaby się rozjechać z bazą.

## Zasada dla każdej sesji

Przed weryfikacją pól `rodo_compliant`, `dpa_available`, `eu_data_hosting` sprawdź `compliance_evidence` danego narzędzia:

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
- **`rodo_compliant` ✓** — definicja czeka na decyzję (zadanie w Notion: „zdecydować definicję RODO ✓"). Obecne ✓ w starszych wpisach ręcznych (ok. 175 w bazie) nie ma dowodu.
- Brak informacji = NULL, nigdy ✓. Oficjalne strony producenta (privacy, security, trust center, DPA), nie blogi ani katalogi.

## Zapytania kontrolne

```sql
-- dowód nie zgadza się z kolumną (ma być 0 wierszy)
SELECT t.slug, e.key, e.value->'value' AS dowod
FROM tools t, jsonb_each(t.compliance_evidence) e
WHERE nullif(e.value->'value','null'::jsonb) IS DISTINCT FROM
  CASE e.key WHEN 'dpa_available' THEN to_jsonb(t.dpa_available)
             WHEN 'eu_data_hosting' THEN to_jsonb(t.eu_data_hosting)
             WHEN 'rodo_compliant' THEN to_jsonb(t.rodo_compliant) END;

-- pola z wartością bez dowodu (do weryfikacji)
SELECT slug FROM tools
WHERE (dpa_available IS NOT NULL AND NOT compliance_evidence ? 'dpa_available')
   OR (eu_data_hosting IS NOT NULL AND NOT compliance_evidence ? 'eu_data_hosting');

-- dowody starsze niż rok
SELECT slug, compliance_checked_at FROM tools
WHERE compliance_checked_at < now() - interval '1 year';
```

Uwaga: `value: null` w jsonb to nie SQL NULL, stąd `nullif(…,'null'::jsonb)`; porównanie bez tego zgłasza fałszywe rozjazdy.

## Stan po wdrożeniu (6.11.2026)

41 narzędzi, 57 pól z dowodem (przegląd z 6.11.2026 na stronach producentów). Bez dowodu zostają: ok. 175 × `rodo_compliant` ✓, 23 × `dpa_available` ✓ i 21 × `eu_data_hosting` ≠ NULL (m.in. Dust, ElevenLabs, tl;dv, Weaviate, UiPath, Grammarly Business, Brand24, Parloa, SentiOne, Senuto, Lex Expert AI, humai — część sprawdzana wcześniej, ale bez zapisanego adresu). Brak dowodu nie znaczy, że wartość jest błędna, tylko że nie ma jak tego odróżnić.

Niedokończone sprawdzenia i decyzja o definicji RODO ✓ są w Notion (zadania P001).

## Panel admina

Panel jeszcze nie pokazuje ani nie zapisuje tych kolumn (to osobny krok). Do tego czasu edycja pól compliance w panelu **nie aktualizuje dowodu** — po takiej zmianie uruchom zapytanie kontrolne o rozjazd.
