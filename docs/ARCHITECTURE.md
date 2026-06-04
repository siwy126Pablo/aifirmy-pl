# 🏗️ aifirmy.pl — Architecture & Decisions

## Cel projektu
Katalog branżowy + agregator treści AI dla polskiego rynku.
Nisza: narzędzia AI, SaaS, kursy, startupy (PL/EU/global).

## Stack techniczny

| Warstwa | Technologia | Uzasadnienie |
|---|---|---|
| **Frontend** | Astro + Tailwind | Najlepszy SSR/SSG dla SEO, lekki, szybki |
| **Backend / API** | Node.js lub Python (FastAPI) | Do wyboru w trakcie developmentu |
| **Baza danych** | PostgreSQL | Relacyjna, sprawdzona, dobra dla katalogów |
| **Cache** | Redis | Szybkie serwowanie listy wpisów |
| **ETL / Scraping** | Apache NiFi | Kompetencja zawodowa Pabla, self-hosted |
| **AI opisy** | OpenAI API (GPT-4o) | Generowanie opisów, kategorii, tagów |
| **CMS / Admin** | Directus lub Supabase | Moderacja wpisów (approve/reject) |
| **Hosting** | Cyberfolks (aktualny) + Cloudflare | Domena aktywna, CDN + ochrona |
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

## Model danych — tabela companies
```sql
CREATE TABLE companies (
  id          SERIAL PRIMARY KEY,
  name        VARCHAR(255) NOT NULL,
  slug        VARCHAR(255) UNIQUE NOT NULL,
  description TEXT,                        -- AI-generated
  url         VARCHAR(500),
  category_id INT REFERENCES categories(id),
  tags        TEXT[],
  pricing     VARCHAR(50),                 -- free/freemium/paid/enterprise
  tier        VARCHAR(20) DEFAULT 'free',  -- free/premium
  status      VARCHAR(20) DEFAULT 'pending', -- pending/approved/rejected
  added_at    TIMESTAMP DEFAULT NOW(),
  updated_at  TIMESTAMP DEFAULT NOW()
);
```

## Pipeline NiFi
```
ŹRÓDŁA (Product Hunt, GitHub, RSS)
  → GetHTTP
  → ExtractText
  → UpdateRecord (normalizacja)
  → CheckDuplicate (hash URL + nazwa)
  → OpenAI API (opis + tagi)
  → PostgreSQL (INSERT status=pending)
  → Panel moderacji (approve/reject)
  → Frontend (status=approved)
```
Częstotliwość: cron 1× dziennie

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

## Decyzje do podjęcia
- [ ] Node.js vs Python dla backend API
- [ ] Directus vs Supabase dla panelu admin
- [ ] Cyberfolks wystarczy czy potrzebny VPS (Hetzner)?
- [ ] Wybór konkretnej niszy (szeroka AI vs wąski segment)

## Status (czerwiec 2026)
- Domena: ✅ aifirmy.pl (Cyberfolks)
- Repo: ⬜ do założenia na GitHub
- Dev: ⬜ nie rozpoczęty

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
# Baza danych
DATABASE_URL=postgresql://user:password@localhost:5432/aifirmy

# AI
OPENAI_API_KEY=sk-...

# Cache
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
