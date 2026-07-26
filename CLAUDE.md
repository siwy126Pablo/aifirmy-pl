# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 🔗 Project links (use these in every session)

| Resource | URL |
|---|---|
| Notion — project overview | https://www.notion.so/aifirmy-pl-Katalog-AI-i-SaaS-373b4cccb4af81bb9ec5ef0a5ca32318 |
| Notion — session notes / status | https://app.notion.com/p/374b4cccb4af8103afbbc353f9fd300e |
| GitHub — main repo | https://github.com/siwy126Pablo/aifirmy-pl |
| GitHub — keep-alive ping | https://github.com/siwy126Pablo/aifirmy-ping |
| Supabase — dashboard | https://supabase.com/dashboard/project/szassqzvivdgvpkciyif |

## 🚀 How to start a new session

Paste this at the beginning of each new chat:

```
Kontynuujemy projekt aifirmy.pl.
Przeczytaj CLAUDE.md i zapoznaj się z aktualnym statusem projektu.
Notion (status): https://app.notion.com/p/374b4cccb4af8103afbbc353f9fd300e
Kontynuujemy: [opisz co robisz].
```

## Project overview

**aifirmy.pl** — a Polish-language catalog and content aggregator for AI tools, SaaS, courses, and startups targeting the PL/EU/global market, with a unique differentiator: every listing is tagged for RODO compliance, EU AI Act risk level, and PLN pricing. Live on Cyberfolks, generating revenue (Stripe live + affiliate).

## Tech stack (current, post Pakiet poprawek 2)

| Layer | Technology | Notes |
|---|---|---|
| Frontend | Astro 6 + Tailwind CSS v4 | `frontend/` subdirectory in repo root — always prefix paths with `frontend/` in git commands |
| Backend/API | No dedicated Node/Python backend | ADR-005 closed — business logic lives in PHP (admin, Stripe, email) + direct Supabase REST calls from frontend |
| Database | PostgreSQL — Supabase free (eu-central-1) | Cyberfolks has MariaDB only — incompatible with schema |
| ETL/Scraping | Apache NiFi 2.9.0 — self-hosted on Windows | 3 sources (HN, BetaList, Product Hunt), two-layer quality filter added 2026-07-19 (see below) |
| AI descriptions (pipeline) | OpenAI `gpt-4o-mini` | Prompt returns `best_for_pl` and `is_real_product` as of 2026-07-19 |
| AI verification (admin) | OpenAI `gpt-4o-mini` | New 2026-07-19 — `verify_tool.php`, `response_format: json_object`, key in `private_html/config/openai.php` on server (not in repo) |
| Admin panel | PHP + Supabase REST API | `admin/index.php`, `admin/affiliate.php`, `admin/verify_tool.php` |
| Payments | Stripe (Live mode) | `checkout.php` + `webhook.php` at `/stripe/webhook.php` (Cloudflare WAF blocks POST to `/admin/`) |
| Email | PHPMailer via Cyberfolks SMTP | `s103.cyber-folks.pl:587` |
| Analytics | Google Analytics 4 (`G-3SP1TRXF7M`) | Conditional load after cookie consent |
| Hosting | Cyberfolks (frontend) + Cloudflare | SSL Full strict, CDN, DNS |

