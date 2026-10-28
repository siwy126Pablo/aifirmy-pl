-- 007_rls_stage3c_premium_listings.sql
-- RLS, etap 3c z 4 (ADR-012): premium_listings.
--
-- Stan przed: RLS wyłączone, rola anon ma pełne uprawnienia (łącznie z INSERT,
-- UPDATE i DELETE) — każdy z kluczem anon mógł sobie "kupić" pakiet premium
-- (wstawić wiersz z przyszłym ends_at), skasować cudze albo przeczytać payment_id
-- (id subskrypcji Stripe) i ceny.
--
-- Kto korzysta z tabeli (stan na 2bbb863):
--   - odczyt anon, build Astro (index, kategoria/[slug], narzedzia/index,
--     narzedzia/[slug], lib/premium.ts): embed premium_listings(id, plan, ends_at)
--     w zapytaniu o tools; strona uznaje pakiet za aktywny, gdy ends_at > teraz
--   - zapisy: stripe/webhook.php (INSERT przy checkout.session.completed, PATCH
--     ends_at przy customer.subscription.deleted), serwer, klucz service_role
--     z private_html/config/db.php (BYPASSRLS). Webhook wstawia tool_id = NULL,
--     tool_id uzupełnia się ręcznie po zakupie
--   - panel admina tej tabeli nie dotyka
--
-- Dlatego: RLS włączone; anon czyta WYŁĄCZNIE wiersze z ends_at > now()
-- (wygasłe nie są stronie potrzebne) i tylko cztery kolumny (GRANT na kolumny).
-- payment_id, price_pln, starts_at, paid_at, created_at zostają niewidoczne
-- publicznie. tool_id jest potrzebne do złączenia z tools. Brak polityk zapisu.
-- SELECT * rolą anon da "permission denied" — build wskazuje kolumny jawnie.
--
-- Idempotentna. Bez FORCE ROW LEVEL SECURITY.

ALTER TABLE public.premium_listings ENABLE ROW LEVEL SECURITY;

REVOKE ALL ON public.premium_listings FROM anon, authenticated;
GRANT SELECT (id, tool_id, plan, ends_at)
  ON public.premium_listings TO anon, authenticated;

DROP POLICY IF EXISTS premium_listings_public_read ON public.premium_listings;
CREATE POLICY premium_listings_public_read ON public.premium_listings
  FOR SELECT TO anon, authenticated USING (ends_at > now());

-- Wycofanie (gdyby oznaczenia premium zniknęły ze strony albo webhook nie mógł pisać):
--   ALTER TABLE public.premium_listings DISABLE ROW LEVEL SECURITY;
--   GRANT ALL ON public.premium_listings TO anon, authenticated;
--   (polityka może zostać — przy wyłączonym RLS nie działa)
