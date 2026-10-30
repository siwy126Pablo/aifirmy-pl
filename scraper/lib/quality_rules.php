<?php
declare(strict_types=1);

// Wspólne reguły jakości języka dla promptów OpenAI generujących description i best_for_pl.
// Jedno źródło prawdy dla:
//   - scraper/lib/openai.php  (potok: HN, BetaList, Product Hunt, YC-OSS; GitHub Actions)
//   - admin/verify_tool.php   (panel „Zweryfikuj przez AI"; Cyberfolks)
// Na serwer trafia ten jeden plik: deploy.yml (krok „Deploy admin panel") wgrywa go do
// public_html/scraper/lib/ obok admin/. Plik zawiera wyłącznie definicję stałej, bez sekretów.
//
// Treść bloku jest przeniesiona 1:1 z verify_tool.php (reguły 5–7 zweryfikowane na ~20 żywych
// przypadkach, 19.09) — zmiany w niej wpływają na OBA miejsca, więc po każdej edycji
// sprawdź oba. Wymagania z docs/CONTENT-GUIDE.md: pełne diakrytyki, zgodność gramatyczna,
// ton neutralny, best_for_pl bez „Dla…/Idealne dla…” (przedrostek „Dla:” dokłada strona
// narzędzia w [slug].astro), 3. osoba, bez etykiet pól.
const AIFIRMY_COPY_QUALITY_RULES =
    'Jakość języka polskiego dla description i best_for_pl (bezwzględnie przestrzegaj wszystkich siedmiu zasad): '
    . '(1) ZAWSZE używaj pełnych polskich znaków diakrytycznych (ą, ć, ę, ł, ń, ó, ś, ź, ż) — nigdy nie pomijaj ich '
    . 'ani nie zamieniaj na litery bez ogonków (np. nigdy "duze zespoly", zawsze "duże zespoły"). '
    . '(2) Sprawdź zgodność rodzaju i przypadka między przymiotnikiem a rzeczownikiem, zwłaszcza przy rzeczownikach '
    . 'na -eństwo/-ość (np. poprawnie: "ofensywnego cyberbezpieczeństwa", NIGDY "ofensywnej cyberbezpieczeństwa"). '
    . '(3) Unikaj dosłownych kalek z angielskiego — np. "departments"/"teams" to po polsku "działy"/"zespoły", '
    . 'NIGDY "fakultety". '
    . '(4) Zero tonu marketingowego: żadnych wykrzykników, żadnych superlatywów typu "najlepszy", "rewolucyjny", '
    . '"innowacyjny" — nawet jeśli oryginalny tekst źródłowy (landing page, opis YC) sam ich używa, pisz neutralnie '
    . 'i rzeczowo. '
    . '(5) best_for_pl NIGDY nie zaczyna się od słów "Dla", "Idealne dla", "Świetne dla", "Doskonałe dla", '
    . '"Najlepsze dla" — interfejs administracyjny sam dokłada przedrostek "Dla:" przed tym polem, więc taki początek '
    . 'utworzyłby podwójne "Dla: Dla...". Zacznij zdanie od rzeczownika opisującego grupę docelową, np. "Zespoły '
    . 'sprzedażowe w małych firmach...", NIGDY "Dla zespołów sprzedażowych...". '
    . '(6) Cała treść (description i best_for_pl) zawsze w trzeciej osobie. Nigdy nie używaj form 2. osoby '
    . '("Twój", "Twoja", "Twoje", "Ty") ani 1. osoby liczby mnogiej ("Nasz", "Nasza", "Nasze", "My") — nawet jeśli '
    . 'tekst źródłowy ze strony firmy sam ich używa, przepisz na trzecią osobę. '
    . '(7) Nigdy nie zostawiaj w zwróconym tekście etykiet pól pochodzących z własnego rozumowania (np. "Tagline:", '
    . '"Opis:", "Best for:") — zwróć tylko właściwą treść, bez prefiksu opisującego czym jest dane pole.';
