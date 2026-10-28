-- 006_rls_stage3b_affiliate_links.sql
-- RLS, etap 3b z 4 (ADR-012): affiliate_links.
--
-- Stan przed: RLS wyłączone, rola anon ma pełne uprawnienia (łącznie z DELETE
-- i TRUNCATE) — każdy z kluczem anon mógł podmienić affiliate_url na własny
-- (przejęcie prowizji) albo przeczytać commission_note.
--
-- Kto korzysta z tabeli (stan na 2bbb863):
--   - odczyt anon, build Astro (index, kategoria/[slug], narzedzia/index,
--     narzedzia/[slug]): embed w zapytaniu o tools —
--     affiliate_links(affiliate_url, disclosure_text, active); strona szuka
--     pierwszego linku z active = true
--   - zapisy i odczyt panelu (admin/affiliate.php, admin/api.php op
--     affiliate_active): serwer, klucz service_role (BYPASSRLS)
-- Zapisów kluczem anon w kodzie nie ma.
--
-- Dlatego: RLS włączone; anon czyta WYŁĄCZNIE aktywne linki (polityka) i tylko
-- cztery kolumny potrzebne stronie (GRANT na kolumny). commission_note,
-- program_name i znaczniki czasu zostają niewidoczne publicznie. tool_id jest
-- potrzebne do złączenia z tools. Brak jakichkolwiek polityk zapisu.
-- SELECT * na tej tabeli rolą anon da "permission denied" — build zawsze
-- wskazuje kolumny jawnie.
--
-- Idempotentna. Bez FORCE ROW LEVEL SECURITY.

ALTER TABLE public.affiliate_links ENABLE ROW LEVEL SECURITY;

REVOKE ALL ON public.affiliate_links FROM anon, authenticated;
GRANT SELECT (tool_id, affiliate_url, disclosure_text, active)
  ON public.affiliate_links TO anon, authenticated;

DROP POLICY IF EXISTS affiliate_links_public_read ON public.affiliate_links;
CREATE POLICY affiliate_links_public_read ON public.affiliate_links
  FOR SELECT TO anon, authenticated USING (active);

-- Wycofanie (gdyby linki afiliacyjne zniknęły ze strony):
--   ALTER TABLE public.affiliate_links DISABLE ROW LEVEL SECURITY;
--   GRANT ALL ON public.affiliate_links TO anon, authenticated;
--   (polityka może zostać — przy wyłączonym RLS nie działa)
