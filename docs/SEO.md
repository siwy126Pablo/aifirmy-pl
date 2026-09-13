# 🔍 SEO.md — aifirmy.pl

> Źródło prawdy dla cyklicznych sesji SEO — analogicznie do CONTENT-GUIDE.md dla tonu treści.
> Ten czat (Claude.ai, projekt aifirmy.pl) służy jako stałe miejsce do przeprowadzania tych sesji.
> Aktualizuj przy każdej sesji: dopisz sekcję `## Sesja YYYY-MM-DD` na dole, zadania → Notion Todo (sekcja P001).

---

## 🎯 Cel dokumentu

Cykliczna (np. co 1-2 tygodnie) kontrola stanu SEO: co działa, co się zepsuło, co wymaga korekty kursu.
Nie duplikuje `STATUS.md` (tam żyje ogólny status projektu) — ten plik jest wąsko wyspecjalizowany w SEO/widoczności.

## ⚠️ Ograniczenie sesji / dostęp do danych

**Aktualizacja 13.09.2026 (część 2):** przez connector Chrome (przeglądarka Pabla, zalogowana sesja)
udało się wejść bezpośrednio do żywego Google Search Console i pobrać aktualne liczby —
patrz sekcje "Indeksowanie" i "Trend ruchu" niżej, to już nie są dane ręcznie przepisane ze STATUS.md.
Nadal brak stałego konektora API (Ahrefs/Semrush) do słów kluczowych/backlinków — do tego wciąż potrzebny
byłby albo dostęp przez przeglądarkę przy każdej sesji, albo osobny konektor MCP.
**Rekomendacja pozostaje aktualna:** jeśli Pablo chce, żeby te dane ciągnęły się automatycznie bez
przechodzenia przez przeglądarkę za każdym razem, warto rozważyć podłączenie dedykowanego konektora GSC.

---

## 📊 Fundament techniczny — status (na podstawie CLAUDE.md/STATUS.md, wrzesień 2026)

| Element | Status |
|---|---|
| Sitemapa (`@astrojs/sitemap`) | ✅ Naprawiona (był bug 23.07 — przypadkowe usunięcie) |
| Trailing slash na linkach wewnętrznych | ✅ Naprawione we wszystkich 4 miejscach (commit `9f2c7f1`) |
| Redirect www→apex | ✅ Cloudflare Redirect Rule, 301 (31.08) |
| Canonical tagi | ✅ Obecne (`<link rel="canonical">`), potwierdzone live |
| JSON-LD `FAQPage` | ✅ Generowany z tej samej tablicy co widoczna treść (bez ryzyka rozjazdu) |
| Schema.org `SoftwareApplication` | ✅ Wdrożone na stronach narzędzi |
| Schema.org `BreadcrumbList` / `Organization` | ❌ **Brak** — nie potwierdzone w żadnym dokumencie, niski koszt wdrożenia |
| Osierocone strony po zmianie statusu | ✅ Naprawione (`deploy.yml`, commit `d62e41c`, 09.09) |
| AI-content disclosure (zgodność z AI Act art. 50) | ✅ W stopce wszystkich stron |
| Meta description/OG/Twitter Card | ✅ Obecne na stronach narzędzi (potwierdzone live, patrz niżej) |
| GA4 | ⚠️ Znane niewiarygodne (ad-blockery w trybie stub) — Search Console/AWStats jako źródło prawdy |

**Wniosek:** fundament techniczny jest w dobrym stanie — sierpień/wrzesień to była seria realnych napraw,
nie jest to obszar wymagający pilnej interwencji. Uwaga przenosi się na **treść, dystrybucję i drobne luki**.

---

## 🔴 Nowe ustalenia z tej sesji (live-check, 2026-09-13)

Sprawdzona żywa strona narzędzia (`/narzedzia/feynobg/`) ujawniła dwa konkretne problemy:

### 1. Meta description łamie własne zasady tonu (CONTENT-GUIDE.md)
Live meta description dla FeyNoBg:
> "FeyNoBg to innowacyjny model do automatycznego usuwania tła. Dzięki najlepszemu pomiarowi S w czterech benchmarkach, jest doskonałym narzędziem dla twórców wizualnych."

