-- 013_ai_act_risk_evidence.sql
-- ai_act_risk dostaje to samo uzasadnienie w rekordzie co pola RODO/DPA/hosting UE (ADR-015, 19.11.2026).
--
-- Powod: do 31.10.2026 trigger promote_scrape_to_tools() wstawial ai_act_risk = 'minimal' na sztywno,
-- wiec ok. 185 wpisow z potoku mialo 'minimal' bez zadnej oceny. Przeglad 19.11.2026 zapisal dla
-- kazdego wpisu ocene i uzasadnienie w compliance_evidence.ai_act_risk (+ publiczna notke w ai_act_notes).
-- Zmiana tylko w opisach kolumn (COMMENT ON COLUMN): schemat i dane bez zmian.
--
-- Kontrakt: compliance_evidence.ai_act_risk = { "value": "minimal|limited|high|unacceptable|null",
--   "url": "...", "note": "...", "checked": "RRRR-MM-DD" }. Zasada waznosci jak dla pozostalych pol:
--   dowod jest wazny tylko gdy evidence.value = aktualna wartosc kolumny.
-- Panel: pole "miekkie" (admin/compliance_evidence.php, COMPLIANCE_SOFT_FIELDS) - zmiana bez dowodu
--   nie jest odrzucana, daje ostrzezenie, a wpis zostaje w kolejce przegladu.

COMMENT ON COLUMN tools.ai_act_risk IS
  'Ocena redakcji ryzyka AI Act (ADR-014, ADR-015), nie potwierdzona klasyfikacja prawna. minimal / limited / high / unacceptable; NULL = nie oceniono lub nie da sie ocenic. limited: produkt glowny rozmawia z osobami spoza firmy uzytkownika albo generuje tresci syntetyczne (art. 50); high: ocenia pojedynczych pracownikow lub wnioskuje o emocjach (zalacznik III pkt 4). Uzasadnienie: compliance_evidence.ai_act_risk. Wartosc inna niz NULL bez wpisu = brak uzasadnienia (do przegladu). Trigger promote_scrape_to_tools() wstawia NULL.';

COMMENT ON COLUMN tools.ai_act_notes IS
  'Publiczna notka do oceny ai_act_risk, wyswietlana w FAQ na stronie narzedzia tylko gdy ai_act_risk nie jest NULL. Wzor: "Wstepna ocena redakcji: ...". Powod oceny dla redakcji jest w compliance_evidence.ai_act_risk.note, nie tutaj.';

COMMENT ON COLUMN tools.compliance_evidence IS
  'Dowody weryfikacji rodo_compliant / dpa_available / eu_data_hosting oraz ai_act_risk. Klucz = nazwa kolumny, wartosc = {value, url, note, checked}. Wpis istnieje = pole bylo sprawdzone, NIE weryfikuj go ponownie bez powodu (rok od checked lub zmiana u producenta). Dowod jest wazny tylko gdy evidence.value = aktualna wartosc kolumny. value null = sprawdzono, nie da sie ocenic / strona nie mowi nic o tym polu. Wartosc kolumny inna niz NULL bez wpisu = brak dowodu (starszy wpis lub zapis bez uzasadnienia). Przy zmianie pola ZAWSZE aktualizuj tez wpis (value, url, note, checked) i compliance_checked_at; panel wymusza to dla pol RODO/DPA/hosting, a dla ai_act_risk tylko ostrzega. Reguly i zapytania kontrolne: docs/COMPLIANCE-EVIDENCE.md.';
