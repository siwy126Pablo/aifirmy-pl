# 🏗️ aifirmy.pl — Architecture & Decisions

## Cel projektu
Katalog branżowy + agregator treści AI dla polskiego rynku.
Nisza: narzędzia AI, SaaS, kursy, startupy (PL/EU/global).

## Stack techniczny

| Warstwa | Technologia | Uzasadnienie |
|---|---|---|
| **Frontend** | Astro + Tailwind | Najlepszy SSR/SSG dla SEO, lekki, szybki |
| **Backend / API** | Node.js lub Python (FastAPI) | Do wyboru w trakcie developmentu (ADR-005 otwarta) |
| **Baza danych** | PostgreSQL — **Supabase free** (eu-central-1, Frankfurt) | Cyberfolks ma tylko MariaDB — niekompatybilna ze schematem (ADR-007) |
| **Cache** | Redis | Szybkie serwowanie listy wpisów |
| **ETL / Scraping** | Apache NiFi 2.9.0 — **lokalnie na Windows** | Kompetencja zawodowa, Oracle Cloud dropped (ADR-008) |
| **AI opisy** | OpenAI API (GPT-4o) | Generowanie opisów, kategorii, tagów |
| **CMS / Admin** | **Supabase Studio** | Wbudowany panel, zero konfiguracji (ADR-006) |
| **Hosting** | Cyberfolks (frontend) + Cloudflare | Domena aktywna, CDN + ochrona (ADR-004) |
| **Wersjonowanie** | GitHub (repo: aifirmy-pl) | |

## Struktura repozytorium
```
aifirmy-pl/
├── frontend/          ← Astro + Tailwind
│   ├── src/
│   │   ├── pages/     ← index, kategoria, wpis
│   │   ├── components/← karta wpisu, filtry, nawigacja
│   │   └── layouts/
│   └── public/
├── backend/           ← API (Node.js / FastAPI)
│   ├── routes/
│   ├── models/        ← Company, Category, Tag
│   └── services/      ← AI generator, scraper
├── nifi-flows/        ← eksporty przepływów NiFi (.json)
├── db/
│   └── migrations/    ← SQL migracje PostgreSQL
└── docs/
    ├── ARCHITECTURE.md ← ten plik
    ├── API.md
    └── decisions/     ← ADR (Architecture Decision Records)
```

## Model danych — tabela tools (główna)
```sql
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
-- Pozostałe tabele: categories, tags, tool_tags (M:N), premium_listings, scrape_queue
-- Pełny schemat: db/migrations/001_initial.sql
```
**Unikalny wyróżnik:** `rodo_compliant`, `dpa_available`, `eu_data_hosting`, `ai_act_risk` — żaden polski katalog AI tych pól nie taguje.

## Pipeline NiFi
```
Hacker News API (Product Hunt zablokowany — 403 Cloudflare)
  → GenerateFlowFile (timer 60s)
  → InvokeHTTP (GET topstories.json)
  → SplitJson (jeden flowfile per ID)
  → InvokeHTTP (GET /v0/item/{id}.json — szczegóły)
  → InvokeHTTP (POST OpenAI API — opis PL + kategoria + tagi)
  → PutDatabaseRecord (INSERT scrape_queue status=ai_done)
  → Moderacja w Supabase Studio (approve/reject)
  → Frontend (status=approved)
```
Częstotliwość: cron 1× dziennie o 2:00

**Konfiguracja JDBC (Supabase Session Pooler):**
- URL: `jdbc:postgresql://aws-1-eu-central-1.pooler.supabase.com:5432/postgres`
- User: `postgres.szassqzvivdgvpkciyif`
- Driver: `C:\nifi\lib\postgresql-42.x.x.jar`

## AI Prompt (opis firmy)
```
Opisz firmę/narzędzie w 2 zdaniach.
Ton: neutralny, informacyjny, SEO-friendly, bez marketingu.
Język: polski.
Dodaj: główna kategoria, 3-5 tagów, ocena segmentu (enterprise/SMB/solo).
Format: JSON { description, category, tags, segment }
```

