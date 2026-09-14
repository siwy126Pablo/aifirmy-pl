# Pakiet poprawek 3 — zadania z audytu 2026-09-13

> Źródła: `UX-AUDIT.md`, `CONTENT-GUIDE.md`, `SEO.md`, `DESIGN-SYSTEM.md` (sesja 10.09), `STATUS.md`.
> Decyzja Pabla (13.09, po sesji strategicznej): **development NIE jest mrożony** — zakres poprawek jest mały, robimy równolegle z growth.

---

## 🔴 Krytyczne — wiarygodność wyróżnika, wpływa na growth

### Zadanie 1 — Hero strony głównej nie komunikuje RODO/AI Act/UE
Nagłówek mówi generycznie o "przejrzystych opisach i cenach". Mocne zdanie pozycjonujące już istnieje na `/premium` ("jedyny polski katalog, który taguje narzędzia pod kątem RODO, AI Act i hostingu w UE") — trzeba je przenieść tam, gdzie trafiają kupujący.
*Prompt: Zaktualizuj hero na stronie głównej (`index.astro`). Zastąp obecny nagłówek/podtytuł wersją komunikującą wyróżnik RODO/AI Act/hosting UE, spójną tonem z `/premium`, ale skróconą pod hero (nie kopiuj 1:1 zdania sprzedażowego z premium). Zachowaj istniejący layout/CTA.*

### Zadanie 2 — Badge "✗ Nie" czyta się jak zarzut, nie jak "nie zweryfikowano"
`rodo_compliant`/`dpa_available` domyślnie `false` (manual-only) renderują się jako twarde "✗ Nie", mimo że FAQ tuż pod spodem poprawnie tłumaczy niuans. Potwierdzone niezależnie przez UX i SEO tego samego dnia — to jedna decyzja, nie dwie.
*Prompt: Zmień rendering badge'y RODO/DPA/Interfejs PL w sekcji "Zgodność i dane" (`[slug].astro`). Rozróżnij stan "nie zweryfikowano" (pole=false/null, brak ręcznej weryfikacji) od faktycznego "zweryfikowano jako niezgodne" — jeśli dziś nie ma w schemacie osobnej flagi na ten drugi przypadek, na razie zmień etykietę/wizualia na neutralne ("nie zweryfikowano" zamiast "✗ Nie"), zgodnie z regułą z `CONTENT-GUIDE.md` sekcja 3 ("brak potwierdzenia ≠ potwierdzone naruszenie").*

### Zadanie 3 — Meta description z superlatywami i możliwą halucynacją (FeyNoBg)
Live: *"FeyNoBg to innowacyjny model... Dzięki najlepszemu pomiarowi S w czterech benchmarkach, jest doskonałym narzędziem..."* — trzy superlatywy + niejasna metryka "S". Wpływa bezpośrednio na CTR w SERP.
*Prompt: Popraw ręcznie `description_pl`/meta description dla FeyNoBg w Supabase — usuń superlatywy ("innowacyjny", "najlepszemu", "doskonałym") i niejasne odniesienie do "pomiaru S" (zweryfikuj co to za benchmark albo usuń, jeśli nie da się potwierdzić). Napisz zgodnie z `CONTENT-GUIDE.md` sekcja 1.*

### Zadanie 4 — Dwa znane błędy językowe (Trident, WorkID.ai)
Trident: "narzędzie do ofensywnej cyberbezpieczeństwa" → "ofensywnego". WorkID.ai: "Duze zespoly IT" → "Duże zespoły" (zgubione diakrytyki).
*Prompt: Popraw ręcznie w Supabase: `tools.description_pl` dla Trident (zgodność rodzaju gramatycznego) i `tools.best_for_pl` dla WorkID.ai (przywróć polskie znaki diakrytyczne). Sprawdź czy oba pola są też reużywane gdzie indziej (meta/OG/"Podobne narzędzia") i czy poprawka się tam propaguje automatycznie.*

