# 🤖 Kontekst: aifirmy.pl

## Status (czerwiec 2026)
- Domena: ✅ zarejestrowana (aifirmy.pl)
- Hosting: ✅ aktywny (Cyberfolks — tylko frontend)
- Baza danych: ✅ PostgreSQL na Supabase free (eu-central-1)
- Frontend: ✅ Astro scaffold zbudowany lokalnie
- NiFi: 🔄 flow w trakcie (Tydzień 3)
- Aplikacja na serwerze: ❌ nie wdrożona — public_html pusty
- Ocena potencjału: ⭐⭐⭐⭐⭐ — PRIORYTET #1 z projektów biznesowych

## Koncepcja
Katalog branżowy + agregator treści AI. Wpisy: firmy AI (SaaS/narzędzia), kursy online (IT/AI/data), startupy PL/EU/global.

Każdy wpis: nazwa, opis AI-generated, kategorie/tagi, link, model cenowy, status free/premium.

## Model zarabiania
- Etap 1: darmowe wpisy + premium (wyróżnienie, dofollow, logo, top of category)
- Etap 2: reklamy AdSense/direct, lead generation
- Etap 3: newsletter sponsorowany, raporty branżowe PDF

## Stack techniczny
- Scraping/ETL: **Apache NiFi 2.9.0** (lokalnie na Windows)
- AI opisy: OpenAI API (GPT-4o)
- Baza: **PostgreSQL na Supabase free** (Cyberfolks ma tylko MariaDB)
- Frontend: **Astro** + Tailwind (SSR + sitemap)
- CMS/Admin: **Supabase Studio** (wbudowany panel)
- Hosting: Cyberfolks (frontend) + Cloudflare

## Pipeline NiFi (aktualny)
ŹRÓDŁA → SCRAPING → AI → MODERACJA → STRONA → MONETYZACJA

Źródło: **Hacker News API** (Product Hunt zablokowany przez Cloudflare)
NiFi: GenerateFlowFile → InvokeHTTP → SplitJson → InvokeHTTP (szczegóły) → OpenAI API → PutDatabaseRecord (scrape_queue) → moderacja w Supabase Studio

## Plan 6 tygodni
- Tydzień 1: ✅ strategia i nisza, analiza konkurencji, 9 kategorii, 100 narzędzi
- Tydzień 2: ✅ baza Supabase — 6 tabel, seed, keep-alive GitHub Actions
- Tydzień 3: 🔄 NiFi flow (w trakcie) — scraping HN, OpenAI opisy, scrape_queue
- Tydzień 4: frontend — lista, karta wpisu, SEO, deploy na Cyberfolks
- Tydzień 5: monetyzacja — pakiety premium, Stripe/PayU
- Tydzień 6: growth — Google Search Console, LinkedIn, cold outreach

## Ryzyko
Dużo katalogów AI istnieje (Futurepedia, Toolify) — nisza musi być precyzyjnie wybrana.
Opcje: narzędzia AI dla polskiego B2B, konkretny segment branżowy.

## Dlaczego ten projekt pasuje
NiFi i ETL to kompetencje zawodowe Pabla. Nisza wolna — nikt w Polsce nie robi katalogu AI B2B z tagami RODO i AI Act.
Praca dzienna po uruchomieniu: ~1h/dzień.

## Kolejne kroki (Tydzień 3 — dokończenie)
1. SplitJson + drugi InvokeHTTP po szczegóły itemów HN
2. InvokeHTTP → OpenAI API (generowanie opisu PL + kategoria + tagi)
3. PutDatabaseRecord → INSERT do `scrape_queue`
4. Cron scheduler na 2:00 w nocy

## Jak używać Claude w tym projekcie
- NiFi: "Pomóż mi zbudować SplitJson flow dla Hacker News API"
- Frontend: "Zaprojektuj stronę kategorii w Astro z listą narzędzi z Supabase"
- Nisza: "Zaproponuj 10 fraz SEO long-tail dla kategorii Finanse i AI Act"
- Prompty AI: "Zaktualizuj prompt do generowania opisów narzędzi AI dla B2B"