Problemy:
- **"innowacyjny", "najlepszemu", "doskonałym"** — trzy superlatywy/marketingowe słowa w jednym zdaniu, wprost zabronione przez `CONTENT-GUIDE.md` sekcja 1 ("Zero wykrzykników, zero superlatywów")
- **"najlepszemu pomiarowi S w czterech benchmarkach"** — wygląda na halucynację/zgubiony kontekst benchmarku (niejasne co to za metryka "S") — pasuje do znanego wzorca z `CONTENT-GUIDE.md` checklisty ("Halucynacje nazw produktów/modeli")
- To pole trafia bezpośrednio do meta description / OG / Twitter Card = widoczne w wynikach wyszukiwania i przy udostępnianiu — słaby, niewiarygodnie brzmiący opis w SERP obniża CTR niezależnie od pozycji

**To nie jest tylko problem contentowy — to problem SEO (CTR w wynikach wyszukiwania).**
Prawdopodobnie nie odosobniony przypadek — `CONTENT-GUIDE.md` już flaguje "ton marketingowy" jako pozycję do audytu próbki 15-20 wpisów. Warto przy następnym audycie contentowym specyficznie sprawdzić opisy pod kątem tego, czy tekst nadaje się na meta description (SERP-safe), nie tylko pod kątem tonu na stronie.

### 2. Title tag generyczny na wszystkich stronach narzędzi
Wzorzec: `"{Nazwa} — aifirmy.pl"` (potwierdzone live). Nie zawiera kategorii ani wyróżnika (RODO/AI Act/cena).
Dla katalogu, gdzie **unikalny wyróżnik to właśnie RODO/AI Act/PLN**, title nie wykorzystuje tego do przechwytywania long-tail zapytań typu "[narzędzie] RODO", "[narzędzie] cena PLN", "[narzędzie] AI Act ryzyko".

**Potencjalny kierunek (do decyzji, nie do wdrożenia bez ustalenia z Pablo):**
`"{Nazwa} — opis, cena, RODO | aifirmy.pl"` lub podobny wzorzec z kategorią/wyróżnikiem.
Wymaga przemyślenia długości (Google przycina ~60 znaków) i testu na kilku przykładach przed zmianą globalnego szablonu.

### 3. Potwierdzone live: badge RODO "✗ Nie" (już znany punkt z audytu UX 13.09)
Live strona pokazuje "RODO ✗ Nie" mimo że FAQ tuż pod spodem poprawnie tłumaczy "brak potwierdzonej zgodności ≠ naruszenie".
Rozjazd między binarnym badge a niuansowym FAQ — to jest realny problem UX **i** SEO pośrednio (rich snippet/structured data pokazujące "Nie" może wpływać na to, jak Google interpretuje treść, choć nie ma na to bezpośredniego dowodu — warto to monitorować, nie traktować jako pewnik).

---

## 📈 Trend ruchu i indeksowania — dane live z GSC (13.09.2026, przez connector Chrome)

| Data | Kliknięcia (GSC) | Zaindeksowane strony |
|---|---|---|
| ~23.07 | 4 | 59 |
| ~30.07 | 5 | 142 |
| ~06.08 | 8 | 144-155 |
| 23.08 | 12 | 198 |
| ~30.08 | 15 | 198 |
| **13.09 (live)** | **19** *(ostatnie 3 mies.)* | **275** *(dane GSC na 04.09)* |

**Rozstrzygnięcie wcześniejszej obserwacji "utknęło na 198":** ✅ nieaktualne — to był po prostu brak nowszego
pomiaru w STATUS.md po 30.08, nie realny problem indeksacji. Indeksacja rośnie razem z katalogiem.

**Skuteczność wyszukiwania (3 miesiące, GSC → Skuteczność):** 19 kliknięć, 2,4 tys. wyświetleń,
CTR średni **0,8%**, średnia pozycja **18,5**. CTR 0,8% przy pozycji ~18 nie jest samo w sobie alarmujące
(pozycja 18 to druga strona wyników — naturalnie niski CTR), ale potwierdza, że jakość meta description
(patrz sekcja niżej) ma znaczenie w miarę jak pozycje będą się poprawiać.

### 🔎 Rozbicie "Niezindeksowano" (174 stron, GSC → Indeksowanie → Strony, stan na 04.09.2026)

