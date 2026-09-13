# CONTENT-GUIDE.md — aifirmy.pl

> Źródło prawdy dla tonu, jakości i spójności copy — analogicznie do ARCHITECTURE.md dla kodu.
> Aktualizuj przy każdej decyzji dot. głosu marki. Audyt cykliczny w Claude.ai — wyniki dopisywane
> do CHANGELOG.md/STATUS.md, nie do osobnego pliku.

---

## 1. Ton i głos marki

**Zasada bazowa (z ARCHITECTURE.md):** neutralny, informacyjny, SEO-friendly, bez marketingu.

W praktyce oznacza to:

| ✅ Dobrze | ❌ Źle |
|---|---|
| "Narzędzie do automatyzacji procesów rekrutacyjnych, integruje się z ATS." | "Rewolucyjne narzędzie, które zmieni Twój sposób rekrutacji na zawsze!" |
| "Umożliwia zespołom sprzedaży śledzenie kontaktów bez ręcznego wprowadzania danych." | "Najlepsze narzędzie CRM na rynku, oszczędź czas i pieniądze już dziś." |
| "Oferuje plan darmowy z ograniczoną liczbą zapytań miesięcznie." | "Fantastyczna oferta cenowa dopasowana do każdego budżetu." |

Reguły:
- Zero wykrzykników, zero superlatywów ("najlepszy", "rewolucyjny", "fantastyczny") — nawet jeśli źródłowy tekst (YC one-liner, landing page) ich używa
- Trzecia osoba, czas teraźniejszy, zdania oznajmujące
- Fakty i funkcje, nie obietnice ("umożliwia X", nie "pomoże Ci osiągnąć X")
- Nazwa produktu nie jest podmiotem emocjonalnym — nie personifikuj ("Trident dba o Twoje bezpieczeństwo")

## 2. Reguły dla description_pl / tagline_pl / best_for_pl

**description_pl:**
- Długość: 2 zdania, ok. 150–250 znaków łącznie (obecnie niespójne — 1–3 zdania w praktyce, patrz sekcja 5)
- Zdanie 1: co to jest + główna funkcja. Zdanie 2: dla kogo / jaki problem rozwiązuje
- Zawsze zaczyna się od nazwy narzędzia ("X to narzędzie/platforma/aplikacja, która...")
- Bez żargonu bez wyjaśnienia (jeśli źródło używa "agentic", "LLM-native" itp. — opisz efekt, nie termin)

**tagline_pl:** (pole istnieje w schemacie, obecnie nieużywane w widocznym UI — do potwierdzenia czy w planach)

**best_for_pl:**
- Jedno zdanie, format: "[typ zespołu/firmy] + [kontekst/branża]", np. "Zespoły sprzedażowe w firmach SaaS"
- Zawsze pełne polskie znaki diakrytyczne — **znany defekt pipeline'u, patrz sekcja 5**
- Nie powtarzaj treści z description_pl innymi słowami — to ma być dodatkowa informacja (kto), nie streszczenie

## 3. Zasady dla legal/FAQ copy

FAQ (RODO/DPA/EU hosting/AI Act) to edukacyjne wyjaśnienia ogólne per kategoria/poziom ryzyka — **nigdy** oceny prawne per-narzędzie generowane przez AI (ta zasada już istnieje i działa dobrze, potwierdzona świadomym wykluczeniem `ai_act_notes` z automatyzacji).

Reguły:
- Niuans zamiast binarności: brak potwierdzenia ≠ potwierdzone naruszenie. Język typu "brak potwierdzonej zgodności" zamiast "niezgodne"
- Każde wyjaśnienie kończy się praktyczną wskazówką ("warto zweryfikować przed wdrożeniem"), nie zostawia czytelnika z samym faktem
- Zawsze neutralny ton prawny — nie strasz, nie uspokajaj, informuj
- **Spójność z UI:** treść FAQ i etykiety badge'y muszą komunikować to samo. Jeśli FAQ mówi "nie potwierdzono", badge nie może mówić "Nie" (patrz otwarty punkt w sekcji 5 — już zgłoszony w sesji UX 13.09)

## 4. Spójność microcopy

| Element | Miejsce | Zasada |
|---|---|---|
| CTA główny | karta, detal | "Zobacz szczegóły →" / "Odwiedź stronę [Nazwa] →" — zawsze ze strzałką, zawsze z nazwą narzędzia dla linku zewnętrznego |
| Etykiety cennika | `pricingLabels` w `category-colors.ts` | Darmowe / Freemium / Płatne / Open source — jedno źródło, nie duplikować lokalnie |
| Etykiety AI Act | `aiActRiskColors` | Minimalny / Ograniczony / Wysoki / Niedopuszczalny — pełne + `shortLabel` |
| Disclosure afiliacyjny | karta (tooltip), detal (tekst) | Świadomie różne wzorce — nie ujednolicać (patrz CLAUDE.md) |
| Badge zgodności (RODO/DPA/PL UI) | sekcja "Zgodność i dane" | ✗/✓ Nie/Tak — **do przeglądu, patrz sekcja 5** |

## 5. Znane code smells contentowe — checklist do audytu

Punkty kontrolne przy każdym cyklicznym audycie (próbka losowa 15-20 wpisów):

- [ ] **Diakrytyki** — czy `best_for_pl`/`description_pl` mają pełne polskie znaki (znany defekt: WorkID.ai "Duze zespoly" zamiast "Duże zespoły", znaleziono 13.09.2026)
- [ ] **Zgodność rodzaju gramatycznego** — szczególnie przy rzeczownikach odczasownikowych/przymiotnikach obcego pochodzenia (znany defekt: Trident "ofensywnej cyberbezpieczeństwa" zamiast "ofensywnego", znaleziono 13.09.2026)
- [ ] **Długość opisu** — czy trzyma się 2 zdań, czy są outliery 1-zdaniowe lub 3+ zdaniowe
- [ ] **Nienaturalne kalki językowe** — słowa brzmiące jak dosłowne tłumaczenie z angielskiego (znany przykład: "fakultyty" zamiast "działy/zespoły")
- [ ] **Halucynacje nazw produktów/modeli** — nieistniejące nazwy (GPT-5.5-Cyber i podobne — już łapane przez `is_real_product`, ale warto losowo weryfikować)
- [ ] **Generyczność bez konkretu** — opis, który pasowałby do dowolnego narzędzia w kategorii (brak nazw funkcji, integracji, konkretnego przypadku użycia)
- [ ] **Ton marketingowy** — wykrzykniki, superlatywy, przenikające z one_linera YC lub landing page mimo instrukcji w prompcie

**Otwarte pytanie projektowe (nie językowe, dotyczy szablonu):** binarne badge "✗ Nie" dla `rodo_compliant`/`dpa_available` przy niezweryfikowanych polach — zgłoszone równolegle w sesji UX 13.09, jedna decyzja do podjęcia dla obu wątków.

---

*Utworzono: 2026-09-13, pierwsza wersja robocza z sesji audytu contentu. Aktualizuj po każdej decyzji dot. tonu/reguł.*
