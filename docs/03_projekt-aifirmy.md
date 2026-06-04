# 🤖 Kontekst: aifirmy.pl

## Status (czerwiec 2026)
- Domena: ✅ zarejestrowana (aifirmy.pl)
- Hosting: ✅ aktywny (Cyberfolks)
- Aplikacja: ❌ nie wdrożona — public_html pusty
- Ocena potencjału: ⭐⭐⭐⭐⭐ — PRIORYTET #1 z projektów biznesowych

## Koncepcja
Katalog branżowy + agregator treści AI. Wpisy: firmy AI (SaaS/narzędzia), kursy online (IT/AI/data), startupy PL/EU/global.

Każdy wpis: nazwa, opis AI-generated, kategorie/tagi, link, model cenowy, status free/premium.

## Model zarabiania
- Etap 1: darmowe wpisy + premium (wyróżnienie, dofollow, logo, top of category)
- Etap 2: reklamy AdSense/direct, lead generation
- Etap 3: newsletter sponsorowany, raporty branżowe PDF

## Stack techniczny
- Scraping/ETL: **Apache NiFi** + Python fallback
- AI opisy: OpenAI API
- Baza: PostgreSQL + Redis
- Frontend: Next.js / Astro / Nuxt + Tailwind (SSR + sitemap)
- CMS/Admin: Supabase / Directus
- Hosting: Cyberfolks + Cloudflare

## Pipeline NiFi (planowany)
ŹRÓDŁA → SCRAPING → NORMALIZACJA → AI → BAZA → STRONA → MONETYZACJA

Źródła: Product Hunt, GitHub Trending, Indie Hackers, RSS/API
NiFi: GetHTTP → ExtractText → UpdateRecord → deduplikacja → AI prompt → moderacja

## Plan 6 tygodni
- Tydzień 1: strategia i nisza, analiza konkurencji, struktura kategorii
- Tydzień 2: MVP backend — baza, model wpisu, admin
- Tydzień 3: NiFi flow, scraping 1–2 źródeł, AI descriptions
- Tydzień 4: frontend — lista, karta wpisu, SEO
- Tydzień 5: monetyzacja — pakiety premium, Stripe/PayU
- Tydzień 6: growth — Google, LinkedIn, cold outreach

## Ryzyko
Dużo katalogów AI istnieje (Futurepedia, Toolify) — nisza musi być precyzyjnie wybrana.
Opcje: narzędzia AI dla polskiego B2B, konkretny segment branżowy.

## Dlaczego ten projekt pasuje
NiFi i ETL to kompetencje zawodowe Pabla. Oracle Cloud VCN już skonfigurowane. 
Praca dzienna po uruchomieniu: ~1h/dzień.

## Kolejne kroki
1. Analiza 3–5 konkurentów i wybór niszy
2. Struktura bazy danych PostgreSQL
3. Minimalny NiFi flow — 1 źródło
4. Prosty frontend Astro (najlepszy dla SEO)

## Jak używać Claude w tym projekcie
- Architektura: "Zaprojektuj strukturę bazy dla katalogu AI"
- NiFi: "Pomóż mi zbudować flow scraping Product Hunt"
- Nisza: "Przeanalizuj konkurencję i zaproponuj niszę"
- Prompty AI: "Napisz prompt do generowania opisów firm AI"
