-- Linki afiliacyjne dla narzędzi w katalogu (np. ClickUp przez PartnerStack).
-- Pozwala dodawać/edytować/dezaktywować programy partnerskie bez zmian w kodzie.

CREATE TABLE affiliate_links (
  id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  tool_id          UUID        NOT NULL REFERENCES tools(id) ON DELETE CASCADE,
  program_name     TEXT        NOT NULL,
  affiliate_url    TEXT        NOT NULL,
  commission_note  TEXT,
  disclosure_text  TEXT        NOT NULL DEFAULT 'Ten link jest linkiem afiliacyjnym — możemy otrzymać prowizję.',
  active           BOOLEAN     NOT NULL DEFAULT true,
  created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_affiliate_links_tool_id ON affiliate_links(tool_id);

-- Funkcja triggera nie była dotąd zcommitowana do repo (schemat tools powstał
-- bezpośrednio w Supabase Studio) — CREATE OR REPLACE, żeby nie kolidować,
-- jeśli w bazie istnieje już funkcja o tej samej nazwie i tym samym zachowaniu.
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_affiliate_links_updated_at
BEFORE UPDATE ON affiliate_links
FOR EACH ROW
EXECUTE FUNCTION set_updated_at();
