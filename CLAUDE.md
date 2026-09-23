# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 🔗 Project links (use these in every session)

| Resource | URL |
|---|---|
| Notion — project overview | https://www.notion.so/aifirmy-pl-Katalog-AI-i-SaaS-373b4cccb4af81bb9ec5ef0a5ca32318 |
| Notion — session notes / status | https://app.notion.com/p/374b4cccb4af8103afbbc353f9fd300e |
| Notion — LinkedIn drafts | https://app.notion.com/p/3c5b4cccb4af81a08f4cd24070dffab9 |
| GitHub — main repo | https://github.com/siwy126Pablo/aifirmy-pl |
| GitHub — keep-alive ping | https://github.com/siwy126Pablo/aifirmy-ping |
| Supabase — dashboard | https://supabase.com/dashboard/project/szassqzvivdgvpkciyif |
| AWStats (Cyberfolks) | https://s103.cyber-folks.pl:2223/CMD_AWSTATS/aifirmy.pl/index.html |

## 🚀 How to start a new session

Paste this at the beginning of each new chat:

```
Kontynuujemy projekt aifirmy.pl.
Przeczytaj CLAUDE.md i zapoznaj się z aktualnym statusem projektu.
Notion (status): https://app.notion.com/p/374b4cccb4af8103afbbc353f9fd300e
Kontynuujemy: [opisz co robisz].
```

**Before doing anything:** run `git status` and `git log --oneline -5`, then compare against `origin/main` — this project is worked on from two computers and pushes get rejected fairly often (`[rejected] (fetch first)`). Standard fix: `git pull --rebase && git push`. If anything looks unexpected on remote, stop and ask Pablo rather than guessing.

## Project overview

**aifirmy.pl** — a Polish-language catalog and content aggregator for AI tools, SaaS, courses, and startups targeting the PL/EU/global market, with a unique differentiator: every listing is tagged for RODO compliance and EU AI Act risk level — no other Polish AI catalog does this. PLN pricing is shown where available (price_from_pln, ~1% fill rate as of 2026-09, manual-entry only via "Dodaj wpis"), not a systemic differentiator yet. Live on Cyberfolks; monetization infrastructure is live (Stripe live + affiliate), but revenue = 0 as of 2026-09-20. 284 approved tools across **10 categories** as of 2026-09-20.

## Tech stack (current)

| Layer | Technology | Notes |
|---|---|---|
| Frontend | Astro 6 + Tailwind CSS v4 | `frontend/` subdirectory in repo root — always prefix paths with `frontend/` in git commands |
| Backend/API | No dedicated Node/Python backend | ADR-005 closed — business logic lives in PHP (admin, Stripe, email) + direct Supabase REST calls from frontend |
| Database | PostgreSQL — Supabase free (eu-central-1) | Cyberfolks has MariaDB only — incompatible with schema |
| ETL/Scraping | **PHP scraper + GitHub Actions** (`scraper/`) | **4 sources**: HN, BetaList, Product Hunt, YC-OSS API. Migrated from Apache NiFi (self-hosted, Windows) on **2026-09-20** — NiFi flow stopped/decommissioned, kept as reference only. See "ETL pipeline" section below. |
| AI descriptions (pipeline) | OpenAI `gpt-4o-mini` | Prompt returns `best_for_pl` and `is_real_product`; YC source additionally passes real `source_context` (YC one_liner/long_description) instead of guessing from title alone |
| AI verification (admin) | OpenAI `gpt-4o-mini` | `verify_tool.php`, `response_format: json_object`, key in `private_html/config/openai.php` on server (not in repo) |
| Admin panel | PHP + Supabase REST API | `admin/index.php`, `admin/affiliate.php`, `admin/verify_tool.php`, `admin/logs.php` |
| Payments | Stripe (Live mode) | `checkout.php` + `webhook.php` at `/stripe/webhook.php` (Cloudflare WAF blocks POST to `/admin/`) |
| Email | PHPMailer via Cyberfolks SMTP | `s103.cyber-folks.pl:587` |
| Analytics | Google Analytics 4 (`G-3SP1TRXF7M`) + Search Console + AWStats | **GA4 is unreliable** — see "Analytics reliability" section below. Search Console + AWStats are the trusted sources. |
| Hosting | Cyberfolks (frontend) + Cloudflare | SSL Full strict, CDN, DNS, **Redirect Rules (www→apex, added 2026-08-31)** |