| Przyczyna | Źródło | Liczba stron | Ocena |
|---|---|---|---|
| Strona zawiera przekierowanie | Strona internetowa | **91** | ⚠️ Do sprawdzenia — patrz niżej |
| Alternatywna strona z prawidłowym tagiem kanonicznej | Strona internetowa | 50 | ✅ Oczekiwane/nieszkodliwe — Google poprawnie wskazuje kanoniczną wersję |
| Strona zeskanowana, ale jeszcze nie zindeksowana | Systemy Google | 18 | ✅ Normalne dla rosnącego katalogu, kwestia czasu/autorytetu domeny |
| Strona wykryta — obecnie niezindeksowana | Systemy Google | 14 | ✅ Normalny zaległy crawl budget |
| Nie znaleziono (404) | Strona internetowa | 1 | 🟢 Pojedynczy przypadek, niski priorytet — zidentyfikować URL |

**⚠️ Nowe ustalenie — "Strona zawiera przekierowanie" (91 stron, status weryfikacji: Niepowodzenie, próba walidacji z 01.08 nieudana 05.08):**
Trend rośnie od połowy czerwca, w ostatnich tygodniach plateau ~85-91. Przykładowe URL-e z tabeli:
`narzedzia/architecture-diagram-ai` (bez trailing slash), `narzedzia/pilotcite`, `narzedzia/gamma` (bez trailing slash),
oraz dwa **`http://` (nie https!)**: `kategoria/prawo-compliance`, `kategoria/finanse`.

Interpretacja: większość to prawdopodobnie zwykłe, nieszkodliwe warianty URL (bez trailing slash) poprawnie
przekierowujące do kanonicznej wersji — to oczekiwane zachowanie, nie błąd. **Ale dwa warianty `http://`
zasługują na osobne sprawdzenie:** czy to tylko stare, historyczne wpisy z indeksu Google sprzed wymuszenia
HTTPS (nieszkodliwe, będą wygasać same), czy jest gdzieś aktywne źródło nadal generujące linki `http://`
(np. stary wpis w sitemapie, zewnętrzny link, dawny cache) — **nie potwierdzone w tej sesji, wymaga dalszego
sprawdzenia** (np. `curl -I http://aifirmy.pl/kategoria/finanse` żeby zweryfikować że przekierowanie 301→https
faktycznie działa, plus przegląd czy `astro.config.mjs`/sitemap nigdy nie generuje protokołu http). Warto też
sprawdzić w zakładce "Sprawdź szczegóły" w GSC dokładną przyczynę niepowodzenia walidacji z 05.08.

**Backlinki:** tylko 1 potwierdzony zewnętrzny backlink w całej historii projektu (piperic.com → wpis o Descript, 23.08).

---

## 🎯 Otwarte punkty z innych dokumentów, istotne dla SEO

Z `STATUS.md`, sesja UX 13.09 (część nakłada się bezpośrednio na SEO/CTR):
- Hero strony głównej nie komunikuje wyróżnika RODO/AI Act/UE (tylko na `/premium`) — traci szansę na trafienie w intencję wyszukiwania już na landing page
- Ceny w PLN (deklarowany wyróżnik) nie są widoczne nigdzie w UI — potencjalnie tracone zapytania z intencją "cena"
- Brak widocznego pola wyszukiwania tekstowego na `/narzedzia/` — tylko pigułki kategorii

Z `CONTENT-GUIDE.md`, sekcja 5 (checklist do audytu contentowego — częściowo pokrywa się z SEO):
- Diakrytyki, zgodność gramatyczna, ton marketingowy — wszystko to trafia do meta description/treści widocznej w SERP

---

## 🧭 Rekomendacje / propozycje zmian z tej sesji

**Wysoki priorytet (realny problem SEO, nie tylko kosmetyka):**
1. ~~Zweryfikować świeży stan indeksacji w Search Console~~ ✅ **Zrobione tą sesją (przez connector Chrome)** — 275 zaindeksowanych, rośnie, nieaktualna obawa
2. Sprawdzić źródło dwóch `http://` (nie https) URL-i w raporcie "Strona zawiera przekierowanie" (91 stron) — czy to tylko stary indeks, czy aktywnie generowane linki
3. Przejrzeć próbkę meta descriptions pod kątem superlatywów/halucynacji (nie tylko treść na stronie, ale konkretnie pole trafiające do `<meta description>`/OG/Twitter) — rozszerzyć checklistę `CONTENT-GUIDE.md` sekcja 5 o tę optykę

**Średni priorytet:**
3. Rozważyć wzbogacenie `<title>` o kategorię/wyróżnik zamiast generycznego `"{Nazwa} — aifirmy.pl"` — wymaga decyzji o dokładnym wzorcu i testu na próbce przed globalną zmianą
4. Dodać `BreadcrumbList` JSON-LD (tani do wdrożenia, poprawia rich snippets)

