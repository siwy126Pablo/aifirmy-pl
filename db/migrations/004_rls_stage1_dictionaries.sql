-- 004_rls_stage1_dictionaries.sql
-- RLS, etap 1 z 4 (audyt 25.10.2026): tabele-słowniki categories, tags, tool_tags.
--
-- Stan przed: RLS wyłączone, rola anon ma pełne uprawnienia (łącznie z DELETE
-- i TRUNCATE) — każdy z kluczem anon mógł zmienić lub wyczyścić te tabele.
--
-- Dostęp w kodzie (stan na f4a3d7e) — wyłącznie ODCZYT kluczem anon:
--   - build Astro: index, kategoria/[slug], narzedzia/index, narzedzia/[slug]
--     (embed categories(...) i tool_tags(tags(...)))
--   - panel PHP: admin/index.php (lista kategorii, filtr, embed), verify_tool.php
--   - trigger promote_scrape_to_tools() czyta categories z uprawnieniami
--     wywołującego (panel -> anon), więc anon musi mieć SELECT na categories
-- Zapisów do tych trzech tabel w kodzie NIE MA (strona, panel, scraper, webhook).
-- Dlatego: publiczny SELECT i brak jakichkolwiek polityk zapisu. Zmiany słowników
-- robi się z SQL Editora / roli service_role (działa z pominięciem RLS).
-- Dług do etapu 2: gdyby panel miał kiedyś edytować kategorie lub tagi, trzeba
-- go przełączyć na klucz service_role — NIE dodawać polityk zapisu dla anon.
--
-- Poza zakresem (etapy 2–4): tools, premium_listings, scrape_queue,
-- affiliate_links, klucze, kod panelu i scrapera.
--
-- Idempotentna (można uruchomić ponownie). Bez FORCE ROW LEVEL SECURITY —
-- właściciel tabel (postgres, SQL Editor) ma dalej działać bez polityk.

ALTER TABLE public.categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tags       ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tool_tags  ENABLE ROW LEVEL SECURITY;

-- RLS nie obejmuje TRUNCATE, REFERENCES ani TRIGGER, więc uprawnienia tabel
-- zawężamy też przez GRANT: tylko SELECT dla ról API.
REVOKE ALL ON public.categories, public.tags, public.tool_tags FROM anon, authenticated;
GRANT SELECT ON public.categories, public.tags, public.tool_tags TO anon, authenticated;

DROP POLICY IF EXISTS categories_public_read ON public.categories;
CREATE POLICY categories_public_read ON public.categories
  FOR SELECT TO anon, authenticated USING (true);

DROP POLICY IF EXISTS tags_public_read ON public.tags;
CREATE POLICY tags_public_read ON public.tags
  FOR SELECT TO anon, authenticated USING (true);

DROP POLICY IF EXISTS tool_tags_public_read ON public.tool_tags;
CREATE POLICY tool_tags_public_read ON public.tool_tags
  FOR SELECT TO anon, authenticated USING (true);

-- Wycofanie (gdyby strona lub panel przestały działać):
--   ALTER TABLE public.categories DISABLE ROW LEVEL SECURITY;
--   ALTER TABLE public.tags       DISABLE ROW LEVEL SECURITY;
--   ALTER TABLE public.tool_tags  DISABLE ROW LEVEL SECURITY;
--   GRANT ALL ON public.categories, public.tags, public.tool_tags TO anon, authenticated;
--   (polityki mogą zostać — przy wyłączonym RLS nie działają)
