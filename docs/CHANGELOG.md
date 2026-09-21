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

## [v0.4] — 2026-06-09 (tydzień 5.5 — poprawki przed T6)

### Zrobione
- ✅ Slugify naprawiony — zachowuje wszystkie litery ASCII, usuwa tylko znaki specjalne i prefiks "Show HN:"
- ✅ Prompt OpenAI zwraca pole `name` (krótka nazwa produktu, max 30–50 znaków, bez prefiksów)
- ✅ Usunięte śmieciowe wpisy z HN (Inbox-beam, Lathe, KVarN, Build a Basic AI Agent, Verbatim RAG Model)
- ✅ Trigger `promote_scrape_to_tools()` mapuje `category` → `category_id` (lookup po nazwie)
- ✅ `pricing_model` klasyfikowany automatycznie przez OpenAI (free/freemium/paid/open_source)
- ✅ Soft delete w panelu admina — przycisk "Usuń" w `scrape_queue` i `tools`, PATCH status=rejected, bez reload
- ✅ Filtr HN w NiFi (RouteOnAttribute) — blokuje GitHub, blogi, newsy (reuters, cnbc, bbc, medium, substack)
- ✅ Odmiana "2 wpisów" na stronie kategorii (helper pluralize)
- ✅ OG image `og-default.png` (1200×630px, granat + indigo)
- ✅ Runbook moderacji zaktualizowany (nowa ścieżka projektu, kolumny `name`/`ai_pricing_model`, soft delete w adminie)

### Odkrycia / problemy
- Connection pool w NiFi InvokeHTTP cache'uje stare połączenie po zmianie configu — trzeba Stop → 30s → Start
- Migracja pełnego źródła HN → BetaList RSS (zadanie 7b) odłożona na później — zrealizowana docelowo jako dodatkowe równoległe źródło, nie zamiennik (patrz v0.6/v0.7)

### Następny tydzień
- Start T6: Google Analytics, Search Console, Stripe checkout

---

## [v0.7] — 2026-07-08 (tydzień 7)

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

## [v0.8] — 2026-07-15 (dokończenie linków afiliacyjnych)

### Zrobione
- ✅ Aplikacja ClickUp/PartnerStack **zaakceptowana tego samego dnia** — komisja zaktualizowana: flat fee per country (geo-based), nowe aktywacje włącznie z darmowymi planami, cookie 180 dni, last touch attribution
- ✅ Rzeczywisty link afiliacyjny aktywny: `https://try.web.clickup.com/x86tvl83r5tw`, wpisany do `admin/affiliate.php` dla "Brain² by ClickUp"
- ✅ Naprawiona luka: strona główna i strony kategorii (`CompanyCard.astro`) linkowały bezpośrednio na `website_url`, pomijając affiliate link — rozszerzono logikę z `[slug].astro` na komponent karty (commit `6515c3f`). Karta pokazuje ⓘ tooltip z disclosure, CTA używa `affiliateUrl ?? url`
- ✅ Zweryfikowane przez `npm run build` — strona główna i kategoria "Zarządzanie projektami" poprawnie linkują na link afiliacyjny
- ✅ Zweryfikowane na żywo na produkcji — zrzut ekranu potwierdza kartę "Brain² by ClickUp" z linkiem "Strona →" i ikonką ⓘ

### Zmieniam podejście do
- Temat affiliate links (ADR-009) uznany za **zamknięty**. Jedyne co zostało poza zakresem Claude: podpięcie wypłat Stripe/PayPal w PartnerStack — ręczne zadanie Pabla

### Następny krok
- Dokumentacja: ADR-009 dopisane do `DECISIONS.md`, `ARCHITECTURE.md`, Tydzień 7 w `CLAUDE.md`

---

## [v0.9] — 2026-07-17 (sesja moderacyjna — problem jakości pipeline'u)

### Zrobione
- ✅ Weryfikacja kolejnej partii wpisów z `scrape_queue` — zastosowane spójne kryteria oceny: realny, identyfikowalny produkt vs. artykuł/news/benchmark; realna przydatność B2B dla polskich firm; poprawne przypisanie kategorii

