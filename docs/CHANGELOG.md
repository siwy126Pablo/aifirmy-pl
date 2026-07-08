# 📅 Changelog — aifirmy.pl

> Tygodniowy dziennik postępów. Przydatny do postów LinkedIn, retrospektyw i motywacji.
> Format: co zrobiłem / co odkryłem / co zmieniam.

---

## [Nierozpoczęty] — czerwiec 2026

### Zrobione
- ✅ Zarejestrowana domena aifirmy.pl (Cyberfolks)
- ✅ Hosting aktywny
- ✅ Koncepcja produktu i architektura udokumentowane
- ✅ Repo do założenia na GitHubie

### Odkrycia
- Nisza do sprecyzowania — szeroki "katalog AI" to za dużo konkurencji
- Astro jako frontend — lepszy dla SEO niż Next.js przy tym typie produktu

### Następny krok
- Analiza 3–5 konkurentów
- Założenie repo na GitHubie
- Wybór konkretnej niszy

---

## [v0.1] — 2026-06-04 (tydzień 1)

### Zrobione
- ✅ Zainicjowana struktura repozytorium (`frontend/`, `backend/`, `nifi-flows/`, `db/migrations/`, `docs/`)
- ✅ Zainstalowany Tailwind CSS v4 z pluginem `@tailwindcss/vite` (Astro 6)
- ✅ `astro.config.mjs` — dodany plugin Tailwind i `site: 'https://aifirmy.pl'`
- ✅ `src/layouts/Layout.astro` — boilerplate HTML, meta SEO, Open Graph, Twitter Card, canonical URL, named slot `head`
- ✅ `src/components/CompanyCard.astro` — karta firmy z nazwą, opisem, kategorią, tagiem cenowym, linkami
- ✅ `src/data/companies.ts` — wspólne źródło danych (interface `Company`, 3 wpisy, helper `getCompanyBySlug`)
- ✅ `src/pages/index.astro` — strona główna: hero, grid 3 kart firm
- ✅ `src/pages/narzedzia/[slug].astro` — strona detalu firmy, `getStaticPaths`, schema.org `SoftwareApplication`
- ✅ `.env.example` z wymaganymi zmiennymi środowiskowymi

### Odkrycia / problemy
- Tailwind v4 nie wymaga `tailwind.config.js` — tylko `@import "tailwindcss"` w CSS i plugin Vite
- Schema.org w Astro wymaga `<Fragment slot="head">` z `set:html` zamiast zwykłego `<script>`
- Dane firm na razie hardcoded w `src/data/companies.ts` — gotowe do podmiany na fetch z PostgreSQL

### Następny tydzień
- Strona `/kategoria/[slug]` — lista firm per kategoria
- Podłączenie PostgreSQL i zamiana hardcoded danych na zapytania do bazy
- Decyzja ADR-005: Node.js vs Python dla backend API

---

## [v0.1] — 2026-06-04 (tydzień 1)

### Zrobione
- ✅ Zainicjowana struktura repozytorium (`frontend/`, `backend/`, `nifi-flows/`, `db/migrations/`, `docs/`)
- ✅ Zainstalowany Tailwind CSS v4 z pluginem `@tailwindcss/vite` (Astro 6)
- ✅ `astro.config.mjs` — dodany plugin Tailwind i `site: 'https://aifirmy.pl'`
- ✅ `src/layouts/Layout.astro` — boilerplate HTML, meta SEO, Open Graph, Twitter Card, canonical URL, named slot `head`
- ✅ `src/components/CompanyCard.astro` — karta firmy z nazwą, opisem, kategorią, tagiem cenowym, linkami
- ✅ `src/data/companies.ts` — wspólne źródło danych (interface `Company`, 3 wpisy, helper `getCompanyBySlug`)
- ✅ `src/pages/index.astro` — strona główna: hero, grid 3 kart firm
- ✅ `src/pages/narzedzia/[slug].astro` — strona detalu firmy, `getStaticPaths`, schema.org `SoftwareApplication`
- ✅ `.env.example` z wymaganymi zmiennymi środowiskowymi
- ✅ Claude Code w VS Code połączony z kontem Pro
- ✅ CLAUDE.md jako pamięć projektu dla Claude Code

### Odkrycia / problemy
- Tailwind v4 nie wymaga `tailwind.config.js` — tylko `@import "tailwindcss"` w CSS i plugin Vite
- Astro v6 + Tailwind v4: instalacja wymaga `--template minimal --no-git --no-install` (bug z Node.js v24)
- PowerShell na Windows wymaga `Set-ExecutionPolicy RemoteSigned` przed npm
- Schema.org w Astro wymaga `<Fragment slot="head">` z `set:html` zamiast zwykłego `<script>`
- Stary projekt AIFIRMY (Next.js + FastAPI + MSSQL) porzucony — zaczynamy od zera z właściwym stackiem
- Dane firm na razie hardcoded w `src/data/companies.ts` — gotowe do podmiany na fetch z PostgreSQL

### Następny tydzień
- Baza danych PostgreSQL na Supabase
- 6 tabel, seed danych
- Keep-alive GitHub Actions

---

## [v0.2] — 2026-06-04 (tydzień 2)

