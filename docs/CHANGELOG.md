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

## [v0.2] — 2026-06-04 (tydzień 2)

### Zrobione
- ✅ Baza danych PostgreSQL na Supabase free (region: eu-central-1, Frankfurt)
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

## [v0.3] — 2026-06-04 (tydzień 3, w trakcie)

### Zrobione
- ✅ Apache NiFi 2.9.0 zainstalowany lokalnie na Windows (Java 25)
- ✅ JDBC driver PostgreSQL wgrany do `C:\nifi\lib`
- ✅ DBCPConnectionPool połączony z Supabase (Session Pooler, port 5432)
- ✅ Podstawowy flow na kanwie: `GenerateFlowFile → InvokeHTTP → EvaluateJsonPath → PutDatabaseRecord`
- ✅ Hacker News API odpowiada poprawnie (zwraca tablicę ID topowych postów)

### Odkrycia / problemy
- Product Hunt blokuje requesty (403 Cloudflare) — nie można scrapować bezpośrednio
- Supabase Direct Connection wymaga IPv6 — użyć Session Pooler zamiast Direct
- DBCPConnectionPool wymaga user w formacie `postgres.[project-id]` dla Supabase poolera
- InvokeHTTP bez `GenerateFlowFile` nie odpala się sam nawet z timerem

### Zmieniam podejście do
- Źródło danych: Product Hunt → Hacker News API (ADR-008)
- NiFi: Oracle Cloud → lokalnie Windows na czas developmentu (ADR-008)

### Następny tydzień
- SplitJson → drugi InvokeHTTP po szczegóły każdego itemu HN
- InvokeHTTP → OpenAI API (generowanie opisu PL + kategoria + tagi)
- PutDatabaseRecord → INSERT do `scrape_queue`
- Cron scheduler na 2:00 w nocy

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