### Odkrycia / problemy
- **Potwierdzony powracający wzorzec halucynacji:** OpenAI generuje wiarygodnie brzmiące opisy i nazwy produktów dla postów HN, które w rzeczywistości nie są produktami (eseje, benchmarki, prace akademickie) — w tym nieistniejące nazwy modeli (np. "GPT-5.5-Cyber", "GPT-5.6 Sol", "GPT-5.6 Sol Ultra" pojawiające się wielokrotnie)
- Show HN / Launch HN z identyfikatorem batcha YC pozostają najbardziej wiarygodnym sygnałem prawdziwego produktu
- Problem nie został rozwiązany przez dodanie BetaList/Product Hunt jako równoległych źródeł (Tydzień 6) — te źródła zmniejszają udział złych wpisów z HN, ale nie eliminują halucynacji przy samym HN

### Zmieniam podejście do
- TODO dopisane do runbooka moderacji: potrzebne dodatkowe źródło danych o wyższym stosunku sygnału do szumu niż HN, lub zaostrzenie filtrów przed krokiem OpenAI

### Następny krok
- Ocena dodatkowych źródeł o wysokim SNR (kandydaci do rozważenia w kolejnej sesji planistycznej)
- Odświeżenie `STATUS.md` i `CHANGELOG.md` o Tydzień 7 i ustalenia z tej sesji (ta aktualizacja)

---

## [v0.10] — 2026-09-08 (redesign karty katalogowej i strony detalu)

### Zrobione
- ✅ `693bf92` — redesign `CompanyCard.astro`: kolorowy pill AI Act (`aiActRiskColors`, pomijany gdy null), pill kategorii, warunkowy badge "Hosting UE", neutralny badge `pricing_model` (w tym `open_source`), opis `line-clamp-3`, nowa hierarchia CTA (dominujący przycisk → `/narzedzia/[slug]/`, drugorzędny link zewnętrzny z tooltipem ⓘ disclosure)
- ✅ `f53c90c` — poprawka wyrównania: `line-clamp-4`, kontener "Najlepsze dla" z zarezerwowaną wysokością (`min-h-4`) niezależnie od tego czy `best_for_pl` jest puste — karty w tym samym rzędzie grid przestały się różnić wysokością
- ✅ `1b7e7f5` — `line-clamp-4` → `line-clamp-6` (decyzja po weryfikacji na żywo — więcej widocznego tekstu)
- ✅ `b3e4c41` — redesign hero + nowy sidebar "W skrócie" na `[slug].astro`: import współdzielonego `aiActRiskColors`/`pricingLabels` z `category-colors.ts` (wcześniej: dwa niezależne, sprzeczne mappingi na tej stronie + trzecia kopia w `CompanyCard`), większy CTA, pill kategorii z trailing slash
- ✅ `f9f5e53` — cofnięcie disclosure afiliacyjnego na stronie detalu z tooltipa (hover) z powrotem na zawsze widoczny tekst pod CTA — tooltip niewidoczny na dotyku, strona detalu ma miejsce na widoczny tekst (karta katalogowa zostaje przy tooltipie, tam to uzasadnione ciasnotą)

### Odkrycia / problemy
- **Audyt spójności ujawnił, że `[slug].astro` nigdy nie było w zakresie redesignu karty** — miało dwa własne, sprzeczne mappingi kolorów AI Act (żaden nie używał PL etykiet, jeden w ogóle bez kolorowania wg ryzyka) i osobny, kolorowy badge `pricing_model` wizualnie konkurujący z sygnałem ryzyka — dokładnie to, czego świadomie unikaliśmy przy redesignie karty
- Tooltip disclosure (hover/`title`) nie działa na urządzeniach dotykowych — realne ryzyko przy stronie z większym ruchem niż karta; karta zostaje przy tooltipie (uzasadnione ciasnotą), strona detalu wraca do widocznego tekstu
- Refaktor przy okazji: `aiActRiskColors` zyskał `shortLabel` (sam poziom, bez prefiksu "AI Act:") do użytku w sidebarze; `pricingLabels`/`PricingModel` wydzielone z lokalnej kopii w `CompanyCard.astro` do `category-colors.ts` — jedno źródło prawdy dla obu plików

### Zmieniam podejście do
- Disclosure afiliacyjny: różne wzorce świadomie w dwóch miejscach — tooltip ⓘ na karcie (ciasnota), widoczny tekst na stronie detalu (jest miejsce, większy ruch, dotyk)

