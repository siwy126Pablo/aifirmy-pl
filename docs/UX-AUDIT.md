# 🎨 UX-AUDIT.md — aifirmy.pl

> Cykliczny audyt UI/UX — analogicznie do SEO.md i CONTENT-GUIDE.md.
> Ten czat (Claude.ai, projekt aifirmy.pl) służy jako stałe miejsce do przeprowadzania tych sesji.
> Aktualizuj przy każdej sesji: dopisz sekcję `## Sesja YYYY-MM-DD` na dole, zadania → Notion Todo (strona P001).

---

## 🎯 Cel dokumentu

Cykliczny (co jakiś czas, bez sztywnego harmonogramu) przegląd wyglądu i użyteczności strony — co działa, co się rozjechało, co wymaga korekty kursu. Nie duplikuje `CONTENT-GUIDE.md` (tam żyje ton i jakość treści) ani `SEO.md` (tam widoczność w wyszukiwarce) — ten plik jest wąsko wyspecjalizowany w samym interfejsie: hierarchia wizualna, spójność komponentów, responsywność, czytelność.

## 📊 Stan obecny — komponenty i wzorce (wrzesień 2026)

| Element | Status |
|---|---|
| Karta katalogowa (`CompanyCard.astro`) | ✅ Zredesignowana 08.09 — kolorowy AI Act, neutralny pricing, hierarchia CTA |
| Spójność karty między kontekstami (strona główna / kategoria / wyniki filtra) | ✅ Naprawiona 10.09 — wcześniej wyniki filtra na `/narzedzia/` miały uproszczoną, inną kartę niż reszta serwisu |
| Strona detalu narzędzia — hero + sidebar "W skrócie" | ✅ Zredesignowana 09.09 |
| Strona detalu — pasek "Zgodność i dane" | ✅ Dodana 09.09 |
| Strona główna | ✅ Odchudzona 10.09 (514 KB → 35 KB) — lekka wizytówka, nie duplikat katalogu |
| `/narzedzia/` (filtrowanie przez Pagefind) | ✅ Wdrożona 10.09 |
| Responsywność (sprawdzone: 390px mobile) | ✅ Nawigacja, karty, pigułki filtrów, hero+sidebar, panel zgodności — wszystko poprawnie się składa |
| Hero strony głównej — komunikacja wyróżnika (RODO/AI Act/UE) | ❌ Brak — zidentyfikowane 13.09, wciąż otwarte |
| Badge RODO/DPA/Interfejs PL — czytelność stanu "niezweryfikowane" | ❌ Czyta się jak "niezgodny" — zidentyfikowane 13.09, wciąż otwarte |
| Ceny w PLN w UI | ❌ Brak mimo istniejących danych (`price_from_pln`) |

## 🔴 Wysoki priorytet

- [ ] **Hero strony głównej nie komunikuje unikalnego wyróżnika.** Nagłówek/podtytuł mówi o "przejrzystych opisach i cenach" — nie wspomina RODO/AI Act/hosting UE, czyli jedynej rzeczy odróżniającej katalog od konkurencji. Ten wyróżnik dziś żyje tylko na `/premium`.
- [ ] **Badge'e "✗ Nie" (RODO/DPA/Interfejs PL na stronie detalu) czytają się jak zarzut, nie jak brak informacji.** Realne ryzyko wizerunkowe dla katalogu, którego głównym USP jest wiarygodność informacji o zgodności. Potrzebny inny wizualny/tekstowy wzorzec dla "nie zweryfikowano" vs. faktyczne "niezgodne".

## 🟡 Średni priorytet

- [ ] **Pusta przestrzeń w sidebarze "W skrócie" na stronie detalu.** Gdy narzędzie ma mniej wypełnionych pól (np. brak `eu_data_hosting`), panel po prawej (dopasowany wysokością do hero w gridzie) zostaje z zauważalnym pustym obszarem na dole. Wygląda na niedokończone, nie na "mniej danych do pokazania".
- [ ] **Ceny w PLN nigdzie nie są widoczne** — tylko kategoria (Darmowe/Freemium/Płatne/Open source). `price_from_pln` istnieje w schemacie od początku, nigdy nie trafił do UI. Niespójne z deklarowanym wyróżnikiem "informacje o cenach w PLN".
- [ ] **Brak wyszukiwania tekstowego na `/narzedzia/`** — tylko pigułki kategorii. Pagefind (wdrożony 10.09) już obsługuje wolny tekst, brakuje tylko pola wyszukiwania w interfejsie — relatywnie tani krok na już istniejącym fundamencie.

