# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 🔗 Project links (use these in every session)

| Resource | URL |
|---|---|
| Notion — project overview | https://www.notion.so/aifirmy-pl-Katalog-AI-i-SaaS-373b4cccb4af81bb9ec5ef0a5ca32318 |
| Notion — session notes | https://app.notion.com/p/374b4cccb4af8103afbbc353f9fd300e |
| GitHub — main repo | https://github.com/siwy126Pablo/aifirmy-pl |
| GitHub — keep-alive ping | https://github.com/siwy126Pablo/aifirmy-ping |
| Supabase — dashboard | https://supabase.com/dashboard/project/szassqzvivdgvpkciyif |

## 🚀 How to start a new session

Paste this at the beginning of each new chat:

```
Kontynuujemy projekt aifirmy.pl.
Przeczytaj CLAUDE.md i zapoznaj się z aktualnym statusem projektu.
Notion (projekt): https://www.notion.so/aifirmy-pl-Katalog-AI-i-SaaS-373b4cccb4af81bb9ec5ef0a5ca32318
Notion (notatki sesji): https://app.notion.com/p/374b4cccb4af8103afbbc353f9fd300e
Jesteśmy w Tygodniu X, kontynuujemy: [opisz co robisz].
```

## Project overview

**aifirmy.pl** — a Polish-language catalog and content aggregator for AI tools, SaaS, courses, and startups targeting the PL/EU/global market. Domain is live on Cyberfolks; frontend development started June 2026.

## Tech stack (aktualny)

| Layer | Technology | Notes |
|---|---|---|
| Frontend | Astro 6 + Tailwind CSS v4 | ✅ wdrożony (`@tailwindcss/vite`, bez pliku konfiguracyjnego) |
| Backend/API | brak dedykowanego backendu Node/Python | ADR-005 zamknięte przez brak działania — logika biznesowa żyje w PHP (admin, Stripe, e-mail) + bezpośrednich wywołaniach Supabase REST API z frontendu |
| Database | PostgreSQL — **Supabase free** (eu-central-1, Frankfurt) | Cyberfolks ma tylko MariaDB — niekompatybilne ze schematem |
| ETL/Scraping | Apache NiFi 2.9.0 — **self-hosted lokalnie na Windows** | 3 źródła: Hacker News (cron 2:00), BetaList (cron 3:00), Product Hunt RSS (cron 4:00) |
| AI descriptions | OpenAI API — **`gpt-4o-mini`** | nie `gpt-4o` |
| Admin panel | **PHP + Supabase REST API** | nie Supabase Studio — ADR-006 wymaga aktualizacji |
| Płatności | **Stripe (Live mode)** — `checkout.php` + `webhook.php` | webhook pod `/stripe/webhook.php` (Cloudflare WAF blokuje POST do `/admin/`) |
| Email | **PHPMailer** przez SMTP Cyberfolks | `s103.cyber-folks.pl:587` |
| Analytics | **Google Analytics 4** (`G-3SP1TRXF7M`) | warunkowe ładowanie po cookie consent |
| Hosting | Cyberfolks (frontend) + Cloudflare (CDN, DDoS, SSL Full strict) | |

