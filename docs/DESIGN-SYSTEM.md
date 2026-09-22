# 🎨 DESIGN-SYSTEM.md — aifirmy.pl

> Cykliczny audyt czysto graficzny — typografia, paleta kolorów jako system, ikonografia, spacing.
> Osobna dyscyplina od `UX-AUDIT.md` (tam: hierarchia informacji, czytelność, flow) —
> ten plik odpowiada na pytanie "czy to spójny, świadomie zaprojektowany język wizualny",
> nie "czy da się tego użyć".
> Ten czat (Claude.ai, projekt aifirmy.pl) służy jako stałe miejsce do przeprowadzania tych sesji.
> Aktualizuj przy każdej sesji: dopisz sekcję `## Sesja YYYY-MM-DD` na dole, zadania → Notion Todo (strona P001).

---

## 🎯 Cel dokumentu

Ocena strony jako systemu graficznego: czy typografia, kolory i ikony tworzą spójny, skalowalny język wizualny, czy są zbiorem lokalnych, dobrych-w-danym-momencie decyzji podjętych sesja po sesji. Metoda: pomiar bezpośrednio w DOM (computed styles), nie ocena wizualna ze zrzutów — to jedyny sposób żeby odróżnić "wygląda spójnie" od "jest spójne".

## 📊 Stan obecny (sesja 10.09.2026, zmierzone w DOM)

| Element | Ustalenie |
|---|---|
| Typografia | `font-family: ui-sans-serif, system-ui` — brak wybranego kroju, domyślny stos systemowy |
| Skala nagłówków | Brak zdefiniowanej skali — `<h2>` używany zarówno dla etykiet sekcji (14px) jak i tytułów kart (18px), brak `<h3>` na stronie głównej |
| Kolor marki (logo/CTA/akcent hero) | ✅ Idealnie spójny — identyczna wartość `oklch` we wszystkich 3 zmierzonych miejscach |
| Ikonografia kategorii | ✅ W pełni spójna technicznie — identyczny viewBox/stroke-width/styl we wszystkich 10 ikonach |
| Paleta kolorów kafli kategorii | Częściowo systemowa (spójna jasność/nasycenie) — ale wyczerpana: 2 z 10 kategorii mają niemal identyczny, bezbarwny kolor |
| Kształt odznak (border-radius) | ✅ Spójny — wszystkie badge'e używają pełnego zaokrąglenia |
| Powiązanie pola `icon` w DB z realnym renderowaniem | ❌ Rozjazd udokumentowany w `CLAUDE.md` — DB trzyma nazwę wg Tabler Icons, front dopasowuje ikonę po nazwie kategorii, nie po tym polu |

## 🔴 Wysoki priorytet

- [ ] **Brak wybranego kroju pisma.** Strona renderuje się inaczej typograficznie w zależności od systemu operacyjnego odwiedzającego — zero kontroli nad tożsamością typograficzną marki. Wymaga decyzji: webfont (Google Fonts / self-hosted) dopasowany do charakteru katalogu B2B (czytelny, neutralny, nie ozdobny).
- [ ] **Paleta kolorów kategorii wyczerpuje się przy obecnej liczbie kategorii.** "Zarządzanie projektami" (63 narzędzia, największa kategoria) i "Cyberbezpieczeństwo AI" mają praktycznie identyczny, bezbarwny kolor tła ikony. Wymaga przeglądu całej palety 10 kolorów naraz (nie punktowej zamiany jednego), żeby zapewnić realną rozróżnialność.

## 🟡 Średni priorytet

- [ ] **Brak zdefiniowanej skali typograficznej.** Rozmiary nagłówków (`<h2>` używane niespójnie dla 14px i 18px, brak `<h3>`) sugerują dobieranie punktowe, nie z systemu. Warto ustalić prostą skalę (np. 12/14/16/18/24/36px z jasną zasadą kiedy który poziom) i zmapować istniejące nagłówki na właściwe znaczniki HTML (ważne też dla SEO/dostępności, nie tylko estetyki).
- [ ] **Rozjazd pola `icon` w bazie vs. rzeczywiste renderowanie.** Udokumentowany w `CLAUDE.md`, nigdy nienaprawiony. Krucha konstrukcja — zmiana nazwy kategorii cicho zepsuje ikonę. Warto ujednolicić: albo DB faktycznie steruje ikoną, albo pole zostaje formalnie oznaczone jako nieużywane/do usunięcia.