### Zadanie 5 — Źródło dwóch URL-i `http://` w GSC
`kategoria/prawo-compliance` i `kategoria/finanse` pojawiają się w raporcie "zawiera przekierowanie" z protokołem `http://`. Nie ustalono, czy to stary indeks czy aktywnie generowany link.
*Prompt: Wykonaj `curl -I http://aifirmy.pl/kategoria/finanse` i `curl -I http://aifirmy.pl/kategoria/prawo-compliance` — potwierdź że 301→https działa. Sprawdź `grep -r "http://aifirmy"` w `frontend/src` i w wygenerowanej sitemapie, żeby wykluczyć aktywne źródło. Jeśli czysto — to rezydualny stary indeks, zamknij temat bez zmian w kodzie.*

---

## 🟠 Ważne — jakość i SEO, nie pilne, ale tanie

### Zadanie 6 — Ceny PLN nigdzie niewidoczne w UI
`price_from_pln` istnieje w schemacie od początku, nigdy nie trafił do UI — niespójne z deklarowanym wyróżnikiem "ceny w PLN".
*Prompt: Sprawdź w Supabase ile wierszy `tools.price_from_pln` ma niepustą wartość (SELECT count(*) WHERE price_from_pln IS NOT NULL). Jeśli pokrycie jest sensowne (>30-40%), dodaj wyświetlanie ceny na `CompanyCard.astro` i `[slug].astro` obok istniejącego `pricingLabels`, warunkowo gdy pole niepuste. Jeśli pokrycie niskie — zdecyduj czy warto inwestować w uzupełnianie danych zamiast UI.*

### Zadanie 7 — Brak wyszukiwania tekstowego na `/narzedzia/`
Pagefind już obsługuje pełny tekst (wdrożony 10.09), brakuje tylko widocznego pola wyszukiwania.
*Prompt: Dodaj pole wyszukiwania tekstowego na `/narzedzia/`, podpięte pod istniejący `pagefind.search()`. Zachowaj istniejący próg 60 wyników/"Pokaż więcej" i fallback bez JS (pigułki kategorii jako prawdziwe linki).*

### Zadanie 8 — Title tag generyczny, bez wyróżnika
Wzorzec `"{Nazwa} — aifirmy.pl"` nie wykorzystuje RODO/AI Act/ceny do przechwytywania long-tail zapytań.
*Prompt: Zaproponuj i przetestuj na 3-5 przykładach nowy wzorzec title (np. `"{Nazwa} — opis, cena, RODO | aifirmy.pl"`), z limitem ~60 znaków. Nie wdrażaj globalnie bez akceptacji — najpierw pokaż warianty.*

### Zadanie 9 — Brak `BreadcrumbList` JSON-LD
Tani do wdrożenia, poprawia rich snippets.
*Prompt: Dodaj `BreadcrumbList` JSON-LD na `[slug].astro` i `kategoria/[slug].astro` (Strona główna > Kategoria > Narzędzie), tym samym wzorcem co istniejący `FAQPage` (jedna tablica danych, nie duplikacja).*

### Zadanie 10 — Systematyczny audyt próbki 15-20 wpisów
Dotąd sprawdzone tylko 2-3 przykłady punktowo. Checklist już istnieje w `CONTENT-GUIDE.md` sekcja 5.
*Prompt: Wylosuj 15-20 wpisów z `tools` (status=approved). Dla każdego sprawdź: diakrytyki, zgodność rodzaju gramatycznego, długość opisu (2 zdania), kalki językowe, halucynacje nazw, generyczność, ton marketingowy. Policz częstość każdego typu błędu, zwróć listę konkretnych wpisów do poprawy.*

### Zadanie 11 — Krok jakości językowej w `verify_tool.php`
Dziś panel weryfikuje real-product/kategorię/pricing, nie sprawdza jakości języka.
*Prompt: Rozszerz `verify_tool.php` o dodatkowy krok/checkbox w modalu: flagowanie podejrzenia błędu językowego (diakrytyki, zgodność rodzaju) w `description_pl`/`best_for_pl`, bez automatycznej poprawki — tylko oznaczenie do ręcznej weryfikacji, zgodnie z checklistą z `CONTENT-GUIDE.md`.*

