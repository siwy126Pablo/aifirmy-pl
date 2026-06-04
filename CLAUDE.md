# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

**aifirmy.pl** — a Polish-language catalog and content aggregator for AI tools, SaaS, courses, and startups targeting the PL/EU/global market. Domain is live on Cyberfolks; frontend development started June 2026.

## Planned tech stack

| Layer | Technology | Notes |
|---|---|---|
| Frontend | Astro + Tailwind CSS | |
| Backend/API | Node.js (Express/Fastify) **or** Python (FastAPI) — ADR-005 pending | |
| Database | PostgreSQL — **Supabase free** (eu-central-1, Frankfurt) | Cyberfolks has MariaDB only — incompatible with schema |
| Cache | Redis | |
| ETL/Scraping | Apache NiFi 2.9.0 — **self-hosted locally on Windows** | Oracle Cloud dropped — ADR-003 updated |
| AI descriptions | OpenAI API (GPT-4o) | |
| Admin panel | **Supabase Studio** — ADR-006 resolved | Built-in table editor, no custom admin needed |
| Hosting | Cyberfolks (frontend) + Cloudflare (CDN, DDoS, SSL Full strict) | |

## Repository structure (planned)

```
aifirmy-pl/
├── frontend/          ← Astro + Tailwind
│   └── src/
│       ├── pages/     ← index, /narzedzia/[slug], /kategoria/[slug]
│       ├── components/← company-card.astro, filters, navigation
│       └── layouts/
├── backend/           ← API
│   ├── routes/
│   ├── models/        ← Company, Category, Tag
│   └── services/      ← AI generator, scraper
├── nifi-flows/        ← Apache NiFi flow exports (.json)
├── db/
│   └── migrations/    ← PostgreSQL SQL migrations
└── docs/              ← ARCHITECTURE.md, DECISIONS.md, CHANGELOG.md, API.md, NIFI-FLOWS.md, SETUP.md
```

## Architecture decisions (settled)

- **ADR-001:** Frontend is Astro — chosen for minimal client-side JS, best Core Web Vitals and SEO for a content catalog.
- **ADR-002:** Docs-as-code — documentation lives in `/docs` as Markdown committed alongside code. Notion is for planning/goals only, not technical docs.
- **ADR-003:** ETL is Apache NiFi 2.9.0 — **self-hosted locally on Windows**, not Oracle Cloud. NiFi flows are exported as JSON and version-controlled in `/nifi-flows/`.
- **ADR-004:** Hosting is Cyberfolks (frontend) + Cloudflare — Cloudflare must be configured before launch (not after).
- **ADR-006:** Admin panel is **Supabase Studio** — resolved. Cyberfolks has MariaDB only (incompatible), so Supabase free tier (PostgreSQL, eu-central-1 Frankfurt) is used for both database and admin panel.
- **ADR-007:** Database is **Supabase free (PostgreSQL)** — Cyberfolks package (cyber_IN_unlimited) provides MariaDB only, which lacks `JSONB`, `text[]`, `GIN` index, and Polish full-text search. Supabase free: 500 MB, 50k users/mo, 5 GB transfer. Project keep-alive via GitHub Actions cron (repo: `aifirmy-ping`, pings every 5 days).
- **ADR-008:** NiFi runs **locally on Windows** (NiFi 2.9.0, Java 25, JDBC driver in `C:\nifi\lib`). Supabase connection via Session Pooler: `aws-1-eu-central-1.pooler.supabase.com:5432`, user `postgres.[project-id]`. Product Hunt blocked (Cloudflare 403) — replaced with **Hacker News API** as primary scraping source.

## Open architectural decisions

- **ADR-005:** Backend language — Node.js vs Python (FastAPI). Evaluate based on AI/scraping library ecosystem.

## Data model

Six tables. Core table is `tools` (replaces the old `companies` concept):

```sql
-- tools: main catalog entry
CREATE TABLE tools (
  id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  slug             TEXT        NOT NULL UNIQUE,
  name             TEXT        NOT NULL,
  tagline_pl       TEXT,
  description_pl   TEXT,                          -- AI-generated
  logo_url         TEXT,
  website_url      TEXT        NOT NULL,
  category_id      UUID        REFERENCES categories(id),
  pricing_model    TEXT        CHECK (pricing_model IN ('free','freemium','paid','open_source')),
  price_from_pln   NUMERIC(10,2),
  price_note       TEXT,
  rodo_compliant   BOOLEAN     NOT NULL DEFAULT false,
  dpa_available    BOOLEAN     DEFAULT false,
  eu_data_hosting  BOOLEAN     DEFAULT false,
  ai_act_risk      TEXT        CHECK (ai_act_risk IN ('minimal','limited','high','unacceptable')),
  ai_act_notes     TEXT,
  target_size      TEXT[],                        -- ['smb','mid','enterprise']
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

-- Other tables: categories, tags, tool_tags (M:N), premium_listings, scrape_queue
-- Full schema in db/migrations/001_initial.sql
```

**Unique differentiators:** `rodo_compliant`, `dpa_available`, `eu_data_hosting`, `ai_act_risk` — no Polish AI catalog tags these fields.

## ETL pipeline (NiFi)