**Lokalizacja projektu:** `C:\Dev\aifirmy-pl` (przeniesiony z `C:\Users\pawel\Praca` — ESET blokował `node_modules` pod `C:\Users\`).

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

> ⚠️ ADR-006 is now out of date in practice (admin panel is PHP + Supabase REST API, not Supabase Studio) — flag for a follow-up ADR update.

## Open architectural decisions

- **ADR-005:** ~~Backend language — Node.js vs Python (FastAPI).~~ Superseded in practice — no dedicated backend was built; consider formally closing this ADR.

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

NiFi 2.9.0 running locally on Windows. Three sources feed `scrape_queue`, each on its own daily cron:

- **Hacker News API** — cron 2:00
- **BetaList** — cron 3:00
- **Product Hunt RSS** — cron 4:00 (Product Hunt's own API/site is blocked by Cloudflare 403 — RSS is the workaround)

**Flow per source:**
```
GenerateFlowFile (cron 2:00 / 3:00 / 4:00)
  → InvokeHTTP (GET source: HN topstories / BetaList / Product Hunt RSS)
  → SplitJson / parse feed (one flowfile per item)
  → InvokeHTTP (GET item details, where applicable)
  → InvokeHTTP (POST OpenAI API gpt-4o-mini — generate name, description, category, tags, segment, pricing_model)
  → EvaluateJsonPath (extract fields)
  → PutDatabaseRecord (INSERT scrape_queue, Supabase via JDBC Session Pooler)
  → Moderation via admin panel (PHP + Supabase REST API) (approve/reject)
  → Frontend (status=approved)
```

**Known quirks:**
- `EvaluateJsonPath` doesn't support arrays → `ai_tags` stored as raw JSONB in `ai_response` column
- `scraped_at` excluded from INSERT — Supabase fills automatically via DEFAULT
- OpenAI prompt kept as one line with no quotes / no `\n` — `ReplaceText` turns literal `\n` into the letter `n`, and quotes lose their escaping backslash otherwise, which breaks the JSON response
- `InvokeHTTP` caches its connection pool — after changing processor config, **Stop the flow, wait ~30s, then Start** or the old config keeps being used
- Cloudflare WAF blocks `POST /admin/` — this is why the Stripe webhook lives at `/stripe/webhook.php` instead of under `/admin/`

NiFi JDBC: `jdbc:postgresql://aws-1-eu-central-1.pooler.supabase.com:5432/postgres`, user `postgres.szassqzvivdgvpkciyif`.
Flow exported to `/nifi-flows/` in repo.

## AI prompt for tool descriptions

Current system prompt (single line, no quotes — see NiFi quirks above):

```
Opisz narzedzie/firme w 2 zdaniach. Ton: neutralny, informacyjny, SEO-friendly. Jezyk: polski. Format odpowiedzi tylko JSON bez markdown: { name, description, category, tags, segment, pricing_model } Zasady dla pola name: - Krotka nazwa produktu lub narzedzia (max 50 znakow) - Bez prefiksu Show HN: i podobnych - Bez podtytulu po myslniku lub dwukropku Zasady dla pola pricing_model: - Wybierz JEDNA wartosc: free, freemium, paid, open_source Zasady dla pola category: - Wybierz JEDNA kategorie z tej listy: Automatyzacja procesów, Analityka i BI, Finanse i księgowość, HR i rekrutacja, Marketing i content, Obsługa klienta, Prawo i compliance, Sprzedaż i CRM, Zarządzanie projektami
```

Uses `gpt-4o-mini` (not `gpt-4o`).

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

## Workflow — two Claude instances

- **Claude.ai (this chat)** = architect and advisor — strategy, planning, decisions, documentation
- **Claude Code in VS Code** = executor — code generation, file editing, commits
- **CLAUDE.md** = bridge between them — must always be up to date
- **`.md` files pinned to Claude.ai project** = full context on both sides

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

## Current status (czerwiec 2026)

**Tydzień 1 ✅** — Strategia, nisza, 9 kategorii, lista 100 narzędzi.

**Tydzień 2 ✅** — Baza Supabase (6 tabel):
- `tools`, `categories`, `tags`, `tool_tags`, `premium_listings`, `scrape_queue`
- Seed: 9 kategorii, 10 tagów, 3 zatwierdzone narzędzia (Make, n8n, Rossum)
- Indeks pełnotekstowy (`GIN`, `to_tsvector('simple', ...)`)
- Trigger `updated_at` na `tools`
- Keep-alive: cron GitHub Actions w repo `aifirmy-ping` (ping co 5 dni)

**Tydzień 3 ✅** — Pipeline NiFi (3 źródła: HN + BetaList + Product Hunt RSS):
- HN topstories → SplitJson → item details → OpenAI → INSERT `scrape_queue`
- Cron: HN 2:00, BetaList 3:00, Product Hunt RSS 4:00
- Flow wyeksportowany do `/nifi-flows/`

**Tydzień 4 ✅** — Frontend Astro:
- Wszystkie strony (`index`, `/narzedzia/[slug]`, `/kategoria/[slug]`)
- Integracja z Supabase (zapytania zamiast danych hardcodowanych)
- Deploy na Cyberfolks

**Tydzień 5 ✅** — Monetyzacja:
- Stripe Live mode, `checkout.php` + `webhook.php`
- Panel admina PHP (`admin/index.php`)
- Soft delete narzędzi

**Tydzień 6 ✅** — Growth:
- Google Analytics 4 (`G-3SP1TRXF7M`, warunkowe ładowanie po cookie consent)
- Search Console
- E-mail po zakupie (PHPMailer, SMTP Cyberfolks)

**Tydzień 7 ✅** — Affiliate links:
- Tabela `affiliate_links` (relacja do `tools`, trigger `updated_at` reużywa `set_updated_at()`)
- Panel `admin/affiliate.php` — CRUD + toggle aktywności, wzorowany na `admin/index.php`
- Frontend `[slug].astro` — CTA przełącza się na `affiliate_url` + disclosure, gdy aktywny link istnieje
- Pierwszy program: ClickUp / PartnerStack (Tier 2 PL, $10/signup, cookie 180 dni) — aplikacja złożona, czeka na akceptację

**T5.5 ✅** — Poprawki pipeline: slugify, `name` generowane przez AI, `pricing_model` z AI, filtr URL, obsługa BetaList.

**Ostatnie sesje ✅** — Cookie consent, polityka prywatności, wyróżnienie premium, obsługa anulowania subskrypcji.

**Known setup quirks (Windows):**
- Astro v6 + Tailwind v4 installation requires `--template minimal --no-git --no-install` flag due to Node.js v24 bug
- PowerShell requires `Set-ExecutionPolicy RemoteSigned` before running npm
- Projekt trzymany w `C:\Dev\aifirmy-pl` — ESET blokował `node_modules` pod `C:\Users\`

**Not yet built:** dedykowany backend Node/Python (obecnie logika w PHP + Supabase REST API), `db/migrations/` jako uporządkowany katalog migracji, finalna weryfikacja konfiguracji Cloudflare.

## Documentation rules

- Every important technical decision → entry in `DECISIONS.md` (ADR format)
- Every week of development → entry in `CHANGELOG.md`
- One commit = code change + documentation update (no context switching)