**Niski priorytet / do obserwacji:**
5. Monitorować, czy badge RODO/DPA "✗ Nie" ma mierzalny wpływ SEO (niepewne, ale warto mieć na radarze przy okazji naprawy UX)

**Nie-SEO, ale blokujące SEO pośrednio (już znane, patrz STATUS.md):**
6. Brak backlinków poza jednym przypadkiem — to pytanie o dystrybucję (LinkedIn/outreach), nie o technikalia strony. Zgodnie z sesją strategiczną 13.09 to i tak już priorytet #1 projektu.

---

## 🔁 Format cyklicznej sesji (checklist na start)

1. **Search Console** (jeśli podłączony konektor, użyj go zamiast ręcznych liczb): kliknięcia, wyświetlenia, zaindeksowane strony, nowe błędy Coverage
2. **AWStats**: realny ruch vs. boty (GA4 nadal traktujemy jako niewiarygodne)
3. **Backlinki**: nowe od ostatniej sesji (GSC → Links, lub ręcznie)
4. **Spot-check 2-3 nowych stron narzędzi**: title/meta/schema/trailing slash/ton opisu
5. **Przegląd otwartych punktów** z tego pliku i z `STATUS.md` backlog dot. SEO
6. Dopisać `## Sesja YYYY-MM-DD` na dole tego pliku + zaktualizować zadania w Notion (sekcja P001)

---

## 📝 Historia sesji

### Sesja 2026-09-13 (pierwsza sesja, ustanowienie dokumentu)

**Zrobione:** przegląd dokumentacji projektu (CLAUDE.md/STATUS.md/CONTENT-GUIDE.md), live-check jednej strony narzędzia przez `web_fetch` (brak dostępu do GSC/Ahrefs/Semrush w tym czacie).

**Znalezione:** meta description z superlatywami i prawdopodobną halucynacją benchmarku (FeyNoBg); title tag generyczny bez wyróżnika RODO/AI Act; potwierdzone live zaindeksowanie badge'a RODO "✗ Nie" (już znane z audytu UX); zaindeksowane strony utknęły na 198 w dwóch ostatnich pomiarach GSC mimo wzrostu katalogu.

**Zadania przekazane do Notion Todo (P001):** patrz sekcja SEO w todo.

**Otwarte na następną sesję (zaktualizowane po części 2):** próbka audytu meta descriptions (5-10 losowych narzędzi), sprawdzenie źródła `http://` URL-i, decyzja o stałym konektorze GSC vs. sprawdzanie przez przeglądarkę co sesję.

### Sesja 2026-09-13, część 2 (live GSC przez connector Chrome)

**Zrobione:** dostęp do żywego panelu Google Search Console przez connector Chrome (zalogowana sesja Pabla) — zakładki Skuteczność i Indeksowanie → Strony.

**Znalezione:**
- Rozstrzygnięte pytanie z części 1: indeksacja **nie** utknęła — 275 stron zaindeksowanych (wg danych GSC na 04.09.2026), rosnąca razem z katalogiem. Wcześniejsza obawa wynikała z braku nowszego ręcznego pomiaru w STATUS.md, nie z realnego problemu.
- Skuteczność 3 miesiące: 19 kliknięć, 2,4 tys. wyświetleń, CTR 0,8%, śr. pozycja 18,5
- Nowe, wcześniej nieznane ustalenie: 91 stron w kategorii "Strona zawiera przekierowanie" (status weryfikacji: Niepowodzenie od 05.08), w tym dwa przykłady z protokołem `http://` zamiast `https://` — źródło nie zostało ustalone w tej sesji, wymaga sprawdzenia czy to tylko rezydualny stary indeks czy aktywnie generowany link
- Pozostałe kategorie niezindeksowanych stron (50 alternatywne/kanoniczne, 18 zeskanowane-nie zindeksowane, 14 wykryte-nie zindeksowane, 1× 404) ocenione jako normalne/oczekiwane dla rosnącego katalogu

**Otwarte na następną sesję:** ustalić źródło `http://` URL-i; sprawdzić szczegóły niepowodzenia walidacji z 05.08 w GSC ("Sprawdź szczegóły"); zidentyfikować konkretny URL za pojedynczym 404.

---

*Utworzono: 2026-09-13. Aktualizuj po każdej sesji SEO — nowa sekcja na dole, nie nadpisuj historii.*
