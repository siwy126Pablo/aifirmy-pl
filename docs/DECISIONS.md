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

## ADR-005 — Backend: Node.js vs Python (FastAPI)
**Data:** —
**Status:** ❌ Zamknięta — superseded

**Opcje:**
- Node.js (Express / Fastify)
- Python (FastAPI)

**Kryteria decyzji:**
- Znajomość języka
- Ekosystem bibliotek AI/scraping
- Wydajność przy katalogu

**Notatka (czerwiec 2026):**
ADR-005 stała się bezprzedmiotowa. Dedykowany backend Node.js/Python nigdy nie powstał — logika biznesowa żyje w PHP (admin panel, Stripe checkout, webhook, email) + bezpośrednich wywołaniach Supabase REST API z frontendu Astro. Formalnie zamknięta czerwiec 2026.

---

## ADR-006 — Panel admin: Supabase Studio
**Data:** czerwiec 2026
**Status:** ✅ Przyjęta (zaktualizowana)

**Kontekst:**
Potrzebny panel do moderacji wpisów (approve/reject) i zarządzania danymi katalogu.

**Opcje:**
- Supabase Studio — wbudowany, zero konfiguracji
- Directus — headless CMS, więcej kontroli
- Custom admin — pełna kontrola, więcej pracy

**Decyzja:** Supabase Studio

**Uzasadnienie:**
Baza i tak trafiła na Supabase (ADR-007). Studio jest dostępne od razu, bez dodatkowego deploymentu. Dla solo developera i ~20 wpisów dziennie do moderacji — wystarczy w pełni.

**Aktualizacja (czerwiec 2026):**
W praktyce Supabase Studio zostało zastąpione przez custom PHP panel admina (admin/index.php). Panel używa Supabase REST API przez curl — Cyberfolks nie ma pdo_pgsql więc bezpośrednie połączenie JDBC nie wchodzi w grę. Studio pozostaje dostępne do zadań SQL (migracje, diagnostyka) ale nie jest głównym narzędziem moderacji.

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

## ADR-010 — Działalność nierejestrowana: limit przychodu i próg alarmowy
**Data:** wrzesień 2026 (20.09.2026, poprawka 21.09.2026)
**Status:** ⏳ do pisemnego potwierdzenia z księgowym
**Źródło potwierdzenia:** konsultacja z księgowym, 21.09.2026 — zachować pisemny ślad

**Kontekst:**
Infrastruktura monetyzacji jest live (Stripe + affiliate), przychód = 0 na 2026-09-20. Właściciel projektu nie ma zarejestrowanej działalności gospodarczej.

**Poprawka 21.09.2026 — warunek 60 miesięcy prawdopodobnie NIE jest spełniony:**
Działalność nierejestrowana wymaga (poza limitem przychodu), żeby w ostatnich 60 miesiącach
(5 latach) właściciel nie prowadził działalności gospodarczej. Właściciel miał wcześniej
zarejestrowaną JDG: **zawieszoną 31.01.2022, zamkniętą 11.12.2023.** Zawieszenie działalności
liczy się jak jej niewykonywanie (nie jak jej zamknięcie) — jeśli to prawidłowa interpretacja,
okres 60 miesięcy liczy się od 31.01.2022, czyli warunek zostałby spełniony **najwcześniej
1.02.2027**. Do tego czasu bieżąca działalność najprawdopodobniej **nie kwalifikuje się** jako
działalność nierejestrowana, niezależnie od limitu przychodu poniżej.

**Konsekwencja robocza (do czasu pisemnego potwierdzenia z księgowym):**
- Brak płatnej sprzedaży — wdrożone jako kill-switch w `admin/checkout.php` +
  `frontend/src/pages/premium.astro` (`SALES_ENABLED = false`, patrz commit "feat: G0").
- Wypłaty z programów afiliacyjnych (PartnerStack) na osobę fizyczną wstrzymane — nie
  podpinać metody wypłaty (Stripe/PayPal), dopóki nie zapadnie decyzja o rejestracji JDG
  albo księgowy nie potwierdzi pisemnie, że warunek 60 miesięcy jest jednak spełniony.
- **Tripwire:** pierwsza realna (nie testowa) prowizja affiliate naliczona na koncie
  PartnerStack ma być natychmiast odnotowana i skonfrontowana z tym ADR przed jakąkolwiek
  próbą wypłaty lub zaksięgowaniem jej jako przychodu należnego — to sygnał do działania,
  nie tylko kwota progu kwartalnego.
- Zaproszenie do "programu pilotażowego" (baner na `/premium` zastępujący płatną sprzedaż)
  musi pozostać **bezpłatne i bez świadczeń wzajemnych** — żadnej wymiany usługa-za-usługę
  czy usługa-za-dane, które mogłyby zostać uznane za przychód należny lub za rozpoczęcie
  działalności zarobkowej przed wyjaśnieniem warunku 60 miesięcy.

**Fakty (do potwierdzenia z księgowym):**
- Limit działalności nierejestrowanej jest kwartalny, liczony jako 225% płacy minimalnej:
  - 2026: 225% × 4 806 zł = **10 813,50 zł / kwartał**
  - 2027: 225% × 4 950 zł = **11 137,50 zł / kwartał**
- Do limitu liczy się przychód należny z całej działalności (Stripe + affiliate łącznie)
- Po przekroczeniu limitu jest 7 dni na rejestrację w CEIDG
- Osobny, wcześniejszy warunek (patrz poprawka wyżej): brak prowadzonej działalności
  gospodarczej w ostatnich 60 miesiącach

**Decyzja robocza:** próg alarmowy ~60% limitu (≈ 6 488 zł przychodu należnego w kwartale,
2026) — **nieaktualna dopóki warunek 60 miesięcy nie zostanie wyjaśniony**, bo do 1.02.2027
limit przychodu może być bez znaczenia (działalność nierejestrowana może być niedostępna
z innego powodu).

**Uwaga:** wpis zawiera wyłącznie powyższe fakty — nie stanowi porady prawnej ani podatkowej. Wszystkie liczby i zasady wymagają potwierdzenia z księgowym.

---

*Aktualizuj przy każdej ważnej decyzji technicznej.*
