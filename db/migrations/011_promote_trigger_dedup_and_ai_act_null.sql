-- 011_promote_trigger_dedup_and_ai_act_null.sql
-- Trigger promote_scrape_to_tools(): dwie poprawki (zadanie z backlogu, 31.10.2026).
--
-- (a) ai_act_risk: nowe wpisy z potoku dostają NULL (= nie zweryfikowano)
--     zamiast stałego 'minimal'. Stała etykieta trafiała na stronę publicznie
--     (plakietka + zdanie w FAQ JSON-LD "klasyfikowane jest jako ryzyko minimalne
--     zgodnie z AI Act") bez żadnej weryfikacji. scrape_queue.ai_act_risk było
--     puste we wszystkich wierszach, więc nic nie jest tracone. Kolumna
--     tools.ai_act_risk jest nullable, a CHECK dopuszcza NULL. Frontend
--     (CompanyCard, narzedzia/[slug]) pomija plakietkę i FAQ przy pustej wartości.
--     ISTNIEJĄCE wiersze NIE są ruszane — Paweł sprawdza je osobno, jeden po drugim.
--
-- (c) Ochrona przed duplikatem. Wcześniej jedyną barierą był UNIQUE(slug), a pętla
--     slugów po cichu dokładała "-NNN", więc ponowne opublikowanie wiersza kolejki
--     tworzyło drugą kartę. Teraz, gdy:
--       - scrape_queue.tool_id wskazuje istniejący wiersz tools, albo
--       - w tools jest już wiersz z tym samym source_url (niepustym),
--     trigger NIE wstawia nowego wiersza. Jeśli kolejka nie miała tool_id,
--     dostaje wskazanie na istniejące narzędzie (publikacja nadal => tool_id).
--     Zamiast RAISE EXCEPTION jest RAISE NOTICE: wyjątek w AFTER UPDATE cofnąłby
--     zmianę stage i panel pokazałby błąd przy zwykłym ponownym kliknięciu.
--     UWAGA: status istniejącego narzędzia nie jest zmieniany. Jeśli było
--     'rejected', ponowna publikacja go nie przywraca (osobna decyzja).
--
-- Bez zmian: warunek wyzwolenia, mapowanie kategorii, pętla slugów, favicon
-- liczony z source_url, rodo_compliant = NULL, search_path (migracja 009).
-- Kolumny website_url NIE ruszamy tutaj: naprawa linków Product Hunt idzie u źródła.
--
-- Idempotentna (CREATE OR REPLACE).

CREATE OR REPLACE FUNCTION public.promote_scrape_to_tools()
 RETURNS trigger
 LANGUAGE plpgsql
 SET search_path TO 'public', 'pg_temp'
AS $function$
DECLARE
  v_category_id UUID;
  v_slug TEXT;
  v_domain TEXT;
  v_logo_url TEXT;
  v_existing_id UUID;
BEGIN
  IF NEW.stage = 'published' AND OLD.stage != 'published' THEN

    -- (c) Ochrona przed duplikatem: najpierw tool_id z kolejki, potem source_url.
    IF NEW.tool_id IS NOT NULL THEN
      SELECT id INTO v_existing_id FROM tools WHERE id = NEW.tool_id;
    END IF;

    IF v_existing_id IS NULL AND COALESCE(NEW.source_url, '') != '' THEN
      SELECT id INTO v_existing_id
      FROM tools
      WHERE source_url = NEW.source_url
      LIMIT 1;
    END IF;

    IF v_existing_id IS NOT NULL THEN
      IF NEW.tool_id IS DISTINCT FROM v_existing_id THEN
        UPDATE scrape_queue SET tool_id = v_existing_id WHERE id = NEW.id;
      END IF;
      RAISE NOTICE 'promote_scrape_to_tools: pominięto duplikat (queue %, tool %)', NEW.id, v_existing_id;
      RETURN NEW;
    END IF;

    SELECT id INTO v_category_id
    FROM categories
    WHERE name_pl ILIKE '%' || NEW.ai_category || '%'
    LIMIT 1;

    v_slug := slugify(COALESCE(NEW.name, NEW.raw_name, 'narzedzie'));
    WHILE EXISTS (SELECT 1 FROM tools WHERE slug = v_slug) LOOP
      v_slug := v_slug || '-' || floor(random() * 1000)::text;
    END LOOP;

    IF COALESCE(NEW.source_url, '') != '' THEN
      v_domain := regexp_replace(
        regexp_replace(NEW.source_url, '^https?://(www\.)?', ''),
        '/.*$', ''
      );
      v_logo_url := 'https://www.google.com/s2/favicons?domain=' || v_domain || '&sz=128';
    ELSE
      v_logo_url := NULL;
    END IF;

    INSERT INTO tools (
      slug, name, description_pl, website_url, category_id, pricing_model,
      rodo_compliant, ai_act_risk, status, source, source_url, logo_url, best_for_pl
    ) VALUES (
      v_slug,
      COALESCE(NEW.name, NEW.raw_name, 'Bez nazwy'),
      NEW.ai_description,
      COALESCE(NEW.source_url, ''),
      v_category_id,
      COALESCE(NEW.ai_pricing_model, 'freemium'),
      NULL,               -- NULL = nie zweryfikowano (migracja 002)
      NULL,               -- NULL = nie zweryfikowano (było 'minimal', migracja 011)
      'approved',
      NEW.source_name,
      NEW.source_url,
      v_logo_url,
      NEW.best_for_pl
    );

    UPDATE scrape_queue
    SET tool_id = (SELECT id FROM tools WHERE slug = v_slug)
    WHERE id = NEW.id;
  END IF;
  RETURN NEW;
END;
$function$;
