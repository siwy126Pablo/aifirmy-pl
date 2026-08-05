# 📊 STATUS.md — aifirmy.pl
> Ostatnia aktualizacja: 2026-07-18

---

## 🚦 Status ogólny

| Element | Status |
|---|---|
| **Faza** | Projekt live — backlog i growth, pierwsza płatność afiliacyjna aktywna |
| **Domena** | ✅ aifirmy.pl (Cyberfolks) |
| **Hosting** | ✅ Aktywny — Cyberfolks + Cloudflare |
| **Baza danych** | ✅ Supabase PostgreSQL (eu-central-1) |
| **Pipeline NiFi** | ✅ 3 źródła: HN + BetaList + Product Hunt, cron 2:00/3:00/4:00 |
| **Frontend** | ✅ Wdrożony, GitHub Actions auto-deploy |
| **Cloudflare** | ✅ SSL Full, CDN, DNS |
| **Panel admina** | ✅ PHP + Supabase REST API + soft delete |
| **Monetyzacja** | ✅ Stripe Live mode, checkout + webhook, email po zakupie |
| **Affiliate** | ✅ ClickUp/PartnerStack aktywny, link + disclosure na produkcji |
| **Analytics** | ✅ Google Analytics 4 + Google Search Console |

---

## ✅ Tydzień 1 — Strategia i nisza

- Nisza: katalog narzędzi AI dla polskiego B2B, opisy po polsku, zgodność z RODO i AI Act, ceny w PLN
- 9 kategorii: Automatyzacja, Sprzedaż/CRM, Obsługa klienta, Marketing/content, Finanse, HR, Analityka/BI, Prawo/compliance, Zarządzanie projektami
- Lista 100 narzędzi (nazwa, kategoria, model cenowy, RODO, AI Act, URL)
- Analiza konkurencji (aiport.pl, smartnetstudio.pl, ainarzedziapolska.lovable.app) — brak tagu RODO/AI Act u nikogo
- Stack technologiczny ustalony (ADR-001 do ADR-008)
- Repo `aifirmy-pl` na GitHubie

---

## ✅ Tydzień 2 — Baza danych

- 6 tabel na Supabase free (eu-central-1, Frankfurt): `tools`, `categories`, `tags`, `tool_tags`, `premium_listings`, `scrape_queue`
- Indeksy GIN, trigger `updated_at`, full-text search po polsku
- Seed: 9 kategorii, 10 tagów, 3 zatwierdzone wpisy (Make, n8n, Rossum)
- Keep-alive: GitHub Actions cron w repo `aifirmy-ping` (ping co 5 dni)
- Trigger `promote_scrape_to_tools()` — automatyczne przenoszenie z `scrape_queue` do `tools` po zmianie stage na `published`

**Kluczowe decyzje:**
- Cyberfolks ma tylko MariaDB (brak JSONB, text[], GIN) → Supabase PostgreSQL (ADR-007)
- Panel admin: Supabase Studio → zastąpiony PHP panel (ADR-006)

---

## ✅ Tydzień 3 — Pipeline NiFi

**Gotowy flow end-to-end (3 źródła, po optymalizacji):**

```
[Źródło 1 — Hacker News, cron 2:00]
GenerateFlowFile
  → InvokeHTTP (HN topstories — 500 ID)
  → SplitJson (1 flowfile per ID)
  → InvokeHTTP (HN item details)
  → EvaluateJsonPath (pre-filter: hn_url, hn_type)
  → RouteOnAttribute (filtr URL — blokuje GitHub, newsy, blogi)
  → EvaluateJsonPath (hn_time, hn_type, hn_url, hn_title...)
  → RouteOnAttribute (filtr daty — ostatnie 24h)
  → RouteOnAttribute (filtr słów kluczowych — AI, tool, SaaS, LLM...)
  → ExecuteSQL (deduplikacja)
  → RouteOnAttribute (nowy rekord?)
  → [wspólna ścieżka OpenAI]

[Źródło 2 — BetaList, cron 3:00]
GenerateFlowFile (BetaList trigger)
  → InvokeHTTP (https://feeds.feedburner.com/BetaList)
  → SplitXml (depth=1)
  → RouteOnContent (tylko <entry>)
  → EvaluateXPath (bl_title, bl_url, bl_description, bl_published)
  → UpdateAttribute (normalizacja → hn_title, hn_url, hn_time, hn_type=story)
  → RouteOnAttribute (filtr daty)
  → [wspólna ścieżka OpenAI]

[Źródło 3 — Product Hunt, cron 4:00]
GenerateFlowFile (PH trigger)
  → InvokeHTTP (https://www.producthunt.com/feed)
  → SplitXml (depth=1)
  → RouteOnContent (tylko <entry>)
  → EvaluateXPath (tytuł, url, opis, data)
  → UpdateAttribute (normalizacja → hn_title, hn_url, hn_time, hn_type=story)
  → RouteOnAttribute (filtr — hn_type=story)
  → [wspólna ścieżka OpenAI]

[Wspólna ścieżka OpenAI]
  → InvokeHTTP (OpenAI gpt-4o-mini — opis PL, kategoria, segment, pricing_model, name)
  → EvaluateJsonPath (ai_description, ai_category, ai_name, ai_pricing_model)
  → PutDatabaseRecord (INSERT → scrape_queue, stage=ai_done)
```

