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

**aifirmy.pl** — a Polish-language catalog and content aggregator for AI tools, SaaS, courses, and startups targeting the PL/EU/global market, with a unique differentiator: every listing is tagged for RODO compliance, EU AI Act risk level, and PLN pricing. Live on Cyberfolks, generating revenue (Stripe live + affiliate). ~260+ tools across **10 categories** as of September 2026.

## Tech stack (current)

| Layer | Technology | Notes |
|---|---|---|
| Frontend | Astro 6 + Tailwind CSS v4 | `frontend/` subdirectory in repo root — always prefix paths with `frontend/` in git commands |
| Backend/API | No dedicated Node/Python backend | ADR-005 closed — business logic lives in PHP (admin, Stripe, email) + direct Supabase REST calls from frontend |
| Database | PostgreSQL — Supabase free (eu-central-1) | Cyberfolks has MariaDB only — incompatible with schema |
| ETL/Scraping | Apache NiFi 2.9.0 — self-hosted on Windows | **4 sources**: HN, BetaList, Product Hunt, **YC-OSS API** (added 2026-08-30) |
| AI descriptions (pipeline) | OpenAI `gpt-4o-mini` | Prompt returns `best_for_pl` and `is_real_product`; YC branch additionally passes real `source_context` (YC one_liner/long_description) instead of guessing from title alone |
| AI verification (admin) | OpenAI `gpt-4o-mini` | `verify_tool.php`, `response_format: json_object`, key in `private_html/config/openai.php` on server (not in repo) |
| Admin panel | PHP + Supabase REST API | `admin/index.php`, `admin/affiliate.php`, `admin/verify_tool.php` |
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
│   └── verify_tool.php      ← "Zweryfikuj przez AI" endpoint
├── nifi-flows/              ← NiFi flow exports (.json) — export manually after UI changes, one-line JSON so git diff shows "1 line changed" even for large edits (normal)
├── db/
│   └── migrations/          ← PostgreSQL SQL migrations (not always used — schema changes often made directly in Supabase SQL Editor)
└── docs/                    ← ARCHITECTURE.md, DECISIONS.md, CHANGELOG.md, STATUS.md
```

**Not in repo (server-only, never committed):**
- `private_html/config/db.php` — admin panel session password
- `private_html/config/openai.php` — `OPENAI_API_KEY` constant for `verify_tool.php` (unconditional `require_once` — admin panel 500s if missing)
- `private_html/logs/verify_debug.log` — debug checkpoint log for `verify_tool.php`

## Architecture decisions (settled)

- **ADR-001:** Frontend is Astro.
- **ADR-002:** Docs-as-code in `/docs`, Notion for planning only.
- **ADR-003/008:** ETL is Apache NiFi 2.9.0, self-hosted locally on Windows.
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
  rodo_compliant   BOOLEAN     NOT NULL DEFAULT false,   -- manual-only, never touched by AI
  dpa_available    BOOLEAN     DEFAULT false,
  eu_data_hosting  BOOLEAN     DEFAULT false,
  ai_act_risk      TEXT        CHECK (ai_act_risk IN ('minimal','limited','high','unacceptable')),
  ai_act_notes     TEXT,
  target_size      TEXT[],
  best_for_pl      TEXT,                          -- one-sentence target audience, AI-generated
  ai_verified_at   TIMESTAMPTZ,                   -- last time "Zweryfikuj przez AI" changes were approved
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

`scrape_queue` mirrors most of `tools` plus: `raw_name`, `raw_desc`, `raw_json`, `url_hash`, `ai_description`, `ai_category`, `ai_tags`, `ai_rodo`, `ai_pricing_model`, `best_for_pl`, `tool_id`, `error_msg`, `scraped_at`, `processed_at`, `source_name` (values: `hacker_news`, `yc_ai`), and:
- `stage` — `pending` → `ai_done` → `published`, or `ai_rejected` (when AI's `is_real_product` is `false`). **CHECK constraint on this column must be updated whenever a new stage value is introduced** — see gotcha below, this bit us once already.

**Unique differentiators:** `rodo_compliant`, `dpa_available`, `eu_data_hosting`, `ai_act_risk`, `best_for_pl` — no Polish AI catalog tags these. Now surfaced not just as badges but as full educational FAQ explanations (see "Frontend FAQ" section below).

## Database trigger — `promote_scrape_to_tools()`

**Lives in Supabase (Postgres function/trigger), NOT in this repo.** The PHP "Zatwierdź" button in `admin/index.php` only sets `scrape_queue.stage = 'published'`; this trigger does the actual `INSERT INTO tools`. Current definition includes `best_for_pl` and favicon fallback:

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

**Note:** favicon fallback only applies to *new* rows via this trigger; a one-time backfill was run manually for pre-existing tools.

**⚠️ Known gap:** `website_url` for entries sourced from Product Hunt sometimes ends up pointing to the PH listing page instead of the tool's real domain (this is a `source_url`/`website_url` data quality issue upstream of the trigger, not a trigger bug). Fixed manually per-case when spotted (e.g. TraceLLM, Cleanlist AI). Worth a spot-check pass periodically.

## ETL pipeline (NiFi) — 4 sources, two-layer quality filter

NiFi 2.9.0 running locally on Windows. Four sources feed `scrape_queue` via daily crons (HN 2:00, BetaList 3:00, Product Hunt 4:00, **YC-OSS API 5:00**), converging on a shared OpenAI path (YC branch skips one shared filter — see below).

### HN branch (before merge with BetaList/Product Hunt)
```
GenerateFlowFile → InvokeHTTP (topstories) → SplitJson → InvokeHTTP (item details)
  → EvaluateJsonPath (pre-filter) → RouteOnAttribute "przepusc" (URL domain blocklist)
  → EvaluateJsonPath → RouteOnAttribute "nowe_i_story" (last 24h + type=story)
  → RouteOnAttribute "show_launch_hn" (title starts with "show hn:"/"launch hn:" OR
    matches YC batch pattern) — drastically reduces volume but high precision