### Następny krok (otwarte na start kolejnej sesji)
- **Do zweryfikowania w kodzie (niepotwierdzone, tylko z wizualnego zrzutu produkcji):**
  1. "Kategoria" w sidebarze "W skrócie" wygląda kolorowo na produkcji — spec zakładał zwykły neutralny tekst (kolor już jest w pillu w hero, sidebar miał być neutralnym podsumowaniem)
  2. Layout CTA + "Strona producenta ↗" przy braku aktywnego affiliate linku (np. Decawork) — na zrzucie wygląda jakby siedziały w jednej linii zamiast być spięte pionowo; może to tylko kompresja zrzutu, wymaga potwierdzenia w kodzie/DOM
- **Prompt B (zaplanowany, nie zaczęty):** pasek "Zgodność i dane" (RODO/DPA jako tile'e), usunięcie starego dolnego paska trust-badges z surowym `AI Act: minimal` (obecnie duplikuje nowy pill w sidebarze, kontrast teraz bardziej rażący niż przed redesignem hero), powiększenie "Podobne narzędzia" (obecnie małe/ciasne, reużycie stylu `CompanyCard`)

## [v0.11] — 2026-09-09 (dokończenie strony detalu + krytyczny bug deployu)

### Zrobione
- ✅ `7825cba` — pasek "Zgodność i dane" (RODO/DPA/Interfejs PL jako neutralne tile'e) na `[slug].astro`, usunięcie zdublowanego surowego badge `AI Act: {enum}` z starego paska trust-badges (potwierdzone grep-em w całym `frontend/src` — zero wystąpień)
- ✅ `452e947` — powiększenie kart "Podobne narzędzia": zapytanie rozszerzone o realny join `categories(name_pl)`, lżejsza karta wizualnie spójna z `CompanyCard` bez zmyślonych badge'y dla danych, których zapytanie nie pobiera
- ✅ `2b6497c` — usunięcie zdublowanego linku "Strona producenta" w hero `[slug].astro` — prowadził do tego samego adresu co główny przycisk CTA (błąd projektowy odkryty podczas przeglądu, nie w oryginalnym planie Prompt A)
- ✅ Ręczne czyszczenie 40 osieroconych stron narzędzi na serwerze (`rm -rf` przez SSH, backup w `~/backup-orphaned-20260909/`), zweryfikowane 200→404 przez Chrome
- ✅ `d62e41c` — naprawa `deploy.yml`: nowy krok czyszczący (`Clean up orphaned narzedzia pages`) usuwający z serwera katalogi `narzedzia/{slug}/`, które zniknęły z buildu — manifest slugów wgrywany poza webroot, porównanie przez `grep -qxF`, zabezpieczenie przed pustym/wadliwym manifestem (próg min. 10 linii), `set -euo pipefail`, `rm -rf --`. Zweryfikowane na żywym deployu (run #152, zielony) bez regresji na 10 sprawdzonych żywych i 10 usuniętych narzędziach.

- ✅ `f3ee5e2` — naprawa panelu admina: zakładka "Narzędzia" renderowała tylko pierwsze 100 z 277 zatwierdzonych narzędzi (zaszyty `&limit=100`, zero paginacji, zero sygnalizacji obcięcia). Dodana paginacja (`?tab=tools&page=N`, `offset` obok `limit`), stabilny tie-breaker sortowania (`order=created_at.desc,id.asc`) żeby granice stron nie duplikowały/pomijały wierszy przy remisach `created_at`. Zweryfikowane na żywo: 100+100+77=277, brak duplikatów/dziur na obu granicach stron.

### Odkrycia / problemy
- **Krytyczny bug w `deploy.yml`:** `appleboy/scp-action` używa zwykłego SCP — nadpisuje pliki, nigdy nie usuwa tych, które zniknęły z `dist/`. Każde narzędzie, które kiedykolwiek zmieniło status z `approved`, zostaje jako żywa, publicznie dostępna strona na serwerze na zawsze, niezależnie od tego czy zmiana statusu była słuszna czy przez pomyłkę.
- **Skala:** 40 z 41 narzędzi ze statusem `!= approved` w `tools` miało wciąż żywą stronę (200) na produkcji — w tym halucynacje z pipeline'u z lipca (Siri AI, Apple Core AI Framework, OpenAI, kilkanaście surowych tytułów "Show HN: ...").
- **SEO bez szkód:** Google Search Console (Inspekcja URL) potwierdza dla sprawdzonych przypadków (`openai`, `siri-ai`) — "Adres URL jest Google nieznany". Nigdy nie odkryte, bo sitemapa jest budowana z tego samego zapytania co strony — nic do zgłoszenia w Search Console.
- Diagnostyka: `cf-cache-status: DYNAMIC` wykluczył Cloudflare jako przyczynę; świeży pełny rebuild w GitHub Actions nic nie zmienił (bo build nigdy nie generuje pliku dla strony poza `getStaticPaths()` — SCP nie ma czego wgrać).

- **Osobne odkrycie, nie związane z bugiem deployu:** przy okazji sprawdzania czemu "Yolo" (Ultralytics YOLO26, status `rejected` z masowego odrzucenia 18.07) nie pojawia się w panelu do edycji, wyszedł drugi, niezależny bug — limit 100 wierszy bez paginacji, dotykający 177 zatwierdzonych narzędzi. Sama nieobecność "Yolo" w zakładce "Narzędzia" to osobna sprawa (zakładka filtruje wyłącznie `status=approved`, nie pokazuje odrzuconych) — świadomie zostawiona bez zmian, brak obecnie potrzeby przeglądu/przywracania odrzuconych narzędzi z poziomu UI.

### Zmieniam podejście do
- Naprawa `deploy.yml` (dodanie mirror/`--delete`) świadomie odłożona na osobną sesję — zmiana z realnym ryzykiem (możliwość usunięcia czegoś poza zasięgiem, np. `private_html/config/` z sekretami), nie robić pod presją czasu na koniec długiej sesji.
- Naprawa `deploy.yml` jednak zrobiona tego samego dnia (nie odłożona) — diagnoza okazała się kompletna i jednoznaczna, ryzyko dobrze zrozumiane (współdzielony `target` między krokami SCP frontend/admin), więc zdecydowaliśmy się dokończyć zamiast czekać na kolejną sesję.

### Następny krok (priorytet #1 na start następnej sesji)
Naprawa `deploy.yml` — mirror/`--delete` dla `public_html/narzedzia/` i innych katalogów z `astro build`, z dokładną weryfikacją że nie obejmuje `private_html/config/` (sekrety) ani kroku `admin/` (osobne SCP). Rozważyć dry-run przed pierwszym żywym uruchomieniem. Backup `~/backup-orphaned-20260909/` na serwerze do skasowania po potwierdzeniu że nic z niego nie jest potrzebne.


## [v0.12] — 2026-09-10 (Faza 2: filtrowanie katalogu przez Pagefind)

### Zrobione
- ✅ `a03a685` + poprawka premium — fundament: naprawiony ukryty bug (`index.astro` renderował WSZYSTKIE ~277 narzędzi mimo nagłówka "Najnowsze wpisy", brak `.limit()` → 514 KB), przy okazji naprawiony bug liczników kategorii, poprawka zapewniająca że aktywne premium zawsze widoczne w całości, zainstalowany `astro-pagefind`, strony narzędzi otagowane `data-pagefind-body/meta/filter`
- ✅ Prompt B — nowa strona `/narzedzia/` (wzorzec kolekcja→zasób obok `/narzedzia/{slug}/`): domyślny pełny SSR (CompanyCard, dla crawlerów/bez JS), pigułki filtrów jako prawdziwe linki do `/kategoria/{slug}/` (fallback bez JS), z JS: `pagefind.search(null, {filters})`, próg 60 wyników + "Pokaż więcej", link w nawigacji + CTA na stronie głównej

### Odkrycia / problemy
- **Decyzja architektoniczna, nie oczywista od razu:** spośród 4 podejść (linki / toggle w JS / żywe zapytania Supabase / Pagefind) wybrano Pagefind Wariant B2 — jedyne godzące SEO, płynność i skalowanie do 1000+ bez kosztu żywego backendu/RLS
- Strona główna: 514 KB → 35 KB
- Zweryfikowane w pełni na żywej produkcji przez przeglądarkę (nie tylko lokalnie): filtrowanie bez przeładowania, próg 60/"Pokaż więcej", fallback bez JS strukturalnie potwierdzony

### Następny krok
- Kolejne wymiary filtrowania w UI (cennik, AI Act) — dane już otagowane, brakuje UI
- Narastanie plików `dist/pagefind/` przy kolejnych buildach — ten sam typ problemu co bug `deploy.yml` z 09.09, do obserwacji

---

## [v0.13] — 2026-09-13 (panel logowania błędów)

### Zrobione
- ✅ Tabela `activity_log` (Supabase) — RLS append-only, wspólna dla `verify_tool.php` i planowanego scrapera
- ✅ `verify_tool.php` — helper `verify_log_activity()`, logowanie `error` (wyjątki) i `warning` (martwe URL-e: curl-level i HTTP≥400, osobne konteksty)
- ✅ `admin/logs.php` — nowy panel: filtr source/level, paginacja, kolorystyka pill spójna z `category-colors.ts`

### Odkrycia / problemy
- Pierwszy test dymny (podmiana `website_url` na martwy adres) nie wygenerował wpisu w logach — okazało się, że fetch strony z kodem HTTP ≥400 kończy się kontrolowanym early-return (JSON błędu), nie wyjątkiem `\Throwable`. Nowy log w głównym catch bloku nigdy nie miał szans się odpalić tą ścieżką — trzeba było dopisać osobne logowanie `warning` bezpośrednio przy obu early-return blokach (curl error i HTTP≥400)
- Nauka: przy dodawaniu logowania do istniejącego kodu z wieloma ścieżkami błędów, nie zakładać, że wszystkie błędy przechodzą przez jeden centralny catch — zweryfikować to na produkcji przed uznaniem zadania za zamknięte

### Następny krok
- Pilot scrapera YC-OSS jako alternatywa dla NiFi (PHP + GitHub Actions scheduled workflow) — activity_log gotowe do przyjęcia logów ze scrapera (source='scraper:yc_ai_pilot', run_id do wykorzystania)

---

## [v0.14] — 2026-09-13/15 (sesja UX — audyt katalogu)

### Zrobione
- ✅ Hero strony głównej z wyróżnikiem RODO/AI Act/UE (`a032d72`, `1f90b02`) — wcześniej tylko na `/premium`
- ✅ Migracja `002_tri_state_compliance_fields.sql` — `rodo_compliant`/`dpa_available`/`eu_data_hosting` nullable (NULL = nie zweryfikowano); trigger `promote_scrape_to_tools()`, panel admina (tri-state select) i frontend (kafelki + FAQ) zaktualizowane
- ✅ "🔍 Znalezione sygnały" w modalu weryfikacji — dosłowne cytaty ze strony o RODO/DPA/hostingu UE, bez oceny, zapis nadal ręczny (`79243aa`)
- ✅ Badge AI Act "minimalny" wyciszony do szarego (`1945ddd`)
- ✅ Wyszukiwanie tekstowe na `/narzedzia/` — debounce + `pagefind.search()` z filtrem kategorii, naprawiony bug empty-state (`2976272`)
- ✅ Formularz "Dodaj wpis" przyjmuje `price_from_pln` (`664c416`)
- ✅ 19.09: font Inter self-hosted (`5c68209`), nowe kolory kategorii Zarządzanie projektami i Cyberbezpieczeństwo AI (`c93f7f0`)

### Odkrycia / problemy
- `price_from_pln` wypełnione dla 0,9% wpisów (3/329), wszystkie z jednorazowej partii z tygodnia 1 — deklarowany wyróżnik "ceny w PLN" nie miał pokrycia w danych
- Zasada "rodo_compliant manual-only" pozostaje w mocy — "Znalezione sygnały" to evidence (cytat), nie ocena; wyjątek udokumentowany w `CLAUDE.md`
- Pagefind zlewa `false`/`null` w meta `rodo:`/`eu_hosting:` — do naprawy razem z filtrem po statusie zgodności

### Zmieniam podejście do
- Pozycjonowanie: "RODO + AI Act + PLN" → **"RODO + AI Act"** (PLN jako bonus) w `CLAUDE.md`/`ARCHITECTURE.md`/`SEO.md`; UI cen odłożone do wyższego fill rate

### Następny krok
- Otwarte, niski priorytet: duplikacja homepage/`/narzedzia/`, badge "Najpopularniejszy" na `/premium` (usunięcie zlecone, wykonanie niepotwierdzone — na 20.09 wciąż w kodzie), "Podobne narzędzia" tylko wg kategorii

---

## [v0.15] — 2026-09-15 (refaktoryzacja panelu admina — zakładka "Narzędzia")

### Zrobione
- ✅ Wyszukiwanie po nazwie + filtr kategorii + sortowalne nagłówki (`tools_tab_url()` współdzielone z paginacją) (`f7b26dd`)
- ✅ Modal edycji `#edit-modal` — wszystkie pola jednym PATCH przez wspólny `patchTool()`, zastępuje 3 zduplikowane funkcje `save*` (`cc1a12c`)
- ✅ Mini-formularze zastąpione read-only badge'ami (`785306f`): 6→0 pól formularza, 9→3 przyciski/wiersz, −36% rozmiaru szablonu wiersza

### Odkrycia / problemy
- Problemem nie był rozmiar ani paginacja, tylko gęstość interakcji (6 zawsze widocznych mini-formularzy, ~20 elementów DOM na wiersz)

### Następny krok
- Modal edycji rozszerzany w sesji Content o kolejne pola (v0.16)

---

## [v0.16] — 2026-09-13/19 (sesja Content/Copywriting — audyt jakości języka)

### Zrobione
- ✅ `CONTENT-GUIDE.md` — źródło prawdy dla tonu i jakości copy (`c7cbb1f`)
- ✅ `verify_tool.php` — reguły jakości języka w prompcie (diakrytyki, gramatyka, długość, ton, 3. osoba, bez etykiet pól) (`4fed1fe`, `6dd180c`); reguły 5–7 (bez podwójnego "Dla:", 3. osoba, bez etykiet) zweryfikowane na ~20 żywych przypadkach, zero nawrotów
- ✅ Audyt ~245/300 wpisów: ~61 jednoznacznych błędów (Wzorzec A "Dla: Dla..." ×35, mieszanie osób ×12, gramatyka ×7, diakrytyki ×4 + pojedyncze) i ~55 wpisów w stylu Wzorca B
- ✅ Masowa regeneracja ~50 wpisów przez panel: ~20 przyjętych w całości, ~15 częściowo, ~8 odrzuconych
- ✅ 4 wpisy usunięte jako martwe/rebrandowane: Legora, Tokenless (→ Touchy), Hotjar (→ Contentsquare), PilotCite (→ AEO Mantis)
- ✅ RODO/DPA/hosting UE zebrane dla ~35 narzędzi przez web search
- ✅ Modal "Edytuj": ręczna edycja `description_pl`/`best_for_pl` (`fb571b0`), `pricing_model` (`398c151`), `name` (`793ef84`); `name` z `maxlength="30"` (limit czysto UI-owy — brak CHECK constraint w bazie), nazwa w wierszu tabeli synchronizowana po zapisie

### Odkrycia / problemy
- Regeneracja AI systematycznie gubi polski kontekst rynkowy z ręcznych wpisów (Rossum — KSeF; Surfer SEO, Woodpecker.co — polskie firmy z Wrocławia; potwierdzone też na Exact Online, Typeform, UiPath). Surfer SEO potwierdzony w KRS, Woodpecker.co notowany na GPW (WPR) — żaden opis w bazie tego nie wspomina, ręczne dopisanie do rozważenia
- Możliwa halucynacja modelu "Qwen3.8-27B" w yolo-auto-api, możliwy duplikat rows-ai/rows — do sprawdzenia

### Zmieniam podejście do
- Wzorzec B (~55 wpisów w stylu 1-zdaniowym, konwencja z sierpnia) potraktowany jako decyzja stylistyczna, nie bug — odłożony jako "Faza 4 audytu contentu"

### Następny krok
- Decyzja stylistyczna ws. Wzorca B (niska pilność)

---

## [v0.17] — 2026-09-16/19 (audyt `website_url` całego katalogu)

### Zrobione
- ✅ Skrypt PHP (`scraper/url_audit.php`) + workflow (`cc5fd5f`; workflow `url-audit-oneshot.yml` przekształcony 20.09 na cykliczny `url-audit.yml` — co kwartał + ręcznie; katalog rośnie, potrzebny stały nadzór nad URL-ami, nie tylko jednorazowy): 283 sprawdzone, 43 oflagowane (~15%)
- ✅ 7 przypadków doprowadzonych do końca: 3 usunięte (Drift → 1mind/Salesloft, Causal → Lucanet/xP&A, Understudy → Orchestra.ai), 1 naprawiony (Brainware: Kofax → Hyland, `website_url` → `hyland.com/en/solutions/products/brainware-intelligent-capture`), 1 fałszywy alarm (MailBroom)
- ✅ Łącznie w audycie contentu (13–19.09) usunięto 7 produktów (Legora, Tokenless, Hotjar, PilotCite, Drift, Causal, Understudy); stan bazy 20.09: 284 zatwierdzone

### Odkrycia / problemy
- ~15% `website_url` w katalogu oflagowane. Wśród przypadków doprowadzonych do końca: produkty przejęte/rebrandowane i błędne przypisanie do innej firmy (Brainware); pozostałe ~37 to głównie HTTP 403 (blokady botów, nie martwe strony) i legalne konsolidacje domen (np. Notion, Freshworks, Segment/Twilio, Zendesk/Klaus)

### Następny krok
- Pozostałe ~37 oflagowanych URL-i (niska pilność), Amorphic Labs (nazwa firmy vs. produkt — sprawdzić, czy powinno być "AgentMuxer"), decyzja ws. opisu Brainware (poprawny co do firmy, ale bez polskiego kontekstu z oryginału)

---

## [v0.18] — 2026-09-19 (growth — pierwszy post LinkedIn)

### Zrobione
- ✅ Post 1 LinkedIn (RODO/AI Act) opublikowany 19.09.2026 — pierwszy krok Fazy 1 po sesji strategicznej z 13.09

### Następny krok
- Post 2 LinkedIn (draft w Notion) i start cold outreachu — wciąż nierozpoczęte

---

## [v0.19] — 2026-09-21 (kill-switch sprzedaży Premium + znalezisko http→https)

### Zrobione
- ✅ **G0 — kill-switch publicznego zakupu Featured** (`admin/checkout.php` +
  `frontend/src/pages/premium.astro`, flaga `SALES_ENABLED = false` w obu plikach):
  do czasu wyjaśnienia z księgowym warunku 60 miesięcy dla działalności
  nierejestrowanej (`DECISIONS.md` ADR-010) sprzedaż pakietów Premium jest
  wstrzymana. `checkout.php` odrzuca każde żądanie (redirect na
  `/premium/?error=sales_paused`) zanim dotknie Stripe SDK; `premium.astro`
  wyłącza przyciski "Kup teraz" i pokazuje neutralny baner z zaproszeniem do
  bezpłatnego programu pilotażowego. `webhook.php` nietknięty.
- ✅ ADR-010 zaktualizowane: wcześniejsza JDG (zawieszona 31.01.2022, zamknięta
  11.12.2023) najprawdopodobniej wyklucza kwalifikację jako działalność
  nierejestrowana do 1.02.2027 — status zmieniony na "do pisemnego potwierdzenia
  z księgowym", dopisane konsekwencje robocze (wypłaty PartnerStack wstrzymane,
  tripwire na pierwszą realną prowizję affiliate).

### Odkrycia / problemy
- 🐛 **`http://aifirmy.pl` nie przekierowuje 301 na `https://`** — serwuje pełną
  treść strony bezpośrednio pod http (200 OK, brak nagłówka `Location`),
  potwierdzone zarówno na stronie narzędzia (`/narzedzia/lempire-lemlist-ekosystem/`)
  jak i na stronie głównej, więc problem jest site-wide, nie per-URL. Canonical
  tag jest poprawny (`https://aifirmy.pl/.../`), ale — tak jak przy wcześniejszym
  buggu www→apex (sierpień 2026) — canonical nie zastępuje twardego 301.
  **Naprawa po stronie Cloudflare (Redirect Rule `http://*` → `https://${1}`),
  jeszcze niewykonana** — wymaga działania w panelu Cloudflare, nie w kodzie.

### Następny krok
- Cloudflare: dodać Redirect Rule wymuszającą https (albo włączyć "Always Use HTTPS")
- Po deployu G0: zweryfikować na żywo, że `/premium` pokazuje wyłączone przyciski
  i że bezpośredni POST na `/admin/checkout.php` faktycznie redirectuje zamiast
  tworzyć sesję Stripe

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