**Optymalizacja kosztów:**
- Model: `gpt-4o` → `gpt-4o-mini` (koszt 20× niższy)
- Filtry: data + słowa kluczowe + deduplikacja SQL
- Klucz OpenAI w Parameter Context NiFi (sensitive, nie hardcoded)
- `nifi-flows/` wykluczone z git (zawiera sekrety)

**Kolumny scrape_queue (rozszerzone):**
- `name` — nazwa narzędzia z AI (nie raw HN title)
- `ai_pricing_model` — free/freemium/paid/open_source z AI
- `ai_category` — ograniczone do 9 kategorii z listy

**Quirki:**
- NiFi ReplaceText: `\n` → literalne `n`, cudzysłowy gubią backslash — prompt musi być jedną linią bez cudzysłowów
- `EvaluateJsonPath` nie obsługuje tablic → tagi w `ai_response` jako JSONB
- Product Hunt i BetaList — Atom XML z namespace, XPath wymaga `local-name()` lub bez namespace
- Autostart NiFi przez Windows Task Scheduler (nie Windows Service — NiFi 2.x nie obsługuje `nifi.cmd install`)

**⚠️ Znany problem jakości (otwarty, nierozwiązany):**
Krok OpenAI generujący opisy często halucynuje nazwy produktów i opisy z tytułów HN, które nie są realnymi produktami (eseje, benchmarki, artykuły akademickie), oraz wymyśla nieistniejące nazwy modeli. Dodanie BetaList i Product Hunt jako dodatkowych źródeł (Tydzień 6) nie rozwiązało problemu u źródła HN — filtr 7a (blokada GitHub/newsów/blogów) ograniczył część szumu, ale nie eliminuje w pełni. TODO zapisane w runbooku moderacji: potrzebne kolejne źródło o wyższym stosunku sygnału do szumu niż HN (np. dedykowane RSS launch-oriented). Show HN / Launch HN z identyfikatorem batcha YC pozostają najbardziej wiarygodnym sygnałem prawdziwego produktu.

---

## ✅ Tydzień 4 — Frontend

- Astro 6 + Tailwind CSS v4, build statyczny
- Strony: `/`, `/narzedzia/[slug]`, `/kategoria/[slug]`, `/premium`, `/kontakt`, `/dziekujemy`, `/polityka-prywatnosci`
- Dane z Supabase REST API (`@supabase/supabase-js`)
- Sitemap: `/sitemap-index.xml` (integracja `@astrojs/sitemap`)
- Schema.org SoftwareApplication na stronach detalu
- Deploy: GitHub Actions (auto-deploy po każdym git push)
- Cloudflare: SSL Full strict, CDN, nameservery

**Frontend — poprawki po T5.5:**
- Tytuły stron: separator zmieniony z `|` na `—`, usunięta duplikacja "aifirmy.pl | aifirmy.pl"
- Strona detalu `/narzedzia/[slug]` — dodane tagi (tool_tags JOIN tags), logo, kategoria, pricing badge
- Strona kategorii — odmiana liczby wpisów (1 wpis / 2-4 wpisy / 5+ wpisów)
- OG image: `og-default.png` (1200×630px, granat + indigo)
- Badge "Polecane" + indigo tło dla wpisów z aktywnym premium_listings
- Sortowanie premium wpisów na górze listy (index + kategoria)

**Quirki LiteSpeed na Cyberfolks:**
- SCP wgrywa pliki z uprawnieniami 700 → GitHub Actions naprawia przez `find ... chmod 755/644`
- URL panelu admina tylko z rozszerzeniem: `/admin/index.php`

---

## ✅ Tydzień 5 — Monetyzacja i panel

**Panel admina PHP:**
- URL: `https://aifirmy.pl/admin/index.php`
- Logowanie hasłem (sesja PHP, hasło w `private_html/config/db.php`)
- Kolejka wpisów z pełnym opisem AI — przyciski Zatwierdź/Odrzuć/Usuń
- Soft delete: PATCH status=rejected przez Supabase REST API (bez reload DOM)
- Lista narzędzi, formularz dodawania ręcznego
- Połączenie przez Supabase REST API (curl) — brak pdo_pgsql na Cyberfolks

