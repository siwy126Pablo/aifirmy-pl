-- 014: dwie nowe kategorie (ADR-016) + przeniesienie 86 zatwierdzonych wpisów.
--   * "Narzędzia dla programistów i agentów AI" (slug programisci-agenci-ai) — 73 wpisy
--   * "Spotkania i rozmowy" (slug spotkania-rozmowy) — 11 wpisów
--   * Infermedica i Docplanner -> "Obsługa klienta" (zdrowie nie dostaje własnej kategorii)
-- Asercje na końcu: przy innej liczbie wierszy cała migracja się wycofuje.
-- Rollback (kolejność odwrotna, kategorie źródłowe): patrz sekcja ROLLBACK na dole pliku.

INSERT INTO categories (slug, name_pl, icon, sort_order)
VALUES
  ('programisci-agenci-ai', 'Narzędzia dla programistów i agentów AI', 'code', 11),
  ('spotkania-rozmowy',     'Spotkania i rozmowy',                     'microphone', 12)
ON CONFLICT DO NOTHING;

DO $$
DECLARE
  v_dev uuid := (SELECT id FROM categories WHERE slug = 'programisci-agenci-ai');
  v_meet uuid := (SELECT id FROM categories WHERE slug = 'spotkania-rozmowy');
  v_cs uuid := (SELECT id FROM categories WHERE slug = 'obsluga-klienta');
  n int;
BEGIN
  IF v_dev IS NULL OR v_meet IS NULL OR v_cs IS NULL THEN
    RAISE EXCEPTION 'brak kategorii docelowej (dev %, meet %, cs %)', v_dev, v_meet, v_cs;
  END IF;

  UPDATE tools SET category_id = v_dev, updated_at = now()
  WHERE status = 'approved' AND slug IN (
    -- z Automatyzacji procesów (44)
    'agent-relay','aireterag','artifex','botbinio','boxesdev','bullet','byteask','caveman','chatoss',
    'composal','datadog-ai','deltix','documemory','experiential-labs','front-end-skill-pack-for-ai-agents',
    'herdr','hiloop','hindsight-vectorize','hoplite','hue','langfuse','browser-tools-sdk','mistral-ai',
    'momentic','munito-ai','ngrok-ai-gateway','openrelay','papaya','polylane','pomerado','postman-ai',
    'qdrant','revix-ai','sentry','shoehorn','speko','starsling','testera','vespper','weaviate',
    'yolo-auto-api','zenbu','vendo','visoai',
    -- ze Zarządzania projektami (29)
    'agent-fm','aletheionagi','amorphic-labs','belvedir','excalibur','executor','hyperprobe','infrawrench',
    'jcode','linzumi','llamatik-code','machine0','manufact','mirrors','mosaic','mouse','openvisio',
    'orchestra','otis','prized','rystic','superserve','sx-20','taskpeace','tiptap-ai-toolkit','workers-io',
    'contextvault','assemble','architecture-diagram-ai');
  GET DIAGNOSTICS n = ROW_COUNT;
  IF n <> 73 THEN RAISE EXCEPTION 'dev: oczekiwano 73, jest %', n; END IF;

  UPDATE tools SET category_id = v_meet, updated_at = now()
  WHERE status = 'approved' AND slug IN (
    'fathom','grain','otter-ai','tldv','gladia','recall-ai','gong-io','modjo','leexi','fireflies-ai','insight7');
  GET DIAGNOSTICS n = ROW_COUNT;
  IF n <> 11 THEN RAISE EXCEPTION 'spotkania: oczekiwano 11, jest %', n; END IF;

  UPDATE tools SET category_id = v_cs, updated_at = now()
  WHERE status = 'approved' AND slug IN ('infermedica','docplanner-znanylekarz');
  GET DIAGNOSTICS n = ROW_COUNT;
  IF n <> 2 THEN RAISE EXCEPTION 'obsluga: oczekiwano 2, jest %', n; END IF;
END $$;

-- ROLLBACK (kategorie sprzed zmiany; slugi kategorii: automatyzacja, zarzadzanie, sprzedaz-crm, hr-rekrutacja, obsluga-klienta):
--  * do 'zarzadzanie': 29 slugów ze sekcji "ze Zarządzania projektami" oraz fathom, grain, otter-ai, tldv
--  * do 'sprzedaz-crm': gong-io, modjo, leexi
--  * do 'hr-rekrutacja': fireflies-ai, docplanner-znanylekarz
--  * do 'obsluga-klienta': insight7
--  * do 'automatyzacja': 44 slugi ze sekcji "z Automatyzacji procesów", gladia, recall-ai, infermedica
--  * potem DELETE FROM categories WHERE slug IN ('programisci-agenci-ai','spotkania-rozmowy');
