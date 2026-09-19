export const categoryColors: Record<string, { bg: string; text: string }> = {
  'Automatyzacja procesów': { bg: 'bg-violet-50', text: 'text-violet-600' },
  'Analityka i BI': { bg: 'bg-blue-50', text: 'text-blue-600' },
  'Finanse i księgowość': { bg: 'bg-emerald-50', text: 'text-emerald-600' },
  'HR i rekrutacja': { bg: 'bg-orange-50', text: 'text-orange-600' },
  'Marketing i content': { bg: 'bg-pink-50', text: 'text-pink-600' },
  'Obsługa klienta': { bg: 'bg-teal-50', text: 'text-teal-600' },
  'Prawo i compliance': { bg: 'bg-amber-50', text: 'text-amber-600' },
  'Sprzedaż i CRM': { bg: 'bg-red-50', text: 'text-red-600' },
  // Poprzednie wartości (bg-gray-100/slate-50) miały chroma ~0.003 — praktycznie
  // bezbarwne i wizualnie nierozróżnialne od siebie (DESIGN-SYSTEM.md, sesja
  // 10.09/13.09). Zamienione na te same hue co największe wolne przestrzenie na
  // kole barw pozostałych 8 kategorii tła (mierzone z realnych wartości OKLCH w
  // node_modules/tailwindcss/theme.css): teal 180.72° -> blue 254.604° (luka
  // 73.9°, środek 217.7°) i amber 95.277° -> emerald 166.113° (luka 70.8°,
  // środek 130.7°) — stąd hue 217/130 poniżej. Lightness/chroma teł (0.977/
  // 0.983, 0.014/0.021) mieszczą się dokładnie w zakresie pozostałych 8 teł
  // (L 0.969-0.987, C 0.013-0.022); kolory tekstu interpolowane analogicznie
  // między tekstami sąsiadów (teal-600/blue-600 i emerald-600/amber-600).
  //
  // Tekst "Zarządzanie projektami" celowo NIE ma chroma=0.16 (jak pierwotnie
  // interpolowane) — przy L=56%, hue=217 to poza gamutem sRGB (R liniowe
  // wychodzi ujemne, zweryfikowane niezależnie dwiema metodami konwersji:
  // macierzą Ottossona i pełnym łańcuchem OKLab->XYZ->sRGB ze specyfikacji
  // CSS Color 4, zgodne do 1e-9). Poleganie na tym, że przeglądarka to
  // "jakoś" przytnie, to niejawna zależność od zachowania silnika renderowania.
  // Granica gamutu przy L=56%/H=217 wyliczona wyszukiwaniem binarnym to
  // C≈0.100019 — użyte C=0.1 (bezpieczny margines poniżej granicy) daje
  // maksymalnie nasycony kolor, który mieści się w sRGB jawnie, nie na
  // podstawie przycięcia: oklch(56% 0.1 217) = #00829b, potwierdzone tym
  // samym niezależnym dwutorowym przeliczeniem.
  // Tekst "Cyberbezpieczeństwo AI" (chroma 0.16) sprawdzony tą samą metodą —
  // w pełni w gamucie (R/G/B liniowe: 0.130/0.314/0.007, wszystkie w [0,1]),
  // bez zmian.
  'Zarządzanie projektami': { bg: 'bg-[oklch(97.7%_0.014_217)]', text: 'text-[oklch(56%_0.1_217)]' },
  'Cyberbezpieczeństwo AI': { bg: 'bg-[oklch(98.3%_0.021_130)]', text: 'text-[oklch(62%_0.16_130)]' },
};

export const categoryColorFallback = { bg: 'bg-gray-100', text: 'text-gray-600' };

// 'minimal' celowo wyciszony (szary, niski kontrast) — to domyślny,
// "nic się nie dzieje" przypadek na ~90% kart katalogu, nie powinien
// przyciągać wzroku na równi z rzadszymi, decyzyjnymi poziomami ryzyka.
// limited/high/unacceptable zostają nasycone — te MAJĄ przyciągać uwagę.
export const aiActRiskColors: Record<string, { bg: string; text: string; label: string; shortLabel: string }> = {
  minimal: { bg: 'bg-gray-100', text: 'text-gray-500', label: 'AI Act: minimalny', shortLabel: 'Minimalny' },
  limited: { bg: 'bg-amber-100', text: 'text-amber-800', label: 'AI Act: ograniczony', shortLabel: 'Ograniczony' },
  high: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'AI Act: wysoki', shortLabel: 'Wysoki' },
  unacceptable: { bg: 'bg-red-100', text: 'text-red-800', label: 'AI Act: niedopuszczalny', shortLabel: 'Niedopuszczalny' },
};

export type PricingModel = 'free' | 'freemium' | 'paid' | 'open_source';

export const pricingLabels: Record<PricingModel, string> = {
  free: 'Darmowe',
  freemium: 'Freemium',
  paid: 'Płatne',
  open_source: 'Open source',
};