**Stripe — Live mode:**
- `admin/checkout.php` — tworzy Stripe Checkout Session, redirect
- `frontend/public/stripe/webhook.php` — obsługuje eventy:
  - `checkout.session.completed` → INSERT do `premium_listings` + email
  - `customer.subscription.deleted` → UPDATE `ends_at = now()`
- Email po zakupie: PHPMailer przez SMTP Cyberfolks (`s103.cyber-folks.pl:587`)
  - Admin: powiadomienie na `kontakt@aifirmy.pl`
  - Klient: potwierdzenie zakupu
- Live Price IDs (4 pakiety: 49/99/199/299 zł/mc) — wszystkie 4 zweryfikowane, INSERT do `premium_listings` działa poprawnie
- Webhook URL: `/stripe/webhook.php` (poza `/admin/` — Cloudflare WAF blokuje POST do /admin/)

**Konta pocztowe:**
- `kontakt@aifirmy.pl` ✅
- `noreply@aifirmy.pl` ✅

**SEO:**
- Google Search Console — zweryfikowane, sitemap przesłany (16 stron)
- Google Analytics 4 — G-3SP1TRXF7M, skrypt w Layout.astro (ładowanie warunkowe po cookie consent)

---

## ✅ Tydzień 6 — Growth

- ✅ Google Analytics 4 — aktywne
- ✅ Google Search Console — sitemap przesłany
- ✅ BetaList jako drugie źródło NiFi
- ✅ Product Hunt RSS jako trzecie źródło NiFi
- ✅ Email po zakupie — PHPMailer SMTP
- ✅ Wyróżnienie premium na frontendzie (badge "Polecane", sortowanie)
- ✅ Obsługa anulowania subskrypcji (customer.subscription.deleted)
- ✅ Konta pocztowe: kontakt@ i noreply@aifirmy.pl
- ✅ Stripe Live mode — pierwsza płatność 49 zł
- ✅ Cookie consent banner + polityka prywatności (`/polityka-prywatnosci`)
- ✅ Formularz kontaktowy — naprawiony, wiadomości docierają na kontakt@aifirmy.pl

---

## ✅ Tydzień 7 — Linki afiliacyjne (lipiec 2026)

**Kontekst:** pierwsza okazja monetyzacji przez program partnerski — ClickUp przez PartnerStack.

**Zrobione:**
- ✅ Nowa tabela `affiliate_links` w Supabase (relacja do `tools`, trigger `updated_at` reużywa istniejącą funkcję `set_updated_at()`)
- ✅ Migracja `db/migrations/001_affiliate_links.sql`
- ✅ Panel `admin/affiliate.php` — CRUD + toggle aktywności bez przeładowania strony, wzorowany 1:1 na `admin/index.php`
- ✅ Frontend `[slug].astro` — CTA przełącza się na `affiliate_url` + wyświetla `disclosure_text`, gdy aktywny link istnieje
- ✅ Aplikacja do programu ClickUp Affiliate złożona i **zaakceptowana tego samego dnia** (Tier 2 Polska, cookie 180 dni, flat fee per country geo-based, nowe aktywacje włącznie z darmowymi planami, last touch attribution)
- ✅ Rzeczywisty link afiliacyjny aktywny: `https://try.web.clickup.com/x86tvl83r5tw`, wpisany do panelu dla "Brain² by ClickUp"
- ✅ Naprawiona luka: strona główna i strony kategorii (`CompanyCard.astro`) linkowały bezpośrednio na `website_url`, pomijając affiliate link — rozszerzono logikę z `[slug].astro` na komponent karty (commit `6515c3f`). Karta pokazuje ⓘ tooltip z disclosure, CTA używa `affiliateUrl ?? url`
- ✅ Zweryfikowane na żywo na produkcji (zrzut ekranu potwierdza kartę z linkiem "Strona →" + tooltip disclosure)
- ✅ ADR-009 dopisany do `DECISIONS.md`, `ARCHITECTURE.md`, `CLAUDE.md`

**Temat ADR-009 uznany za zamknięty.** Jedyne co zostało — poza zakresem Claude:
- ❌ Podpięcie wypłat w PartnerStack (Stripe/PayPal) — ręczne zadanie Pabla

---

## 📋 Backlog

### 🔴 Techniczne — priorytet
- [ ] **Jakość pipeline'u** — HN generuje zbyt dużo halucynacji (nieistniejące produkty/nazwy modeli z artykułów i esejów). Potrzebne dodatkowe źródło o wyższym SNR niż HN, lub zaostrzenie filtrów przed krokiem OpenAI. TODO zapisane w runbooku moderacji.

### 🟡 Growth (zawieszone ~miesiąc, do wznowienia gdy katalog urośnie)
- [ ] LinkedIn — 2 posty (drafty gotowe: RODO/AI Act + 5 narzędzi AI)
- [ ] Cold outreach do 20 firm z listy 100 narzędzi

