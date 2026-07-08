# 📋 Architecture Decision Records — aifirmy.pl

> Każda ważna decyzja techniczna zapisana tutaj.
> Format: data, kontekst, opcje, decyzja, uzasadnienie.

---

## ADR-001 — Wybór frontendu: Astro
**Data:** czerwiec 2026
**Status:** ✅ Przyjęta

**Kontekst:**
Katalog AI wymaga doskonałego SEO i szybkiego ładowania stron. Większość treści jest statyczna (wpisy firm), ale potrzebne są dynamiczne filtry i wyszukiwanie.

**Opcje:**
- Next.js — popularny, duży ekosystem, ale cięższy
- Astro — zoptymalizowany pod SEO/SSG, lekki, Markdown-first
- Nuxt — Vue-based, mniejszy ekosystem

**Decyzja:** Astro + Tailwind

**Uzasadnienie:**
Astro generuje minimalny JavaScript po stronie klienta, co przekłada się na lepsze Core Web Vitals i SEO. Dla katalogu gdzie treść > interaktywność — idealne.

---

## ADR-002 — Dokumentacja: Markdown w GitHubie
**Data:** czerwiec 2026
**Status:** ✅ Przyjęta

**Kontekst:**
Projekt solo. Poprzednio używałem Confluence — zbyt ciężkie dla jednej osoby.

**Opcje:**
- Confluence — znane, ale płatne i przeładowane dla solo
- Notion — dobre do planowania, słabe dla dokumentacji technicznej
- Markdown w repo (docs-as-code) — dokumentacja razem z kodem
- Docusaurus — publiczna strona dokumentacji

**Decyzja:** Markdown w `/docs` w GitHubie. Docusaurus rozważyć gdy pojawi się publiczne API.

**Uzasadnienie:**
Jeden commit = zmiana kodu + dokumentacja. Zero przełączania kontekstu. GitHub renderuje Markdown natywnie. Dla solo developera najlżejsze podejście.

---

## ADR-003 — ETL/Scraping: Apache NiFi
**Data:** czerwiec 2026
**Status:** ✅ Przyjęta

**Kontekst:**
Potrzebny pipeline do scrapingu źródeł (Product Hunt, GitHub, RSS) i generowania opisów przez AI.

**Opcje:**
- Apache NiFi — self-hosted, wizualny, kompetencja zawodowa
- Python + cron — prosto, ale bez monitoringu
- Make.com / n8n — no-code, ale ograniczenia przy złożonych przepływach

**Decyzja:** Apache NiFi (Oracle Cloud VCN: vcn-n8n, eu-frankfurt-1)

**Uzasadnienie:**
NiFi to codzienna praca zawodowa — brak krzywej uczenia. Istniejąca infrastruktura Oracle Cloud. Dobry monitoring i wizualizacja przepływów.

---

## ADR-004 — Hosting: Cyberfolks + Cloudflare
**Data:** czerwiec 2026
**Status:** ✅ Przyjęta (Cloudflare do skonfigurowania)

**Kontekst:**
Domena aifirmy.pl już na Cyberfolks. Potrzeba CDN i ochrony.

**Decyzja:** Pozostać na Cyberfolks + dodać Cloudflare przed uruchomieniem (nie po).

**Uzasadnienie:**
Zmiana hostingu na Hetzner/OVH możliwa gdy ruch uzasadni koszt. Cloudflare daje CDN, DDoS protection i SSL w 15 min — warto przed startem.

---

## ADR-005 — Backend: do podjęcia
**Data:** —
**Status:** ⏳ Otwarta

**Opcje:**
- Node.js (Express / Fastify)
- Python (FastAPI)

**Kryteria decyzji:**
- Znajomość języka
- Ekosystem bibliotek AI/scraping
- Wydajność przy katalogu

---

## ADR-006 — Panel admin: Supabase Studio
**Data:** czerwiec 2026
**Status:** ✅ Przyjęta

**Kontekst:**
Potrzebny panel do moderacji wpisów (approve/reject) i zarządzania danymi katalogu.

**Opcje:**
- Supabase Studio — wbudowany, zero konfiguracji
- Directus — headless CMS, więcej kontroli
- Custom admin — pełna kontrola, więcej pracy

**Decyzja:** Supabase Studio

**Uzasadnienie:**
Baza i tak trafiła na Supabase (ADR-007). Studio jest dostępne od razu, bez dodatkowego deploymentu. Dla solo developera i ~20 wpisów dziennie do moderacji — wystarczy w pełni.

---