## SEO — konwencje
- URL: `/narzedzia/[slug]`, `/kategoria/[slug]`
- Każda strona: title, meta description, OG tags, schema.org (SoftwareApplication)
- Sitemap: auto-generowany przez Astro
- SSR dla stron dynamicznych, SSG dla statycznych

## Konwencje kodu
- Nazwy plików: kebab-case (`company-card.astro`)
- Komponenty: PascalCase (`CompanyCard`)
- Zmienne: camelCase
- SQL: UPPER_CASE dla słów kluczowych
- Commity: `feat:`, `fix:`, `docs:`, `refactor:`

## Decyzje podjęte
- [x] Nisza: narzędzia AI dla polskiego B2B (RODO, AI Act, ceny w PLN)
- [x] Baza: PostgreSQL na Supabase free zamiast MariaDB na Cyberfolks (ADR-007)
- [x] Panel admin: Supabase Studio zamiast Directus (ADR-006)
- [x] NiFi: lokalnie na Windows zamiast Oracle Cloud (ADR-008)
- [x] Źródło danych: Hacker News API zamiast Product Hunt (zablokowany)

## Decyzje otwarte
- [ ] Node.js vs Python dla backend API (ADR-005)

## Status (czerwiec 2026)
- Domena: ✅ aifirmy.pl (Cyberfolks)
- Repo: ✅ github.com/siwy126Pablo/aifirmy-pl
- Baza: ✅ Supabase (6 tabel, 9 kategorii, 3 wpisy seed)
- Frontend: ✅ Astro scaffold działa lokalnie (`npm run build`)
- NiFi: 🔄 Flow w trakcie (Tydzień 3)
- Backend API: ⬜ nie rozpoczęty
- Cloudflare: ⬜ do skonfigurowania przed startem

---

## 📄 Dokumentacja — podejście

### Strategia: docs-as-code
Dokumentacja żyje w repozytorium razem z kodem. Jeden commit = zmiana kodu + dokumentacja. Zero przełączania kontekstu.

### Struktura /docs
```
docs/
├── ARCHITECTURE.md     ← ten plik — stack, decyzje, struktura
├── DECISIONS.md        ← ADR (Architecture Decision Records)
├── CHANGELOG.md        ← tygodniowy dziennik postępów
├── API.md              ← endpointy (gdy powstanie API)
├── NIFI-FLOWS.md       ← opis przepływów ETL
└── SETUP.md            ← jak uruchomić lokalnie (docker, env, db)
```

### Zasady
- Każda ważna decyzja techniczna → wpis w DECISIONS.md
- Każdy tydzień developmentu → wpis w CHANGELOG.md
- Publiczna dokumentacja API w przyszłości → Docusaurus (deploy na GitHub Pages)
- Notion: tylko planowanie i cele — nie dokumentacja techniczna

---

## ⚙️ Dobre praktyki — setup projektu

### .env.example (obowiązkowy)
```env
# Supabase (PostgreSQL — Session Pooler)
DATABASE_URL=postgresql://postgres.[project-id]:[password]@aws-1-eu-central-1.pooler.supabase.com:5432/postgres

# AI
OPENAI_API_KEY=sk-...

# Cache (przyszłość)
REDIS_URL=redis://localhost:6379

# App
NODE_ENV=development
PORT=3000
```

### Git hooks — przed commitem
Prosty hook który uruchamia Claude Code review zmian:
```bash
# .git/hooks/pre-commit
claude "Przejrzyj staged zmiany i sprawdź czy nie ma oczywistych błędów"
```

### GitHub Issues — podział zadań
- **Issues na GitHubie:** bugi, features techniczne, refactoring
- **Notion Kanban:** strategia, priorytety, cele biznesowe
- Nie duplikuj — każde zadanie ma jedno miejsce

### Cloudflare — przed startem (nie po)
1. Dodaj domenę do Cloudflare
2. Zmień NS na Cloudflare w panelu Cyberfolks
3. Włącz proxy (pomarańczowa chmurka)
4. SSL: Full (strict)

---
