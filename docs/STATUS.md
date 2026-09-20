# 📊 STATUS.md — aifirmy.pl
> Ostatnia aktualizacja: 2026-09-20

---

## 🚦 Status ogólny

| Element | Status |
|---|---|
| **Faza** | Projekt live, infrastruktura monetyzacji działa (Stripe + affiliate), przychód = 0 (stan na 20.09.2026). Katalog urósł z ~90 do **284 zatwierdzonych narzędzi** (stan bazy 20.09, po usunięciu 7 martwych/rebrandowanych wpisów w tygodniu 13–19.09). Ruch organiczny rośnie tydzień do tygodnia. 4. źródło danych (YC-OSS) + 10. kategoria wdrożone. |
| **Domena** | ✅ aifirmy.pl (Cyberfolks) + www→apex redirect (Cloudflare) |
| **Hosting** | ✅ Aktywny — Cyberfolks + Cloudflare |
| **Baza danych** | ✅ Supabase PostgreSQL (eu-central-1), 10 kategorii |
| **Pipeline NiFi** | ✅ 4 źródła: HN + BetaList + Product Hunt + YC-OSS API, dwuwarstwowy filtr jakości |
| **Frontend** | ✅ Kafle kategorii (10), ikony, trust badge'e, rozszerzone FAQ (RODO/DPA/EU/AI Act), AI-content disclosure, redesign karty i hero strony detalu (09.09), hero z wyróżnikiem RODO/AI Act/UE (13.09), tri-state RODO/DPA/EU hosting (14.09), wyszukiwanie tekstowe na `/narzedzia/` (15.09), font Inter (19.09) |
| **Cloudflare** | ✅ SSL Full, CDN, DNS, Redirect Rules (www→apex) |
| **Panel admina** | ✅ PHP + Supabase REST API, "Odrzucone przez AI", "Zweryfikuj przez AI" (logo fix wdrożony), panel logów błędów (activity_log, 13.09), modal edycji + wyszukiwanie/filtr/sortowanie w zakładce "Narzędzia" (15–19.09), "Znalezione sygnały" RODO/DPA/UE w weryfikacji (14.09) |
| **Monetyzacja** | ✅ Infrastruktura live: Stripe Live mode, checkout + webhook, email po zakupie — przychód = 0 na 2026-09-20 |
| **Affiliate** | ✅ ClickUp/PartnerStack aktywny |
| **Analytics** | ✅ Search Console (główne źródło prawdy) + AWStats; ⚠️ GA4 niewiarygodne (patrz niżej) |

---

## ✅ Tydzień 1-7 (do lipca 2026) — fundament

Strategia i nisza, baza danych (6→7 tabel), pipeline NiFi (3 źródła: HN/BetaList/Product Hunt), frontend Astro, monetyzacja Stripe, linki afiliacyjne (ClickUp/PartnerStack). Szczegóły w historii poniżej i w Notion.

---

## ✅ Pakiet poprawek 2 (lipiec 2026) — UX, jakość pipeline'u, panel admina

10/10 zadań zamkniętych: trust badge RODO/AI Act, FAQ szablonowe, sekcja "Podobne narzędzia", pole `best_for_pl`, kafle kategorii, ikony kategorii, większy panel karty, logo (favicon fallback), funkcja "Zweryfikuj przez AI", dwuwarstwowy filtr jakości pipeline'u (Show HN/Launch HN + `is_real_product`). Pełne szczegóły w Notion.

---

## ✅ Sierpień 2026 — naprawy fundamentu SEO i danych

**Martwa sitemapa (23.07)** — `@astrojs/sitemap` przypadkowo usunięty w `d0639c1`, sitemapa miała tylko 16 URL-i (seed z Tygodnia 2) zamiast ~90+. Naprawione, prawdopodobnie główna przyczyna słabej widoczności od startu.

**CHECK constraint blokował `ai_rejected` (23.07)** — kolumna `stage` w `scrape_queue` nie dopuszczała nowej wartości `ai_rejected`, wpisy odrzucane przez AI nie zapisywały się w ogóle. Naprawione przez `ALTER TABLE ... DROP/ADD CONSTRAINT`.

**Linki wewnętrzne bez ukośnika** — `CompanyCard.astro`, `[slug].astro`, kafle kategorii budowały linki bez trailing slash, generując zbędne przekierowania wykrywane przez Google jako "strona zawiera przekierowanie" (commit `9f2c7f1`). Naprawione we wszystkich 4 miejscach.

**Brak przekierowania www→apex (31.08)** — `www.aifirmy.pl` serwował treść bezpośrednio zamiast przekierować do wersji kanonicznej (mimo poprawnego `<link rel="canonical">`). Naprawione regułą Cloudflare Redirect Rules (301, `https://www.*` → `https://${1}`).

**Zgodność z AI Act — disclosure treści AI-generowanej** — sprawdzony stan prawny (polska ustawa o systemach AI, Dz.U. 2026 poz. 1003; obowiązki przejrzystości z art. 50 UE AI Act w mocy od 2.08.2026). Dodany disclaimer w stopce wszystkich stron + sekcja w polityce prywatności, niezależnie od pewności czy wyjątek "ludzka weryfikacja redakcyjna" formalnie chroni pipeline.

**Rozszerzone FAQ o RODO/DPA/EU hosting/AI Act (31.08, commit `6faf053`)** — dotychczasowe krótkie odpowiedzi FAQ zastąpione pełnymi wyjaśnieniami prawnymi (co oznacza RODO/DPA/przetwarzanie w UE w praktyce, pełny opis obowiązków dla każdego z 4 poziomów ryzyka AI Act). JSON-LD generowany automatycznie z tej samej tablicy `faqs` co widoczna treść — eliminuje ryzyko rozjazdu.

---

## ✅ Nowe źródło NiFi — YC-OSS API (30.08.2026)

**Kontekst:** pipeline HN/BetaList/Product Hunt dawał od tygodni słaby sygnał — typowo 1-3 zatwierdzone wpisy na 10-15 w kolejce, powtarzające się halucynacje (nieistniejące modele typu "GPT-5.5-Cyber"), generyczne opisy bez konkretnego produktu.