```

### BetaList/Product Hunt branches
Atom feed → SplitXml → RouteOnContent → EvaluateXPath (needs `local-name()` for namespaced XML) → UpdateAttribute (normalize to shared `hn_*` attribute names) → date filter.

### YC-OSS API branch (added 2026-08-30)
```
GenerateFlowFile (YC AI Trigger, cron 5:00)
  → InvokeHTTP (GET https://yc-oss.github.io/api/tags/artificial-intelligence.json)
  → SplitJson ($.*)
  → EvaluateJsonPath (yc_name, yc_website, yc_one_liner, yc_long_description,
    yc_launched_at, yc_batch, yc_slug)
  → RouteOnAttribute "nowa_firma" (launched_at within last 90 days:
    ${yc_launched_at:toNumber():gt(${now():toNumber():divide(1000):minus(7776000)})})
  → UpdateAttribute:
      hn_title = ${yc_name}
      hn_url = ${yc_website}
      hn_type = story
      source_context = ${yc_one_liner:replaceAll('[\r\n]+', ' '):replaceAll('"', '')}
                        ${yc_long_description:replaceAll('[\r\n]+', ' '):replaceAll('"', '')}
      source_name_override = yc_ai
  → [connects DIRECTLY to ExecuteSQL dedup, bypassing the shared "pasuje" keyword filter —
     YC data is already pre-verified as AI via the tag endpoint itself]
```

**Why this source is higher quality:** every company on the YC list went through real funding — eliminates the "article/essay" hallucination category entirely. `website` field always points to the real domain, not a launch-platform listing page. `one_liner`/`long_description` give the OpenAI prompt real source text to translate instead of guessing from a bare title (dramatically improves description accuracy — see the Linzumi before/after test in project history).

### Merge point (HN + BetaList + Product Hunt only — YC bypasses this) — shared processors
```
RouteOnAttribute "pasuje" (keyword filter: ai/tool/saas/launch/llm/gpt/model/open source)
  Uses `matches('(?i).*\bai\b.*')` with a word boundary — NOT `contains('ai')`, which was
  a critical historical bug matching the substring "ai" inside ANY word (brAIn, explAIn...).
  → ExecuteSQL (dedup) → RouteOnAttribute (new record?)
```

### Shared OpenAI path (all 4 sources converge here)
```
ReplaceText (builds OpenAI request body):
  system prompt includes 10 categories (not 9 — updated 2026-09-01), best_for_pl and
  is_real_product rules
  user content: "Nazwa: ${hn_title} URL: ${hn_url} Kontekst: ${source_context:isEmpty():ifElse('brak', ${source_context})}"
  (source_context only populated for YC branch — HN/BetaList/PH get "brak")
  → InvokeHTTP (OpenAI gpt-4o-mini)
  → EvaluateJsonPath "ai_response" ($.choices[0].message.content)
  → EvaluateJsonPath (ai_category, ai_description, ai_name, ai_pricing_model, ai_segment,
    ai_best_for_pl, ai_is_real_product)
  → ReplaceText (builds INSERT JSON):
      "source_name":"${source_name_override:isEmpty():ifElse('hacker_news', ${source_name_override})}"
      (defaults to hacker_news for branches without the override attribute; yc_ai for YC branch)
      stage: ${ai_is_real_product:equals('false'):ifElse('ai_rejected','ai_done')}
  → PutDatabaseRecord (INSERT scrape_queue, Record Reader: JsonTreeReader)
```

**Current system prompt category list (10, as of 2026-09-01):** Automatyzacja procesów, Analityka i BI, Finanse i księgowość, HR i rekrutacja, Marketing i content, Obsługa klienta, Prawo i compliance, Sprzedaż i CRM, Zarządzanie projektami, **Cyberbezpieczeństwo AI**.

**Even with `is_real_product` and real source_context, expect occasional wrong category/pricing guesses** — this is inherent model uncertainty, not a bug. Confirmed via manual research on multiple tools that both the "old" (pipeline) and "new" (verify_tool.php live-check) values can each be wrong in different cases — there's no single authoritative source, both are AI inferences from limited context. The verification UI's manual accept/reject per field is the correct mitigation, not further prompt engineering (a previous prompt tweak to fix one direction of the free/paid confusion introduced the opposite error elsewhere).

### Known NiFi quirks
- `\n` in ReplaceText Replacement Value → literal `n` — system prompt/content must be one line
- `\"` in Replacement Value loses its backslash — no raw quotes inside JSON content strings; strip them (`replaceAll('"', '')`) rather than trying to escape
- `EvaluateJsonPath` can't handle arrays → tags stored as raw JSONB
- InvokeHTTP caches its connection pool after config changes — **Stop flow → wait ~30s → Start**
- `nifi.cmd status` can report "Management Server communication failed" even when the process is alive — check `java -version` and logs before assuming failure
- URL-domain filter and Show HN/Launch HN filter live **only on the HN branch**. Keyword filter ("pasuje") lives **after** the HN/BetaList/PH merge and is shared by those three — but the **YC-OSS branch bypasses it entirely** (connects straight to the dedup step). A filter change in the wrong place can silently block sources.
- `contains('x')` in NiFi Expression Language matches substrings, not whole words — use `matches('(?i).*\bx\b.*')` with word boundaries
- Full flow export is a single-line JSON file — `git diff` on `nifi-flows/*.json` always shows "1 line changed" even for large structural edits; this is normal, verify actual content with `grep` for expected attribute/URL names rather than trusting the diff line count

## Admin panel (`admin/`, PHP + Supabase REST API)

- **`admin/index.php`** — tabs: Kolejka (scrape_queue, stage=ai_done) / **Odrzucone przez AI** (stage=ai_rejected) / Narzędzia (tools, with per-row **"Zweryfikuj przez AI"** button and per-row inline URL/category/logo editors) / Dodaj wpis (manual add_tool form)
- **`admin/affiliate.php`** — affiliate_links CRUD, toggle active without reload
- **`admin/verify_tool.php`** — POST endpoint, `tool_id` in, `{"old": {...}, "new": {...}}` out:
  1. Fetch current tool + categories from Supabase (categories interpolated live from DB, not hardcoded)
  2. Live curl-fetch `website_url` (6s timeout, browser UA, follows redirects, hard-fails on HTTP ≥400)
  3. Extract `<title>`, meta description, body text (script/style/nav/footer stripped, capped ~3000 chars) + `logo_hint`
  4. Call `gpt-4o-mini` with `response_format: json_object`
  5. Resolve AI's category string back to `category_id` server-side; **auto-disables the category checkbox client-side with a warning if no exact match** — this correctly caught the AI Act "Bezpieczeństwo IT" hallucination in Sept 2026 before it could be saved as a null category
  6. `rodo_compliant` is **never** sent to or returned from the AI — manual-only field, by design
  7. `ai_act_risk_suggestion` and `logo_hint` default to **unchecked** in the UI; description/category/pricing_model/best_for_pl default **checked**. `category_ai_act_hint` (see below) also defaults unchecked.
  8. Wrapped in `try/catch (\Throwable)` + `register_shutdown_function`, logging to `error_log()` and `private_html/logs/verify_debug.log`

### `extract_logo_hint()` — fixed 2026-09-05
Original implementation required specific attribute order in `<meta>`/`<link>` tags (`property` before `content`, `rel` before `href`) — HTML doesn't enforce this, so many real sites (especially Next.js-based, common among YC startups) were missed despite having valid tags. **Fixed:** extract the full tag first, then match attributes within it regardless of order. Added `rel="apple-touch-icon"` as a fallback when `icon`/`shortcut icon` is absent. Added a final fallback to `https://www.google.com/s2/favicons?domain={domain}&sz=128` (same domain-extraction logic as `promote_scrape_to_tools()`) when both extractions fail — previously returned `null` with no safety net. Shared helper `resolve_logo_url()` extracted for relative URL resolution, now applied consistently to both favicon and `og:image` (was favicon-only before).

**Observed pattern even after the fix:** `og:image` frequently resolves successfully but points to a promotional banner (e.g. `og-home-en.jpg`, `/opengraph-image`), not an actual logo. The Google favicon fallback is generally the safer default to accept in the verification UI — reject `og:image`-sourced `logo_hint` suggestions unless visually confirmed.

### `$CATEGORY_AI_ACT_HINTS` (category-based AI Act risk fallback)
Static map providing a fallback AI Act risk suggestion (labeled "Sugestia wg kategorii — Załącznik III AI Act", with an explicit "not legal advice" disclaimer) when the AI's own page-derived suggestion is empty. Covers 7 of 10 categories with high confidence (HR i rekrutacja=high, Obsługa klienta=limited, most others=minimal). **Deliberately excludes Finanse i księgowość, Prawo i compliance, and Cyberbezpieczeństwo AI** — all three are too heterogeneous internally for a safe category-level default (e.g. a generic SaaS pentesting tool is `minimal` but something managing critical-infrastructure security could be `high` under Annex III).

### Pricing prompt refinement (trial vs. permanent free plan)
System prompt for `verify_tool.php` includes an explicit rule distinguishing a permanent free tier from a free trial ("free trial", "X days free" ≠ `free`/`freemium`). Added after a real bug where Woodpecker.co (paid, trial-based) was misclassified as `free`. **Caution:** this fix can overcorrect in the other direction — a real case (Linzumi) showed the live-check suggesting `paid` when the actual pricing page had a genuine permanent free tier for solo builders, phrased as "Yours forever, no credit card" rather than the word "Free". Treat pricing_model discrepancies as requiring manual research, not as evidence either side of the diff is more trustworthy.

**Frontend logo placeholder logic** (`CompanyCard.astro`, `[slug].astro`): if `logo_url` is empty, render a colored initial-letter placeholder using the tool's category color.

## Frontend FAQ (`[slug].astro`) — expanded 2026-08-31 (commit `6faf053`)

FAQ section now has **6 questions** (was 4): RODO → DPA → EU data hosting → pricing model → AI Act risk → target_size. RODO and AI Act questions now include full plain-language legal explanations (what RODO/DPA/EU-hosting/each AI Act risk level actually means and requires), not just a one-line yes/no — this is deliberate: it's a **general educational explainer per category/risk-level**, the same text for every tool in that bucket, never an AI-generated per-tool legal judgment (that risk was deliberately avoided, same reasoning as the `$CATEGORY_AI_ACT_HINTS` exclusions above).

JSON-LD `FAQPage` schema is generated automatically from the same `faqs` array used for the visible accordion — don't maintain these separately, that was a deliberate fix to avoid future drift between visible content and structured data.

## SEO conventions & fixes

- URLs: `/narzedzia/[slug]`, `/kategoria/[slug]` — canonical form always has a **trailing slash**
- Every page: `title`, meta description, OG tags, schema.org `SoftwareApplication`; tool detail pages also have `FAQPage` JSON-LD
- Sitemap auto-generated by Astro (`@astrojs/sitemap`)

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
- **`admin/` is separate from `frontend/`** — lives at repo root, deployed via a different SCP step in the same GitHub Actions workflow
- **No shared `Footer.astro` component** — footer markup (including the AI-content disclosure line) is duplicated across 6 page files (`index.astro`, `[slug].astro`, `premium.astro`, `polityka-prywatnosci.astro`, `kontakt.astro`, `dziekujemy.astro`). Any footer change needs to be applied to all 6. Flagged as a refactoring candidate, not yet done.

## Workflow — two Claude instances

- **Claude.ai (this chat)** = architect and advisor — strategy, planning, decisions, documentation, prompt drafting for Claude Code
- **Claude Code in VS Code** = executor — code generation, file editing, commits
- **CLAUDE.md** = bridge between them — must always be up to date
- **SQL migrations and NiFi UI config are done manually by Pablo**, guided by exact SQL/config text from Claude.ai — NiFi has no CLI/API access, and SQL changes follow an established "SQL Editor, not Claude Code" convention
- **Manual deploy trigger available:** `.github/workflows/deploy.yml` has `workflow_dispatch` alongside `push` — use "Run workflow" from the GitHub Actions tab for DB-only changes that need a rebuild (SSG means DB changes alone don't appear on the live site), instead of `git commit --allow-empty`

## Environment variables & secrets

`.env.example` (frontend, committed):
```
DATABASE_URL=postgresql://postgres.[project-id]:[password]@aws-1-eu-central-1.pooler.supabase.com:5432/postgres
OPENAI_API_KEY=sk-...   # NiFi Parameter Context, separate from admin panel's key
NODE_ENV=development
PORT=3000
```
**Note:** local `npm run build` fails without a real `frontend/.env` containing `PUBLIC_SUPABASE_URL`/`PUBLIC_SUPABASE_ANON_KEY` (pages are SSG and query Supabase at build time). When this file isn't available in a session, verify changes with `npx astro check` (type-check, no DB needed) instead, and note in the diff summary that a full build wasn't possible.

**Server-only, never in repo** (all under `private_html/`, same pattern as `db.php`):
- `config/db.php` — admin session password
- `config/openai.php` — `define('OPENAI_API_KEY', 'sk-...')` for `verify_tool.php`. **Required for admin panel to load at all.**

## Current status (as of 2026-09-05)

Live, revenue-generating (Stripe + affiliate), catalog grown from ~90 to ~260+ tools. 4th NiFi source (YC-OSS API) and 10th category (Cyberbezpieczeństwo AI) both shipped and verified. `verify_tool.php` logo detection fixed. Search Console shows a consistent week-over-week traffic increase (see STATUS.md for the trend table); GA4 is known-unreliable (see Analytics section above).

**Immediate next priorities (per the roadmap agreed 2026-08-23):**
1. Resume growth activities (LinkedIn posts — drafts revised and saved in Notion — and cold outreach), paused since ~July pending catalog growth, which has now happened
2. Monitor traffic toward the 1000 UV/month AdSense threshold
3. Continue spot-checking "Zweryfikuj przez AI" results as more YC-sourced tools accumulate, especially watching for category taxonomy gaps (the way Cyberbezpieczeństwo AI was discovered) and pricing_model disagreements (expected, not a bug — resolve per-case via manual research)

**Deferred, not forgotten:** newsletter, PDF industry report, additional affiliate programs, AI-written articles section (deliberately paused — see Notion for the full risk analysis: higher error cost than short tool descriptions, copyright exposure, clearer AI Act Art. 50(4) applicability, and it doesn't address the actual current bottleneck of domain authority/distribution).

## Documentation rules

- Every important technical decision → entry in `DECISIONS.md` (ADR format)
- Every significant session → entry in `CHANGELOG.md` and/or Notion status page
- **Watch for doc/reality drift.** Confirmed instances so far: `promote_scrape_to_tools()` living in Supabase (not repo); the "keyword filter" once described in docs didn't match its buggy actual implementation (`contains` vs. word-boundary match); the `categories` table schema (has `name_pl` NOT NULL, not just `slug`) was assumed incorrectly in an earlier memory note until directly queried. **When in doubt, verify against the live database schema / trigger / NiFi processor definition rather than trusting a doc or a prior assumption — this has been wrong more than once.**
