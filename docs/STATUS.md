# 📊 STATUS.md — aifirmy.pl
> Ostatnia aktualizacja: 2026-09-05

---

## 🚦 Status ogólny

| Element | Status |
|---|---|
| **Faza** | Projekt live, generujący przychód. Katalog urósł z ~90 do ~260+ narzędzi. Ruch organiczny rośnie tydzień do tygodnia. 4. źródło danych (YC-OSS) + 10. kategoria wdrożone. |
| **Domena** | ✅ aifirmy.pl (Cyberfolks) + www→apex redirect (Cloudflare) |
| **Hosting** | ✅ Aktywny — Cyberfolks + Cloudflare |
| **Baza danych** | ✅ Supabase PostgreSQL (eu-central-1), 10 kategorii |
| **Pipeline NiFi** | ✅ 4 źródła: HN + BetaList + Product Hunt + YC-OSS API, dwuwarstwowy filtr jakości |
| **Frontend** | ✅ Kafle kategorii (10), ikony, trust badge'e, rozszerzone FAQ (RODO/DPA/EU/AI Act), AI-content disclosure |
| **Cloudflare** | ✅ SSL Full, CDN, DNS, Redirect Rules (www→apex) |
| **Panel admina** | ✅ PHP + Supabase REST API, "Odrzucone przez AI", "Zweryfikuj przez AI" (logo fix wdrożony) |
| **Monetyzacja** | ✅ Stripe Live mode, checkout + webhook, email po zakupie |
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

Konsekwentny, przyspieszający wzrost. Ciekawy wzorzec: strony narzędzi z tytułem zawierającym starą + nową nazwę po rebrandingu (np. "Brevo (dawniej Sendinblue)") notują nieproporcjonalnie duży wzrost wyświetleń — możliwy sygnał do świadomego stosowania przy innych narzędziach po zmianie nazwy.

**Pierwszy zaobserwowany zewnętrzny backlink** (23.08): `piperic.com` linkuje do wpisu o Descript.

---

## 📋 Backlog

### 🗺️ Plan działań (ustalony 23.08, wciąż aktualny)

1. ✅ Nowe źródła NiFi (Priorytet #1) — zrobione (YC-OSS API)
2. **Faza 1 — Wznowienie growth** — LinkedIn (2 posty, drafty odświeżone i zapisane w Notion jako osobna podstrona) + cold outreach do firm z listy 100 narzędzi. Katalog urósł z 3 do 260+ narzędzi, fundament techniczny ustabilizowany — naturalny moment na wznowienie.
3. **Faza 2 — Monetyzacja etap 2** — AdSense po przekroczeniu 1000 UV/mc (obecnie realny ruch zewnętrzny wciąż daleko od progu); rozważyć 2-3 kolejne programy afiliacyjne
4. **Faza 3** — Newsletter, raport branżowy PDF (po ustabilizowaniu ruchu/bazy odbiorców)
5. **Faza 4 — Artykuły o AI** — świadomie odłożone (ryzyko szkodliwości błędów, praw autorskich, art. 50 ust. 4 AI Act, koszt czasowy); wrócić gdy ruch i jakość pipeline'u dojrzeją

### 🟡 Inne otwarte punkty
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
| AI weryfikacja (admin) | OpenAI gpt-4o-mini | `response_format: json_object`, kategoria AI Act hint dla 7/10 kategorii, logo fix wdrożony |
| Baza danych | Supabase PostgreSQL free | eu-central-1; 10 kategorii; trigger `promote_scrape_to_tools()` |
| Admin panel | PHP + Supabase REST API | /admin/index.php, /admin/affiliate.php, /admin/verify_tool.php |
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