**Project location:** `C:\Dev\aifirmy-pl` (moved from `C:\Users\pawel\Praca` — ESET blocked `node_modules` under `C:\Users\`).

## Repository structure

```
aifirmy-pl/
├── frontend/              ← Astro + Tailwind (ALWAYS prefix paths with frontend/)
│   └── src/
│       ├── pages/          ← index, /narzedzia/[slug], /kategoria/[slug]
│       ├── components/     ← CompanyCard.astro, icons/CategoryIcon.astro
│       └── lib/             ← category-colors.ts
├── admin/                  ← PHP admin panel (repo root, NOT under frontend/)
│   ├── index.php            ← main panel: Kolejka / Odrzucone przez AI / Narzędzia / Dodaj wpis
│   ├── affiliate.php        ← affiliate links CRUD
│   └── verify_tool.php      ← "Zweryfikuj przez AI" endpoint (new 2026-07-19)
├── nifi-flows/              ← NiFi flow exports (.json) — export manually after UI changes
├── db/
│   └── migrations/          ← PostgreSQL SQL migrations (not always used — some schema changes made directly in Supabase Studio)
└── docs/                    ← ARCHITECTURE.md, DECISIONS.md, CHANGELOG.md, STATUS.md
```

**Not in repo (server-only, never committed):**
- `private_html/config/db.php` — admin panel session password
- `private_html/config/openai.php` — `OPENAI_API_KEY` constant for `verify_tool.php` (added 2026-07-19, unconditional `require_once` — admin panel 500s if missing)
- `private_html/logs/verify_debug.log` — debug checkpoint log for `verify_tool.php`

## Architecture decisions (settled)

- **ADR-001:** Frontend is Astro.
- **ADR-002:** Docs-as-code in `/docs`, Notion for planning only.
- **ADR-003/008:** ETL is Apache NiFi 2.9.0, self-hosted locally on Windows (Java, Task Scheduler autostart, not Windows Service).
- **ADR-004:** Hosting is Cyberfolks + Cloudflare.
- **ADR-006:** Admin panel is PHP + Supabase REST API (superseded Supabase Studio in practice).
- **ADR-007:** Database is Supabase free (PostgreSQL) — Cyberfolks MariaDB lacks JSONB/text[]/GIN.
- **ADR-009:** Affiliate links via dedicated `affiliate_links` table + `admin/affiliate.php` — **closed**, ClickUp/PartnerStack live on production.

## Open architectural decisions

- **ADR-005:** Closed — no dedicated backend was ever built.

## Data model

Core table `tools`, plus `categories`, `tags`, `tool_tags`, `premium_listings`, `scrape_queue`, `affiliate_links`.

```sql
-- tools: main catalog entry (columns added over time, see below)
CREATE TABLE tools (
  id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  slug             TEXT        NOT NULL UNIQUE,
  name             TEXT        NOT NULL,
  tagline_pl       TEXT,
  description_pl   TEXT,
  logo_url         TEXT,                          -- auto-favicon fallback + manual override (2026-07-19)
  website_url      TEXT        NOT NULL,
  category_id      UUID        REFERENCES categories(id),
  pricing_model    TEXT        CHECK (pricing_model IN ('free','freemium','paid','open_source')),
  price_from_pln   NUMERIC(10,2),
  price_note       TEXT,
  rodo_compliant   BOOLEAN     NOT NULL DEFAULT false,   -- manual-only, never touched by AI
  dpa_available    BOOLEAN     DEFAULT false,
  eu_data_hosting  BOOLEAN     DEFAULT false,
  ai_act_risk      TEXT        CHECK (ai_act_risk IN ('minimal','limited','high','unacceptable')),
  ai_act_notes     TEXT,
  target_size      TEXT[],
  best_for_pl      TEXT,                          -- 🆕 2026-07-19: one-sentence target audience, AI-generated
  ai_verified_at   TIMESTAMPTZ,                   -- 🆕 2026-07-25: last time "Zweryfikuj przez AI" changes were approved (see verify_tool.php below)
  has_pl_ui        BOOLEAN     DEFAULT false,
  has_pl_support   BOOLEAN     DEFAULT false,
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
```

`scrape_queue` mirrors most of the above plus: `raw_name`, `raw_desc`, `raw_json`, `url_hash`, `ai_description`, `ai_category`, `ai_tags`, `ai_rodo`, `ai_pricing_model`, `best_for_pl`, `tool_id`, `error_msg`, `scraped_at`, `processed_at`, and:
- `stage` — `pending` → `ai_done` → `published`, **or `ai_rejected`** 🆕 (2026-07-19, when AI's `is_real_product` field is `false`)

**⚠️ Gotcha found 2026-07-23:** the `scrape_queue_stage_check` CHECK constraint on this column was never updated when `ai_rejected` was introduced — its allowed-values list only had `scraped`, `ai_pending`, `ai_done`, `mod_pending`, `published`, `rejected`. Every AI-rejected row failed silently at the `PutDatabaseRecord` INSERT step until this was caught (first real case: BetaList's "DataEase AI" tripped `ERROR: violates check constraint`). Fixed via `ALTER TABLE ... DROP CONSTRAINT` + `ADD CONSTRAINT` with `ai_rejected` added to the array. **Lesson: when adding a new `stage`/enum-like value anywhere (NiFi, trigger, PHP), always check `pg_get_constraintdef` for that column first** — updating the application logic alone doesn't update the database-level CHECK, and the failure mode (INSERT silently aborted) can look identical to "the AI just isn't rejecting anything."

**Unique differentiators:** `rodo_compliant`, `dpa_available`, `eu_data_hosting`, `ai_act_risk`, `best_for_pl` — no Polish AI catalog tags these.

**⚠️ Important:** `db/migrations/001_initial.sql` mentioned historically was never committed — the `tools` schema was built directly in Supabase Studio. Treat Supabase as the source of truth for schema, not the migrations folder, unless a migration file demonstrably matches current state.

## Database trigger — `promote_scrape_to_tools()`

**This lives in Supabase (Postgres function/trigger), NOT in this repo.** Easy to miss when searching the codebase for "where does approval actually insert into `tools`" — the PHP "Zatwierdź" button in `admin/index.php` only sets `scrape_queue.stage = 'published'`; this trigger does the actual `INSERT INTO tools`. Current definition (as of 2026-07-19, includes `best_for_pl` and favicon fallback):

```sql
CREATE OR REPLACE FUNCTION promote_scrape_to_tools()
RETURNS TRIGGER AS $$
DECLARE
  v_category_id UUID;
  v_slug TEXT;
  v_domain TEXT;
  v_logo_url TEXT;
BEGIN
  IF NEW.stage = 'published' AND OLD.stage != 'published' THEN
    SELECT id INTO v_category_id FROM categories
    WHERE name_pl ILIKE '%' || NEW.ai_category || '%' LIMIT 1;

    v_slug := slugify(COALESCE(NEW.name, NEW.raw_name, 'narzedzie'));
    WHILE EXISTS (SELECT 1 FROM tools WHERE slug = v_slug) LOOP
      v_slug := v_slug || '-' || floor(random() * 1000)::text;
    END LOOP;

    IF COALESCE(NEW.source_url, '') != '' THEN
      v_domain := regexp_replace(regexp_replace(NEW.source_url, '^https?://(www\.)?', ''), '/.*$', '');
      v_logo_url := 'https://www.google.com/s2/favicons?domain=' || v_domain || '&sz=128';
    END IF;

    INSERT INTO tools (
      slug, name, description_pl, website_url, category_id, pricing_model,
      rodo_compliant, ai_act_risk, status, source, source_url, logo_url, best_for_pl
    ) VALUES (
      v_slug, COALESCE(NEW.name, NEW.raw_name, 'Bez nazwy'), NEW.ai_description,
      COALESCE(NEW.source_url, ''), v_category_id, COALESCE(NEW.ai_pricing_model, 'freemium'),
      false, 'minimal', 'approved', NEW.source_name, NEW.source_url, v_logo_url, NEW.best_for_pl
    );

    UPDATE scrape_queue SET tool_id = (SELECT id FROM tools WHERE slug = v_slug) WHERE id = NEW.id;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;
```

**Note:** `logo_url` fallback only applies to *new* rows going through this trigger. A one-time backfill (`UPDATE tools SET logo_url = ...`) was run manually for 61 pre-existing tools on 2026-07-19.

## ETL pipeline (NiFi) — two-layer quality filter added 2026-07-19

NiFi 2.9.0 running locally on Windows. Three sources feed `scrape_queue` via daily crons (HN 2:00, BetaList 3:00, Product Hunt 4:00), converging on a shared OpenAI path.

**HN branch (before merge with BetaList/Product Hunt):**
```
GenerateFlowFile → InvokeHTTP (topstories) → SplitJson → InvokeHTTP (item details)
  → EvaluateJsonPath (pre-filter) → RouteOnAttribute "przepusc" (URL domain blocklist:
    github.com, medium.com, substack.com, dev.to, gist.github, twitter.com, wsj.com,
    computerworld.com, reuters.com, cnbc.com, theguardian.com, nytimes.com, bbc.com, and more)
  → EvaluateJsonPath → RouteOnAttribute "nowe_i_story" (last 24h + type=story)
  → RouteOnAttribute "show_launch_hn" 🆕 (title starts with "show hn:"/"launch hn:" OR
    matches YC batch pattern `(?i).*\(yc [a-z][0-9]{2}\).*`) — drastically reduces volume
    (observed 2/500 in one batch) but high precision (verified: Bribes.fyi, PilotCite)
```

**BetaList/Product Hunt branches:** Atom feed → SplitXml → RouteOnContent → EvaluateXPath (needs `local-name()` for namespaced XML) → UpdateAttribute (normalize to shared `hn_*` attribute names) → date filter.

**Merge point (all 3 sources) — shared processors:**
```
RouteOnAttribute "pasuje" (keyword filter: ai/tool/saas/launch/llm/gpt/model/open source)
  ⚠️ FIXED 2026-07-19: was `contains('ai')`, which matched the substring "ai" inside
     ANY word (br-AI-n, expl-AI-n, maint-AI-n...), not the standalone word. Was likely
     the single biggest source of noise in the whole pipeline — confirmed by a real
     example ("Heavy TV Watching Impact" passed only because of "brain"). Fixed to
     `matches('(?i).*\bai\b.*')` with a word boundary.
  → ExecuteSQL (dedup) → RouteOnAttribute (new record?)
```

**Shared OpenAI path:**
```
ReplaceText (builds OpenAI request body — system prompt, see below)
  → InvokeHTTP (OpenAI gpt-4o-mini)
  → EvaluateJsonPath "ai_response" ($.choices[0].message.content — raw string, not yet parsed)
  → EvaluateJsonPath (ai_category, ai_description, ai_name, ai_pricing_model, ai_segment,
    ai_best_for_pl 🆕, ai_is_real_product 🆕) — re-parses ai_response, extracts scalar fields
  → ReplaceText (builds INSERT JSON — maps ai_* attributes to DB column names, which do
    NOT always match 1:1, e.g. ai_name → "name". stage is now conditional: 🆕
    ${ai_is_real_product:equals('false'):ifElse('ai_rejected','ai_done')})
  → PutDatabaseRecord (INSERT scrape_queue, Record Reader: JsonTreeReader)
```

**Current system prompt** (single line, no quotes, no `\n` — see quirks):
```
Opisz narzedzie/firme w 2 zdaniach. Ton: neutralny, informacyjny, SEO-friendly. Jezyk: polski. Format odpowiedzi tylko JSON bez markdown: { name, description, category, tags, segment, pricing_model, best_for_pl, is_real_product } Zasady dla pola name: - Krotka nazwa produktu lub narzedzia (max 50 znakow) - Bez prefiksu Show HN: i podobnych - Bez podtytulu po myslniku lub dwukropku - Przyklad: Inbox-beam, KVarN, Lathe Zasady dla pola pricing_model: - Wybierz JEDNA wartosc: free, freemium, paid, open_source - open_source: projekt na GitHub bez platnego SaaS - free: narzedzie bez zadnych platnych planow - freemium: darmowy tier + platne plany - paid: tylko platne plany, brak darmowej wersji Zasady dla pola category: - Wybierz JEDNA kategorie z tej listy (pisownia musi byc identyczna): Automatyzacja procesów, Analityka i BI, Finanse i księgowość, HR i rekrutacja, Marketing i content, Obsługa klienta, Prawo i compliance, Sprzedaż i CRM, Zarządzanie projektami - Jesli narzedzie nie pasuje do zadnej - wybierz najblizszą Zasady dla pola best_for_pl: - Jedno krotkie zdanie po polsku, max 60 znakow, bez kropki na koncu - Opisuje dla jakiego typu firmy lub zespolu narzedzie jest najlepsze - Przyklad: Male zespoly sprzedazy w SMB, Dzialy HR w srednich firmach Zasady dla pola is_real_product: - Wartosc boolowska true lub false, bez cudzyslowow - true TYLKO jesli tytul i url opisuja faktyczny, konkretny produkt lub narzedzie ktore mozna odwiedzic i wyprobowac (SaaS, aplikacja, API, biblioteka, platforma) - false jesli to artykul, esej, badanie naukowe, wpis blogowy, dyskusja, ogloszenie lub tresc niezwiazana z konkretnym istniejacym produktem - W razie watpliwosci wybierz false
```

**Even with `is_real_product`, expect the model to still generate plausible-sounding name/description/category for non-products** — the field is a signal for filtering, not a guarantee the rest of the output is trustworthy. Real observed example: an essay titled "I argued with the father of open source..." got a full fake product writeup AND correctly `is_real_product: false` in the same response.

**Known NiFi quirks:**
- `\n` in ReplaceText Replacement Value → literal `n` (Java `Matcher.appendReplacement` bug) — system prompt must be one line
- `\"` in Replacement Value loses its backslash — no quotes inside JSON content strings
- `EvaluateJsonPath` can't handle arrays → tags stored as raw JSONB
- InvokeHTTP caches its connection pool after config changes — **Stop flow → wait ~30s → Start**
- `nifi.cmd status` can report "Management Server communication failed" even when the process is alive and just slow to fully start — check `java -version` and `logs/nifi-app.log`/`nifi-bootstrap.log` before assuming failure
- The URL-domain filter and the Show HN/Launch HN filter live **only on the HN branch** (before the 3-source merge). The keyword filter lives **after** the merge and is shared — a filter change in the wrong place can silently block BetaList/Product Hunt entirely
- `contains('x')` in NiFi Expression Language matches substrings, not whole words — for short strings like "ai" use `matches('(?i).*\bx\b.*')` with word boundaries

## Admin panel (`admin/`, PHP + Supabase REST API)

- **`admin/index.php`** — tabs: Kolejka (scrape_queue, stage=ai_done) / **Odrzucone przez AI** 🆕 (stage=ai_rejected, actions: Usuń trwale / Przenieś do kolejki) / Narzędzia (tools, with per-row **"Zweryfikuj przez AI"** button 🆕) / Dodaj wpis (manual add_tool form)
- **`admin/affiliate.php`** — affiliate_links CRUD, toggle active without reload
- **`admin/verify_tool.php`** 🆕 (2026-07-19) — POST endpoint, `tool_id` in, `{"old": {...}, "new": {...}}` out:
  1. Fetch current tool + categories from Supabase
  2. Live curl-fetch `website_url` (6s timeout, browser UA, follows redirects, hard-fails on HTTP ≥400)
  3. Extract `<title>`, meta description, body text (script/style/nav/footer stripped, capped ~3000 chars) + `logo_hint` (og:image → favicon fallback)
  4. Call `gpt-4o-mini` with `response_format: json_object`, categories interpolated live from DB (not hardcoded)
  5. Resolve AI's category string back to `category_id` server-side; auto-disables the category checkbox client-side with a warning if no exact match
  6. `rodo_compliant` is **never** sent to or returned from the AI — manual-only field, by design
  7. `ai_act_risk_suggestion` and `logo_hint` default to **unchecked** in the UI (require deliberate review); description/category/pricing_model/best_for_pl default **checked**
  8. Wrapped in `try/catch (\Throwable)` (not `\Exception` — `TypeError` doesn't extend it) + `register_shutdown_function` catching fatals try/catch can't intercept, both logging to `error_log()` and `private_html/logs/verify_debug.log`

**Debugging history (2026-07-19):** First deploy returned a silent `502 Bad Gateway` with zero PHP error logs. Timing checkpoints revealed the whole request completes in 2-3s (not a timeout) and dies between the OpenAI response and building the output. Root cause: `is_array($ai)` passed for both objects and arrays — when the model returned `category` as an array (hedging between two categories) instead of a string, `trim()`/`mb_strtolower()` on it threw an uncaught `TypeError` under `declare(strict_types=1)`, killing the script with no output. Fixed with an `is_string()` guard plus the error handling described above.

**`ai_verified_at` tracking (2026-07-25):** the Tools tab now shows "Sprawdzono: DD.MM.RRRR" next to the verify button when `tools.ai_verified_at` is set. It's only written when the user actually approves and saves at least one field from the diff modal (not merely on running the check) — written atomically in the same PATCH request as the approved fields, never as a separate call. Cancelling or unchecking everything before saving leaves it untouched. Prevents re-verifying the same tool repeatedly without a reason.

**Frontend logo placeholder logic** (`CompanyCard.astro`, `[slug].astro`): if `logo_url` is empty, render a colored initial-letter placeholder using the tool's category color (same palette as `CategoryIcon`/`category-colors.ts`) instead of a blank space.

## SEO conventions

- URLs: `/narzedzia/[slug]`, `/kategoria/[slug]`
- Every page: `title`, meta description, OG tags, schema.org `SoftwareApplication`; tool detail pages also have schema.org `FAQPage` JSON-LD 🆕
- Sitemap auto-generated by Astro (`@astrojs/sitemap`); SSG throughout (no SSR)

**⚠️ Gotcha found 2026-07-23:** `@astrojs/sitemap` was accidentally removed from `astro.config.mjs`'s `integrations` array in commit `d0639c1` ("refactor: remove unused sitemap integration") — the import looked like dead code (nothing in the codebase references it directly), but it's a build-time side effect that writes `sitemap-*.xml`. From that commit onward, `astro build` silently stopped generating a sitemap at all. The stale `sitemap-0.xml` sitting in `dist/` (16 URLs — home, 9 categories, contact/thanks/premium/privacy, and only 3 tools: Make/n8n/Rossum, the original Tydzień 2 seed data) went undetected for weeks because `dist/` is gitignored, so nothing flagged the regression in git history. This was likely the **primary cause** of poor organic visibility since launch — more impactful than any individual SEO element (badges, FAQ, schema.org), since Google had no complete signal of which tool pages even existed. Fixed by restoring the two lines (import + `integrations: [sitemap()]`); verified live sitemap now has 93 URLs (79 tool pages + the rest). **Lesson: before removing an import that "looks unused," check whether it's actually a build-time side-effect integration (sitemap, robots.txt generators, etc.) rather than something meant to be referenced elsewhere in code.**

## Code conventions

- File names: `kebab-case`; Components: `PascalCase`; Variables: `camelCase`; SQL: `UPPER_CASE`
- Commits: `feat:`, `fix:`, `docs:`, `refactor:`, `chore:`, `debug:`
- **Repo root has `frontend/` subdirectory** — always `git add frontend/src/...`, never assume paths are relative to repo root without the prefix (this caused at least one failed commit in a past session)
- **Filenames with `[brackets]`** (e.g. `[slug].astro`) need quotes around the path in PowerShell `git add`, not backslash-escaping
- **`admin/` is separate from `frontend/`** — lives at repo root, deployed via a different SCP step in the same GitHub Actions workflow

## Workflow — two Claude instances

- **Claude.ai (this chat)** = architect and advisor — strategy, planning, decisions, documentation, prompt drafting for Claude Code
- **Claude Code in VS Code** = executor — code generation, file editing, commits
- **CLAUDE.md** = bridge between them — must always be up to date
- **Some infrastructure changes (SQL migrations, NiFi UI config) are done manually by Pablo**, guided by exact SQL/config text from Claude.ai — not executed by Claude Code, since NiFi has no CLI/API access in this setup and SQL changes follow an established "SQL Editor, not Claude Code" convention

## Environment variables & secrets

`.env.example` (frontend, committed):
```
DATABASE_URL=postgresql://postgres.[project-id]:[password]@aws-1-eu-central-1.pooler.supabase.com:5432/postgres
OPENAI_API_KEY=sk-...   # NiFi Parameter Context, separate from admin panel's key
NODE_ENV=development
PORT=3000
```

**Server-only, never in repo** (all under `private_html/`, same pattern as existing `config/db.php`):
- `config/db.php` — admin session password
- `config/openai.php` 🆕 — `define('OPENAI_API_KEY', 'sk-...')` for `verify_tool.php`. **Required for admin panel to load at all** — `require_once` is unconditional, missing file = 500 on every admin request.

## Current status (as of 2026-07-25)

All weeks 1-7 complete, live with paying customers (Stripe + affiliate). **Pakiet poprawek 2 complete** (10/10 tasks) — see `STATUS.md` for full breakdown. Highlights:
- Frontend: trust badges, FAQ, similar-tools section, `best_for_pl` field, category tiles + custom icons, larger cards, logo fallback system
- Pipeline: two-layer quality filter (Show HN/Launch HN + `is_real_product`), critical `contains('ai')` substring bug fixed
- Admin: new "Zweryfikuj przez AI" live-verification feature, new "Odrzucone przez AI" tab

**Known setup quirks (Windows):**
- Astro v6 + Tailwind v4 install needs `--template minimal --no-git --no-install` (Node.js v24 bug)
- PowerShell needs `Set-ExecutionPolicy RemoteSigned` before npm
- ESET blocks `node_modules` under `C:\Users\` — project lives in `C:\Dev\`
- NiFi `nifi.cmd status` reporting failure doesn't always mean the process crashed — check logs before restarting

**Not yet built / deferred:** dedicated Node/Python backend (not needed), `db/migrations/` as a maintained folder (schema changes mostly made directly in Supabase), descriptive names for two NiFi processors (cosmetic).

**Post-launch fix (2026-07-23):** `scrape_queue_stage_check` CHECK constraint didn't include `ai_rejected` — see the gotcha note under "Database trigger" above. Fixed; first batch of 8 AI-rejected entries now visible in the admin panel's "Odrzucone przez AI" tab. **Open follow-up:** review those 8 for false negatives — at least one ("DataEase AI" from BetaList) looks like a real product that may have been incorrectly flagged as `is_real_product: false`.

**Post-launch fix (2026-07-23, second session — traffic audit):** `@astrojs/sitemap` integration had been silently missing from `astro.config.mjs` since commit `d0639c1` — see the gotcha note under "SEO conventions" above. Sitemap only had 16 URLs (3 tools) instead of ~93 (79 tools) for weeks, likely the primary cause of near-zero organic traffic (GSC showed 4 total clicks, 59/96 pages indexed since launch). Fixed and verified live (93 URLs). Resubmitted to Search Console; effect on indexing/traffic expected over days to a few weeks, not immediate.

**Follow-ups (2026-07-25):**
- `tools.ai_verified_at` added — tracks last approved AI-verification date per tool (see "Zweryfikuj przez AI" section above)
- GA4 ↔ Search Console linked directly in Google Analytics UI (Admin → Product links) — configuration only, nothing in repo
- `.github/workflows/deploy.yml` gained a `workflow_dispatch` trigger alongside `push` — enables one-click manual deploy from the Actions tab (replaces the `git commit --allow-empty` trick for DB-only changes, and avoids the risk of accidentally re-running a stale old workflow run instead of building current `main`). A PHP-triggered deploy button (via GitHub API) was considered and deliberately rejected — it would require storing a GitHub Personal Access Token on the Cyberfolks server, widening the admin panel's attack surface beyond the existing OpenAI key.

## Documentation rules

- Every important technical decision → entry in `DECISIONS.md` (ADR format)
- Every significant session → entry in `CHANGELOG.md` and/or Notion status page
- Watch for doc/reality drift — this session found two: `promote_scrape_to_tools()` living in Supabase (not repo) was easy to miss when searching for approval logic, and the "keyword filter" described in older docs didn't match its actual (buggy) implementation. When in doubt, verify against the live trigger/processor definition rather than trusting the doc.
