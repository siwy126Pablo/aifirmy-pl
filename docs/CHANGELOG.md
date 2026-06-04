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

## Szablon tygodnia

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