**Źródło:** yc-oss/api — niezależny, aktywnie utrzymywany projekt udostępniający pełną bazę firm Y Combinator jako statyczne pliki JSON, budowany z oficjalnego indeksu Algolia (nie scraping — zero ryzyka ToS). Endpoint: `https://yc-oss.github.io/api/tags/artificial-intelligence.json` (~900+ firm z tagiem AI).

**Dlaczego to działa lepiej:** każda firma przeszła realny proces finansowania YC (eliminuje kategorię "artykuł/esej" halucynacji), pole `website` zawsze wskazuje na prawdziwą domenę (nie na stronę launch-platformy), pola `one_liner`/`long_description` dają OpenAI prawdziwy tekst źródłowy zamiast zgadywania z samej nazwy.

**Architektura nowej gałęzi:**
```
GenerateFlowFile (YC AI Trigger, cron 5:00)
  → InvokeHTTP (GET tags/artificial-intelligence.json)
  → SplitJson ($.*)
  → EvaluateJsonPath (yc_name, yc_website, yc_one_liner, yc_long_description, yc_launched_at, yc_batch, yc_slug)
  → RouteOnAttribute (nowa_firma: launched_at w ostatnich 90 dniach)
  → UpdateAttribute (hn_title=yc_name, hn_url=yc_website, hn_type=story,
     source_context=one_liner+long_description oczyszczone, source_name_override=yc_ai)
  → [bezpośrednio do ExecuteSQL/deduplikacji, z pominięciem wspólnego filtra słów kluczowych]
```

**Dwa bugi znalezione i naprawione podczas budowy:**
1. `source_name` zahardkodowane na `"hacker_news"` we wspólnym `ReplaceText` — wszystkie źródła zapisywałyby się pod tą samą etykietą. Naprawa: `${source_name_override:isEmpty():ifElse('hacker_news', ${source_name_override})}`.
2. Znaki nowej linii i cudzysłowy w `yc_long_description` łamały JSON do OpenAI (400 Bad Request). Naprawa: `replaceAll('[\r\n]+', ' '):replaceAll('"', '')` przed zbudowaniem `source_context`.

**Wynik pierwszego uruchomienia:** 80 firm przeszło filtr 90-dniowy, cała partia przetworzona przez pełny pipeline.

---

## ✅ Nowa kategoria — "Cyberbezpieczeństwo AI" (01.09.2026)

Weryfikacja AI świeżych wpisów z YC ujawniła systemową lukę: 3 potwierdzone narzędzia cyberbezpieczeństwa/AI security (**Trident**, **Fabraix**, **Sentrint**) błędnie zaklasyfikowane w "Prawo i compliance", bo katalog nie miał odpowiedniej kategorii. AI poprawnie próbowało zasugerować "Bezpieczeństwo IT" — panel słusznie odrzucił nieistniejącą kategorię (zabezpieczenie z Zadania 9 zadziałało).

**Wdrożone:**
- SQL: `INSERT INTO categories (slug, name_pl, icon, sort_order) VALUES ('cyberbezpieczenstwo-ai', 'Cyberbezpieczeństwo AI', 'shield-bolt', 10)`
- NiFi: dopisana 10. kategoria do listy w prompcie OpenAI
- Frontend: kolor `slate` (jedyny nieużyty z 9), nowa ikona (tarcza z wykrzyknikiem, geometrycznie odróżniona od tarczy-checka dla "Prawo i compliance"), zaktualizowany `categoryHoverBorder` w `index.astro`
- Migracja: Trident, Fabraix, Sentrint przeniesione. **Palisade sprawdzone i wykluczone** — mimo nazwy sugerującej obronność, to faktycznie AI-sprzedawca dla marketplace'ów, poprawnie w "Sprzedaż i CRM"

**Nauka:** nazwa narzędzia nie jest wiarygodnym sygnałem kategorii — zawsze weryfikować przez AI, nie zgadywać (Erinys, mimo mitologicznej nazwy, to legalne narzędzie dla kancelarii prawnych — poprawnie sklasyfikowane od początku).

---

## ✅ Poprawka wyszukiwania logo w `verify_tool.php` (05.09.2026)

**Problem:** `extract_logo_hint()` wymagała konkretnej kolejności atrybutów w tagu (`property` przed `content`, `rel` przed `href`) — HTML nie wymusza tej kolejności, więc strony Next.js/nowoczesnych frameworków (częste wśród startupów YC) nie były wykrywane mimo poprawnego tagu. Brak fallbacku gdy ekstrakcja zawiedzie.

**Naprawa:** ekstrakcja całego tagu `<meta>`/`<link>` jednym regexem, potem dopasowanie atrybutów niezależnie od kolejności; dodane sprawdzanie `rel="apple-touch-icon"`; dodany fallback na `google.com/s2/favicons` (spójny z logiką triggera `promote_scrape_to_tools()`); wydzielona współdzielona funkcja `resolve_logo_url()`. Zweryfikowane na żywo (Studio pokazuje teraz poprawny favicon fallback).

---

## ✅ Redesign karty katalogowej i hero strony detalu (08.09.2026)

**Kontekst:** karta katalogowa (`CompanyCard.astro`) i strona detalu narzędzia rozjechały się wizualnie — AI Act pokazywany w trzech różnych stylach na samej stronie detalu (żaden z PL etykietą), `pricing_model` konkurował wizualnie z sygnałem ryzyka, kategoria bez spójnego stylu z resztą katalogu.

**Zrobione:** pięć commitów (`693bf92`, `f53c90c`, `1b7e7f5`, `b3e4c41`, `f9f5e53`) — pełny redesign karty (kolorowy AI Act, neutralny pricing, hierarchia CTA) i hero+sidebar strony detalu, z jednym współdzielonym źródłem kolorów/etykiet (`category-colors.ts`: `aiActRiskColors`, `pricingLabels`) zamiast trzech niezależnych kopii.

**Otwarte:** stary dolny pasek trust-badges na stronie detalu (surowy `AI Act: minimal`) nietknięty do czasu Prompt B; dwa punkty do weryfikacji w kodzie (kolor "Kategoria" w sidebarze, layout CTA bez affiliate linku) — patrz `CHANGELOG.md` [v0.10] po pełną listę.

