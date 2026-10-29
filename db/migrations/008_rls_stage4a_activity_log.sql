-- 008_rls_stage4a_activity_log.sql
-- RLS, etap 4a z 4 (ADR-012): sprzątanie activity_log.
--
-- Stan przed: RLS włączone od września (migracja ręczna), ale z dwiema politykami
-- dla anon: INSERT WITH CHECK (true) i SELECT USING (true) — każdy z publicznym
-- kluczem mógł dopisywać fałszywe wpisy do logów (ostrzeżenie dashboardu
-- "RLS Policy Always True") i czytać je wszystkie. Poza tym rola anon miała pełne
-- GRANT-y, w tym UPDATE/DELETE (bronił tylko brak polityk) i TRUNCATE, którego
-- RLS w ogóle nie obejmuje.
--
-- Kto korzysta z tabeli (stan na 4179b4a, sprawdzone w kodzie i w logach Supabase):
--   - zapis: scraper/lib/logging.php (GitHub Actions) i admin/verify_tool.php
--     (serwer) — oba przez sb_post z kluczem service_role; w logach Supabase od
--     28.10 19:50 UTC zero zapytań anon na tę tabelę
--   - odczyt: admin/logs.php — od 4179b4a przez admin/supabase.php, klucz
--     service_role (wcześniej własne helpery na kluczu anon)
-- Frontend publiczny tabeli nie dotyka.
--
-- Dlatego: usunięcie obu polityk anon i REVOKE ALL dla anon/authenticated. RLS
-- zostaje włączone; service_role i postgres omijają je (BYPASSRLS). Skutek dla
-- anon: "permission denied" (HTTP 401). Advisors dostanie INFO
-- rls_enabled_no_policy na activity_log — zamierzone, tak jak na scrape_queue.
--
-- WARUNEK: wdrożony 4179b4a (logs.php na service_role) i panel z działającym
-- kluczem w db.php. Na fallbacku anon podgląd logów przestanie działać.
--
-- Idempotentna. Bez FORCE ROW LEVEL SECURITY.

ALTER TABLE public.activity_log ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "anon can insert logs" ON public.activity_log;
DROP POLICY IF EXISTS "anon can read logs"   ON public.activity_log;

REVOKE ALL ON public.activity_log FROM anon, authenticated;

-- Wycofanie (gdyby logi przestały się zapisywać lub czytać):
--   GRANT SELECT, INSERT ON public.activity_log TO anon;
--   CREATE POLICY "anon can insert logs" ON public.activity_log FOR INSERT TO anon WITH CHECK (true);
--   CREATE POLICY "anon can read logs"   ON public.activity_log FOR SELECT TO anon USING (true);