### 🔵 Później
- [ ] Podpięcie wypłat Stripe/PayPal w PartnerStack (ręcznie, Pablo)
- [ ] AdSense gdy ruch > 1000 UV/mc
- [ ] Newsletter
- [ ] Konta pocztowe: premium@ i newsletter@ (gdy potrzebne)
- [ ] Raport branżowy PDF

---

## 🗂 Stack techniczny

| Warstwa | Technologia | Uwagi |
|---|---|---|
| Frontend | Astro 6 + Tailwind CSS v4 | Statyczny SSG, auto-deploy |
| ETL / Scraping | Apache NiFi 2.9.0 | Lokalnie Windows, 3 źródła, cron 2:00/3:00/4:00 |
| AI opisy | OpenAI gpt-4o-mini | ~$0.15/mc po optymalizacji |
| Baza danych | Supabase PostgreSQL free | eu-central-1, Frankfurt |
| Admin panel | PHP + Supabase REST API | /admin/index.php, /admin/affiliate.php |
| Hosting | Cyberfolks (LiteSpeed) | Frontend + PHP admin + webhook |
| CDN / ochrona | Cloudflare | SSL Full, CDN aktywny |
| CI/CD | GitHub Actions | Auto-deploy + keep-alive ping |
| Płatności | Stripe (live mode) | checkout.php + webhook.php |
| Affiliate | PartnerStack (ClickUp) | affiliate_links table + admin/affiliate.php |
| Email | PHPMailer + Cyberfolks SMTP | kontakt@aifirmy.pl, port 587 |
| Analytics | Google Analytics 4 | G-3SP1TRXF7M |

---

## 🔧 Środowisko developerskie

| Narzędzie | Status |
|---|---|
| Claude Code w VS Code | ✅ połączony z kontem Pro |
| Repo aifirmy-pl | ✅ github.com/siwy126Pablo/aifirmy-pl |
| CLAUDE.md | ✅ aktualny, synchronizowany z claude.ai |
| Lokalizacja projektu | ✅ `C:\Dev\aifirmy-pl` (przeniesiony z C:\Users\pawel\Praca z powodu ESET) |

**Workflow:**
- Claude.ai = architekt i doradca (strategia, decyzje, planowanie)
- Claude Code w VS Code = wykonawca (kod, pliki, commity)
- CLAUDE.md = pomost między nimi

---

## ⚠️ Quirki techniczne — do zapamiętania

| Problem | Rozwiązanie |
|---|---|
| NiFi ReplaceText: `\n` → literalne `n` | System message jako jedna linia bez przełamań |
| NiFi ReplaceText: `\"` gubi backslash | Usuń cudzysłowy z system message |
| InvokeHTTP connection pool cache | Stop flow 30s → Start po zmianie konfiguracji |
| ESET blokuje node_modules | Projekt w `C:\Dev\` zamiast `C:\Users\` |
| Cloudflare WAF blokuje POST /admin/ | Webhook w `/stripe/webhook.php` poza /admin/ |
| Supabase Direct Connection wymaga IPv6 | Używać Session Pooler (port 5432) |
| Cyberfolks brak pdo_pgsql | Supabase REST API przez curl w PHP |
| LiteSpeed chmod po SCP | `find ... chmod 755/644` w GitHub Actions |
| NiFi Atom feed namespace | XPath z `local-name()` np. `//*[local-name()='title']` |
| Pipeline OpenAI halucynuje produkty z nie-produktowych HN title'i | Nierozwiązane — potrzebne dodatkowe źródło/filtr (patrz Backlog 🔴) |

---

## 🔗 Linki

| Zasób | URL |
|---|---|
| Strona | https://aifirmy.pl |
| Panel admina | https://aifirmy.pl/admin/index.php |
| Panel afiliacyjny | https://aifirmy.pl/admin/affiliate.php |
| Repo | https://github.com/siwy126Pablo/aifirmy-pl |
| Supabase | https://supabase.com/dashboard/project/szassqzvivdgvpkciyif |
| Notion — projekt | https://www.notion.so/aifirmy-pl-Katalog-AI-i-SaaS-373b4cccb4af81bb9ec5ef0a5ca32318 |
| Notion — status sesji | https://app.notion.com/p/374b4cccb4af8103afbbc353f9fd300e |
| Notion — runbook | https://app.notion.com/p/377b4cccb4af818ab4a8efb3248426c3 |
| Keep-alive repo | https://github.com/siwy126Pablo/aifirmy-ping |
| Stripe Dashboard | https://dashboard.stripe.com |
| PartnerStack | https://partnerstack.com |
| Google Analytics | https://analytics.google.com |
| Google Search Console | https://search.google.com/search-console |