## ADR-007 — Baza danych: Supabase free (PostgreSQL)
**Data:** czerwiec 2026
**Status:** ✅ Przyjęta

**Kontekst:**
Pakiet Cyberfolks (cyber_IN_unlimited) oferuje tylko MariaDB. Schemat bazy wymaga `JSONB`, `text[]`, `GIN` index i full-text search po polsku — funkcji niedostępnych w MariaDB.

**Opcje:**
- MariaDB na Cyberfolks — dostępna, ale niekompatybilna ze schematem
- Supabase free — PostgreSQL, 500 MB, 5 GB transfer, panel Studio
- Railway / Neon — alternatywy, mniej narzędzi

**Decyzja:** Supabase free (region: eu-central-1, Frankfurt)

**Uzasadnienie:**
Pełne PostgreSQL z wszystkimi potrzebnymi funkcjami. Darmowy plan wystarczy na start (500 MB >> potrzeby katalogu 100–1000 wpisów). Studio zastępuje panel admina. Jedyne ryzyko: pauzowanie po tygodniu nieaktywności — rozwiązane GitHub Actions cron (repo: `aifirmy-ping`).

---

## ADR-008 — NiFi: lokalnie na Windows zamiast Oracle Cloud
**Data:** czerwiec 2026
**Status:** ✅ Przyjęta

**Kontekst:**
Pierwotny plan zakładał NiFi na Oracle Cloud (VCN: vcn-n8n, eu-frankfurt-1). W praktyce łatwiej wystartować lokalnie.

**Opcje:**
- Oracle Cloud VCN — docelowe, ale wymaga konfiguracji sieci i SSL
- Lokalnie Windows — szybki start, NiFi 2.9.0 + Java 25

**Decyzja:** NiFi 2.9.0 lokalnie na Windows (tymczasowo)

**Uzasadnienie:**
Szybszy start bez konfiguracji infrastruktury. Migracja na Oracle Cloud możliwa gdy flow będzie stabilny — eksport JSON z NiFi pozwala przenieść flow bez przepisywania.

**Szczegóły konfiguracji:**
- NiFi: `C:\nifi`, port 8443
- JDBC driver: `C:\nifi\lib\postgresql-42.x.x.jar`
- Połączenie Supabase: Session Pooler, `aws-1-eu-central-1.pooler.supabase.com:5432`
- User: `postgres.szassqzvivdgvpkciyif`
- Źródło danych: Hacker News API (Product Hunt zablokowany przez Cloudflare — 403)

---

## ADR-009 — Linki afiliacyjne: dedykowana tabela + panel admina
**Data:** lipiec 2026
**Status:** ✅ Przyjęta

**Kontekst:**
Pojawiła się pierwsza okazja monetyzacji przez program afiliacyjny (ClickUp przez PartnerStack, Tier 2 Polska, $10/signup firmowy, cookie 180 dni). Potrzebny sposób zarządzania takimi linkami bez hardcodowania w kodzie przy każdym nowym programie.

**Opcje:**
- Hardcode linku w komponencie/stronie narzędzia — szybkie, ale nieskalowalne przy kolejnych programach
- Kolumna `affiliate_url` bezpośrednio w tabeli `tools` — prostsze, ale nie pozwala na notatki o warunkach ani łatwą dezaktywację
- Osobna tabela `affiliate_links` + panel admina — więcej pracy na start, ale skalowalne

**Decyzja:** Osobna tabela `affiliate_links` (relacja do `tools`) + strona `admin/affiliate.php`

**Uzasadnienie:**
Programy afiliacyjne będą się pojawiać częściej. Osobna tabela pozwala trzymać notatki o warunkach (cookie duration, stawki, wymogi disclosure) przy każdym linku, włączać/wyłączać bez usuwania danych, i w przyszłości obsłużyć więcej niż jeden program na to samo narzędzie bez zmiany schematu.

**Szczegóły implementacji:**
- Migracja: `db/migrations/001_affiliate_links.sql`
- Trigger `updated_at` reużywa istniejącą funkcję `set_updated_at()` (potwierdzoną jako identyczną z triggerem `trg_tools_updated` na tabeli `tools`)
- Panel: `admin/affiliate.php`, wzorowany 1:1 na `admin/index.php` (sesja, helpery `sb_get/sb_post/sb_patch`)
- Frontend: `[slug].astro` używa `affiliate_url` + wyświetla `disclosure_text`, gdy istnieje aktywny rekord; bez zmian gdy brak
- Pierwszy wpis testowy: ClickUp / PartnerStack

---

*Aktualizuj przy każdej ważnej decyzji technicznej.*