## 🟢 Niski priorytet / do obserwacji

- [ ] **Dwa niezależne systemy kolorów na jednej karcie** (kolorowy pill kategorii + kolorowy badge AI Act). Świadoma decyzja z sesji redesignu, działa dziś dobrze — ale przy większej różnorodności kategorii/poziomów ryzyka może zacząć wyglądać na zbyt "kolorowe". Nic do zmiany teraz, obserwować przy przyszłych audytach.
- [ ] **Badge AI Act "minimalny" na większości kart** — efekt rozkładu danych (większość narzędzi faktycznie ma minimalne ryzyko), nie błąd UI. Nie da się tego naprawić projektowo.

## ✅ Zamknięte

- ~~Strona główna duplikuje `/narzedzia/` zamiast pełnić odrębną rolę~~ — rozwiązane Fazą 2 (10.09): strona główna to teraz lekka wizytówka (12 kart + CTA), `/narzedzia/` to właściwe narzędzie przeglądania z filtrowaniem.
- ~~Niespójny wygląd karty między stronami~~ — rozwiązane 10.09 (patrz `CHANGELOG.md`, commit `75dfa6f`).

---

## 🔁 Format cyklicznej sesji (checklist na start)

1. Przejrzeć żywą stronę przez Chrome (nie z pamięci) — desktop + widok mobilny (390px), minimum: strona główna, `/narzedzia/`, jedna strona detalu, jedna strona kategorii
2. Sprawdzić otwarte punkty z tego pliku — czy któryś został naprawiony przy okazji innej pracy (oznaczyć jako zamknięty)
3. Ocenić: hierarchia wizualna, spójność komponentów, czytelność (szczególnie stanów "brak danych" vs. "negatywna wartość"), responsywność
4. Priorytetyzować nowe ustalenia (🔴/🟡/🟢), jak w `SEO.md`
5. Dopisać `## Sesja YYYY-MM-DD` na dole tego pliku + zadania do Notion Todo (strona P001)

---

## 📝 Historia sesji

### Sesja 2026-09-10 (pierwsza sesja, ustanowienie dokumentu)

**Zrobione:** przegląd żywej strony przez Chrome — desktop i mobile (390px) — strona główna, `/narzedzia/`, strona detalu narzędzia (Decawork).

**Znalezione:** brak komunikacji wyróżnika RODO/AI Act/UE w hero strony głównej (🔴, potwierdzenie punktu z 13.09); badge'e "✗ Nie" czytelnościowo mylące (🔴, potwierdzenie punktu z 13.09); nowe odkrycie — pusta przestrzeń w sidebarze "W skrócie" przy mniejszej liczbie wypełnionych pól (🟡); brak cen PLN w UI (🟡, potwierdzenie z 13.09); brak wyszukiwania tekstowego na `/narzedzia/` (🟡, potwierdzenie z 13.09, ale teraz tańsze do zrobienia dzięki Pagefind).

**Potwierdzone jako działające dobrze:** spójność karty katalogowej (naprawiona tego samego dnia, `75dfa6f`), responsywność na 390px (nawigacja, karty, pigułki filtrów, panel zgodności — wszystko poprawnie się składa).

**Zamknięte przy tej okazji:** "strona główna duplikuje `/narzedzia/`" (punkt z 13.09) — rozwiązane przez Fazę 2 tego samego dnia.

**Zadania przekazane do Notion Todo (strona P001):** patrz nowa sekcja "🎨 UX/UI — do zrobienia".

**Otwarte na następną sesję:** priorytet na 🔴 (hero + czytelność badge'y "✗ Nie") — oba dotyczą wiarygodności głównego wyróżnika katalogu, warto zrobić przed kolejną rundą growth (LinkedIn/outreach), żeby nowy ruch trafiał na stronę, która od razu komunikuje przewagę.

---

*Utworzono: 2026-09-10. Aktualizuj po każdej sesji — nowa sekcja na dole, nie nadpisuj historii.*
