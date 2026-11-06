-- 012_compliance_evidence.sql
-- Dowody weryfikacji pól rodo_compliant / dpa_available / eu_data_hosting trzymane
-- RAZEM Z DANYMI (6.11.2026).
--
-- Problem: tagi ✓/✗ w tools nie miały zapisanego źródła ani daty sprawdzenia, więc
-- przy kolejnej sesji nie dało się odróżnić wpisu sprawdzonego od ustawionego "z głowy"
-- i weryfikacja szłaby od nowa. Dowód w pliku md / Notion / pamięci czatu nie rozwiązuje
-- tego: ktoś musiałby pomyśleć, żeby go przeczytać. Baza jest jedynym miejscem, które
-- czyta każdy, kto czyta dane (panel, zapytanie SQL, czat z MCP), a opis kolumny
-- (COMMENT ON COLUMN) widzi każdy, kto ogląda schemat.
--
-- Kształt compliance_evidence (jsonb, obiekt):
--   { "<nazwa kolumny>": { "value": true|false|null, "url": "...", "note": "...", "checked": "RRRR-MM-DD" } }
-- Klucze: dpa_available, eu_data_hosting, rodo_compliant.
-- Zasada ważności: dowód jest ważny tylko, gdy evidence.value = aktualna wartość kolumny.
-- Zapytania kontrolne: docs/COMPLIANCE-EVIDENCE.md.

ALTER TABLE tools
  ADD COLUMN IF NOT EXISTS compliance_evidence jsonb NOT NULL DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS compliance_checked_at date;

ALTER TABLE tools
  ADD CONSTRAINT tools_compliance_evidence_is_object
  CHECK (jsonb_typeof(compliance_evidence) = 'object');

COMMENT ON COLUMN tools.compliance_evidence IS
  'Dowody weryfikacji rodo_compliant / dpa_available / eu_data_hosting. Klucz = nazwa kolumny, wartosc = {value, url, note, checked}. Wpis istnieje = pole bylo sprawdzone na stronie producenta, NIE weryfikuj go ponownie bez powodu (rok od checked lub zmiana u producenta). Dowod jest wazny tylko gdy evidence.value = aktualna wartosc kolumny. value null = sprawdzono, strona nie mowi nic o tym polu. Wartosc kolumny inna niz NULL bez wpisu = brak dowodu (starszy wpis reczny). Przy zmianie pola ZAWSZE aktualizuj tez wpis (value, url, note, checked) i compliance_checked_at. Reguly i zapytania kontrolne: docs/COMPLIANCE-EVIDENCE.md.';
COMMENT ON COLUMN tools.compliance_checked_at IS
  'Data ostatniego sprawdzenia ktoregokolwiek z pol rodo/dpa/eu_hosting na stronie producenta (zob. compliance_evidence).';
COMMENT ON COLUMN tools.rodo_compliant IS
  '3 stany: true/false/NULL (NULL = nie zweryfikowano). Reczne. Dla starszych wpisow recznych ok. 80 x true bez udokumentowanego dowodu; definicja czeka na decyzje. Dowody: compliance_evidence.';
COMMENT ON COLUMN tools.dpa_available IS
  '3 stany. true tylko gdy strona producenta (Legal/Trust/Security) ma wlasny dokument DPA. NULL = nie zweryfikowano lub nie znaleziono. Dowody: compliance_evidence.';
COMMENT ON COLUMN tools.eu_data_hosting IS
  '3 stany, regula A: true gdy region UE do wyboru przy zakladaniu konta lub domyslny; false gdy UE to platny dodatek/enterprise albo domyslnie poza UE; NULL = brak informacji. Dowody: compliance_evidence.';

