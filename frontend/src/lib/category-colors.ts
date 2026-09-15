export const categoryColors: Record<string, { bg: string; text: string }> = {
  'Automatyzacja procesów': { bg: 'bg-violet-50', text: 'text-violet-600' },
  'Analityka i BI': { bg: 'bg-blue-50', text: 'text-blue-600' },
  'Finanse i księgowość': { bg: 'bg-emerald-50', text: 'text-emerald-600' },
  'HR i rekrutacja': { bg: 'bg-orange-50', text: 'text-orange-600' },
  'Marketing i content': { bg: 'bg-pink-50', text: 'text-pink-600' },
  'Obsługa klienta': { bg: 'bg-teal-50', text: 'text-teal-600' },
  'Prawo i compliance': { bg: 'bg-amber-50', text: 'text-amber-600' },
  'Sprzedaż i CRM': { bg: 'bg-red-50', text: 'text-red-600' },
  'Zarządzanie projektami': { bg: 'bg-gray-100', text: 'text-gray-600' },
  'Cyberbezpieczeństwo AI': { bg: 'bg-slate-50', text: 'text-slate-600' },
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
