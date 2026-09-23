-- 003_tri_state_pl_fields.sql
-- Migracja has_pl_ui/has_pl_support na model tri-state (NULL = nie zweryfikowano)
-- Analogicznie do 002_tri_state_compliance_fields.sql (rodo_compliant/dpa_available/eu_data_hosting)
-- Kolumny byly juz nullable (is_nullable=YES) -- bez DROP NOT NULL, w przeciwienstwie do 002

ALTER TABLE tools ALTER COLUMN has_pl_ui SET DEFAULT NULL;
ALTER TABLE tools ALTER COLUMN has_pl_support SET DEFAULT NULL;

-- Backfill -- wszystkie 339 istniejacych wierszy mialo false wylacznie jako
-- artefakt starego defaultu kolumny, zadne nie bylo realna, reczna decyzja
-- (potwierdzone: brak jakiejkolwiek sciezki edycji tych pol przed ta migracja)
UPDATE tools SET has_pl_ui = NULL WHERE has_pl_ui = false;
UPDATE tools SET has_pl_support = NULL WHERE has_pl_support = false;
