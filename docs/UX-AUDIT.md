# 🎨 UX-AUDIT.md — aifirmy.pl

> Cykliczny audyt UI/UX — analogicznie do SEO.md i CONTENT-GUIDE.md.
> Ten czat (Claude.ai, projekt aifirmy.pl) służy jako stałe miejsce do przeprowadzania tych sesji.
> Aktualizuj przy każdej sesji: dopisz sekcję `## Sesja YYYY-MM-DD` na dole, zadania → Notion Todo (strona P001).

> **Zakres:** hierarchia informacji, czytelność, flow, spójność komponentów jako doświadczenia użytkownika.
> Typografia, paleta kolorów jako system i ikonografia jako osobna dyscyplina → `DESIGN-SYSTEM.md`, nie tutaj.

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
| Hero strony głównej — komunikacja wyróżnika (RODO/AI Act/UE) | ✅ Naprawiona 13.09 |
| Badge RODO/DPA/Interfejs PL — czytelność stanu "niezweryfikowane" | ✅ Naprawiona 14.09 (pełna migracja modelu danych na 3-stanowy, nie tylko poprawka wizualna — patrz szczegóły w sesji niżej) |
| Ceny w PLN w UI | ❌ Brak mimo istniejących danych (`price_from_pln`) (deklarowany wyróżnik skorygowany w dokumentacji 15.09 — PLN pricing to teraz opcjonalny bonus, nie systemowa cecha; formularz dodawania odblokowany, UI renderowania świadomie odłożone do wyższego fill rate) |

## 🔴 Wysoki priorytet

_(brak otwartych punktów wysokiego priorytetu — oba przeniesione do "✅ Zamknięte")_

## 🟡 Średni priorytet

- [ ] **Pusta przestrzeń w sidebarze "W skrócie" na stronie detalu.** Gdy narzędzie ma mniej wypełnionych pól (np. brak `eu_data_hosting`), panel po prawej (dopasowany wysokością do hero w gridzie) zostaje z zauważalnym pustym obszarem na dole. Wygląda na niedokończone, nie na "mniej danych do pokazania".
- [ ] **Ceny w PLN nigdzie nie są widoczne w UI** — tylko kategoria (Darmowe/Freemium/Płatne/Open source). `price_from_pln` istnieje w schemacie od początku, nigdy nie trafił do UI. Deklaracja skorygowana w dokumentacji 15.09 — PLN pricing to teraz opcjonalny bonus, nie systemowa cecha; formularz "Dodaj wpis" odblokowany dla tego pola na przyszłość, samo UI renderowania świadomie odłożone do wyższego fill rate (dziś ~1%).
- [ ] **Brak wyszukiwania tekstowego na `/narzedzia/`** — tylko pigułki kategorii. Pagefind (wdrożony 10.09) już obsługuje wolny tekst, brakuje tylko pola wyszukiwania w interfejsie — relatywnie tani krok na już istniejącym fundamencie.

## 🟢 Niski priorytet / do obserwacji

- [ ] **Dwa niezależne systemy kolorów na jednej karcie** (kolorowy pill kategorii + kolorowy badge AI Act). Świadoma decyzja z sesji redesignu, działa dziś dobrze — ale przy większej różnorodności kategorii/poziomów ryzyka może zacząć wyglądać na zbyt "kolorowe". Nic do zmiany teraz, obserwować przy przyszłych audytach.
- [ ] **Badge AI Act "minimalny" na większości kart** — efekt rozkładu danych (większość narzędzi faktycznie ma minimalne ryzyko), nie błąd UI. Nie da się tego naprawić projektowo.

## ✅ Zamknięte

- ~~Strona główna duplikuje `/narzedzia/` zamiast pełnić odrębną rolę~~ — rozwiązane Fazą 2 (10.09): strona główna to teraz lekka wizytówka (12 kart + CTA), `/narzedzia/` to właściwe narzędzie przeglądania z filtrowaniem.
- ~~Niespójny wygląd karty między stronami~~ — rozwiązane 10.09 (patrz `CHANGELOG.md`, commit `75dfa6f`).
- ~~Hero strony głównej nie komunikuje unikalnego wyróżnika~~ — rozwiązane 13.09: nagłówek/podtytuł teraz komunikują RODO/AI Act.
- ~~Badge'e "✗ Nie" czytają się jak zarzut, nie jak brak informacji~~ — rozwiązane 14.09: pełna migracja modelu danych `rodo_compliant`/`dpa_available`/`eu_data_hosting` na 3-stanowy (NULL = nie zweryfikowano), nie tylko poprawka wizualna. Patrz szczegóły w sesji 2026-09-13/15 niżej.

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

## Sesja 2026-09-13/15 (Claude.ai — kontynuacja, poza tym czatem)

**Uwaga:** ta sesja odbyła się równolegle w osobnym wątku Claude.ai, nie w tym samym czacie co sesja 09-10 — plik i Notion (strona P001, sekcja "Sesja UX 2026-09-13") prowadzone równolegle od teraz.

**Zrobione, krok po kroku, z pełną weryfikacją na żywo między krokami:**
1. Hero strony głównej — skorygowany, żeby komunikować RODO/AI Act (wyróżnik wcześniej żył tylko na `/premium`).
2. RODO/DPA/EU hosting — pełna migracja modelu danych z 2-stanowego (boolean, default false) na 3-stanowy (NULL = nie zweryfikowano). Migracja SQL (`db/migrations/002_tri_state_compliance_fields.sql`), panel admina (tri-state select + inline-edit), frontend (`[slug].astro` — kafelki i FAQ rozróżniają teraz "nie zweryfikowano" od zweryfikowanego "Nie"). Dodatkowo: "Zweryfikuj przez AI" wykrywa teraz cytaty (nie ocenę) zgodności ze strony narzędzia — świadomy, ograniczony wyjątek od zasady manual-only, udokumentowany w `CLAUDE.md`.
3. Ceny PLN — audyt ujawnił 0,9% fill rate (3/329, wszystkie z jednorazowej partii z tygodnia 1) i brak jakiejkolwiek ścieżki w pipeline/panelu do ich ustawiania. Deklaracja "PLN pricing" jako wyróżnik skorygowana w `CLAUDE.md`/`ARCHITECTURE.md`/`SEO.md` do "RODO + AI Act" (PLN jako bonus). Formularz "Dodaj wpis" odblokowany dla `price_from_pln` na przyszłość, UI renderowania świadomie odłożone do wyższego fill rate.

**Otwarte na następną sesję (patrz też Notion, sekcja UX 2026-09-13):** badge AI Act "minimalny" bez zróżnicowania wizualnego, brak wyszukiwania tekstowego na `/narzedzia/`, "Najpopularniejszy" na `/premium` do weryfikacji czy oparte na realnych danych, "Podobne narzędzia" tylko wg kategorii.

---

*Utworzono: 2026-09-10. Aktualizuj po każdej sesji — nowa sekcja na dole, nie nadpisuj historii.*
