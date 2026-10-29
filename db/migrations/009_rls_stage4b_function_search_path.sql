-- 009_rls_stage4b_function_search_path.sql
-- RLS, etap 4b z 4 (ADR-012): stały search_path w 3 funkcjach (advisors:
-- function_search_path_mutable, 3x WARN).
--
-- Stan przed: set_updated_at(), slugify(text) i promote_scrape_to_tools() nie mają
-- ustawionego search_path, więc nazwy nieprzypisane do schematu rozwiązują się
-- według ścieżki wywołującego (można je podmienić obiektem we własnym schemacie
-- lub w pg_temp).
--
-- Zmiana tylko przez ALTER FUNCTION ... SET — ciała funkcji zostają nietknięte.
--   - set_updated_at, slugify: używają wyłącznie wbudowanych now(), lower(),
--     regexp_replace(), translate() z pg_catalog, który jest zawsze przeszukiwany
--     jako pierwszy, więc search_path = '' jest bezpieczne
--   - promote_scrape_to_tools: odwołuje się do categories, tools, scrape_queue
--     i slugify() bez schematu, więc dostaje search_path = public, pg_temp
--     (pg_temp na końcu, żeby tabele tymczasowe nie przesłaniały tabel z public)
-- Funkcja trigger pozostaje SECURITY INVOKER (działa jako service_role / postgres).
--
-- Idempotentna.

ALTER FUNCTION public.set_updated_at()            SET search_path = '';
ALTER FUNCTION public.slugify(text)               SET search_path = '';
ALTER FUNCTION public.promote_scrape_to_tools()   SET search_path = public, pg_temp;

-- Wycofanie:
--   ALTER FUNCTION public.set_updated_at()          RESET search_path;
--   ALTER FUNCTION public.slugify(text)             RESET search_path;
--   ALTER FUNCTION public.promote_scrape_to_tools() RESET search_path;