**Nauka:** przy redesignie komponentu współdzielonego (karta) warto od razu audytować inne miejsca renderujące te same dane (`[slug].astro`) zamiast zakładać że są już spójne — tu nie były, mimo że wyglądały podobnie na pierwszy rzut oka.

---

## ✅ Dokończenie strony detalu + naprawa krytycznego buga deployu (09.09.2026)

**Zrobione:** trzy commity kończące redesign `[slug].astro` (pasek zgodności, powiększone podobne narzędzia, usunięcie zdublowanego linku CTA) — pełny opis w `CHANGELOG.md` [v0.11].

**Znaleziony podczas weryfikacji na żywo, nie w kodzie:** `deploy.yml` nigdy nie usuwał plików z serwera przy SCP. Efekt: 40 z 41 narzędzi odrzuconych/usuniętych w historii projektu (w tym halucynacje z pipeline'u z lipca) miało wciąż żywe, publiczne strony na produkcji. Wyczyszczone ręcznie przez SSH, backup zrobiony, zero wpływu na SEO (potwierdzone w Search Console — te strony nigdy nie zostały odkryte przez Google, bo sitemapa buduje się z tych samych danych co strony).

**Otwarte — priorytet #1:** naprawa `deploy.yml` (mechanizm mirror/`--delete`), żeby problem się nie powtórzył. Wymaga ostrożnej weryfikacji zasięgu (sekrety w `private_html/`, osobny krok deployu dla `admin/`) przed wdrożeniem — nie robić w pośpiechu.

**Nauka:** SSG + SCP-bez-delete to pułapka, która nie ujawnia się przy normalnym testowaniu (katalog poprawnie filtruje po `status='approved'`, więc nikt nie widzi problemu, dopóki nie sprawdzi się konkretnego, nieaktualnego URL-a bezpośrednio).

**Aktualizacja (ten sam wieczór):** priorytet #1 zrealizowany od razu, nie odłożony — `deploy.yml` ma teraz automatyczny krok czyszczący (commit `d62e41c`), więc problem osieroconych stron nie powinien się powtórzyć bez ręcznej interwencji. Zweryfikowane na żywym deployu (GitHub Actions run #152, zielony), zero regresji na sprawdzonej próbce narzędzi. Backup `~/backup-orphaned-20260909/` można skasować.

## ✅ Poprawka paginacji w panelu admina (09.09.2026, ten sam wieczór)

Zgłoszony brak "Yolo" na liście do edycji doprowadził do znalezienia szerszego buga: zakładka "Narzędzia" pokazywała tylko pierwsze 100 z 277 zatwierdzonych narzędzi (zaszyty limit z wczesnej fazy projektu, bez paginacji i bez żadnej sygnalizacji obcięcia). Naprawione (`f3ee5e2`) — dodana paginacja z bezpiecznym rzutowaniem parametru `page` z URL i stabilnym tie-breakerem sortowania. Zweryfikowane na żywo: suma stron = 277, brak duplikatów/pominięć na granicach.

**Świadomie nieruszone:** widoczność narzędzi ze statusem innym niż `approved` (np. odrzuconych jak Yolo) w panelu — obecnie brak takiej zakładki/filtra, nie było dziś potrzebne, zostaje jako otwarty temat na przyszłość jeśli się okaże potrzebny.

## ✅ Faza 2 — filtrowanie katalogu przez Pagefind, strona /narzedzia/ (10.09.2026)

Jedyna rzecz z pierwotnego planu sesji redesignu (08-09.09), która pozostawała otwarta. Nowa strona `/narzedzia/` z pigułkami filtrów kategorii — statyczny fallback (prawdziwe linki do `/kategoria/{slug}/`) + płynne filtrowanie przez Pagefind bez przeładowania, skaluje się do 1000+ narzędzi bez wzrostu wagi strony (próg 60 + "Pokaż więcej"). Przy okazji naprawiony ukryty bug: strona główna renderowała cały katalog (514 KB → 35 KB).

**Otwarte:** UI dla dodatkowych filtrów (cennik, AI Act) — dane już otagowane. Narastanie plików `dist/pagefind/` przy kolejnych buildach — do obserwacji, ten sam typ ryzyka co bug `deploy.yml`.

---

## ✅ Panel logowania błędów — activity_log (13.09.2026)

**Kontekst:** błędy z `verify_tool.php` (wyjątki PHP, martwe/404 `website_url`) trafiały wyłącznie do plikowego logu na serwerze (`private_html/logs/verify_debug.log`) — nie było scentralizowanego, przeglądalnego miejsca do monitorowania jakości pipeline'u weryfikacji bez SSH.

**Zrobione:**
- Nowa tabela `activity_log` (Supabase, RLS z policy insert/select dla `anon`, append-only — brak policy update/delete) — wspólna dla `verify_tool.php` i w przyszłości dla scrapera
- `admin/verify_tool.php`: nowy helper `verify_log_activity()`, wołany w trzech miejscach — `level='error'` dla przechwyconych wyjątków (`catch (\Throwable $e)`, zrefaktoryzowany z istniejącego inline insertu), `level='warning'` dla dwóch odrębnych trybów martwego URL-a (`curl_error` przy błędzie DNS/timeout, `http_code` przy odpowiedzi ≥400) — każdy z osobnym kształtem `context`
- Nowy `admin/logs.php` — filtrowalny/paginowany podgląd logów (wzorowany na `admin/affiliate.php`), domyślny filtr `warning+error`, rozwijany JSON kontekstu, kolorystyka pill spójna z `aiActRiskColors` (error≈unacceptable, warning≈limited)
- Link nawigacyjny w `admin/index.php`

**Świadomie odłożone:** throttling/dedup powtarzających się `warning` dla tego samego martwego URL-a przy wielokrotnym ręcznym klikaniu "Zweryfikuj przez AI" — akceptowalne przy ręcznej akcji, do rewizji przy scraperze (automatyczny, cykliczny proces zmienia kalkulację).

**Zamierzone wykorzystanie w przyszłości:** `run_id` (kolumna UUID w schemacie) zarezerwowane pod grupowanie logów jednego uruchomienia scrapera — nieużywane przez `verify_tool.php`, celowo.

---

## 🎯 Sesja strategiczna 2026-09-13 — audyt kursu: produkt vs. growth

**Obserwacja:** ostatni miesiąc (redesign karty/detalu, naprawa deploy.yml, paginacja
admina, Pagefind, activity_log, pilot scrapera) to w całości praca po stronie podaży
(produkt/infra), mimo że plan z 23.08 ustalał Fazę 1 (growth: LinkedIn + outreach)
jako priorytet po wzroście katalogu do 260+. Warunek wstrzymania growth odhaczony od
tygodni, growth mimo to nie ruszony. Przychód (1 płatność + 1 afiliacja) zbyt mały,
żeby wnioskować o dopasowaniu produktu — brak udokumentowanego feedbacku od klientów.

**Decyzja robocza:** tymczasowa blokada nowego developmentu produktowego (poza
krytycznymi bugami) na 2–4 tygodnie; cały budżet 1h/dzień → Faza 1 (LinkedIn +
cold outreach) + zbieranie realnego feedbacku.

### 📈 Trend aktywności growth (nowy — uzupełniać przy cotygodniowej kontroli ruchu)

| Data | Posty LinkedIn | Maile cold outreach | Odpowiedzi/feedback |
|---|---|---|---|
| 2026-09-13 | 0 | 0 | 0 |
| 2026-09-19 | 1 | 0 | — |

### Zadania z tej sesji
- [x] Post 1 LinkedIn opublikowany (19.09)
- [ ] Post 2 LinkedIn opublikować (draft w Notion)
- [ ] Rozpocząć cold outreach — ustalić minimalny tygodniowy commitment i zacząć
- [ ] Zebrać 3–5 nieformalnych rozmów/feedbacków od użytkowników/klientów katalogu
- [ ] Powtarzać rytuał sesji strategicznej: trend ruchu + bilans czasu produkt/growth + 1 decyzja na sesję

---

## ✅ Sesja UX 2026-09-13/15 — audyt katalogu (zamknięta niemal w całości)

Realizacja punktów z audytu UX (pełna lista i historia rund: `UX-AUDIT.md`, Notion "Sesja UX 2026-09-13").

- **Hero strony głównej** — dopisany główny wyróżnik (RODO / AI Act / hosting UE), wcześniej komunikowany tylko na `/premium` (`a032d72`, `1f90b02`)
- **RODO/DPA/EU hosting jako 3 stany** — migracja `db/migrations/002_tri_state_compliance_fields.sql`: pola BOOLEAN nullable (NULL = nie zweryfikowano, zamiast domyślnego `false`). Backfill, trigger `promote_scrape_to_tools()` zaktualizowany, panel admina (tri-state select), frontend (kafelki "Zgodność i dane" + FAQ rozróżniają 3 stany) — `a4302d9`, `72067cd`, `3a1ad28`
- **Cena PLN** — fill rate `price_from_pln` = **0,9% (3/329 w audycie; 3 z 284 zatwierdzonych na 20.09)**, wszystkie z jednorazowej partii z tygodnia 1, w pipeline/panelu nie było ścieżki do ich ustawiania. Deklaracja pozycjonowania skorygowana z "RODO + AI Act + PLN" na **"RODO + AI Act"** (PLN jako bonus) w `CLAUDE.md`/`ARCHITECTURE.md`/`SEO.md` (`f5d6222`). Formularz "Dodaj wpis" odblokowany dla `price_from_pln` (`664c416`); UI renderowania cen świadomie odłożone do wyższego fill rate
- **Badge AI Act "minimalny"** — wyciszony wizualnie (`bg-green-100/text-green-800` → `bg-gray-100/text-gray-500`), żeby rzadsze, bardziej decyzyjne poziomy ryzyka wyraźniej się wyróżniały (`1945ddd`)
- **Wyszukiwanie tekstowe na `/narzedzia/`** — debounced input + `pagefind.search()` łączone z filtrem kategorii, przy okazji naprawiony bug empty-state (`2976272`)
- **Nowa funkcja panelu: "🔍 Znalezione sygnały"** w modalu weryfikacji — AI zwraca dosłowne cytaty ze strony nt. RODO/DPA/hostingu UE (bez oceny zgodności), czysto informacyjne, zapis nadal wyłącznie ręczny. Świadomie **nie** koliduje z zasadą "rodo_compliant manual-only" (opis wyjątku w `CLAUDE.md`, `eea5da9`) — `79243aa`, doprecyzowanie panelu `0f5e4cc`
- **Krój pisma i kolory kategorii (19.09, z git log)** — self-hosted Inter przez `@fontsource` zamiast czcionki systemowej (`5c68209`); nowe, rozróżnialne kolory dla "Zarządzanie projektami" i "Cyberbezpieczeństwo AI" — poprzednie były praktycznie identyczne i bezbarwne (chroma ~0.003) (`c93f7f0`). Szczegóły w `DESIGN-SYSTEM.md`

**Otwarte (niski priorytet):**
- Duplikacja strony głównej i `/narzedzia/`
- ~~Badge "Najpopularniejszy" na `/premium`~~ — ✅ usunięty 2026-09-20 (`1a4b3b9`, `premium.astro`); poprzednie usunięcie nie trafiło do repo. Styl wyróżnienia karty Dofollow (ramka/tło/przycisk) bez zmian
- "Podobne narzędzia" tylko wg kategorii, nie prawdziwego podobieństwa
- Meta Pagefind zlewa `false`/`null` (patrz sekcja audytu UX niżej)

---

## ✅ Sesja Content/Copywriting 2026-09-13/19 — audyt jakości języka (zamknięta)

**Zrobione:**
- `CONTENT-GUIDE.md` napisany i zaakceptowany (`c7cbb1f`) — źródło prawdy dla tonu/copy, analogiczne do `ARCHITECTURE.md` dla kodu
- `verify_tool.php` — prompt wzmocniony o reguły jakości języka: diakrytyki, zgodność gramatyczna, długość 2 zdań, bez tonu marketingowego, bez podwójnego "Dla: Dla...", wymuszona 3. osoba, bez etykiet pól typu "Tagline:" w treści (`4fed1fe`, `6dd180c`); ostatnie trzy to reguły 5–7 promptu, zweryfikowane na ~20 żywych przypadkach, zero nawrotów
- **Pełny audyt ~245/300 wpisów** (ręczny, Chrome) — ~61 jednoznacznych błędów: Wzorzec A "Dla: Dla..." ×35, mieszanie osób ×12, gramatyka ×7, diakrytyki ×4 + pojedyncze (Crisp: "Tagline:" w treści, Legora: wymieszane dane, rows-ai/rows: możliwy duplikat, yolo-auto-api: możliwa halucynacja modelu "Qwen3.8-27B"). Do tego ~55 przypadków stylu 1-zdaniowego bez nazwy narzędzia (**Wzorzec B** — konwencja ręcznych wpisów z sierpnia, decyzja stylistyczna, nie bug; wciąż otwarta jako "Faza 4 audytu contentu", patrz Backlog)
- **Faza 3 — masowa regeneracja ~50 wpisów przez panel:** ~20 zaakceptowanych w całości, ~15 częściowo, ~8 odrzuconych (regeneracja pogorszyła dane), **4 wpisy usunięte jako martwe/rebrandowane produkty:** Legora (dane skażone, firma na sprzedaż), Tokenless (rebranding na "Touchy"), Hotjar (wchłonięty przez Contentsquare), PilotCite (rebranding na "AEO Mantis")
- RODO/DPA/hosting UE zebrane web searchem dla ~35 narzędzi (m.in. n8n, UiPath, Mixpanel, Contentsquare/Hotjar — hosting UE potwierdzony; Fathom, Linear — potwierdzone "Nie")
- **Nowe funkcje panelu:** ręczna edycja `description_pl`/`best_for_pl` (wolny tekst, `fb571b0`), `pricing_model` (select, `398c151`) i `name` (input, `793ef84`) w modalu "Edytuj" — niezależnie od "Zweryfikuj przez AI" (przydatne, gdy regeneracja daje gorszy wynik niż to, co już jest w bazie). `name` ma `maxlength="30"` — limit czysto UI-owy (potwierdzone: brak CHECK constraint na kolumnie w bazie); po zapisie nazwa w wierszu tabeli jest synchronizowana bez przeładowania

**Potwierdzony wzorzec:** regeneracja AI systematycznie gubi polski kontekst rynkowy wpisany ręcznie w sierpniu (Rossum straciło wzmiankę o KSeF, Surfer SEO i Woodpecker.co straciły fakt bycia polskimi firmami z Wrocławia). Potwierdzone też na Exact Online, Typeform i UiPath. Surfer SEO to polska firma z Wrocławia (potwierdzone w KRS), Woodpecker.co jest notowany na GPW (WPR) — żaden opis w bazie tego dziś nie wspomina, ręczne dopisanie do rozważenia (patrz Backlog).
---

## ✅ Audyt `website_url` całego katalogu (16–19.09) — zamknięty

- Skrypt PHP (`scraper/url_audit.php`) + workflow GitHub Actions (`cc5fd5f`; workflow `url-audit-oneshot.yml` przekształcony 20.09 na cykliczny `url-audit.yml` — co kwartał, 1.01/1.04/1.07/1.10 o 4:00 UTC + ręcznie; katalog rośnie, a firmy stale znikają, rebrandują się i zmieniają domeny, więc potrzebny jest stały nadzór nad URL-ami, nie tylko jednorazowy; najbliższy przebieg 1.10.2026): **283 sprawdzone, 43 oflagowane (~15%)** (kryteria flagi: host mismatch / kod HTTP ≠ 200 / timeout)
- 7 przypadków doprowadzonych do końca, m.in.:
  - **3 kolejne wpisy usunięte:** Drift (przejęty, stał się "1mind" w Salesloft), Causal (wchłonięty przez Lucanet/xP&A), Understudy (domena to teraz Orchestra.ai)
  - **1 naprawiony:** Brainware — błędnie przypisany do Kofax, faktycznie Hyland; URL (`hyland.com/en/solutions/products/brainware-intelligent-capture`)/nazwa/kategoria/cennik/logo poprawione, opis czeka na decyzję (nowa wersja jest poprawna co do firmy, ale bez polskiego kontekstu z oryginału)
  - **1 fałszywy alarm:** MailBroom
- **Łącznie w wątku audytu contentu (13–19.09) usunięto 7 produktów:** Legora, Tokenless, Hotjar, PilotCite, Drift, Causal, Understudy. Stan bazy 20.09: **284 zatwierdzone**, 48 odrzuconych, 10 kategorii (zapytanie do Supabase `status=eq.approved`)
- **Otwarte:** pozostałe ~37 oflagowanych URL-i (głównie HTTP 403 = blokady botów, nie martwe strony, oraz legalne konsolidacje domen dużych firm — np. Notion, Freshworks, Segment/Twilio, Zendesk/Klaus; niska pilność), Amorphic Labs (możliwa nazwa firmy vs. produkt), decyzja ws. opisu Brainware

---

## ✅ Refaktoryzacja panelu admina — zakładka "Narzędzia" (15.09)

**Diagnoza:** problemem nie był rozmiar/paginacja, tylko gęstość interakcji — 6 zawsze widocznych mini-formularzy na wiersz, ~20 elementów DOM/wiersz.

**Zrobione:**
- Wyszukiwanie po nazwie + filtr kategorii + sortowalne nagłówki, parametry URL współdzielone z paginacją przez `tools_tab_url()` (`f7b26dd`)
- Modal edycji (`#edit-modal`) — wszystkie pola jednym PATCH przez wspólny `patchTool()`, zastępuje 3 zduplikowane funkcje `save*` (`cc1a12c`)
- Stare mini-formularze zastąpione read-only badge'ami (`785306f`): **6→0 pól formularza, 9→3 przyciski/wiersz, −36% rozmiaru szablonu wiersza**
- Modal później rozszerzony o edycję `description_pl`/`best_for_pl`/`pricing_model`/`name` (patrz sekcja Content wyżej)

---

## 📣 Growth — status na 20.09

- ✅ **Post 1 LinkedIn (RODO/AI Act) OPUBLIKOWANY 19.09.2026**
- Post 2 LinkedIn — wciąż tylko draft w Notion
- Cold outreach — nadal nie rozpoczęty

---

## 📋 Obserwacje z sesji weryfikacji AI (wrzesień 2026)

Systematyczne sprawdzenie ~10 świeżych wpisów przez "Zweryfikuj przez AI" ujawniło:

- **Wzorzec `og:image` jako baner, nie logo** — w praktycznie każdym sprawdzonym przypadku sugerowane `logo_hint` to promocyjny baner (np. `og-home-en.jpg`, `opengraph-image`), nie czyste logo. Favicon fallback pozostaje bezpieczniejszym domyślnym wyborem.
- **Model cenowy freemium↔paid — nie systemowy bug.** Zbadane na 7 przykładach: obie strony porównania (dane z YC vs live strona) myliły się na przemian — ręczna weryfikacja przez wyszukiwarkę pokazała, że raz stara wartość była bliższa prawdzie, raz nowa. Wniosek: to inherentna niepewność obu źródeł, nie coś do naprawienia jednym promptem — mechanizm porównania działa zgodnie z zamierzeniem (wymaga ręcznej decyzji za każdym razem).
- **website_url wskazujący na Product Hunt zamiast realnej domeny** — powracający problem jakości danych dla wpisów sourced z PH; wymaga ręcznej korekty per przypadek (TraceLLM, Cleanlist AI naprawione).
- **Realna poprawka kategorii zatwierdzona:** Zomma (Automatyzacja procesów → Finanse i księgowość) — narzędzie wertykalnie dedykowane finansom, precyzyjniejsza kategoria.

---

## 🐛 Dogłębna diagnoza "GA4 pokazuje zero" (sierpień 2026, 3+ tygodnie)

Search Console pokazywał konsekwentny wzrost ruchu, ale GA4 uparcie pokazywał 0 aktywnych użytkowników/zdarzeń. Pełna diagnoza wykluczyła po kolei: mały ruch/brak zgody na cookies, rozszerzenia przeglądarki, błędny Measurement ID, CSP, Service Worker, bug w kodzie (`gtag.js` faktycznie się ładuje i wykonuje — potwierdzone przez wewnętrzne zdarzenia `gtm.dom`/`gtm.load`), sieć/urządzenie domowe (zero również na telefonie, inna sieć).

**Ostateczna diagnoza:** `gtag.js` inicjalizuje się, ale beacon z danymi nigdy nie jest wysyłany — najbardziej prawdopodobne wytłumaczenie to bloker reklam działający w trybie "stub" (podmienia prawdziwy plik na nieszkodliwą atrapę). Grupa docelowa aifirmy.pl (osoby zainteresowane RODO/AI Act) demograficznie pokrywa się z użytkownikami blokerów reklam.

**Weryfikacja niezależnym źródłem — AWStats:** potwierdza realny, mały ale rosnący ruch z wyszukiwarek (24→32 hity/miesiąc), aktywne regularne crawlowanie Googlebota (493 hity/miesiąc), oraz — pożytecznie — potwierdza liczbę błędów 502/500 (spójne z sesjami debugowania `verify_tool.php`) i przekierowań 301.

**Wniosek: nie jest to bug do naprawienia. Search Console + AWStats pozostają głównym źródłem prawdy o ruchu**, nie GA4. Rozważyć Cloudflare Web Analytics jako uzupełnienie w przyszłości (rzadziej blokowany, opcjonalne).

---

## 📈 Trend ruchu (Search Console, kontrole cotygodniowe)

| Data | Kliknięcia | Zindeksowane strony |
|---|---|---|
| ~23.07 | 4 | 59 |
| ~30.07 | 5 | 142 |
| ~06.08 | 8 | 144-155 |
| 23.08 | 12 | 198 |
| ~30.08 | 15 | 198 |
| 20.09 | 11 (1,76 tys. wyświetleń, okno 28 dni) | — |

> ⚠️ **Okno czasowe kolumny "Kliknięcia" dla wierszy do ~30.08 jest niepewne** — nie wiadomo, czy to były 7 czy 28 dni. Wiersz 20.09 (bazowy) ma potwierdzone okno 28 dni, więc nie należy go bezpośrednio porównywać z wcześniejszymi wierszami. Od 20.09 zapisywać okno razem z liczbą.

Konsekwentny, przyspieszający wzrost. Ciekawy wzorzec: strony narzędzi z tytułem zawierającym starą + nową nazwę po rebrandingu (np. "Brevo (dawniej Sendinblue)") notują nieproporcjonalnie duży wzrost wyświetleń — możliwy sygnał do świadomego stosowania przy innych narzędziach po zmianie nazwy.

**Pierwszy zaobserwowany zewnętrzny backlink** (23.08): `piperic.com` linkuje do wpisu o Descript.

---

## 🎨 Audyt UX katalogu (2026-09-13)

Pierwszy przegląd w nowym, cyklicznym formacie "UX designer" — ocena strony z perspektywy
kupującego B2B, na podstawie realnego przeglądu żywej strony (nie tylko dokumentacji).
Pełna lista i uzasadnienia: Notion, sekcja "Sesja UX 2026-09-13".

**🔴 Wysoki priorytet**
- [x] Hero strony głównej nie komunikuje wyróżnika RODO/AI Act/UE (jest tylko na `/premium`)
- [x] Badge RODO/DPA na `[slug].astro` czyta się jak "niezgodny", nie "nie zweryfikowano"
- [x] Ceny w PLN nie są widoczne w UI — rozstrzygnięte 15.09: deklaracja wyróżnika skorygowana do "RODO + AI Act" (fill rate 0,9%), UI renderowania świadomie odłożone

**🟡 Średni priorytet**
- [x] Badge AI Act "minimalny" na ~90% kart bez zróżnicowania wizualnego — wyciszony do szarego (15.09)
- [x] Brak widocznego pola wyszukiwania tekstowego na `/narzedzia/` — dodane (15.09)
- [ ] Strona główna duplikuje `/narzedzia/` zamiast pełnić odrębną rolę

**🟢 Niski priorytet**
- [x] "Najpopularniejszy" na pakiecie Featured (`/premium`) — badge usunięty 2026-09-20
- [ ] "Podobne narzędzia" to tylko ta sama kategoria, nie prawdziwe podobieństwo

---

## 🎨 Audyt UX katalogu (2026-09-13) — aktualizacja

**Punkt 1 (hero strony głównej) — rozwiązany.**

**Punkt 2 (RODO/DPA jako fałszywy negatyw) — zamknięty, Wariant B (pełna naprawa modelu danych):**
- `db/migrations/002_tri_state_compliance_fields.sql` — rodo_compliant/dpa_available/
  eu_data_hosting: BOOLEAN nullable (NULL = nie zweryfikowano). Backfill i trigger
  `promote_scrape_to_tools()` zaktualizowane.
- Panel admina — tri-state UI (select) na formularzu dodawania + inline-edit
  w zakładce "Narzędzia" dla wszystkich trzech pól.
- `[slug].astro` — kafelki "Zgodność i dane" + FAQ rozróżniają 3 stany.

**Nowy punkt backlogu (znaleziony przy okazji, nie w zakresie tej pracy):**
- [ ] Meta Pagefind (`rodo:`/`eu_hosting:` w `data-pagefind-filter`) zlewa `false`/`null`
  w jedno `'false'` — do naprawy razem z "kolejne wymiary filtrowania w UI (cennik, AI Act)"
  jeśli/gdy powstanie filtr po statusie zgodności na `/narzedzia/`.

Pozostałe punkty audytu: ceny PLN, badge AI Act i wyszukiwanie tekstowe — zamknięte 15.09
(patrz sekcja "Sesja UX 2026-09-13/15" wyżej). Nadal otwarte (niski priorytet): duplikacja
homepage/`/narzedzia/`, "Podobne narzędzia" tylko wg kategorii (social proof "Najpopularniejszy" na `/premium` — usunięty 20.09).

---

## 📋 Backlog

### 🗺️ Plan działań (ustalony 23.08, wciąż aktualny)

1. ✅ Nowe źródła NiFi (Priorytet #1) — zrobione (YC-OSS API)
2. **Faza 1 — Wznowienie growth** — LinkedIn (2 posty, drafty odświeżone i zapisane w Notion jako osobna podstrona) + cold outreach do firm z listy 100 narzędzi. Katalog urósł z 3 do 284 zatwierdzonych narzędzi, fundament techniczny ustabilizowany — naturalny moment na wznowienie.
   - ✅ Post 1 LinkedIn (RODO/AI Act) — opublikowany 19.09.2026
   - [ ] Post 2 LinkedIn — wciąż tylko draft w Notion
   - [ ] Cold outreach — nadal nie rozpoczęty
3. **Faza 2 — Monetyzacja etap 2** — AdSense po przekroczeniu 1000 UV/mc (obecnie realny ruch zewnętrzny wciąż daleko od progu); rozważyć 2-3 kolejne programy afiliacyjne
4. **Faza 3** — Newsletter, raport branżowy PDF (po ustabilizowaniu ruchu/bazy odbiorców)
5. **Faza 4 — Artykuły o AI** — świadomie odłożone (ryzyko szkodliwości błędów, praw autorskich, art. 50 ust. 4 AI Act, koszt czasowy); wrócić gdy ruch i jakość pipeline'u dojrzeją

### 🔵 Faza 4 audytu contentu — decyzja stylistyczna (Wzorzec B)
> Uwaga: to numeracja faz *audytu jakości języka* (13–19.09), niezależna od "Fazy 4 — Artykuły o AI" z planu działań powyżej.
- [ ] ~55 wpisów w stylu 1-zdaniowym bez nazwy narzędzia (konwencja ręcznych wpisów z sierpnia) — zdecydować: zostawić jako świadomy styl czy ujednolicić wg `CONTENT-GUIDE.md`. Niska pilność, decyzja stylistyczna, nie bug

### 🟡 Inne otwarte punkty
- [ ] Pozostałe ~37 oflagowanych URL-i z audytu `website_url` (głównie HTTP 403 / konsolidacje domen, niska pilność)
- [ ] Kwartalny audyt URL (`url-audit.yml`): skrypt nie powiadamia o flagowanych wpisach (job kończy się zielono) — po każdym przebiegu przejrzeć wyniki w `admin/logs.php` (source=`url_audit`) lub w artefakcie CSV. Skrypt nie odróżnia nowych flag od znanych, więc ~37 obecnych będzie wracać co kwartał; rozważyć dedup/`last_checked` dopiero gdy to zacznie przeszkadzać
- [ ] **Weryfikacja pierwszego przebiegu `url-audit.yml` (1.10.2026 lub najbliższa okazja ręcznego odpalenia).** **Właściciel: sesja techniczna z Claude Code (lub Pablo ręcznie) — NIE sesja strategiczna/analityczna.** Po pierwszym rzeczywistym uruchomieniu (zaplanowanym lub przez `workflow_dispatch`) sprawdzić w `admin/logs.php` (source=`url_audit`) albo w artefakcie CSV, czy mechanizm faktycznie zadziałał end-to-end na żywym przebiegu, nie tylko przy wcześniejszych testach ręcznych. Ten sam wzorzec weryfikacji co przy pierwszym rzeczywistym teście kroku czyszczącego w `deploy.yml` (09.09.2026)
- [ ] Amorphic Labs — możliwa nazwa firmy zamiast nazwy produktu; sprawdzić, czy nazwa w bazie powinna brzmieć "AgentMuxer"
- [ ] Brainware — decyzja ws. opisu (URL/nazwa/kategoria/cennik/logo już poprawione; nowy opis poprawny co do firmy, ale bez polskiego kontekstu z oryginału)
- [ ] Surfer SEO i Woodpecker.co — obie polskie firmy z Wrocławia (Woodpecker notowany na GPW: WPR), żaden opis w bazie tego nie wspomina — rozważyć ręczne dopisanie przez modal "Edytuj"
- [x] Badge "Najpopularniejszy" na `/premium` — usunięty 2026-09-20 (`1a4b3b9`)
- [ ] Podpięcie wypłat Stripe/PayPal w PartnerStack (ręcznie, Pablo)
- [ ] Newsletter, raport branżowy PDF, konta premium@/newsletter@
- [ ] Rozważyć rozszerzenie YC-OSS o dodatkowe tagi (`saas.json`, `b2b.json`) jeśli sam tag AI okaże się za wąski/za szeroki
- [ ] Rozważyć zawężenie okna `launched_at` z 90 do 30 dni po ocenie jakości pierwszej partii
- [ ] Refaktoryzacja stopki do współdzielonego komponentu `Footer.astro` (obecnie zduplikowana w 6 plikach)
- [ ] Regularnie przeglądać "Odrzucone przez AI" pod kątem fałszywych negatywów

---

## 🗂 Stack techniczny

| Warstwa | Technologia | Uwagi |
|---|---|---|
| Frontend | Astro 6 + Tailwind CSS v4 | SSG, 10 ikon kategorii SVG duotone |
| ETL / Scraping | Apache NiFi 2.9.0 | Lokalnie Windows, **4 źródła** (HN/BetaList/Product Hunt/YC-OSS API), dwuwarstwowy filtr jakości |
| AI opisy (pipeline) | OpenAI gpt-4o-mini | Zwraca best_for_pl, is_real_product; YC branch ma dodatkowy source_context |
| AI weryfikacja (admin) | OpenAI gpt-4o-mini | `response_format: json_object`, kategoria AI Act hint dla 7/10 kategorii, logo fix wdrożony, reguły jakości języka (wg `CONTENT-GUIDE.md`), cytaty RODO/DPA/UE jako evidence (bez oceny) |
| Baza danych | Supabase PostgreSQL free | eu-central-1; 10 kategorii; trigger `promote_scrape_to_tools()` |
| Admin panel | PHP + Supabase REST API | /admin/index.php, /admin/affiliate.php, /admin/verify_tool.php, /admin/logs.php |
| Hosting | Cyberfolks (LiteSpeed) | Frontend + PHP admin + webhook |
| CDN / ochrona | Cloudflare | SSL Full, Redirect Rules (www→apex) |
| CI/CD | GitHub Actions | Auto-deploy, workflow_dispatch, SCP całego admin/ |
| Płatności | Stripe (live mode) | checkout.php + webhook.php |
| Affiliate | PartnerStack (ClickUp) | affiliate_links + admin/affiliate.php |
| Email | PHPMailer + Cyberfolks SMTP | kontakt@aifirmy.pl |
| Analytics | Search Console (primary) + AWStats | GA4 aktywny ale niewiarygodny (adblocki) |

---

## ⚠️ Quirki techniczne — do zapamiętania

| Problem | Rozwiązanie |
|---|---|
| NiFi ReplaceText: `\n` → literalne `n`, `\"` gubi backslash | System message jedną linią, bez cudzysłowów |
| InvokeHTTP connection pool cache | Stop flow 30s → Start po zmianie konfiguracji |
| `nifi.cmd status` fałszywy alarm | Sprawdzić `java -version`, logi przed założeniem awarii |
| Repo ma prefiks `frontend/` dla kodu Astro | Zawsze `git add frontend/src/...` |
| PowerShell + nazwy plików z `[...]` | Cudzysłowy wokół ścieżki w `git add` |
| `contains('x')` w NiFi EL dla krótkich słów | Podciąg, nie całe słowo — użyj `matches('(?i).*\bx\b.*')` |
| PHP `strict_types` + niepewny JSON z AI | `is_string()` guard przed `trim()`/`mb_strtolower()` |
| PHP `try/catch (Exception)` nie łapie `TypeError` | Łapać `\Throwable` |
| Cichy 502 bez logów PHP | Checkpointy czasowe + `register_shutdown_function` |
| Nowa wartość enum w NiFi/triggerze bez CHECK constraint | Zawsze zweryfikować `pg_get_constraintdef` przed dodaniem nowej wartości |
| Usunięcie "nieużywanego" importu bez sprawdzenia efektów ubocznych | `@astrojs/sitemap` wyglądał martwo, generował pliki jako efekt uboczny builda |
| `grep -c` na zminifikowanym pliku w jednej linii | Liczy pasujące LINIE nie wystąpienia — użyj `grep -o ... \| wc -l` |
| Weryfikacja zmian zaraz po deployu | Dodaj `?cachebust=X`, Cloudflare cache potrzebuje chwili nawet po Purge |
| Regex w PHP wymagający konkretnej kolejności atrybutów HTML | HTML nie wymusza kolejności — ekstrahuj cały tag, potem dopasuj atrybuty osobno |
| Brak www→apex redirect mimo poprawnego `<link rel="canonical">` | Canonical tag nie zastępuje twardego 301 — potrzebna osobna reguła CDN/serwera |
| Cloudflare "DNS may not be proxying" ostrzeżenie mimo poprawnej konfiguracji | Zweryfikować bezpośrednio w DNS → Records przed zaufaniem ostrzeżeniu UI |
| GA4 pokazuje zero mimo realnego ruchu | Sprawdzić czy `gtag.js` faktycznie wysyła beacon (nie tylko czy się ładuje) — może być blokowany w trybie "stub" przez adblocki; Search Console/AWStats jako niezależna weryfikacja |
| `git push` rejected (fetch first) przy pracy na 2 komputerach | Standardowe: `git pull --rebase && git push`; zawsze `git log --oneline` na remote przed dalszą pracą jeśli coś niepokoi |

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
| Notion — drafty LinkedIn | https://app.notion.com/p/3c5b4cccb4af81a08f4cd24070dffab9 |
| AWStats (Cyberfolks) | https://s103.cyber-folks.pl:2223/CMD_AWSTATS/aifirmy.pl/index.html |
| Keep-alive repo | https://github.com/siwy126Pablo/aifirmy-ping |
| Stripe Dashboard | https://dashboard.stripe.com |
| PartnerStack | https://partnerstack.com |
