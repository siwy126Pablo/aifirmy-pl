-- 010_rls_stage4c_tools.sql
-- RLS, etap 4c z 4 (ADR-012): tools — ostatnia tabela bez RLS.
--
-- Stan przed: RLS wyłączone, rola anon ma pełne uprawnienia (łącznie z INSERT,
-- UPDATE, DELETE i TRUNCATE) — każdy z publicznym kluczem mógł zmienić lub
-- skasować cały katalog, a także czytać wpisy niepubliczne (status rejected).
--
-- Kto korzysta z tabeli (stan na 4179b4a):
--   - odczyt anon, build Astro (index, kategoria/[slug], narzedzia/index,
--     narzedzia/[slug], w tym "Podobne narzędzia" i dopasowanie po tagach przez
--     tool_tags -> tools!inner): wszystkie zapytania mają status = 'approved'
--     i select('*') + embedy (categories, premium_listings, affiliate_links)
--   - zapisy i odczyty panelu (admin/index.php: lista, "Dodaj wpis", soft delete;
--     admin/api.php: tool_update, soft_delete; admin/affiliate.php: lista do
--     selecta; admin/verify_tool.php): serwer, klucz service_role (BYPASSRLS)
--   - trigger trg_promote_to_tools (INSERT do tools) i trg_tools_updated: działają
--     jako wywołujący, czyli service_role albo postgres — obie role omijają RLS
--   - scraper/url_audit.php: odczyt kluczem z workflow (service_role)
-- Statusy w danych: approved, rejected (pending/premium nie występują).
--
-- Dlatego: RLS włączone; anon czyta WYŁĄCZNIE wiersze approved. Kolumny zostają
-- bez zawężenia (GRANT SELECT na całą tabelę), bo build używa select('*') —
-- zawężenie wymagałoby zmiany zapytań w 4 plikach .astro, poza zakresem tego
-- etapu. Brak jakichkolwiek polityk zapisu; INSERT/UPDATE/DELETE/TRUNCATE
-- odebrane w GRANT. Wpisy rejected/pending przestają być widoczne publicznie.
--
-- Idempotentna. Bez FORCE ROW LEVEL SECURITY.

ALTER TABLE public.tools ENABLE ROW LEVEL SECURITY;

REVOKE ALL ON public.tools FROM anon, authenticated;
GRANT SELECT ON public.tools TO anon, authenticated;

DROP POLICY IF EXISTS tools_public_read ON public.tools;
CREATE POLICY tools_public_read ON public.tools
  FOR SELECT TO anon, authenticated USING (status = 'approved');

-- Wycofanie (gdyby katalog zniknął ze strony albo panel przestał działać):
--   ALTER TABLE public.tools DISABLE ROW LEVEL SECURITY;
--   GRANT ALL ON public.tools TO anon, authenticated;
--   (polityka może zostać — przy wyłączonym RLS nie działa)
