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

## ADR-011 — Kierunek projektu po ocenie growth
**Data:** 10.10.2026
**Status:** ✅ Przyjęte

**Kontekst:**
Plan z 23.08 (potwierdzony w sesji strategicznej 13.09) stawiał na Fazę 1: LinkedIn + cold outreach. Wynik po ~3 tygodniach:
- Post 1 (19.09): 112 wyświetleń, 4 reakcje, 0 komentarzy poza własnym
- Post 2 (08.10): 45 wyświetleń, 0 reakcji, 0 komentarzy poza własnym
- Cold outreach: 12 firm (+4 follow-upy), 1 odpowiedź (Vespper)
- Feedback od użytkowników katalogu: 0
- Ruch organiczny (GSC, okno 28 dni do 19.09): 11 kliknięć, 1 751 wyświetleń (+86% względem poprzedniego okna)

Featured to płatna widoczność — bez ruchu nie ma produktu do sprzedania. Wąskim gardłem jest ruch na stronie, nie dystrybucja. Profil LinkedIn (122 obserwujących, głównie branża danych) nie dociera do klientów Featured (dostawców narzędzi) ani do ich użytkowników.

**Decyzja:**
1. Główny czas → katalog: nowe wpisy i jakość istniejących stron (dane RODO/DPA/hosting, opisy) + tanie poprawki SEO (title, meta). Bez rozwoju nowych funkcji.
2. Wyjątek od pkt 1: naprawy jakości danych (np. prompt pipeline'u w scraper/lib/openai.php bez polskich znaków).
3. LinkedIn nie jest kanałem growth. Posty tylko przy konkretnej okazji, bez dedykowanej pracy graficznej.
4. Outreach minimalny, bez stałego limitu tygodniowego. Cel: weryfikacja danych u dostawców i prośba o link do wpisu (backlink), nie sprzedaż.
5. Blokada developmentu z sesji strategicznej 13.09 — zakończona, zastąpiona pkt 1.

**Punkt kontrolny (~25.01.2027, przed możliwym startem sprzedaży 1.02.2027 — patrz ADR-010):**
Kliknięcia z Google Search Console, okno 28 dni.
- Poniżej 100 → Featured nie startuje; projekt w trybie utrzymania (wpisy, bez inwestycji czasu w rozwój).
- 100 lub więcej → przygotowanie startu Featured.

**Zastępuje:** priorytet Fazy 1 (LinkedIn + cold outreach) z planu 23.08 i decyzji roboczej 13.09.

---

## ADR-012 — RLS w Supabase wdrażane etapami; zapisy tylko przez serwer z service_role
**Data:** 27.10.2026
**Status:** ✅ Przyjęte i wdrożone (etapy 1–4, ostatni 29.10.2026)

**Kontekst:**
Audyt 25.10: RLS wyłączone na 7 tabelach, rola `anon` z pełnymi uprawnieniami (łącznie z `DELETE`/`TRUNCATE`), klucz anon wstrzykiwany do JS panelu także przed zalogowaniem, a w repo brak klucza `service_role`. Włączenie RLS jednym ruchem zepsułoby stronę, panel, scraper i webhook Stripe.

**Decyzja:**
1. Przeglądarka nigdy nie pisze do Supabase. Zapisy panelu idą przez `admin/api.php` (sesja admina + token CSRF + whitelista operacji i pól).
2. Klucz `service_role` istnieje tylko po stronie serwera: `private_html/config/db.php` (panel, webhook) i sekret GitHub (scraper). Nigdy w repo, HTML ani JS. Do czasu dodania klucza kod działa na fallbacku anon, żeby wdrożenie kodu i dodanie klucza były osobnymi krokami.
3. `anon` dostaje wyłącznie odczyt publicznych danych (i INSERT do `activity_log`). Żadnych polityk zapisu dla `anon`.
4. Kolejność: słowniki (✅) → przygotowanie zapisujących (✅ kod) → `scrape_queue` → `premium_listings` → `affiliate_links` → `tools` + `search_path` funkcji. Każdy etap: próba w transakcji wycofanej z rolą `anon`, deploy, smoke test, advisors przed/po. RLS na tabelach zapisywanych przez panel dopiero po zielonym teście panelu.
5. Zapisy nie mogą ginąć po cichu: po włączeniu RLS bez polityki PostgREST zwraca 200 i zero wierszy, więc helpery traktują 0 zmienionych wierszy jako błąd.
6. SQL migracji: Pablo decyduje per zadanie, czy wykonuje je ręcznie w SQL Editorze, czy Claude Code przez MCP (zmiana z 26.10 w `CLAUDE.md`).

**Konsekwencje:**
Po dodaniu klucza panel, webhook i scraper nie zależą od RLS i można je włączać tabela po tabeli. Koszt: zapisy z panelu przechodzą przez Cloudflare i PHP (nowy punkt awarii), a formularze POST panelu nadal czekają na tokeny CSRF.

**Uzupełnienie 28.10 (etap 3):** tabele czytane przez build strony (`affiliate_links`, `premium_listings`) dostają politykę odczytu dla `anon` ograniczoną wierszami (`active`, `ends_at > now()`) **i** kolumnami (`GRANT SELECT (kolumny)` zamiast całej tabeli), żeby `commission_note`, `payment_id` i ceny nie były publiczne; build wskazuje kolumny jawnie, więc `select=*` rolą `anon` świadomie kończy się 401. Tabela bez konsumenta `anon` (`scrape_queue`) nie dostaje żadnej polityki, a `REVOKE ALL` zamyka też `TRUNCATE`. Fallback panelu na klucz anon przestaje być bezpieczną drogą: po etapie 3 klucz `service_role` w `db.php` jest wymagany.

**Uzupełnienie 29.10 (etap 4):** `tools` czyta publicznie wyłącznie wiersze `status = 'approved'` (polityka `tools_public_read`), całą tabelę, bo build używa `select('*')`; zawężenie kolumn zostaje opcją na później (wymaga zmiany czterech stron `.astro`). Konsekwencja: wpisy `rejected` przestają być publicznie czytelne przez REST. `activity_log` nie ma już żadnej polityki ani GRANT-ów dla `anon`: zapis robią `scraper/lib/logging.php` i `verify_tool.php`, odczyt `admin/logs.php`, wszystko na `service_role`, a punkt 3 decyzji ("INSERT do `activity_log`") jest tym samym nieaktualny. Funkcje mają ustawiony `search_path` (`''` w `set_updated_at`, `slugify`; `public, pg_temp` w `promote_scrape_to_tools`). Na tabelach bez polityk (`scrape_queue`, `activity_log`) advisors pokazuje INFO `rls_enabled_no_policy` — zamierzone.

**Uzupełnienie 31.10 (sesja admina):** flagi ciasteczka sesji ustawia jawnie `admin/session_init.php` (`admin_session_start()` zamiast `session_start()` w 5 plikach panelu): `HttpOnly`, `Secure`, `SameSite=Lax`, `path=/`. Powód: produkcyjny `PHPSESSID` nie miał `HttpOnly`, a stoi na całym hoście `aifirmy.pl`, więc był czytelny dla każdego skryptu na publicznej stronie. `Lax`, nie `Strict` (wejście z zewnętrznego linku nie wylogowuje); `path` zostaje `/` (zawężenie do `/admin/` dałoby dwa ciasteczka `PHPSESSID` obok siebie). Przy logowaniu `session_regenerate_id(true)`. Tokeny CSRF w formularzach POST (krok 2) osobno, po obserwacji kroku 1; do tego czasu dług opisany w „Konsekwencjach” pozostaje.

**Uzupełnienie 7.11 (CSRF, krok 2):** formularze POST panelu (`index.php`: Zatwierdź, Odrzuć, Usuń trwale, „To jednak produkt", Dodaj wpis; `affiliate.php`: dodaj i edytuj link) niosą pole ukryte `csrf_token`, a `verify_tool.php` nagłówek `X-CSRF-Token`; wspólne funkcje `admin_csrf_*` w `admin/session_init.php`, jeden token na sesję (ten sam co dla `api.php`). Błędny token = 403 przed jakimkolwiek zapisem. Bez tokenu zostają formularze logowania (świadomie: login CSRF nic tu nie daje atakującemu, a `SameSite=Lax` z kroku 1 i tak ogranicza cross-site POST) oraz publiczny `checkout.php`. Dług z „Konsekwencji" spłacony.

## ADR-013 — Definicja „RODO zgodny" (`rodo_compliant` ✓)
**Data:** 7.11.2026
**Status:** ✅ Przyjęte (wariant 2); weryfikacja starszych wpisów w toku, partiami

**Kontekst:**
Na 7.11 163 z 345 zatwierdzonych narzędzi ma `rodo_compliant = true`, a żadne nie ma dowodu w `compliance_evidence` (149 to starsze wpisy ręczne). Dla `dpa_available` i `eu_data_hosting` reguły były ustalone 6.11, dla `rodo_compliant` nie — a to główny wyróżnik katalogu i twierdzenie o zgodności z prawem.

**Rozważone warianty:** (1) ✓ tylko przy własnym DPA producenta — ścisły, ale pole dublowałoby `dpa_available`; (2) DPA albo polityka prywatności/trust page jawnie opisująca przetwarzanie w kontekście RODO; (3) sama deklaracja „zgodni z RODO" — najsłabsze twierdzenie, ✓ prawie wszędzie.

**Decyzja:** wariant 2. ✓, gdy oficjalna strona producenta ma własny DPA albo politykę zawierającą naraz: podstawy prawne, prawa osób, podmiot odpowiedzialny z nazwy oraz transfery poza EOG z mechanizmem (SCC/DPF) lub informację o ich braku. Sama deklaracja zgodności, brak informacji → NULL. Treść reguły i zapytania: `docs/COMPLIANCE-EVIDENCE.md`.

**Konsekwencje:** ✓ bez dowodu sprawdzamy partiami po 15–20 narzędzi (sprawdzone → ✓ albo NULL, w obu przypadkach z dowodem; niesprawdzone bez zmian do swojej partii). W trakcie liczba widocznych ✓ spadnie, bo część wpisów wróci do „nie zweryfikowano" — świadomie, uczciwszy stan niż ✓ bez pokrycia. Reguła nie zmienia schematu ani kodu panelu.

**Rozszerzenie zakresu (16.11.2026, decyzja Pabla „wariant B"):** reguła B obejmuje też wpisy `status = rejected` — nie są pominięte przy weryfikacji partiami tylko dlatego, że odrzucone. Dla każdego sprawdzamy to samo co dla zatwierdzonych (DPA albo pełna polityka wg reguły B) i zapisujemy dowód niezależnie od wyniku. Przy tej partii (12 odrzuconych, 16.11) kilka okazało się nieaktywnych/przejętych (np. domena zaparkowana na sprzedaż, produkt wchłonięty przez inną firmę) — tam `value: null` z notatką o stanie domeny, bez zgadywania.

---

## ADR-014 — Tagowanie `ai_act_risk` dla narzędzi oceniających osoby (analiza rozmów, coaching handlowców)
**Data:** 12.11.2026
**Status:** ✅ Przyjęte (decyzja Pabla); dotyczy narzędzi analizy rozmów, przegląd pozostałych w toku

**Kontekst:**
Narzędzia analizy rozmów (Modjo, Leexi, tl;dv, Insight7 i podobne) miały `ai_act_risk` `minimal` albo `limited`, choć część z nich ocenia i rankinguje handlowców lub konsultantów. To przypadek z załącznika III pkt 4 AI Act (zatrudnienie: monitorowanie i ocena osób w relacji pracy, motyw 57). Podział według kategorii (`$CATEGORY_AI_ACT_HINTS` w `admin/verify_tool.php`) tego nie widzi, bo transkrypcja i ocena pracownika lądują w jednej kategorii. Obowiązki dla systemów wysokiego ryzyka z załącznika III są odroczone do 2.12.2027 (Digital Omnibus on AI, zatwierdzony przez Radę 29.06.2026).

**Decyzja:** trzy poziomy, oceniane według funkcji narzędzia, nie kategorii:
1. `minimal` — transkrypcja, notatki, streszczenia, uzupełnianie CRM, bez oceny osób.
2. `limited` — samo narzędzie prowadzi rozmowy z ludźmi (agent głosowy lub czatowy); obowiązek przejrzystości z art. 50.
3. `high` — narzędzie ocenia, porównuje lub rankinguje pojedynczych pracowników (scoring jakości per osoba, rankingi zespołu, metryki zachowania per osoba) albo wnioskuje o ich emocjach. Do `high` zawsze `ai_act_notes`: jedno zdanie, która funkcja za tym stoi, i że ostateczna klasyfikacja zależy od sposobu użycia.
Przypadek wątpliwy zostaje na niższym poziomie z notką, dopóki strona producenta nie potwierdzi funkcji oceny.

**Konsekwencje:** 12.11 ustawione na `high` z notką: Insight7 (scoring i coaching konsultantów), Modjo (AI Call Scoring), Leexi (moduł Leexi Coach), tl;dv (coaching sprzedaży); po przeglądzie według strony pomocy producentów także Gong (scorecardy i AI Call Reviewer), Zendesk QA (scorecardy i AutoQA), Grain (scorecardy AI na stronie Coaching) i Outreach (Kaia Coaching Cards). Razem 8 narzędzi. To pierwsze użycie `ai_act_notes` dla zatwierdzonych narzędzi. Zmiany tylko w bazie, na stronie widoczne po deployu. Strona narzędzia pokazuje dla `high` stały tekst edukacyjny (rekrutacja, scoring kredytowy, ocena zgodności), szerszy niż pojedynczy moduł; notka w FAQ to zawęża, ale tekst można doprecyzować osobną decyzją (frontend bez zmian w tym kroku). Do sprawdzenia tą samą regułą zostają Fireflies (talk time i wydźwięk bez scorecardów, na razie `minimal`), Salesloft i COACH (potrzebna strona producenta); poza regułą do decyzji Otter.ai (`limited` przy samej transkrypcji) i Tough Tongue AI (agent głosowy, `minimal`).

---

## ADR-015 — `ai_act_risk`: rubryka oceny i uzasadnienie w rekordzie
**Data:** 19.11.2026
**Status:** ✅ Przyjęte (decyzja Pabla); przegląd wpisów z potoku zakończony 19.11

**Kontekst:**
Do 31.10.2026 trigger `promote_scrape_to_tools()` wstawiał `ai_act_risk = 'minimal'` na sztywno, więc ok. 185 zatwierdzonych wpisów z potoku miało publiczną etykietę (plakietka i FAQ) bez żadnej oceny. Migracja `011` zmieniła wstawianie na NULL, ale istniejących wierszy nie ruszyła (decyzja: nie zerować hurtem, tylko przejrzeć). Wymaganie Pabla: przy każdym przeglądzie hurtowym powód i uzasadnienie zmiany mają trafić do samego rekordu (jak przy RODO), żeby nie powtarzać tej samej pracy.

**Decyzja:**
1. **Rubryka** (rozszerza ADR-014; ocena wg funkcji produktu głównego, nie kategorii):
   - `high` — ADR-014 oraz inne obszary z załącznika III, gdy są rdzeniem produktu.
   - `limited` — rdzeń produktu rozmawia z osobami spoza firmy użytkownika, generuje treści syntetyczne (art. 50 ust. 1, 2, 4), wykrywa emocje z tekstu albo kategoryzuje emocje lub biometrycznie (art. 50 ust. 3).
   - `minimal` — reszta, w tym asystenci, z którymi użytkownik rozmawia świadomie, narzędzia deweloperskie, infrastruktura i testowanie.
   - Precyzja ponad kompletność. Wątpliwe: NULL albo `limited` z zapisaną decyzją redakcji. Wpisy, których nie da się ocenić (np. platforma obejmująca zastosowania od minimalnych po wysokie, wpis możliwy do odrzucenia z katalogu): NULL z dowodem `value: null`.
2. **Uzasadnienie w rekordzie:** `compliance_evidence.ai_act_risk` = `{value, url, note, checked}` plus `compliance_checked_at`, tak jak dla RODO/DPA/hostingu UE. `note` zawiera, co sprawdzono na stronie producenta i dlaczego taka ocena (przy przypadkach granicznych: „Decyzja redakcji RRRR-MM-DD"). Wpis z `value: null` = sprawdzono, ocena niemożliwa.
3. **Notka publiczna:** `ai_act_notes` zaczyna się od „Wstępna ocena redakcji: …" i jest ustawiana przy każdej wartości ≠ NULL (strona pokazuje ją w FAQ tylko gdy `ai_act_risk` nie jest NULL). To ocena redakcyjna, nie potwierdzona klasyfikacja prawna.
4. **Panel: pole miękkie.** `ai_act_risk` korzysta z mechanizmu dowodów, ale w odróżnieniu od RODO/DPA/hostingu zapis bez dowodu nie jest blokowany: przechodzi z ostrzeżeniem, a wpis (wartość bez dowodu) zostaje w kolejce przeglądu. Powód: część zapisów (sugestia „Zweryfikuj przez AI") nie ma w oknie miejsca na uzasadnienie, a blokada utrudniłaby korektę oczywistych pomyłek. „Dodaj wpis" ma domyślnie „Nie oceniono" (NULL), nie „Minimalne".

**Konsekwencje:** 19.11 przejrzane 185 wpisów z potoku (pilot 10 + 8 partii), każdy z dowodem. Stan zatwierdzonych po przeglądzie: NULL 18, high 21, limited 93, minimal 238; 182 wpisy z dowodem `ai_act_risk`. Sześć wpisów celowo bez zapisu (kandydaci do odrzucenia lub wyjaśnienia). Poza kodem panelu zapis dowodu przy SQL/MCP zostaje po stronie osoby zmieniającej (serwer nie ma tam jak go wymusić). Znane: `compliance_evidence` jest czytelne publicznie przez REST (`anon`, wiersze `approved`), więc `note` nie może zawierać niczego, czego nie chcemy upublicznić; ewentualne ograniczenie kolumny to osobna decyzja. Kontekst prawny z przeglądu: art. 50 stosowany od 2.08.2026 (obowiązek oznaczania z ust. 2 z okresem przejściowym do 2.12.2026), obowiązki dla systemów wysokiego ryzyka z załącznika III odroczone do 2.12.2027.

**Dopisek 19.11.2026 (wieczór), korekta pkt 3:** prefiks „Wstępna ocena redakcji: …" ma w bazie tylko standardowa notka (93 z 142 wpisów `minimal`); notki `limited` (18 z 23) i `high` (4 z 4) oraz część `minimal` (49) są pisane osobno i prefiksu nie mają. Zamiast ujednolicać 169 notek w bazie, charakter oceny pokazuje szablon FAQ w `[slug].astro`: zdanie „(wstępna ocena redakcji, nie potwierdzona klasyfikacja prawna)" jest doklejane do każdej wartości `ai_act_risk` ≠ NULL, a ewentualny prefiks notki jest przy wyświetlaniu obcinany, żeby się nie dublował. Konwencja dla nowych notek: samo uzasadnienie, bez prefiksu.

## ADR-016 — Dwie nowe kategorie: „Narzędzia dla programistów i agentów AI" oraz „Spotkania i rozmowy"
**Data:** 20.11.2026
**Status:** ✅ Przyjęte (decyzja Pabla); wdrożone 20.11 (migracja `014`, v0.50)

**Kontekst:**
„Automatyzacja procesów" (83 wpisy) i „Zarządzanie projektami" (62) działały jak worki na wszystko, co nie pasowało gdzie indziej. Przegląd nazw i opisów pokazał w nich około 70 narzędzi dla zespołów technicznych (agenci kodujący, testowanie i przegląd kodu, monitoring, bazy wektorowe, infrastruktura dla agentów) oraz kilkanaście narzędzi do spotkań i rozmów (notatki, transkrypcja, analiza rozmów sprzedażowych), rozproszonych po pięciu kategoriach. Źródła potoku (YC, HN) dowożą głównie narzędzia z pierwszej grupy. ADR-011 (katalog bez nowych funkcji) nie obejmuje zmian struktury kategorii, stąd ta decyzja.

**Decyzja:**
1. Dwie nowe kategorie (sort_order 11 i 12): „Narzędzia dla programistów i agentów AI" (slug `programisci-agenci-ai`) i „Spotkania i rozmowy" (slug `spotkania-rozmowy`).
2. Zdrowie (4 wpisy) i edukacja (2) nie dostają własnej kategorii: za mało wpisów. Infermedica i Docplanner przechodzą do „Obsługi klienta" (są tam CONUS i Elite), Discourse AI i Perfectum AI zostają w „HR i rekrutacji".
3. Przypisanie wg funkcji produktu, na podstawie nazwy i opisu z bazy (stron nie otwierano). Graniczne przypadki: Gladia i Recall.ai (API dla developerów) trafiły do „Spotkań i rozmów" ze względu na temat; Vendo, viso.ai, ContextVault, Assemble i Architecture Diagram AI do kategorii dla programistów; Dock, Easyspecs.ai, Segue, Retool AI, Floot, Salto, Zendesk QA, Loom, Wispr Flow, Speak i Dovetail zostają bez zmian.
4. Obie nowe kategorie, jak Finanse, Prawo i Cyberbezpieczeństwo AI, nie mają domyślnej sugestii w `$CATEGORY_AI_ACT_HINTS` (zbyt zróżnicowane: w „Spotkaniach i rozmowach" są np. narzędzia `high` z ADR-014). Ocena `ai_act_risk` dalej wg funkcji (ADR-014/015).

**Konsekwencje:** przeniesiono 86 zatwierdzonych wpisów (73 + 11 + 2); stan po zmianie: Automatyzacja procesów 83→36, Zarządzanie projektami 62→29, Obsługa klienta 28→29, Sprzedaż i CRM 25→22, HR i rekrutacja 21→19, nowe 73 i 11. Adresy `/narzedzia/slug/` bez zmian; przybywają `/kategoria/programisci-agenci-ai/` i `/kategoria/spotkania-rozmowy/`. Lista kategorii w potoku (`scraper/lib/openai.php`, `pipeline.php`) rozszerzona o nowe nazwy; `verify_tool.php` pobiera kategorie z bazy, więc bez zmian. Wpisy niezatwierdzone (pending/rejected) zostają w starych kategoriach. Otwarte: w nowej kategorii dla programistów część wpisów może trafić do odrzucenia przy ADR-011 (to narzędzia dla developerów, nie dla polskich firm); pozycjonowanie portalu pozostaje decyzją do przeglądu przy checkpoincie 25.01.2027.

---

## ADR-017 — Moderacja: znaleziska żyją w bazie zadań, wpis katalogu ma najwyżej jeden otwarty wiersz
**Data:** 24.11.2026
**Status:** 🧪 Próba (wariant A + rytm partii); przegląd po dwóch partiach, najpóźniej 6.12.2026 (zadanie #6 w bazie Centrum)

**Kontekst:**
Moderacja i przeglądy z 19–20.11 (ai_act_risk, kategorie) dały ok. 27 nowych wierszy zadań P001. Wiersze były nazwane od partii przeglądu, a nie od wpisu katalogu, więc ten sam wpis siedział w kilku wierszach naraz (Resin EDA w 4, FeyNoBg i Airdows w 3, Vorelios w 2). Jeden wiersz mieszał poprawki do wykonania, pytania „odrzucić czy zostawić?" i reguły potoku. ADR-016 (20.11) przeniósł ok. 70 wpisów do nowych kategorii i część wierszy o kategoriach straciła sens, a z samych wierszy nie dało się tego zobaczyć. Pomysł „jeden wiersz-kolejka" (21.11) cofnięto: zmniejsza liczbę wierszy, ale nie naprawia przypisania znalezisk do partii.

Rozważone: **A)** baza zadań z regułami zapisu; **B)** osobna tabela znalezisk przy rekordzie w Supabase (migracja + zmiany w panelu, koliduje z ADR-011); **C)** jednoznaczne poprawki robione od razu w sesji moderacji, wiersze tylko dla decyzji.

**Decyzja (wariant A, na próbę):**
1. Znaleziska z moderacji i przeglądów katalogu zostają w bazie zadań (projekt P001). Bez nowej tabeli, kolumn i funkcji w panelu.
2. Wiersz = jedna decyzja albo jedna paczka poprawek jednego rodzaju, nigdy partia przeglądu. Trzy rodzaje, widoczne w nazwie: „Decyzja:" (czeka na Pabla, np. odrzucić czy zostawić), „Poprawki:" (jednoznaczne, do wykonania), „Potok:" (reguła albo błąd procesu).
3. Wpis katalogu ma najwyżej jeden otwarty wiersz. Przed zapisem szukaj po nazwie wpisu (`zadania_lista`, widok `szukaj`); jeśli wiersz jest, dopisz do niego zamiast tworzyć nowy.
4. Lista wpisów w polu `tresc` jako checklista: `- [ ] Nazwa (slug) — co jest nie tak i skąd to wiemy`. `notatka` max 1 zdanie. Wiersz zamykamy, gdy checklista jest odhaczona.
5. Uzasadnienie zmiany danych zostaje w rekordzie (np. `compliance_evidence`, ADR-013/015); wiersz tylko na nie wskazuje.
6. Wykonanie „Poprawek" w osobnej sesji, po jednym wpisie, z kontrolą w katalogu; zapis do bazy po „ok" Pabla.
7. Zapis wierszy z moderacji nadal dopiero po „ok" (zasada „zamknij" w instrukcjach projektu).
8. Wiersz uznajemy za nieaktualny dopiero po sprawdzeniu, co się zmieniło w samym wpisie: porównać bieżący stan (kategoria, opis, adres, slug, `ai_act_risk`, status) z tym, o co wiersz pytał. Data zmiany rekordu nie wystarcza (19–20.11 wpisy zmieniano przy zapisie dowodów `ai_act_risk`, bez poprawy tego, co zgłaszały wiersze). Wynik sprawdzenia zapisać w wierszu przed jego zamknięciem.
9. Rytm: raz w tygodniu jedna partia 10–15 wpisów (na start 10), razem z cyklicznym przeglądem „Odrzucone przez AI". Kolejkę wyznaczają zapytania kontrolne z `COMPLIANCE-EVIDENCE.md` (np. `ai_act_risk` bez dowodu), a nie notatki w wierszach. Wynik sprawdzenia zapisujemy w rekordzie jako dowód; wiersz „Decyzja:" albo „Poprawki:" powstaje tylko wtedy, gdy trzeba coś zdecydować lub poprawić.
10. Sprawdzenie przy dodawaniu: nowy wpis (z potoku lub ręczny) sprawdzamy już przy zatwierdzaniu. Pola z ADR-013/015 dostają dowód w tej samej operacji; jeśli nie ma czasu na sprawdzenie, pole zostaje NULL („Nie oceniono"), a nie wartością bez dowodu. Powód: od 19.11 do 24.11 przybyło 8 wpisów z `ai_act_risk` bez dowodu, co zjadłoby całą tygodniową partię.
11. Decyzje „odrzucić czy zostawić" podejmujemy klasami, nie pojedynczo: po dwóch pierwszych partiach Pablo ustala, które klasy odpadają z katalogu (kandydaci z przeglądu 19–20.11: sprzęt, B2C, usługi doradcze, dostawcy danych, produkt wycofany, „nie produkt"), a wpisy dopasowujemy do reguł. To jakość katalogu w rozumieniu ADR-011, nie nowa funkcja.

**Przegląd próby:** po dwóch partiach (najpóźniej 6.12) sprawdzić: (a) czy 10 wpisów mieści się w jeden wieczór (czas rzeczywisty z wiersza cyklicznego), (b) ile wierszy powstało, (c) czy któryś wpis jest w więcej niż jednym otwartym wierszu, (d) czy checklisty zgadzają się ze stanem katalogu, (e) ile nowych wpisów bez dowodu doszło mimo punktu 10. Jeśli reguły nie trzymają, następny krok to wariant C (jednoznaczne poprawki od razu w sesji), dopiero potem B. B = osobna zamknięta tabela znalezisk (RLS bez dostępu `anon`), a nie kolumna w `tools`: `anon` czyta dziś wszystkie kolumny zatwierdzonych wpisów, więc notatka typu „kandydat do odrzucenia" byłaby publiczna (por. ostrzeżenie w `COMPLIANCE-EVIDENCE.md`).

**Konsekwencje:** istniejące wiersze (rodzina „Katalog: …" z 19–20.11) nie zmieniają się same; ich scalenie to osobna zmiana w bazie, tylko po liście zmian i „ok". Instrukcje projektu w Claude.ai (sekcja „Moderacja") zmienia Pablo sam; do wklejenia: reguły 2–4 powyżej.

---

---

*Aktualizuj przy każdej ważnej decyzji technicznej.*