## 🟢 Niski priorytet / do obserwacji

- [ ] Brak dedykowanego znaku/symbolu marki (dziś tylko wordmark tekstowy "aifirmy.pl") — nie jest to błąd, minimalistyczny wordmark to ważna, legalna decyzja, ale warto mieć na radarze przy przyszłym rozwoju marki.

## ✅ Mocne strony do zachowania (nie ruszać bez powodu)

- Kolor marki — idealna spójność, zero driftu
- Ikonografia — techniczna spójność zweryfikowana w kodzie SVG, nie tylko wizualnie
- Kształt odznak — jeden, konsekwentny wzorzec pill w całym serwisie
- Jasność/nasycenie palety kategorii jako zasada (problem jest w liczbie dostępnych, rozróżnialnych hue, nie w samej metodzie doboru)

---

## 🔁 Format cyklicznej sesji (checklist na start)

1. Otworzyć żywą stronę przez Chrome i **mierzyć w DOM** (computed styles: font-family, font-size, color, border-radius) — nie oceniać wyłącznie ze zrzutów ekranu, to nie odróżnia "wygląda spójnie" od "jest spójne"
2. Sprawdzić: typografia (czy jest wybrany krój, czy skala nagłówków mapuje się na `<h1>`-`<h4>` konsekwentnie), paleta kolorów jako całość (nie punktowo), ikonografia (viewBox/stroke/styl), spacing i promienie zaokrągleń
3. Sprawdzić otwarte punkty z tego pliku — czy któryś został naprawiony przy okazji innej pracy
4. Priorytetyzować nowe ustalenia (🔴/🟡/🟢)
5. Dopisać `## Sesja YYYY-MM-DD` na dole + zadania do Notion Todo (strona P001)

---

## 📝 Historia sesji

### Sesja 2026-09-10 (pierwsza sesja, ustanowienie dokumentu)

**Kontekst powstania:** poprzednia sesja tego dnia (redesign UI) pomyliła "grafik UI" z audytem UX — powstał `UX-AUDIT.md`, ale żadna z jego ustaleń nie dotyczyła typografii, palety kolorów jako systemu, ani ikonografii jako osobnej dyscypliny. Ten plik powstał żeby to naprawić.

**Zrobione:** pomiar bezpośrednio w DOM strony głównej — font-family/rozmiary nagłówków, kolor marki w 3 miejscach, kolory teł 10 kafli kategorii (lightness/chroma), atrybuty SVG wszystkich 10 ikon kategorii, border-radius odznak.

**Znalezione:** brak wybranego kroju pisma (🔴); paleta kategorii wyczerpana — 2 z 10 kolorów praktycznie identyczne i bezbarwne (🔴); brak zdefiniowanej skali typograficznej, `<h2>` używane niekonsekwentnie (🟡); udokumentowany w `CLAUDE.md` rozjazd pola `icon` w DB vs. realne renderowanie (🟡).

**Potwierdzone jako mocne strony:** kolor marki idealnie spójny (zmierzony, nie oceniony); ikonografia technicznie w pełni spójna (identyczne viewBox/stroke-width/styl); kształt odznak konsekwentny.

**Otwarte na następną sesję:** priorytet na wybór kroju pisma i przegląd całej palety kolorów kategorii naraz — oba dotyczą fundamentu tożsamości wizualnej, warto zrobić przed dalszym skalowaniem katalogu (więcej kategorii = więcej okazji do kolejnych kolizji kolorów).

---

### Sesja 2026-09-13 (weryfikacja postępu)

**Sprawdzone na żywo, oba punkty 🔴 bez zmian:**
- Krój pisma — wciąż `ui-sans-serif, system-ui`, brak własnego fontu
- Paleta kategorii — "Zarządzanie projektami"/"Cyberbezpieczeństwo AI" wciąż identyczne (chroma 0.003 oba)
- Skala nagłówków — wciąż 14×`<h2>`, 0×`<h3>`, bez zmian