### Zadanie 12 — Pusta przestrzeń w sidebarze "W skrócie"
Gdy narzędzie ma mniej wypełnionych pól (np. brak `eu_data_hosting`), panel zostaje z pustym obszarem na dole.
*Prompt: W `[slug].astro`, sidebar "W skrócie" — zmień layout tak, żeby przy brakujących polach panel skracał się naturalnie (flex/gap zamiast sztywnej wysokości dopasowanej do hero), zamiast zostawiać pustą przestrzeń.*

---

## 🟢 Nice to have — Design System (z sesji 10.09, nadal otwarte)

### Zadanie 13 — Brak wybranego kroju pisma
Strona renderuje się domyślnym stosem systemowym (`ui-sans-serif, system-ui`).
*Prompt: Wybierz i wdróż webfont (Google Fonts lub self-hosted) dopasowany do katalogu B2B — czytelny, neutralny. Zweryfikuj wpływ na wagę strony (Core Web Vitals) przed i po.*

### Zadanie 14 — Paleta kolorów kategorii wyczerpana
"Zarządzanie projektami" i "Cyberbezpieczeństwo AI" mają niemal identyczny, bezbarwny kolor tła.
*Prompt: Przejrzyj całą paletę 10 kolorów kategorii naraz (nie punktowo) w `category-colors.ts` — zaproponuj zestaw z realną rozróżnialnością hue, zachowując spójną jasność/nasycenie.*

### Zadanie 15 — Brak zdefiniowanej skali typograficznej
`<h2>` używane niespójnie dla 14px i 18px, brak `<h3>` na stronie głównej.
*Prompt: Ustal prostą skalę typograficzną (np. 12/14/16/18/24/36px z jasną zasadą kiedy który poziom) i zmapuj istniejące nagłówki na właściwe znaczniki HTML — istotne też dla SEO/dostępności.*

### Zadanie 16 — Rozjazd pola `icon` w DB vs. realne renderowanie
DB trzyma nazwę wg Tabler Icons, front dopasowuje ikonę po nazwie kategorii, nie po tym polu — krucha konstrukcja.
*Prompt: Zdecyduj: albo podłącz realnie pole `icon` do renderowania ikon kategorii, albo formalnie oznacz je jako nieużywane/do usunięcia w schemacie i `CLAUDE.md`.*

---

## 📌 Porządkowe / dokumentacyjne

- **Poprawić datę w `UX-AUDIT.md`** — nagłówek sesji mówi "2026-09-10", ale treść odwołuje się do "punktu z 13.09" jako już znanego — wewnętrzna sprzeczność, prawdopodobnie plik edytowany 13.09 bez aktualizacji nagłówka sekcji.
- **Przenieść zadania SEO z `SEO.md` do Notion Todo (P001)** — jedyny audyt z dziś, który nie trafił do Notion, mimo że plik sam tego wymaga.
- **Zdecydować rytm dla "grafika"** — `DESIGN-SYSTEM.md` istnieje, ale ostatnia sesja to 10.09, nie dzisiejsza runda pięciu ról. Włączyć do stałego cyklu (jak strateg/UX/content) czy zostawić rzadziej?

---

## 🚀 Growth (równolegle, z decyzji strategicznej — nie zmienione dzisiejszą decyzją o niemrożeniu developmentu)

- Publikacja 2 gotowych postów LinkedIn (drafty w Notion)
- Cold outreach — ustalić tygodniowy commitment i zacząć wysyłkę
- Dopisać do `STATUS.md` tabelę trackującą aktywności growth
- Zebrać 3-5 nieformalnych feedbacków od użytkowników/klientów premium

---

*Sporządzono: 13.09.2026, na podstawie audytu strateg/UX/copywriter/SEO + design system z 10.09. Do przekazania jako brief dla nowego czatu/Claude Code realizującego "Pakiet poprawek 3".*