NiFi 2.9.0 running locally on Windows. Primary source: **Hacker News API** (Product Hunt blocked by Cloudflare). Runs via cron 1× daily.

Current flow on canvas (Week 3, in progress):
```
GenerateFlowFile (60s timer)
  → InvokeHTTP (GET https://hacker-news.firebaseio.com/v0/topstories.json)
  → EvaluateJsonPath (extract fields)
  → PutDatabaseRecord (INSERT into scrape_queue, Supabase via JDBC Session Pooler)
```

Planned full pipeline:
```
GenerateFlowFile
  → InvokeHTTP (topstories list)
  → SplitJson (one flowfile per ID)
  → InvokeHTTP (GET /v0/item/{id}.json — item details)
  → InvokeHTTP (POST OpenAI API — generate PL description)
  → PutDatabaseRecord (INSERT scrape_queue status=ai_done)
  → Moderation in Supabase Studio (approve/reject)
  → Frontend (status=approved)
```

NiFi JDBC connection: `jdbc:postgresql://aws-1-eu-central-1.pooler.supabase.com:5432/postgres`, user `postgres.szassqzvivdgvpkciyif`.

## AI prompt for company descriptions

```
Opisz firmę/narzędzie w 2 zdaniach.
Ton: neutralny, informacyjny, SEO-friendly, bez marketingu.
Język: polski.
Dodaj: główna kategoria, 3-5 tagów, ocena segmentu (enterprise/SMB/solo).
Format: JSON { description, category, tags, segment }
```

## SEO conventions

- URLs: `/narzedzia/[slug]` and `/kategoria/[slug]`
- Every page requires: `title`, `meta description`, OG tags, `schema.org` (SoftwareApplication)
- Sitemap: auto-generated by Astro
- SSR for dynamic pages, SSG for static pages

## Code conventions

- File names: `kebab-case` (e.g., `company-card.astro`)
- Components: `PascalCase` (e.g., `CompanyCard`)
- Variables: `camelCase`
- SQL keywords: `UPPER_CASE`
- Git commit prefixes: `feat:`, `fix:`, `docs:`, `refactor:`

## Current status (June 2026)

Frontend is scaffolded and builds successfully (`npm run build` inside `frontend/`).

**Built so far:**

- `frontend/` — Astro 6 + Tailwind CSS v4 (`@tailwindcss/vite` plugin, no config file needed)
- `frontend/src/layouts/Layout.astro` — HTML boilerplate, SEO meta tags, full Open Graph + Twitter Card, canonical URL from `Astro.site`, named `<slot name="head">` for per-page additions
- `frontend/src/components/CompanyCard.astro` — company card with name, description, category, pricing badge (free/freemium/paid), internal and external links
- `frontend/src/data/companies.ts` — typed `Company` interface + hardcoded array of 3 entries; shared by all pages. **Replace with PostgreSQL queries once backend is ready.**
- `frontend/src/pages/index.astro` — homepage: hero section, responsive 3-column card grid
- `frontend/src/pages/narzedzia/[slug].astro` — static detail page via `getStaticPaths`; renders company name, description, category, tags, pricing, external CTA; injects `schema.org SoftwareApplication` JSON-LD via `<Fragment slot="head">`

**Not yet built:** `backend/`, DB migrations, NiFi flows, `/kategoria/[slug]` page, admin panel.

## Environment variables

See `.env.example`:

```
# Supabase (PostgreSQL)
DATABASE_URL=postgresql://postgres.[project-id]:[password]@aws-1-eu-central-1.pooler.supabase.com:5432/postgres

# AI
OPENAI_API_KEY=sk-...

# Cache (future)
REDIS_URL=redis://localhost:6379

# App
NODE_ENV=development
PORT=3000
```

## Current status (June 2026)

**Week 1 ✅** — Strategy, niche, 9 categories, list of 100 tools.

**Week 2 ✅** — Database live on Supabase:
- 6 tables created: `tools`, `categories`, `tags`, `tool_tags`, `premium_listings`, `scrape_queue`
- Seed data: 9 categories, 10 tags, 3 approved tools (Make, n8n, Rossum)
- Full-text search index (`GIN`, `to_tsvector('simple', ...)`)
- `updated_at` trigger on `tools`
- Keep-alive: GitHub Actions cron in repo `aifirmy-ping` (pings Supabase every 5 days)

**Week 3 🔄 In progress** — NiFi pipeline:
- NiFi 2.9.0 installed locally on Windows, running
- JDBC driver installed (`C:\nifi\lib\postgresql-42.x.x.jar`)
- DBCPConnectionPool connected to Supabase ✅
- Basic flow on canvas: `GenerateFlowFile → InvokeHTTP → EvaluateJsonPath → PutDatabaseRecord`
- Product Hunt blocked (403) → switched to Hacker News API ✅
- **TODO:** SplitJson, second InvokeHTTP for item details, OpenAI integration, field mapping to `scrape_queue`

**Not yet built:** `backend/`, `/kategoria/[slug]` page, Stripe/PayU, Cloudflare config.

## Documentation rules

- Every important technical decision → entry in `DECISIONS.md` (ADR format)
- Every week of development → entry in `CHANGELOG.md`
- One commit = code change + documentation update (no context switching)
