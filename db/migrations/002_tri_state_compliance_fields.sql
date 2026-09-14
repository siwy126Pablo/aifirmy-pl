-- Migracja modelu danych rodo_compliant/dpa_available/eu_data_hosting
-- z boolean 2-stanowego na 3-stanowy (true/false/NULL = nie zweryfikowano).
-- Wykonana ręcznie w Supabase SQL Editor 2026-09-14 (konwencja projektu:
-- zmiany schematu przez SQL Editor, nie przez Claude Code). Ten plik to
-- wierny zapis historyczny faktycznie wykonanej transakcji.

BEGIN;

-- Schemat: dopuszczenie NULL jako trzeciego stanu
ALTER TABLE tools ALTER COLUMN rodo_compliant DROP NOT NULL;
ALTER TABLE tools ALTER COLUMN rodo_compliant DROP DEFAULT;
ALTER TABLE tools ALTER COLUMN dpa_available DROP DEFAULT;
ALTER TABLE tools ALTER COLUMN eu_data_hosting DROP DEFAULT;

-- Backfill — tylko tam, gdzie false było artefaktem defaultu, nie decyzją
UPDATE tools SET rodo_compliant = NULL
WHERE rodo_compliant = false AND source <> 'manual';

UPDATE tools SET dpa_available = NULL
WHERE dpa_available = false;

UPDATE tools SET eu_data_hosting = NULL
WHERE eu_data_hosting = false;
-- Świadomie NIE dotykamy: 15 wierszy source='manual' z rodo_compliant=false

-- Trigger: jedyna zmiana to rodo_compliant `false` → `NULL` w VALUES
CREATE OR REPLACE FUNCTION public.promote_scrape_to_tools()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
DECLARE
  v_category_id UUID;
  v_slug TEXT;
  v_domain TEXT;
  v_logo_url TEXT;
BEGIN
  IF NEW.stage = 'published' AND OLD.stage != 'published' THEN
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
      NULL,
      'minimal',
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

COMMIT;
