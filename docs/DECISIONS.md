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

## ADR-006 — Panel admin: do podjęcia
**Data:** —
**Status:** ⏳ Otwarta

**Opcje:**
- Supabase — BaaS, szybki start, wbudowany auth
- Directus — headless CMS, bardziej kontrolowany
- Custom admin — pełna kontrola, więcej pracy

---

*Aktualizuj przy każdej ważnej decyzji technicznej.*