**Project location:** `C:\Dev\aifirmy-pl` (moved from `C:\Users\pawel\Praca` — ESET blocked `node_modules` under `C:\Users\`).

## Repository structure

```
aifirmy-pl/
├── frontend/              ← Astro + Tailwind (ALWAYS prefix paths with frontend/)
│   └── src/
│       ├── pages/          ← index, /narzedzia/[slug], /kategoria/[slug]
│       ├── components/     ← CompanyCard.astro, icons/CategoryIcon.astro, CookieConsent.astro
│       ├── lib/             ← category-colors.ts
│       └── layouts/         ← Layout.astro (GA4 script, footer disclosure duplicated per-page — see Known Issues)
├── admin/                  ← PHP admin panel (repo root, NOT under frontend/)
│   ├── index.php            ← main panel: Kolejka / Odrzucone przez AI / Narzędzia / Dodaj wpis
│   ├── affiliate.php        ← affiliate links CRUD
│   ├── verify_tool.php      ← "Zweryfikuj przez AI" endpoint
│   └── logs.php             ← activity_log viewer (filterable/paginated)
├── scraper/                 ← PHP scraper — PRIMARY pipeline since 2026-09-20 (replaces NiFi)
│   ├── run.php               ← dispatcher, `php run.php --source=X`
│   ├── sources/               ← yc_oss.php, hacker_news.php, betalist.php, product_hunt.php
│   ├── lib/                   ← supabase.php (sb_get/sb_post), openai.php (call_openai_description),
│   │                             logging.php (scraper_log), filters.php (shared keyword filter,
│   │                             CANDIDATE_WINDOW_SECONDS), pipeline.php (shared dedup→OpenAI→insert),
│   │                             atom.php (Atom feed parser for BetaList/PH), http.php (GET helper)
│   └── url_audit.php         ← separate tool: quarterly website_url health check (see "Documentation rules")
├── nifi-flows/              ← NiFi flow exports (.json) — HISTORICAL, pipeline decommissioned 2026-09-20.
│                                Kept as reference/fallback, not actively maintained. Export format is a
│                                single-line JSON so `git diff` always shows "1 line changed" even for
│                                large edits (this was normal behavior while NiFi was active — not relevant
│                                for new work, but explains old commit history if you look at it).
├── db/
│   └── migrations/          ← PostgreSQL SQL migrations (not always used — schema changes often made directly in Supabase SQL Editor)
└── docs/                    ← ARCHITECTURE.md, DECISIONS.md, CHANGELOG.md, STATUS.md, SEO.md, CONTENT-GUIDE.md, UX-AUDIT.md, DESIGN-SYSTEM.md
```

**Not in repo (server-only, never committed):**
- `private_html/config/db.php` — admin panel session password
- `private_html/config/openai.php` — `OPENAI_API_KEY` constant for `verify_tool.php` (unconditional `require_once` — admin panel 500s if missing)
- `private_html/logs/verify_debug.log` — debug checkpoint log for `verify_tool.php`

## Architecture decisions (settled)

- **ADR-001:** Frontend is Astro.
- **ADR-002:** Docs-as-code in `/docs`, Notion for planning only.
- **ADR-003/008:** ETL was Apache NiFi 2.9.0 (self-hosted, Windows) — **superseded 2026-09-20**, migrated to PHP scraper + GitHub Actions (cloud-native, no dependency on Pablo's local machine being online). See "ETL pipeline" section below for the current architecture and the migration rationale.
- **ADR-004:** Hosting is Cyberfolks + Cloudflare.
- **ADR-006:** Admin panel is PHP + Supabase REST API.
- **ADR-007:** Database is Supabase free (PostgreSQL) — Cyberfolks MariaDB lacks JSONB/text[]/GIN.
- **ADR-009:** Affiliate links via dedicated `affiliate_links` table + `admin/affiliate.php` — closed, ClickUp/PartnerStack live.
- **ADR-005:** Closed — no dedicated backend was ever built.

## Data model

Core table `tools`, plus `categories`, `tags`, `tool_tags`, `premium_listings`, `scrape_queue`, `affiliate_links`.

```sql
-- tools: main catalog entry
CREATE TABLE tools (
  id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  slug             TEXT        NOT NULL UNIQUE,
  name             TEXT        NOT NULL,
  tagline_pl       TEXT,
  description_pl   TEXT,
  logo_url         TEXT,                          -- auto-favicon fallback + manual override
  website_url      TEXT        NOT NULL,
  category_id      UUID        REFERENCES categories(id),
  pricing_model    TEXT        CHECK (pricing_model IN ('free','freemium','paid','open_source')),
  price_from_pln   NUMERIC(10,2),
  price_note       TEXT,
  rodo_compliant   BOOLEAN,    -- manual-only, never touched by AI; NULL = nie zweryfikowano
                               -- (3-stanowy model od 2026-09-14); verify_tool.php od 2026-09-14
                               -- może podpowiedzieć cytat ze strony (evidence, nie ocena) — patrz
                               -- sekcja "Admin panel" niżej
  dpa_available    BOOLEAN,    -- NULL = nie zweryfikowano; ta sama zasada evidence-only co wyżej
  eu_data_hosting  BOOLEAN,    -- NULL = nie zweryfikowano; ta sama zasada evidence-only co wyżej
  ai_act_risk      TEXT        CHECK (ai_act_risk IN ('minimal','limited','high','unacceptable')),
  ai_act_notes     TEXT,
  target_size      TEXT[],
  best_for_pl      TEXT,                          -- one-sentence target audience, AI-generated
  ai_verified_at   TIMESTAMPTZ,                   -- last time "Zweryfikuj przez AI" changes were approved
  has_pl_ui        BOOLEAN,    -- manual-only, NULL = nie zweryfikowano (3-stanowy model od
                               -- 2026-09-23); brak evidence z verify_tool.php — tylko ręcznie
  has_pl_support   BOOLEAN,    -- NULL = nie zweryfikowano; ta sama zasada co has_pl_ui.
                               -- Edytowalne w panelu, świadomie NIE wyświetlane na froncie
  integrations     TEXT[],
  status           TEXT        NOT NULL DEFAULT 'pending'
                               CHECK (status IN ('pending','approved','rejected','premium')),
  source           TEXT        DEFAULT 'manual',
  source_url       TEXT,
  view_count       INTEGER     NOT NULL DEFAULT 0,
  click_count      INTEGER     NOT NULL DEFAULT 0,
  created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- categories: exact schema confirmed 2026-09-01 (differs from earlier assumptions —
-- verify against live schema before writing INSERTs, don't assume)
CREATE TABLE categories (
  id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  slug             TEXT        NOT NULL,
  name_pl          TEXT        NOT NULL,           -- required! INSERT without this fails
  description_pl   TEXT,
  icon             TEXT,                           -- Tabler Icons naming convention (e.g. 'robot', 'shield-bolt') —
                                                     -- NOT necessarily what CategoryIcon.astro actually renders;
                                                     -- frontend has its own hand-written SVG mapping by category name
  sort_order       INTEGER     DEFAULT 0,
  created_at       TIMESTAMPTZ DEFAULT now()
);
```

**Current 10 categories** (sort_order 1-10): Automatyzacja procesów, Sprzedaż i CRM, Obsługa klienta, Marketing i content, Finanse i księgowość, HR i rekrutacja, Analityka i BI, Prawo i compliance, Zarządzanie projektami, **Cyberbezpieczeństwo AI** (added 2026-09-01).

`scrape_queue` mirrors most of `tools` plus: `raw_name`, `raw_desc`, `raw_json`, `url_hash`, `ai_description`, `ai_category`, `ai_tags`, `ai_rodo`, `ai_pricing_model`, `best_for_pl`, `tool_id`, `error_msg`, `scraped_at`, `processed_at`, `source_name` (values: `hacker_news`, `betalist`, `product_hunt`, `yc_ai_pilot` — see "ETL pipeline" for the fix that made BetaList/Product Hunt report their real source name instead of defaulting to `hacker_news`), and:
- `stage` — `pending` → `ai_done` → `published`, or `ai_rejected` (when AI's `is_real_product` is `false`). **CHECK constraint on this column must be updated whenever a new stage value is introduced** — see gotcha below, this bit us once already.

**Unique differentiators:** `rodo_compliant`, `dpa_available`, `eu_data_hosting`, `ai_act_risk`, `best_for_pl` — no Polish AI catalog tags these. Now surfaced not just as badges but as full educational FAQ explanations (see "Frontend FAQ" section below).

## Database trigger — `promote_scrape_to_tools()`

**Lives in Supabase (Postgres function/trigger), NOT in this repo.** The PHP "Zatwierdź" button in `admin/index.php` only sets `scrape_queue.stage = 'published'`; this trigger does the actual `INSERT INTO tools`. Current definition includes `best_for_pl`, favicon fallback, and (since the 2026-09-14 tri-state migration below) `rodo_compliant = NULL` instead of `false`:

```sql
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
```

**Note:** favicon fallback only applies to *new* rows via this trigger; a one-time backfill was run manually for pre-existing tools.

**Migracja 3-stanowa rodo_compliant/dpa_available/eu_data_hosting (2026-09-14):** `NOT NULL DEFAULT false` zamienione na dopuszczalny `NULL` (= nie zweryfikowano) dla wszystkich trzech pól — patrz `db/migrations/002_tri_state_compliance_fields.sql` dla pełnej treści. Backfill objął tylko wiersze, gdzie `false` było artefaktem defaultu/triggera, nie realną decyzją: **rodo_compliant** 199 NULL / 15 false / 115 true (15 wierszy `source='manual'` z `false` świadomie pominięte w backfillu — te powstały przez ręczne odznaczenie checkboxa w formularzu "Dodaj wpis", więc to rzeczywista decyzja, nie default), **dpa_available** 326 NULL / 3 true, **eu_data_hosting** 326 NULL / 3 true (oba pola nigdy nie mają UI do ustawienia `false` — jedyne 3 wartości `true` w każdym powstały przez bezpośrednią edycję w Supabase, poza panelem admina).

**Migracja 3-stanowa has_pl_ui/has_pl_support (2026-09-23):** oba pola miały ten sam problem fałszywego negatywu (`DEFAULT false` = "nie" zamiast "nie wiadomo"), ale zostały pominięte w migracji z 2026-09-14. SQL wykonany ręcznie w Supabase SQL Editor (bez pliku w `db/migrations/`): `ALTER COLUMN ... SET DEFAULT NULL` + backfill 339 wierszy `false`→`NULL`. Kolumny były już nullable, więc bez `DROP NOT NULL`. Trigger nie wymagał zmian: nie ustawia tych kolumn, więc nowe wiersze dostają default (NULL). Od tej migracji jest **5 pól 3-stanowych**: `rodo_compliant`, `dpa_available`, `eu_data_hosting`, `has_pl_ui`, `has_pl_support`, obsługiwanych w panelu tym samym mechanizmem (`tri_state_from_post()` / `render_tri_state_badge()` / `triStateFromSelect()`).

**⚠️ Known gap:** `website_url` for entries sourced from Product Hunt/BetaList sometimes ends up pointing to the listing page instead of the tool's real domain (this is a `source_url`/`website_url` data quality issue upstream of the trigger, not a trigger bug — carried over unchanged from NiFi into the new PHP scraper). Fixed manually per-case when spotted, or systematically via the quarterly `scraper/url_audit.php` run (see "Documentation rules" section).

## ETL pipeline — PHP scraper + GitHub Actions (migrated from NiFi 2026-09-20)

**4 sources**, each a `scraper/sources/*.php` file run via `php scraper/run.php --source=X`, one GitHub Actions workflow per source (`.github/workflows/scrape-*.yml`), each with `schedule` (cron) + `workflow_dispatch` (manual testing) triggers, same 3 repo secrets (`SUPABASE_URL`→`PUBLIC_SUPABASE_URL`, `SUPABASE_ANON_KEY`→`PUBLIC_SUPABASE_ANON_KEY`, `OPENAI_API_KEY`) mapped in each workflow's `env:` block.

### Why this replaced NiFi (self-hosted, Windows)
The YC-OSS source was first added as a NiFi branch (2026-08-30), then re-implemented as a GitHub Actions PHP pilot to remove the dependency on Pablo's local Windows machine + NiFi process being online for the pipeline to run at all. After confirming the pilot worked correctly (see "Migration diagnostic" below), the remaining 3 NiFi sources (HN, BetaList, Product Hunt) were migrated the same way on 2026-09-20, and NiFi's 4 trigger processors were stopped in the NiFi UI. **The NiFi flow itself is not deleted** — kept in `nifi-flows/` as a reference/fallback, just inactive.

### Migration diagnostic (2026-09-20) — worth knowing if pipeline output looks "stuck"
When both NiFi and the new GitHub Actions pilot ran in parallel on the same schedule, NiFi consistently "won the race" for the same candidate companies — GitHub Actions' `schedule` trigger was observed running **~5-6 hours later** than its configured cron time (a known, documented GitHub Actions behavior for low-traffic repos, not a bug in this project's workflow files). Result: 96 of 97 YC-OSS candidates were already inserted by NiFi by the time the pilot ran, showing up as `duplicates` in the pilot's stats — this looked like "the new pipeline isn't finding anything" but was actually dedup working correctly against a competing pipeline. Confirmed by running with NiFi's trigger skipped for a day: `inserted > 0` immediately. **If a source's stats show fetched/filtered numbers but `inserted: 0` and `duplicates` roughly matching, check whether something else (an old NiFi flow, a stray second workflow run) is inserting the same rows first — before assuming the source itself is broken.**

### Shared components (`scraper/lib/`)
- **`supabase.php`** — `sb_get(path)` / `sb_post(table, data)`, thin curl wrappers reading `SUPABASE_URL`/`SUPABASE_ANON_KEY` from env
- **`openai.php`** — `call_openai_description($title, $url, $context)`. System prompt is a **verbatim copy of the NiFi system prompt** (10 categories, `best_for_pl`/`is_real_product` rules) — kept as a PHP heredoc, confirmed against the live NiFi export at time of writing. Uses `response_format: json_object` (same as `verify_tool.php`) plus a defensive markdown-fence strip as a backup, since `response_format` alone isn't a 100% guarantee. `$context` defaults to `'brak'` when empty — pass `''` for sources with no real source text (HN/BetaList/PH); YC passes the real `one_liner`+`long_description`.
- **`logging.php`** — `scraper_log(level, message, context, runId)`, writes to the same `activity_log` table used by `verify_tool.php` (see "Admin panel" section) with `source` set per-scraper-source (not hardcoded to the YC pilot anymore, as it originally was before this migration)
- **`filters.php`** — shared keyword filter used by HN/BetaList/Product Hunt (YC-OSS bypasses it, same as it did in NiFi — YC data is pre-verified as AI via the API's own tag endpoint):
  ```php
  function matches_keyword_filter(string $title): bool {
      if (preg_match('/\bai\b/i', $title)) return true; // word boundary — NOT substring contains,
      // which was a real historical NiFi bug matching "ai" inside "brain", "explain", etc.
      foreach (['tool', 'saas', 'launch', 'llm', 'gpt', 'model', 'open source'] as $kw) {
          if (stripos($title, $kw) !== false) return true;
      }
      return false;
  }
  ```
  Also defines `CANDIDATE_WINDOW_SECONDS` (currently 86400 = 24h for HN/BetaList/PH, matching NiFi's historical behavior — a widening to ~36h was proposed given the observed schedule-delay risk above, but deliberately deferred as a separate decision, not bundled into the migration commit).
- **`pipeline.php`** — shared dedup (`source_url` exact-string match against `scrape_queue`) → OpenAI call → insert flow, used by HN/BetaList/Product Hunt (YC-OSS keeps its own earlier, slightly different copy of this logic in `yc_oss.php` — not yet consolidated, low priority)
- **`atom.php`** — Atom feed parser + loop, shared by BetaList and Product Hunt
- **`http.php`** — GET helper with timeout + explicit User-Agent (Product Hunt is behind Cloudflare and requires a UA to avoid being blocked)

### Per-source notes, verified against the live NiFi flow export (not just its documented description) before migrating

- **`hacker_news.php`** — topstories (500 IDs) → sequential item-detail fetches (~3 min typical runtime; circuit-breaks after 10 consecutive fetch failures rather than waiting out all 500×timeout if HN is down) → domain blocklist → 24h date filter → Show HN/Launch HN/YC-batch-regex title filter → shared keyword filter → dedup → OpenAI → insert with `source_name = 'hacker_news'`.
- **`betalist.php`** / **`product_hunt.php`** — Atom feed → 24h date filter → shared keyword filter (checked against **name only**, not the feed's tagline/description — this matches NiFi's actual historical behavior, confirmed by inspecting the flow export; widening to full-title/description would roughly triple BetaList's pass-through rate per a live test, but was deliberately left as parity-with-NiFi for a clean 1:1 comparison before further tuning) → dedup → OpenAI → insert.
  - **Fixed a real, previously-unknown data quality bug during migration:** NiFi's shared `ReplaceText` step never set `source_name_override` for BetaList/Product Hunt (only YC-OSS had it) — so every historical BetaList/PH row in `scrape_queue`/`tools` is mislabeled `source_name = 'hacker_news'`. The new PHP sources correctly set `source_name = 'betalist'` / `'product_hunt'`. This was **not** a deliberate parity choice — it was a genuine bug in the old pipeline, fixed rather than carried forward.
  - Name/URL truncation (name cut before the first ` – ` en-dash; URL cut before `?`) replicated from NiFi's actual behavior, to keep dedup consistent against historical rows rather than starting a fresh dedup universe.
  - Product Hunt's `hn_type=story` "filter" mentioned in older docs **never actually existed as a filter in NiFi** — `hn_type=story` was a hardcoded constant written by `UpdateAttribute`, not a condition anything checked. Removed (never implemented) in the PHP version; documented in code so nobody re-adds a filter that was never real.

### Live test results (`workflow_dispatch`, 2026-09-20) — all zero errors, matched a prior dry run exactly
| Source | Fetched | Filtered out | Inserted | Duplicates | Errors |
|---|---|---|---|---|---|
| HN | 500 | 500 | 0 | 0 | 0 |
| BetaList | 25 | 24 | 1 | 0 | 0 |
| Product Hunt | 50 | 50 | 0 | 0 | 0 |

**Confirms the known low-signal problem HN/BetaList/PH have had for months (this is why YC-OSS was added as a 4th source in the first place) — not a migration regression.** Post-migration, expect these three sources to remain a low-volume supplement; YC-OSS is and will likely remain the primary volume driver. Don't be alarmed by near-zero `inserted` counts on a given run — check `filtered` vs `fetched` to confirm the source is actually being reached and processed, not silently failing.

### Known scraper quirks (PHP/GitHub Actions era)
- **GitHub Actions `schedule` triggers can run several hours late** on low-traffic repos — don't assume a workflow "didn't run" just because the wall-clock time doesn't match the cron expression; check the Actions run history for actual trigger times before debugging further.
- `sb_get`/`sb_post` dedup is a plain exact-string match on `source_url` — not normalized (no protocol/www/trailing-slash stripping), so the same company can occasionally slip through as a near-duplicate if a source's reported URL format shifts slightly between runs. Known, accepted tradeoff (same class of limitation NiFi had) — clean up occasional duplicates via the admin panel rather than treating this as a bug to fix urgently.
- A source's GitHub Actions job stays green even when individual items fail (counted in the `errors` stat and logged as `warning`/`error` to `activity_log`) — `run.php` doesn't propagate a non-zero exit code for partial failures, only for a fully unhandled exception. Check `admin/logs.php` or the workflow's own summary line, not just the job's pass/fail badge, to catch partial degradation.
- Regex domain blocklist (carried over from NiFi) matches by **substring**, not host — e.g. `dev.to` also matches `dev.tools`, `bbc.com` also matches `abbc.com`. Known limitation, not yet worth fixing given it only makes the filter slightly *more* aggressive (false exclusions), not less.

### Historical: NiFi processor architecture (for reference only — decommissioned 2026-09-20)
The original NiFi flow (`nifi-flows/aifirmy-main-flow-v2.json`) had HN/BetaList/Product Hunt converge on a shared merge point (`RouteOnAttribute "pasuje"` keyword filter, using a word-boundary AI match after fixing a historical `contains('ai')` substring bug) then a shared OpenAI `ReplaceText`/`InvokeHTTP`/`EvaluateJsonPath` path, with YC-OSS bypassing the shared keyword filter and connecting straight to dedup. `\n`/`\"` in `ReplaceText` values had to be avoided (literal-`n` and lost-backslash quirks respectively) — this doesn't apply to the PHP scraper, which uses `json_encode()` natively. If ever reviving the NiFi flow, note it will be significantly stale relative to the current prompt/schema (10 categories, tri-state RODO/DPA/EU fields, etc.) and would need reconciliation, not just a restart.

## Admin panel (`admin/`, PHP + Supabase REST API)

- **`admin/index.php`** — tabs: Kolejka (scrape_queue, stage=ai_done) / **Odrzucone przez AI** (stage=ai_rejected) / Narzędzia (tools — search by name, category filter, sortable columns, edit modal with single shared `patchTool()` PATCH for all fields including `description_pl`/`best_for_pl`/`pricing_model`/`name`, per-row **"Zweryfikuj przez AI"** button) / Dodaj wpis (manual add_tool form, includes tri-state RODO/DPA/EU-hosting/Interfejs PL/Wsparcie PL selects and `price_from_pln`). All 5 tri-state fields also appear as read-only badge columns in the Narzędzia table and as selects in the edit modal
- **`admin/affiliate.php`** — affiliate_links CRUD, toggle active without reload
- **`admin/logs.php`** — filterable/paginated `activity_log` viewer (added 2026-09-09), default filter `warning+error`, expandable JSON context, pill colors reuse `aiActRiskColors` from `category-colors.ts` (error≈unacceptable, warning≈limited). `activity_log` is append-only (RLS: insert/select only for `anon`, no update/delete), shared by `verify_tool.php` and the `scraper/` pipeline. Has a reserved (currently unused by `verify_tool.php`) `run_id` UUID column intended for grouping one scraper run's log lines together.
- **`admin/verify_tool.php`** — POST endpoint, `tool_id` in, `{"old": {...}, "new": {...}}` out:
  1. Fetch current tool + categories from Supabase (categories interpolated live from DB, not hardcoded)
  2. Live curl-fetch `website_url` (6s timeout, browser UA, follows redirects, hard-fails on HTTP ≥400)
  3. Extract `<title>`, meta description, body text (script/style/nav/footer stripped, capped ~3000 chars) + `logo_hint`
  4. Call `gpt-4o-mini` with `response_format: json_object`
  5. Resolve AI's category string back to `category_id` server-side; **auto-disables the category checkbox client-side with a warning if no exact match** — this correctly caught the AI Act "Bezpieczeństwo IT" hallucination in Sept 2026 before it could be saved as a null category
  6. `rodo_compliant` is **never** sent to or returned from the AI — manual-only field, by design.
     `dpa_available`/`eu_data_hosting` follow the same manual-only principle.

     **Deliberate, limited exception (added 2026-09-14):** the same OpenAI call now also
     extracts literal on-page *citations* about RODO/DPA/EU hosting (`rodo_evidence`,
     `dpa_evidence`, `eu_hosting_evidence`) — not a compliance judgment. Returned as a
     separate `compliance_evidence` top-level key (doesn't map to any `tools` column).
     Rendered in the verify modal as an informational panel ("🔍 Znalezione sygnały —
     nie ocena zgodności") directly above the tri-state selects, built via `textContent`
     (not `innerHTML` — the quote text originates from an untrusted external page). No
     "apply" checkbox: nothing here writes to the DB automatically, the human still sets
     RODO/DPA/EU hosting manually via the existing tri-state selects. This does NOT
     reverse the manual-only rule above — same reasoning as `$CATEGORY_AI_ACT_HINTS`
     below: surface evidence, never let the model make the legal call itself, since
     these three fields are the catalog's core trust differentiator and a wrong AI
     assertion here is costlier than a wrong category guess.
  7. `ai_act_risk_suggestion` and `logo_hint` default to **unchecked** in the UI; description/category/pricing_model/best_for_pl default **checked**. `category_ai_act_hint` (see below) also defaults unchecked.
  8. Wrapped in `try/catch (\Throwable)` + `register_shutdown_function`, logging to `error_log()`, `private_html/logs/verify_debug.log`, and `activity_log` (via the shared `verify_log_activity()` helper — `level='error'` for caught exceptions, `level='warning'` for the two distinct "dead URL" modes: curl-level failure vs. HTTP ≥400 response, each with its own `context` shape)
  9. Prompt also enforces content-quality rules from `docs/CONTENT-GUIDE.md` (added 2026-09-19): diacritics, grammatical agreement, exactly 2 sentences, no marketing tone, no doubled "Dla: Dla...", forced 3rd person, no literal field-label leakage like "Tagline:" in body text — verified against ~20 live cases with zero regressions before rollout.

### `extract_logo_hint()` — fixed 2026-09-05
Original implementation required specific attribute order in `<meta>`/`<link>` tags (`property` before `content`, `rel` before `href`) — HTML doesn't enforce this, so many real sites (especially Next.js-based, common among YC startups) were missed despite having valid tags. **Fixed:** extract the full tag first, then match attributes within it regardless of order. Added `rel="apple-touch-icon"` as a fallback when `icon`/`shortcut icon` is absent. Added a final fallback to `https://www.google.com/s2/favicons?domain={domain}&sz=128` (same domain-extraction logic as `promote_scrape_to_tools()`) when both extractions fail — previously returned `null` with no safety net. Shared helper `resolve_logo_url()` extracted for relative URL resolution, now applied consistently to both favicon and `og:image` (was favicon-only before).

**Observed pattern even after the fix:** `og:image` frequently resolves successfully but points to a promotional banner (e.g. `og-home-en.jpg`, `/opengraph-image`), not an actual logo. The Google favicon fallback is generally the safer default to accept in the verification UI — reject `og:image`-sourced `logo_hint` suggestions unless visually confirmed.

### `$CATEGORY_AI_ACT_HINTS` (category-based AI Act risk fallback)
Static map providing a fallback AI Act risk suggestion (labeled "Sugestia wg kategorii — Załącznik III AI Act", with an explicit "not legal advice" disclaimer) when the AI's own page-derived suggestion is empty. Covers 7 of 10 categories with high confidence (HR i rekrutacja=high, Obsługa klienta=limited, most others=minimal). **Deliberately excludes Finanse i księgowość, Prawo i compliance, and Cyberbezpieczeństwo AI** — all three are too heterogeneous internally for a safe category-level default (e.g. a generic SaaS pentesting tool is `minimal` but something managing critical-infrastructure security could be `high` under Annex III).

### Pricing prompt refinement (trial vs. permanent free plan)
System prompt for `verify_tool.php` includes an explicit rule distinguishing a permanent free tier from a free trial ("free trial", "X days free" ≠ `free`/`freemium`). Added after a real bug where Woodpecker.co (paid, trial-based) was misclassified as `free`. **Caution:** this fix can overcorrect in the other direction — a real case (Linzumi) showed the live-check suggesting `paid` when the actual pricing page had a genuine permanent free tier for solo builders, phrased as "Yours forever, no credit card" rather than the word "Free". Treat pricing_model discrepancies as requiring manual research, not as evidence either side of the diff is more trustworthy — confirmed as an inherent both-sides-can-be-wrong situation across 7 manually researched cases, not a one-off.

**Frontend logo placeholder logic** (`CompanyCard.astro`, `[slug].astro`): if `logo_url` is empty, render a colored initial-letter placeholder using the tool's category color.

**Shared badge styling (added 2026-09-08):** `aiActRiskColors` (colors + PL
label + `shortLabel`) and `pricingLabels`/`PricingModel` now live in
`frontend/src/lib/category-colors.ts` as the single source of truth,
imported by both `CompanyCard.astro` and `narzedzia/[slug].astro`. Before
this, `[slug].astro` had its own independent (and inconsistent) copies —
if either file starts diverging again, check this file first before
writing a new local mapping.

**Affiliate disclosure — two intentionally different patterns:**
`CompanyCard.astro` shows disclosure as a hover tooltip (ⓘ icon) — tight
card space justifies this. `[slug].astro` shows it as always-visible text
under the CTA — hover tooltips don't work on touch devices, and the detail
page has room. Don't "fix" one to match the other; this split is
deliberate.

## Frontend FAQ (`[slug].astro`) — expanded 2026-08-31 (commit `6faf053`)

FAQ section now has **6 questions** (was 4): RODO → DPA → EU data hosting → pricing model → AI Act risk → target_size. RODO and AI Act questions now include full plain-language legal explanations (what RODO/DPA/EU-hosting/each AI Act risk level actually means and requires), not just a one-line yes/no — this is deliberate: it's a **general educational explainer per category/risk-level**, the same text for every tool in that bucket, never an AI-generated per-tool legal judgment (that risk was deliberately avoided, same reasoning as the `$CATEGORY_AI_ACT_HINTS` exclusions above). Since the 2026-09-14 tri-state migration, RODO/DPA/EU-hosting FAQ answers and the `[slug].astro` "Zgodność i dane" tiles distinguish 3 states (yes/no/not-yet-verified), not just yes/no. Since 2026-09-23 the 4th tile ("Interfejs PL") uses the same `triStatePill()` helper as the RODO/DPA/EU tiles. `has_pl_support` is tri-state and editable in the admin panel but **deliberately not shown on the frontend yet**. The grid has 4 columns; whether and where to show it is a separate UI decision (tracked in the STATUS.md backlog).

JSON-LD `FAQPage` schema is generated automatically from the same `faqs` array used for the visible accordion — don't maintain these separately, that was a deliberate fix to avoid future drift between visible content and structured data.

## SEO conventions & fixes

- URLs: `/narzedzia/[slug]`, `/kategoria/[slug]` — canonical form always has a **trailing slash**
- Every page: `title`, meta description, OG tags, schema.org `SoftwareApplication`; tool detail pages also have `FAQPage` JSON-LD
- Sitemap auto-generated by Astro (`@astrojs/sitemap`)
- Text search on `/narzedzia/` (added 2026-09-15) uses Pagefind (`pagefind.search()`, debounced input, combined with the existing category filter)

**⚠️ Gotcha (fixed, keep in mind for future refactors):** `@astrojs/sitemap` was once accidentally removed from `astro.config.mjs` as a "dead import" — it's actually a build-time side effect (writes `sitemap-*.xml`), invisible to static analysis since nothing directly references it in code. `dist/` being gitignored meant the regression went undetected for weeks. **Lesson: before removing an import that "looks unused," check whether it's a build-time side-effect integration.**

**⚠️ Internal link trailing-slash bug (fixed, commit `9f2c7f1`):** `CompanyCard.astro`, `[slug].astro` (similar-tools section), and the category tiles all built internal links **without** a trailing slash (`/narzedzia/${slug}` instead of `/narzedzia/${slug}/`), while the canonical form has one. Every internal link therefore triggered an unnecessary server redirect that Google discovered before the canonical URL — inflating Search Console's "page has a redirect" count. All 4 link-generation sites now consistently include the trailing slash. **When adding any new internal link to `/narzedzia/` or `/kategoria/`, always include the trailing slash.**

**⚠️ www subdomain never redirected to apex (fixed 2026-08-31, Cloudflare-side, not code):** `www.aifirmy.pl` served full page content directly instead of 301-redirecting to `https://aifirmy.pl` — even though `<link rel="canonical">` correctly pointed to the apex domain. A `<link rel="canonical">` tag is **not** a substitute for an actual redirect; Google has to work harder (and slower) to consolidate signals via canonical alone versus following a clean 301. Fixed via a Cloudflare Redirect Rule (`https://www.*` → `https://${1}`, 301). This is infrastructure-level, not something to "fix" again in Astro — if a future domain/subdomain issue appears, check Cloudflare Redirect Rules and DNS proxy status (orange cloud) before assuming a code bug.

## Analytics reliability — read this before trusting GA4 numbers

**GA4 (`G-3SP1TRXF7M`) reported ~0 active users/events for 3+ consecutive weeks in August 2026 despite real, growing, verified traffic in Search Console.** A full diagnostic session ruled out (in order): insufficient traffic/missing cookie consent, browser extensions, wrong Measurement ID, CSP blocking the request, a Service Worker intercepting fetch, and a code bug in `Layout.astro`/`CookieConsent.astro` (the loading code is structurally correct and verified working — `gtag.js` does load and initialize, confirmed via internal `gtm.dom`/`gtm.load` dataLayer events that only the real downloaded script can produce). The same zero result reproduced on a completely different device and network (phone on cellular data).

**Conclusion: `gtag.js` initializes but the actual collection beacon is never sent** — most likely explanation is an ad blocker operating in "stub" mode (serves a harmless fake script instead of blocking the request outright, which is why the script *appears* to work). This is plausibly representative of aifirmy.pl's actual visitor base (people researching AI tools/RODO/AI Act skew toward ad-blocker usage), not just an artifact of the testing environment.

**Practical implication for any future session:** don't trust GA4 as the primary traffic signal. **Search Console** (server-side click/impression data from Google, unblockable) and **AWStats** (raw Cyberfolks server logs) are the reliable sources. When interpreting AWStats, remember: a large fraction of "hits" are bots (`not viewed` traffic, often far exceeding real visits) and Pablo's/Claude's own admin panel + testing activity — real external search-referral traffic is a small subset, cross-check against Search Console's click count for a sanity check.

## Code conventions

- File names: `kebab-case`; Components: `PascalCase`; Variables: `camelCase`; SQL: `UPPER_CASE`
- Commits: `feat:`, `fix:`, `docs:`, `refactor:`, `chore:`, `debug:`
- **Repo root has `frontend/` subdirectory** — always `git add frontend/src/...`
- **Filenames with `[brackets]`** (e.g. `[slug].astro`) need quotes around the path in PowerShell `git add`
- **`admin/` and `scraper/` are separate from `frontend/`** — both live at repo root, `admin/` is deployed via a different SCP step in the same GitHub Actions workflow as the frontend build; `scraper/` runs entirely inside its own GitHub Actions jobs (no deploy step — it's PHP CLI scripts, not served over HTTP)
- **No shared `Footer.astro` component** — footer markup (including the AI-content disclosure line) is duplicated across 6 page files (`index.astro`, `[slug].astro`, `premium.astro`, `polityka-prywatnosci.astro`, `kontakt.astro`, `dziekujemy.astro`). Any footer change needs to be applied to all 6. Flagged as a refactoring candidate, not yet done.

## Workflow — two Claude instances

- **Claude.ai (this chat)** = architect and advisor — strategy, planning, decisions, documentation, prompt drafting for Claude Code
- **Claude Code in VS Code** = executor — code generation, file editing, commits
- **CLAUDE.md** = bridge between them — must always be up to date
- **SQL migrations are done manually by Pablo**, guided by exact SQL text from Claude.ai — SQL changes follow an established "SQL Editor, not Claude Code" convention. (NiFi UI config followed the same manual convention while NiFi was active; no longer relevant post-migration, but the same principle now applies to anything requiring interactive web UI clicking that isn't code — e.g. GitHub repo secrets, Cloudflare rules.)
- **Manual deploy trigger available:** `.github/workflows/deploy.yml` has `workflow_dispatch` alongside `push` — use "Run workflow" from the GitHub Actions tab for DB-only changes that need a rebuild (SSG means DB changes alone don't appear on the live site), instead of `git commit --allow-empty`
- **Same pattern now applies to `scraper/` workflows** — each `.github/workflows/scrape-*.yml` has `workflow_dispatch`; always test a new/changed source manually this way before trusting its `schedule` trigger, and check the run's logged "Podsumowanie: {...}" JSON stats line (via `admin/logs.php` or the Actions run's step log) rather than just the green/red pass-fail badge.

## Environment variables & secrets

`.env.example` (frontend, committed):
```
DATABASE_URL=postgresql://postgres.[project-id]:[password]@aws-1-eu-central-1.pooler.supabase.com:5432/postgres
OPENAI_API_KEY=sk-...
NODE_ENV=development
PORT=3000
```
**Note:** local `npm run build` fails without a real `frontend/.env` containing `PUBLIC_SUPABASE_URL`/`PUBLIC_SUPABASE_ANON_KEY` (pages are SSG and query Supabase at build time). When this file isn't available in a session, verify changes with `npx astro check` (type-check, no DB needed) instead, and note in the diff summary that a full build wasn't possible.

**GitHub Actions repo secrets** (Settings → Secrets and variables → Actions), used by both `deploy.yml` and all `scraper/`-related workflows: `PUBLIC_SUPABASE_URL`, `PUBLIC_SUPABASE_ANON_KEY`, `OPENAI_API_KEY`. These must exist as actual repo secrets — adding `env: SOMEVAR: ${{ secrets.X }}` to a workflow file does nothing if the secret `X` was never created in repo settings; a missing secret shows up as scraper errors (curl to an empty/malformed URL), not a clean failure.

**Server-only, never in repo** (all under `private_html/`, same pattern as `db.php`):
- `config/db.php` — admin session password
- `config/openai.php` — `define('OPENAI_API_KEY', 'sk-...')` for `verify_tool.php`. **Required for admin panel to load at all.**

## Current status (as of 2026-09-20)

Live; monetization infrastructure is live (Stripe + affiliate), revenue = 0 as of 2026-09-20. Catalog grown from ~90 to 284 approved tools. 4th source (YC-OSS API) and 10th category (Cyberbezpieczeństwo AI) both shipped and verified. `verify_tool.php` logo detection fixed. **ETL pipeline fully migrated from NiFi to GitHub Actions (PHP scraper) on 2026-09-20** — all 4 sources live-tested via `workflow_dispatch` with zero errors, NiFi's 4 trigger processors stopped. Since 2026-09-05: the UX audit is closed (homepage hero now states the RODO/AI Act/EU differentiator, RODO/DPA/EU-hosting are tri-state fields, text search on `/narzedzia/`); the content audit is closed (`docs/CONTENT-GUIDE.md` is the source of truth for copy tone/quality, ~61 unambiguous language errors found and addressed, 7 dead/rebranded products removed); the first full `website_url` audit is closed (283 checked, 43 flagged, ~37 low-priority still open) and now re-runs quarterly via `.github/workflows/url-audit.yml` (see STATUS.md); the admin "Narzędzia" tab was refactored (edit modal, search/filter/sort). First LinkedIn post published 2026-09-19; cold outreach not started. Search Console shows a consistent week-over-week traffic increase (see STATUS.md for the trend table); GA4 is known-unreliable (see Analytics section above). Full history: STATUS.md / CHANGELOG.md.

**Immediate next priorities (per the roadmap agreed 2026-08-23, reaffirmed in the 2026-09-13 strategic session):**
1. Resume growth activities — 1 LinkedIn post published (2026-09-19), post 2 still a draft, cold outreach not yet started. A 2–4 week deliberate pause on new product development was agreed 2026-09-13 to force this forward.
2. Monitor traffic toward the 1000 UV/month AdSense threshold
3. Continue spot-checking "Zweryfikuj przez AI" results as more sourced tools accumulate, especially watching for category taxonomy gaps (the way Cyberbezpieczeństwo AI was discovered) and pricing_model disagreements (expected, not a bug — resolve per-case via manual research)
4. Confirm the new `scraper/` pipeline keeps running cleanly on its own schedule over the next several days now that NiFi is stopped (check `admin/logs.php` and `scrape_queue` growth, not just individual workflow pass/fail badges)

**Deferred, not forgotten:** newsletter, PDF industry report, additional affiliate programs, AI-written articles section (deliberately paused — see Notion for the full risk analysis); widening the HN/BetaList/PH candidate window from 24h to ~36h and/or the keyword filter from name-only to full-title (both deliberately deferred separately from the pipeline migration, one variable at a time); consolidating `yc_oss.php`'s own dedup/insert logic into the shared `pipeline.php` used by the other 3 sources.

## Documentation rules

- Every important technical decision → entry in `DECISIONS.md` (ADR format)
- Every significant session → entry in `CHANGELOG.md` and/or Notion status page
- **Watch for doc/reality drift.** Confirmed instances so far: `promote_scrape_to_tools()` living in Supabase (not repo); the "keyword filter" once described in docs didn't match its buggy actual implementation (`contains` vs. word-boundary match); the `categories` table schema (has `name_pl` NOT NULL, not just `slug`) was assumed incorrectly in an earlier memory note until directly queried; Product Hunt's `type=story` "filter" in NiFi turned out to never have existed as an actual condition when the flow export was inspected during the 2026-09-20 migration. **When in doubt, verify against the live database schema / trigger / actual processor or code definition rather than trusting a doc or a prior assumption — this has been wrong more than once, most recently during the NiFi→GitHub Actions migration itself.**