-- Backfill: dowody z przegladu 6.11.2026 (strony producentow sprawdzone w Chrome).
-- Adres url = null tam, gdzie adresu nie zapisano w trakcie przegladu.
WITH v(slug, field, val, url, note) AS (VALUES
 ('make-integromat','dpa_available','true','https://www.make.com/en/privacy-and-gdpr','strona GDPR: DPA i lista subprocesorow'),
 ('make-integromat','eu_data_hosting','null','https://www.make.com/en/security','tylko AWS, bez lokalizacji'),
 ('onetrust','dpa_available','true','https://www.onetrust.com/trust/','Trust Center: DPA/SCC i subprocesorzy'),
 ('onetrust','eu_data_hosting','null','https://www.onetrust.com/trust/','lokalizacji hostingu nie znaleziono'),
 ('rossum','dpa_available','null','https://rossum.ai/intelligent-document-processing/security-and-trust/','DPA nie znaleziono (jest DPO i polityki zgodne z GDPR); baner: Coupa przejmuje Rossum'),
 ('rossum','eu_data_hosting','null','https://rossum.ai/intelligent-document-processing/security-and-trust/','wzmianka o opcji roznych regionow AWS, bez jednoznacznej deklaracji UE'),
 ('mixpanel','eu_data_hosting','false','https://docs.mixpanel.com/docs/privacy/eu-residency','domyslnie serwery w USA (GCP), UE tylko w opcjonalnym programie EU Data Residency'),
 ('n8n','dpa_available','true','https://n8n.io/legal/data-processing-agreement/','gotowy DPA do pobrania'),
 ('n8n','eu_data_hosting','true','https://n8n.io/legal/security/','n8n cloud na Microsoft Azure, hosting w UE; firma z Berlina'),
 ('crisp','eu_data_hosting','true','https://crisp.chat/en/privacy/','dane przechowywane w Europie, rdzen infrastruktury w Amsterdamie (wersja polityki z 15.07.2026); DPA nie sprawdzano'),
 ('fieldguide','dpa_available','true','https://www.fieldguide.com/trust','DPA i SCC; website_url poprawiony z fieldguide.ai (inna firma)'),
 ('fieldguide','eu_data_hosting','false','https://www.fieldguide.com/trust','hosting na AWS, centra danych w USA'),
 ('mistral-ai','eu_data_hosting','true','https://help.mistral.ai/','domyslnie dane w UE, endpoint w USA jako opcja (dokladny artykul nie zapisany)'),
 ('framer-ai','dpa_available','true','https://www.framer.com/legal/dpa/','jest strona DPA'),
 ('framer-ai','eu_data_hosting','null','https://www.framer.com/legal/dpa/','lokalizacji hostingu nie znaleziono'),
 ('fathom-analytics','eu_data_hosting','null',NULL,'polityka prywatnosci mowi tylko o transferach poza UE/EOG z SCC, bez deklaracji hostingu'),
 ('assembled','dpa_available','null',NULL,'strona security: tylko GDPR Compliant, bez DPA'),
 ('assembled','eu_data_hosting','null',NULL,'strona security: bez lokalizacji hostingu'),
 ('linear','eu_data_hosting','true','https://linear.app/security','wybor UE lub US przy zakladaniu workspace (regula A)'),
 ('qdrant','eu_data_hosting','true','https://qdrant.tech/security/','dane tylko w regionie klastra, region wybiera klient (regula A)'),
 ('otter-ai','eu_data_hosting','null','https://otter.ai/privacy-security','oswiadczenie GDPR, AWS S3, bez informacji o regionie'),
 ('fathom','dpa_available','true','https://fathom.ai/dpa','dokument DPA'),
 ('fathom','eu_data_hosting','null','https://fathom.ai/privacy','polityka prywatnosci bez deklaracji regionu'),
 ('relevance-ai','dpa_available','true','https://trust.relevanceai.com/','Data Processing Addendum'),
 ('relevance-ai','eu_data_hosting','true','https://trust.relevanceai.com/','podprocesorzy AWS/MongoDB, regiony US/EU/AU do wyboru (regula A)'),
 ('apify','eu_data_hosting','false','https://trust.apify.com/','podprocesorzy w USA: AWS, MongoDB, Snowflake, Mezmo'),
 ('deepl','dpa_available','true','https://deepl.safebase.us/','dokument DPA w trust center'),
 ('deepl','eu_data_hosting','false','https://deepl.safebase.us/','komunikat z 6.02.2026: domyslnie globalnie (AWS UE/US/JP), Data Residency UE jako platny dodatek'),
 ('pigment','eu_data_hosting','true','https://www.pigment.com/security','klient wybiera europe-west3 (Frankfurt) lub us-west1 (Oregon); wlasnego DPA na stronie nie widziano'),
 ('descript','dpa_available','null',NULL,'brak wlasnego DPA na stronie producenta'),
 ('legalsifter','dpa_available','null',NULL,'tylko oswiadczenie GDPR i EU-US DPF, bez DPA'),
 ('langfuse','dpa_available','true','https://langfuse.com/security','DPA'),
 ('langfuse','eu_data_hosting','true','https://langfuse.com/security','regiony US/EU/JP do wyboru (regula A)'),
 ('gladia','dpa_available','true','https://www.gladia.io/security','DPA w stopce'),
 ('gladia','eu_data_hosting','true','https://www.gladia.io/security','domyslnie europejski dostawca, Francja'),
 ('survicate','dpa_available','true','https://survicate.com/gdpr/','link do DPA'),
 ('survicate','eu_data_hosting','true','https://survicate.com/gdpr/','AWS Irlandia'),
 ('leexi','dpa_available','true',NULL,'FAQ compliance-sovereignty: sekcja DPA'),
 ('leexi','eu_data_hosting','true',NULL,'FAQ compliance-sovereignty: AWS Paryz'),
 ('edrone','eu_data_hosting','true','https://help.edrone.me/','artykul 832954: AWS Dublin + Frankfurt; DPA (PDF wp.edrone.me) znaleziony wyszukiwarka, nieotwarty, wiec bez dowodu dla DPA'),
 ('loom','eu_data_hosting','null','https://support.atlassian.com/loom/docs/understand-loom-data-residency/','rezydencja Niemcy ustawiana przy provisioningu od maja 2026; niejednoznaczne czy do wyboru dla kazdego klienta'),
 ('typeform','dpa_available','true','https://www.typeform.com/legal/','Data Processing Agreement'),
 ('exact-online','dpa_available','true','https://www.exact.com/trust/privacy','Data Processing Addendum i lista podprocesorow'),
 ('zapier-ai','dpa_available','true','https://zapier.com/legal/data-processing-addendum','DPA'),
 ('zendesk-ai','dpa_available','true','https://www.zendesk.com/company/data-processing-agreement/','DPA'),
 ('zendesk-ai','eu_data_hosting','null','https://www.zendesk.com/company/data-processing-agreement/','regiony EEA (Irlandia/Frankfurt) opisane w artykule pomocy, ale wybor lokalizacji jako dodatek Data Center Location; niejednoznaczne'),
 ('perplexity-for-business','dpa_available','true','https://www.perplexity.ai/hub/legal/dpa','DPA'),
 ('glean','dpa_available','true','https://www.glean.com/legal/dpa','PDF DPA z 6.03.2026'),
 ('retool-ai','dpa_available','true','https://docs.retool.com/legal/privacy-protection-security','sekcja DPA'),
 ('apollo-io','dpa_available','true','https://www.apollo.io/dpa','DPA'),
 ('looker-studio-google','dpa_available','true','https://support.google.com/looker-studio/answer/7657915','Data Processing Amendment'),
 ('ironclad','dpa_available','true','https://legal.ironcladapp.com/','Data Processing Addendum'),
 ('paradox-ai-olivia','dpa_available','true','https://www.paradox.ai/legal/us-dpa','DPA'),
 ('clearbit-hubspot','dpa_available','true','https://clearbit.com/Clearbit_DPA_20MAR2024.pdf','PDF DPA z marca 2024'),
 ('photoroom','dpa_available','true','https://www.photoroom.com/legal/data-processing-agreement','DPA'),
 ('photoroom','eu_data_hosting','null','https://www.photoroom.com/legal/security','GCP/AWS/Cloudflare, bez informacji o regionie'),
 ('zowie','dpa_available','true','https://www.getzowie.com/data-processing-agreement','DPA')
),
agg AS (
  SELECT slug,
         jsonb_object_agg(field, jsonb_build_object('value', val::jsonb, 'url', url, 'note', note, 'checked', '2026-11-06')) AS ev
  FROM v GROUP BY slug
)
UPDATE tools t
SET compliance_evidence = a.ev,
    compliance_checked_at = DATE '2026-11-06'
FROM agg a
WHERE t.slug = a.slug;