---

### Sesja 2026-09-19 (naprawa obu punktów 🔴)

**Zrobione i zweryfikowane na żywo na produkcji:**
- Krój pisma: Inter, self-hosted przez `@fontsource/inter` (`5c68209`), tylko wagi faktycznie używane (400/500/600/700), pełne pliki wagowe (nie `-latin`/`-latin-ext`) ze względu na poprawny `unicode-range`. Fallback stack zachowany po Inter. Zweryfikowane: 8/28 plików realnie załadowanych na produkcji, polskie znaki diakrytyczne renderują się poprawnie.
- Paleta kolorów: nowe, rozróżnialne kolory dla "Zarządzanie projektami" (hue 217, środek luki teal→blue) i "Cyberbezpieczeństwo AI" (hue 130, środek luki amber→emerald) — `4e504b3`, poprawka gamutu w `c93f7f0`. Kolor tekstu "Zarządzanie projektami" pierwotnie poza gamutem sRGB (R liniowe ujemne) — naprawione do jawnie w-gamucie `oklch(56% 0.1 217)` = `#00829b`, granica gamutu wyznaczona wyszukiwaniem binarnym (C≈0.100019), zweryfikowana dwiema niezależnymi metodami konwersji zgodnymi do 1e-9. Zweryfikowane wizualnie na produkcji: oba kolory jednoznacznie rozróżnialne.

**Przy okazji odkryte:** wyszukiwanie tekstowe na `/narzedzia/` (punkt 🟡 z tej samej listy) już istnieje — zrobione poza tą sesją, potwierdzone na żywo.

**Zamknięte:** oba punkty 🔴. Zostają: skala typograficzna (`<h2>` niekonsekwentne, brak `<h3>`), rozjazd pola `icon` w DB vs. renderowanie — oba 🟡.

---

### Sesja 2026-09-22 (redesign hero + dark band, prowadzone jako sesja Art Director/UI Designer)

**Zrobione:** nowy token koloru akcentu (`#facc15`) i ink (`#0d0b21`) w `global.css`. Hero przebudowany na asymetryczny układ z highlight-barem pod frazą "RODO i AI Act", nowy podtytuł. Dark band przed stopką zastąpił stary płaski przycisk — dynamiczna liczba narzędzi z bazy, spójny CTA z hero. Commit `3363df6`, zweryfikowany na żywo (preview + produkcja): blob w hero używa dosłownie tej samej klasy `bg-indigo-600` co reszta marki, liczba w dark bandzie realna nie placeholder.

**Nowa, formalna zasada systemu kolorów:**
- `#facc15` (accent) — wyłącznie akcje wewnętrzne (zostań w katalogu): hero CTA, dark band CTA
- `indigo-600` — akcje wychodzące (afiliacja/strona producenta): "Odwiedź stronę {narzędzie}" na kartach i stronie narzędzia. Świadomie nietknięte — rozróżnienie kolorem między "zostajesz" a "wychodzisz" ma realną konsekwencję biznesową (najmocniejszy kolor marki nie powinien pchać ruchu do linków afiliacyjnych)
- Accent nigdy w warstwie danych (karty, badge'e, panel zgodności) — wyłącznie hero i dark band

**Odkryte przy okazji, niezwiązane z tą zmianą:** `frontend/.tmp-shots/` — pełny profil Chrome (cache, Trust Tokens, Sync Data) z 15.09, nigdy niescommitowany, przyczyna nieustalona. Do wyjaśnienia i usunięcia osobno.

**Zamknięte tą sesją:** oba pierwotne punkty 🔴 (font, paleta kategorii) + pełna koncepcja wizualna hero/CTA.

**Zostaje na następną sesję (🟡, realizacja, nie decyzje projektowe):** skala typograficzna, pole `icon` w DB, pusta przestrzeń w sidebarze, ceny PLN.

---

*Utworzono: 2026-09-10, w reakcji na brak tej sesji przy pierwotnym audycie "grafik UI". Aktualizuj po każdej sesji — nowa sekcja na dole, nie nadpisuj historii.*
