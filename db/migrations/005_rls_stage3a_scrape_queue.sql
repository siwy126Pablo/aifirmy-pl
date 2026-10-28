-- 005_rls_stage3a_scrape_queue.sql
-- RLS, etap 3a z 4 (ADR-012): scrape_queue.
--
-- Stan przed: RLS wyłączone, rola anon ma pełne uprawnienia (łącznie z DELETE
-- i TRUNCATE) — każdy z kluczem anon mógł przeczytać, zmienić lub wyczyścić kolejkę.
--
-- Kto korzysta z tabeli (stan na 2bbb863, sprawdzone w kodzie i w logach Supabase):
--   - scraper PHP (GitHub Actions): GET dedup + POST insert, klucz service_role
--     z sekretu SUPABASE_SERVICE_ROLE_KEY (scraper/lib/supabase.php -> sb_key())
--   - panel admina (serwer, admin/index.php + admin/api.php): GET/PATCH/DELETE,
--     klucz service_role z private_html/config/db.php (admin/supabase.php)
--   - trigger trg_promote_to_tools -> promote_scrape_to_tools() (SECURITY INVOKER):
--     działa jako wywołujący, czyli service_role (panel) lub postgres (SQL Editor);
--     obie role omijają RLS (BYPASSRLS), więc trigger działa bez zmian
-- Frontend publiczny (build Astro) NIE czyta scrape_queue. W logach Supabase
-- z ostatniej doby nie ma żadnych zapytań rolą anon do tej tabeli.
--
-- Dlatego: RLS włączone, BEZ jakichkolwiek polityk, a uprawnienia tabeli dla ról
-- API odebrane w całości (RLS nie obejmuje TRUNCATE, REFERENCES ani TRIGGER).
-- Skutek dla anon: "permission denied for table scrape_queue" (HTTP 401/403),
-- nie pusty wynik. service_role i postgres mają BYPASSRLS i własne GRANT-y.
--
-- Idempotentna. Bez FORCE ROW LEVEL SECURITY (właściciel tabeli ma dalej działać).

ALTER TABLE public.scrape_queue ENABLE ROW LEVEL SECURITY;

REVOKE ALL ON public.scrape_queue FROM anon, authenticated;

-- Wycofanie (gdyby scraper lub panel przestały działać):
--   ALTER TABLE public.scrape_queue DISABLE ROW LEVEL SECURITY;
--   GRANT ALL ON public.scrape_queue TO anon, authenticated;
