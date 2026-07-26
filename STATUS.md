# 📊 STATUS.md — aifirmy.pl
> Ostatnia aktualizacja: 2026-07-25

---

## 🚦 Status ogólny

| Element | Status |
|---|---|
| **Faza** | Projekt live — Pakiet poprawek 2 zakończony (UX + jakość pipeline'u + nowa funkcja admina) |
| **Domena** | ✅ aifirmy.pl (Cyberfolks) |
| **Hosting** | ✅ Aktywny — Cyberfolks + Cloudflare |
| **Baza danych** | ✅ Supabase PostgreSQL (eu-central-1) |
| **Pipeline NiFi** | ✅ 3 źródła: HN + BetaList + Product Hunt, cron 2:00/3:00/4:00 — z dwuwarstwowym filtrem jakości |
| **Frontend** | ✅ Wdrożony, GitHub Actions auto-deploy, kafle kategorii + ikony + trust badge'e + FAQ + podobne narzędzia |
| **Cloudflare** | ✅ SSL Full, CDN, DNS |
| **Panel admina** | ✅ PHP + Supabase REST API + soft delete + zakładka "Odrzucone przez AI" + "Zweryfikuj przez AI" |
| **Monetyzacja** | ✅ Stripe Live mode, checkout + webhook, email po zakupie |
| **Affiliate** | ✅ ClickUp/PartnerStack aktywny, link + disclosure na produkcji |
| **Analytics** | ✅ Google Analytics 4 + Google Search Console |

---

## ✅ Tydzień 1 — Strategia i nisza

- Nisza: katalog narzędzi AI dla polskiego B2B, opisy po polsku, zgodność z RODO i AI Act, ceny w PLN
- 9 kategorii: Automatyzacja procesów, Analityka i BI, Finanse i księgowość, HR i rekrutacja, Marketing i content, Obsługa klienta, Prawo i compliance, Sprzedaż i CRM, Zarządzanie projektami
- Lista 100 narzędzi, analiza konkurencji, stack ustalony (ADR-001 do ADR-008), repo na GitHubie

---

## ✅ Tydzień 2 — Baza danych

- 6 tabel na Supabase free (eu-central-1): `tools`, `categories`, `tags`, `tool_tags`, `premium_listings`, `scrape_queue`
- Indeksy GIN, trigger `updated_at`, full-text search po polsku, keep-alive GitHub Actions
- Trigger `promote_scrape_to_tools()` — automatyczne przenoszenie z `scrape_queue` do `tools`

---

## ✅ Tydzień 3 — Pipeline NiFi

**Flow end-to-end (wersja po Pakiecie poprawek 2, 2026-07-19):**

```
[Źródło 1 — Hacker News, cron 2:00]
GenerateFlowFile
  → InvokeHTTP (HN topstories — 500 ID)
  → SplitJson (1 flowfile per ID)
  → InvokeHTTP (HN item details)
  → EvaluateJsonPath (pre-filter: hn_url, hn_type)
  → RouteOnAttribute (filtr URL — blokuje GitHub, newsy, blogi: "przepusc")
  → EvaluateJsonPath (hn_time, hn_type, hn_url, hn_title...)
  → RouteOnAttribute (filtr daty — ostatnie 24h: "nowe_i_story")
  → RouteOnAttribute (filtr Show HN/Launch HN/YC batch — "show_launch_hn") 🆕
  → [dalej HN-only, przed połączeniem z BetaList/PH]

[Źródło 2 — BetaList, cron 3:00]
GenerateFlowFile → InvokeHTTP (feeds.feedburner.com/BetaList) → SplitXml
  → RouteOnContent (<entry>) → EvaluateXPath → UpdateAttribute (normalizacja)
  → RouteOnAttribute (filtr daty)

[Źródło 3 — Product Hunt, cron 4:00]
GenerateFlowFile → InvokeHTTP (producthunt.com/feed) → SplitXml
  → RouteOnContent (<entry>) → EvaluateXPath → UpdateAttribute (normalizacja)
  → RouteOnAttribute (hn_type=story)

[Punkt połączenia wszystkich 3 źródeł]
  → RouteOnAttribute (filtr słów kluczowych — "pasuje": ai/tool/saas/launch/llm/gpt/model/open source) 🔧 naprawiony
  → ExecuteSQL (deduplikacja) → RouteOnAttribute (nowy rekord?)

[Wspólna ścieżka OpenAI]
  → ReplaceText (buduje request — prompt zwraca też best_for_pl, is_real_product) 🆕
  → InvokeHTTP (OpenAI gpt-4o-mini)
  → EvaluateJsonPath (ai_response = $.choices[0].message.content)
  → EvaluateJsonPath (ai_category, ai_description, ai_name, ai_pricing_model, ai_segment, ai_best_for_pl, ai_is_real_product) 🆕
  → ReplaceText (buduje INSERT JSON — stage warunkowy: 'ai_rejected' vs 'ai_done') 🆕
  → PutDatabaseRecord (INSERT → scrape_queue)
```

**⚠️ Dwuwarstwowy filtr jakości (Pakiet poprawek 2, 2026-07-19):**

1. **Filtr Show HN/Launch HN/YC batch** (gałąź HN, przed połączeniem z innymi źródłami) — przepuszcza tylko tytuły "Show HN:"/"Launch HN:" lub ze wzorcem `(YC XNN)`. Drastycznie redukuje wolumen z HN (zaobserwowane: 2/500 w jednej paczce), wysoka trafność potwierdzona na żywo (Bribes.fyi, PilotCite — oba realne produkty).
2. **Pole `is_real_product`** w odpowiedzi OpenAI + warunkowy `stage` (`ai_rejected` vs `ai_done`) przed zapisem — model ma legalną możliwość oznaczenia nie-produktu zamiast zmyślania opisu.

**🐛 Krytyczny bug znaleziony i naprawiony przy okazji:** wspólny filtr słów kluczowych używał `contains('ai')` zamiast dopasowania całego słowa — przepuszczał każdy tytuł zawierający podciąg "ai" w dowolnym słowie (**br-AI-n**, expl**AI**n, maint**AI**n, itd.). Naprawione na `matches('(?i).*\bai\b.*')`. Prawdopodobnie **główne** źródło szumu w pipeline, większe niż brak filtra Show HN/Launch HN — potwierdzone realnym przykładem: artykuł "Heavy TV Watching Impact" przeszedł ten filtr wyłącznie dzięki słowu "br**ai**n".

**Kolumny scrape_queue (rozszerzone):**
- `name`, `ai_pricing_model`, `ai_category` — z T5.5
- `best_for_pl` 🆕 — jednozdaniowy opis grupy docelowej
- `stage` — teraz też wartość `ai_rejected` obok `ai_done`/`published` 🆕

**Quirki:**
- NiFi ReplaceText: `\n` → literalne `n`, cudzysłowy gubią backslash — prompt jedną linią bez cudzysłowów
- `EvaluateJsonPath` nie obsługuje tablic → tagi w `ai_response` jako JSONB
- Atom XML z namespace (BetaList/PH) — XPath wymaga `local-name()`
- `nifi.cmd status` może pokazać "Management Server communication failed" mimo że proces żyje — sprawdzić `nifi-app.log`/`nifi-bootstrap.log` i `java -version` przed założeniem awarii
- Filtr URL i filtr Show HN/Launch HN żyją **tylko na gałęzi HN**; filtr słów kluczowych żyje **po** połączeniu źródeł — zmiana w złym miejscu może przypadkowo zablokować BetaList/Product Hunt

---

## ✅ Tydzień 4-6 — Frontend, monetyzacja, growth

- Astro 6 + Tailwind CSS v4, statyczny SSG, deploy GitHub Actions
- Panel admina PHP, Stripe Live mode (4 pakiety), email po zakupie, GA4 + Search Console
- BetaList + Product Hunt jako 2./3. źródło NiFi, cookie consent, formularz kontaktowy

---

## ✅ Tydzień 7 — Linki afiliacyjne (lipiec 2026)

- Tabela `affiliate_links`, panel `admin/affiliate.php`, ClickUp/PartnerStack aktywny na produkcji, disclosure na kartach i detalu
- **Temat zamknięty (ADR-009).** Jedyne co zostało poza zakresem Claude: wypłaty w PartnerStack (ręcznie, Pablo)

---

## ✅ Pakiet poprawek 2 (2026-07-18 → 2026-07-19) — inspiracja: audyt FindYourAI

Wszystkie 10 zadań zamknięte. Pełna historia realizacji: Notion, strona statusu projektu.

**Frontend / UX:**
1. **Trust badge RODO/AI Act** — pigułki na karcie i detalu, kolor wg poziomu ryzyka
2. **FAQ szablonowe** na stronie narzędzia — 4 pytania z podstawieniem zmiennych (bez AI), schema.org FAQPage JSON-LD
3. **Sekcja "Podobne narzędzia"** — SQL po category_id + fallback po tool_tags, max 3
4. **Pole `best_for_pl`** — patrz sekcja NiFi wyżej; wyświetlane na karcie i detalu
5. **Kafle kategorii na stronie głównej** — grid 3×3 pod hero, licznik narzędzi per kategoria
6. **9 niestandardowych ikon kategorii** — duotone, jeden kolor per kategoria (zaprojektowane iteracyjnie w rozmowie)
7. **Większy panel karty** — `line-clamp-3` → `line-clamp-6`, świadomie zaakceptowany jeden wyjątek (TunnelMind, ~305 zn.)
8. **Logo narzędzi** — hybryda: auto-favicon (add_tool + trigger SQL) + ręczne nadpisanie + placeholder z inicjałem. Backfill dla 61 istniejących narzędzi.

**Pipeline (NiFi + SQL):** patrz sekcja "Tydzień 3" — filtr Show HN/Launch HN, naprawiony filtr słów kluczowych, `is_real_product` + routing `ai_rejected`.

**Panel admina — nowa funkcja:**
9. **"Zweryfikuj przez AI"** — przycisk per narzędzie. Live fetch `website_url` → ekstrakcja treści → gpt-4o-mini (`response_format: json_object`) → modal z diff → zatwierdzenie zaznaczonych pól → UPDATE. `rodo_compliant` wykluczone z AI (tylko ręczna edycja). Klucz w `private_html/config/openai.php` na serwerze.
10. **Zakładka "Odrzucone przez AI"** — wpisy `stage='ai_rejected'`, akcje Usuń trwale / Przenieś do kolejki, licznik + banner.

**Dodatkowe bugi znalezione i naprawione przy weryfikacji na żywo:**
- Zdublowany lokalny `<header>` na stronie narzędzia (leftover po migracji na wspólny Layout)
- Zgubiony prop `logoUrl` na stronie kategorii (obecny w roboczym drzewie, zgubiony przy commicie)
- `is_array($ai)` w `verify_tool.php` przepuszczające niestringowy `category` z AI → nieuchwycony `TypeError` pod `strict_types` → cichy 502 bez logów. Naprawione: `is_string()` guard, `try/catch (\Throwable)`, `register_shutdown_function`, `response_format: json_object`.

**Rozjazdy dokumentacji z rzeczywistością znalezione przy okazji:**
- `promote_scrape_to_tools()` to trigger Postgresa w Supabase, nie kod w repo
- Filtr "słów kluczowych" opisany jako istniejący od T5.5 w praktyce działał wadliwie (`contains` zamiast granicy słowa)

---

## 🐛 Uzupełnienie 2026-07-23 — przeoczony CHECK constraint blokował `ai_rejected`

Kilka dni po zamknięciu Pakietu poprawek 2, pierwszy realny wpis z `is_real_product: false` (BetaList: "DataEase AI") ujawnił błąd w `PutDatabaseRecord`: `ERROR: new row for relation "scrape_queue" violates check constraint "scrape_queue_stage_check"`.

**Przyczyna:** przy implementacji Zadania 10 zaktualizowano trigger `promote_scrape_to_tools()` i logikę NiFi (routing `ai_rejected` vs `ai_done`), ale nie sprawdzono samego `CHECK` constraintu na kolumnie `stage`. Oryginalna lista dozwolonych wartości (`scraped`, `ai_pending`, `ai_done`, `mod_pending`, `published`, `rejected`) nigdy nie zawierała `ai_rejected`.

**Naprawa:**
```sql
ALTER TABLE scrape_queue DROP CONSTRAINT scrape_queue_stage_check;
ALTER TABLE scrape_queue ADD CONSTRAINT scrape_queue_stage_check 
CHECK (stage = ANY (ARRAY['scraped'::text, 'ai_pending'::text, 'ai_done'::text, 'ai_rejected'::text, 'mod_pending'::text, 'published'::text, 'rejected'::text]));
```

**Efekt uboczny:** w okresie między wdrożeniem Zadania 10 a tą poprawką, wpisy oznaczone przez AI jako `is_real_product: false` prawdopodobnie w ogóle nie zapisywały się do `scrape_queue` (błąd INSERT je odrzucał) — zakładka "Odrzucone przez AI" mogła być pusta nie dlatego, że model nic nie odrzucił, tylko dlatego że odrzucone wpisy nie trafiały do bazy wcale.

Po naprawie potwierdzono w panelu admina: 8 pozycji w zakładce "Odrzucone przez AI". **Do zrobienia:** przejrzeć je pod kątem fałszywych negatywów — "DataEase AI" (narzędzie do analizy danych) wygląda jak prawdziwy produkt błędnie odrzucony, nie tylko halucynacje na "false" są ryzykiem, ale też błędne "false" dla realnych narzędzi.

---

## 🐛 Uzupełnienie 2026-07-23 (druga sesja) — audyt ruchu ujawnił martwą integrację sitemap

Przy ocenie ruchu strony (Google Search Console + GA4) okazało się, że mimo ~6-7 tygodni działania strony, ruch organiczny jest praktycznie zerowy (4 kliknięcia łącznie z wyszukiwania, 17 aktywnych użytkowników w 30 dni), a Search Console pokazywał tylko **59 zindeksowanych stron** i **37 niezindeksowanych** (w tym 14 sklasyfikowanych jako "strona zawiera przekierowanie").

**Rzeczywista przyczyna (znacznie poważniejsza niż przekierowania):** sitemapa (`sitemap-0.xml`) zawierała tylko **16 adresów URL** — stronę główną, 9 kategorii, kontakt/dziękujemy/premium/polityka-prywatności, i **tylko 3 narzędzia (Make, n8n, Rossum — oryginalny seed z Tygodnia 2)**, mimo że w bazie było już ~79 zatwierdzonych narzędzi, wszystkie poprawnie budujące się jako osobne strony.

**Root cause:** integracja `@astrojs/sitemap` została przypadkowo usunięta z `astro.config.mjs` w commicie `d0639c1` ("refactor: remove unused sitemap integration") — autor tej zmiany błędnie uznał import za martwy kod, nie zauważając, że to efekt uboczny w trakcie builda (zapisuje pliki `sitemap-*.xml`), a nie coś referencjonowane gdzie indziej w kodzie. Od tego commitu `astro build` w ogóle nie generował sitemapy. Stary plik z 16 URL-ami, który znaleźliśmy, był artefaktem sprzed tego commita (czerwiec, gdy baza miała tylko dane seed) — `dist/` jest w `.gitignore`, więc regresja nigdy nie ujawniła się w historii gita.

**Naprawa:** przywrócone 2 linijki w `astro.config.mjs` (import + `integrations: [sitemap()]`). Po deployu sitemapa zawiera **93 URL-e** (79 stron narzędzi + reszta), zweryfikowane bezpośrednio na produkcji.

**Efekt uboczny do zapamiętania:** to prawdopodobnie **główna przyczyna** słabej widoczności w Google przez cały okres od uruchomienia strony — ważniejsza niż jakikolwiek pojedynczy element SEO (trust badge, FAQ, schema.org), bo bez kompletnej sitemapy Google nie miał pełnego sygnału o istnieniu większości stron do zaindeksowania.

**Do zrobienia po stronie Pabla:** w Search Console → Mapy witryn, ponownie zgłosić `sitemap-index.xml`, żeby przyspieszyć ponowne pobranie zamiast czekać na naturalny harmonogram Google. Efekt (wzrost zindeksowanych stron, potencjalnie ruchu) spodziewany w ciągu kilku dni do paru tygodni, nie natychmiast.

**Mniejsza, dodatkowa obserwacja (nie wymaga akcji):** 14 stron sklasyfikowanych jako "zawiera przekierowanie" w Search Console to efekt standardowego zachowania Astro — strony narzędzi mają `canonical` z ukośnikiem na końcu (`/narzedzia/make-integromat/`), a Google odkrył też warianty bez ukośnika. Nieszkodliwe, samo się ureguluje po ponownym crawlowaniu.

---

## 🐛 Uzupełnienie 2026-07-25 — drobne poprawki panelu admina i deployu

**Pole `ai_verified_at` w `tools`** — nowa kolumna (`TIMESTAMPTZ`, nullable) śledząca datę ostatniej zatwierdzonej weryfikacji przez funkcję "Zweryfikuj przez AI" (Zadanie 9). Zapisywana wyłącznie gdy użytkownik faktycznie zatwierdzi i zapisze przynajmniej jedną zmianę (nie przy samym uruchomieniu weryfikacji) — zapobiega niepotrzebnemu powtarzaniu weryfikacji tych samych narzędzi. Wyświetlana w zakładce "Narzędzia" jako "Sprawdzono: DD.MM.RRRR" obok przycisku weryfikacji, aktualizowana w miejscu (bez przeładowania strony), zapisywana atomowo w tym samym PATCH co inne zatwierdzone pola.

**Połączenie GA4 ↔ Search Console** — skonfigurowane bezpośrednio w interfejsie Google Analytics (Admin → Połączenia usług → Search Console), właściwość domenowa `aifirmy.pl` połączona ze strumieniem danych z sieci `aifirmy.pl`. Umożliwia raporty łączące zapytania wyszukiwania z zachowaniem użytkowników w GA4 bez przełączania się między dwoma panelami. Czysto konfiguracyjne, nic w kodzie repo.

**`workflow_dispatch` w `.github/workflows/deploy.yml`** — dodany trigger umożliwiający ręczne uruchomienie deployu z zakładki GitHub Actions ("Run workflow"), obok istniejącego triggera `push`. Rozwiązuje dwa problemy: (1) zastępuje dotychczasowy trik `git commit --allow-empty` przy zmianach dotykających tylko bazy danych (np. SQL backfill), (2) eliminuje ryzyko pomyłkowego "Re-run" starego, nieaktualnego runu zamiast zbudowania świeżego stanu `main` — `workflow_dispatch` zawsze buduje z aktualnego stanu wybranej gałęzi. Zero nowych sekretów — autoryzacja przez własne logowanie GitHub, nie token na serwerze.

**Rozważone i odrzucone:** przycisk w panelu PHP wyzwalający deploy przez GitHub API (workflow_dispatch przez REST) — wymagałby trzymania GitHub Personal Access Tokena na serwerze Cyberfolks, co rozszerzyłoby powierzchnię ataku panelu admina (już przechowuje klucz OpenAI) o token z realną mocą wyzwalania CI/CD. Świadomie wybrano bezpieczniejszą opcję — ręczne uruchomienie z poziomu GitHub UI zamiast z panelu.

---

## 📋 Backlog

### 🟡 Growth (zawieszone ~miesiąc)
- [ ] LinkedIn — 2 posty (drafty gotowe)
- [ ] Cold outreach do 20 firm

### 🔵 Później
- [ ] Podpięcie wypłat Stripe/PayPal w PartnerStack (ręcznie, Pablo)
- [ ] AdSense gdy ruch > 1000 UV/mc
- [ ] Newsletter, raport branżowy PDF
- [ ] Konta pocztowe: premium@ i newsletter@
- [ ] Nazwanie opisowe dwóch procesorów NiFi (generyczne domyślne nazwy — kosmetyka)
- [ ] Obserwować skuteczność filtra `is_real_product` — regularnie przeglądać "Odrzucone przez AI" pod kątem fałszywych negatywów

---

## 🗂 Stack techniczny

| Warstwa | Technologia | Uwagi |
|---|---|---|
| Frontend | Astro 6 + Tailwind CSS v4 | SSG, 9 ikon kategorii SVG duotone |
| ETL / Scraping | Apache NiFi 2.9.0 | Lokalnie Windows, 3 źródła, dwuwarstwowy filtr jakości |
| AI opisy (pipeline) | OpenAI gpt-4o-mini | Zwraca też best_for_pl, is_real_product |
| AI weryfikacja (admin) | OpenAI gpt-4o-mini | `response_format: json_object`, klucz w `private_html/config/openai.php` |
| Baza danych | Supabase PostgreSQL free | eu-central-1; trigger `promote_scrape_to_tools()` |
| Admin panel | PHP + Supabase REST API | /admin/index.php, /admin/affiliate.php, /admin/verify_tool.php |
| Hosting | Cyberfolks (LiteSpeed) | Frontend + PHP admin + webhook |
| CDN / ochrona | Cloudflare | SSL Full |
| CI/CD | GitHub Actions | Auto-deploy, SCP całego admin/ |
| Płatności | Stripe (live mode) | checkout.php + webhook.php |
| Affiliate | PartnerStack (ClickUp) | affiliate_links + admin/affiliate.php |
| Email | PHPMailer + Cyberfolks SMTP | kontakt@aifirmy.pl |
| Analytics | Google Analytics 4 | G-3SP1TRXF7M |

---

## ⚠️ Quirki techniczne — do zapamiętania

| Problem | Rozwiązanie |
|---|---|
| NiFi ReplaceText: `\n` → literalne `n` | System message jako jedna linia bez przełamań |
| NiFi ReplaceText: `\"` gubi backslash | Usuń cudzysłowy z system message |
| InvokeHTTP connection pool cache | Stop flow 30s → Start po zmianie konfiguracji |
| `nifi.cmd status` — komunikat o błędzie komunikacji | Sprawdzić `java -version`, logi — proces mógł nie dojść do pełnego startu |
| ESET blokuje node_modules | Projekt w `C:\Dev\` zamiast `C:\Users\` |
| Cloudflare WAF blokuje POST /admin/ | Webhook w `/stripe/webhook.php` poza /admin/ |
| Supabase Direct Connection wymaga IPv6 | Session Pooler (port 5432) |
| Cyberfolks brak pdo_pgsql | Supabase REST API przez curl w PHP |
| PowerShell + nazwy plików z `[...]` | Cudzysłowy wokół ścieżki w `git add`, nie backslash |
| Repo ma prefiks `frontend/` dla kodu Astro | Zawsze `git add frontend/src/...` |
| Wieloczęściowe sesje Claude Code | `git status` PRZED commitem — złapaliśmy zgubiony prop i zdublowany header dzięki wizualnej weryfikacji, nie diffowi |
| Filtry NiFi na różnych poziomach flow | URL/Show HN — tylko gałąź HN; słowa kluczowe — współdzielone po połączeniu źródeł |
| `contains('x')` w NiFi EL dla krótkich słów | Sprawdza podciąg — użyj `matches('(?i).*\bx\b.*')` z granicą słowa |
| PHP `strict_types` + niepewny JSON z AI | `is_array()` nie gwarantuje stringa — `is_string()` guard przed `trim()`/`mb_strtolower()` |
| PHP `try/catch (Exception)` nie łapie `TypeError` | Łapać `\Throwable` — `TypeError`/`Error` go nie dziedziczą po `\Exception` |
| Cichy 502 bez logów PHP | Checkpointy czasowe + `register_shutdown_function` sprawdzający `error_get_last()` |
| OpenAI niezgodny JSON mimo instrukcji w prompcie | `response_format: {"type": "json_object"}` w payloadzie |
| Nowa wartość `stage` w NiFi/triggerze bez sprawdzenia CHECK constraintu | Zawsze zweryfikować `pg_get_constraintdef` dla kolumn z CHECK przed dodaniem nowej dozwolonej wartości gdziekolwiek indziej — sama zmiana w NiFi/triggerze nie wystarczy, INSERT zostanie odrzucony przez bazę |
| Usunięcie "nieużywanego" importu bez sprawdzenia efektów ubocznych | `@astrojs/sitemap` w `astro.config.mjs` wyglądał na martwy import (nigdzie nie referencjonowany w kodzie), ale generuje pliki przy buildzie jako efekt uboczny — usunięcie go po cichu wyłączyło całą sitemapę na tygodnie, niewykryte bo `dist/` jest gitignored |
| `grep -c 'wzorzec'` na zminifikowanym pliku XML w jednej linii | `-c` liczy pasujące LINIE, nie wystąpienia — dla pliku bez łamań linii zawsze zwróci 1 niezależnie od liczby dopasowań w tej linii; do liczenia wystąpień użyj `grep -o 'wzorzec' plik \| wc -l` |
| Weryfikacja zmian na produkcji zaraz po deployu | Cloudflare cache/propagacja może potrzebować chwili nawet po "Purge Everything" — dodaj `?cachebust=X` do URL-a przy sprawdzaniu w przeglądarce, żeby ominąć cache po stronie klienta |

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