### Zrobione (region: eu-central-1, Frankfurt)
- ✅ 6 tabel: `tools`, `categories`, `tags`, `tool_tags`, `premium_listings`, `scrape_queue`
- ✅ Indeksy, trigger `updated_at`, full-text search GIN po polsku
- ✅ Seed: 9 kategorii, 10 tagów, 3 zatwierdzone wpisy (Make, n8n, Rossum)
- ✅ Keep-alive: GitHub Actions cron w repo `aifirmy-ping` (ping co 5 dni)
- ✅ Panel admina: Supabase Studio (Table Editor)

### Odkrycia / problemy
- Cyberfolks (cyber_IN_unlimited) ma tylko MariaDB — niekompatybilna z schematem (brak JSONB, text[], GIN)
- Supabase darmowy plan pauzuje projekty po tygodniu nieaktywności — rozwiązane cron pingiem
- UptimeRobot i Freshping niedostępne bez płatności / deprecated → GitHub Actions jako alternatywa

### Zmieniam podejście do
- Baza: MariaDB na Cyberfolks → PostgreSQL na Supabase (ADR-007)
- Panel admin: Directus/custom → Supabase Studio (ADR-006)

### Następny tydzień
- NiFi flow: SplitJson + drugi InvokeHTTP dla szczegółów itemów
- Integracja OpenAI API do generowania opisów PL
- Mapowanie pól na tabelę `scrape_queue`
- Cron o 2:00 w nocy

---

## [v0.3] — 2026-06-04 (tydzień 3)

### Zrobione
- ✅ Apache NiFi 2.9.0 zainstalowany lokalnie na Windows (Java 25)
- ✅ JDBC driver PostgreSQL wgrany do `C:\nifi\lib`
- ✅ DBCPConnectionPool połączony z Supabase (Session Pooler, port 5432)
- ✅ Pełny pipeline end-to-end: `GenerateFlowFile → InvokeHTTP (HN topstories) → SplitJson → InvokeHTTP (item details) → InvokeHTTP (OpenAI GPT-4o) → PutDatabaseRecord (scrape_queue)`
- ✅ Cron scheduler: codziennie o 2:00 w nocy
- ✅ Flow wyeksportowany do `/nifi-flows/` w repo

### Odkrycia / problemy
- Product Hunt blokuje requesty (403 Cloudflare) — zamieniono na Hacker News API
- Supabase Direct Connection wymaga IPv6 — użyć Session Pooler zamiast Direct
- DBCPConnectionPool wymaga user w formacie `postgres.[project-id]` dla Supabase poolera
- `EvaluateJsonPath` nie obsługuje tablic → tagi zostają w `ai_response` jako JSONB
- `scraped_at` (timestamptz) usunięty z INSERT — Supabase wypełnia automatycznie
- Cudzysłowy i `\n` psujące JSON do OpenAI → uproszczony prompt

### Zmieniam podejście do
- Źródło danych: Product Hunt → Hacker News API (ADR-008)
- NiFi: Oracle Cloud → lokalnie Windows na czas developmentu (ADR-008)

### Następny tydzień
- Frontend Astro: strona `/kategoria/[slug]`, podłączenie Supabase
- Deploy na Cyberfolks
- Konfiguracja Cloudflare

---

## [v0.4] — 2026-07-08 (tydzień 4)

### Zrobione
- ✅ Migracja `db/migrations/001_affiliate_links.sql` — tabela `affiliate_links` (program partnerski per narzędzie, `active` toggle, `disclosure_text`)
- ✅ Panel admina `admin/affiliate.php` — lista (JOIN z `tools`), dodawanie, edycja, toggle aktywności bez przeładowania strony (PATCH przez Supabase REST z JS)
- ✅ Link nawigacyjny w `admin/index.php` do panelu linków afiliacyjnych
- ✅ Frontend: `narzedzia/[slug].astro` używa `affiliate_url` zamiast `website_url` gdy istnieje aktywny link, z dyskretnym tekstem ujawnienia pod CTA

### Odkrycia / problemy
- `db/migrations/001_initial.sql` wspomniana w CLAUDE.md nigdy nie trafiła do repo — schemat `tools` powstał bezpośrednio w Supabase Studio, więc trigger `updated_at` nie miał udokumentowanej nazwy funkcji. Nowy trigger (`set_updated_at`) zdefiniowany z `CREATE OR REPLACE`, żeby nie kolidować z ewentualną funkcją o innej nazwie w bazie.
- Pierwszy realny przypadek testowy do dodania ręcznie: ClickUp przez PartnerStack (cookie 180 dni, Tier 2 Polska, $10/signup)

### Następny tydzień
- Dodać ręcznie pierwszy wpis ClickUp/PartnerStack przez panel i zweryfikować end-to-end na produkcji
- Strona `/kategoria/[slug]`, deploy na Cyberfolks, konfiguracja Cloudflare

---



```
## [v0.X] — [data]

### Zrobione
-

### Odkrycia / problemy
-

### Zmieniam podejście do
-

### Następny tydzień
-
```

---

*Aktualizuj co tydzień — 5 minut w niedzielę wieczorem.*
