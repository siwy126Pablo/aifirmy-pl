export interface Company {
  slug: string;
  name: string;
  description: string;
  category: string;
  tags: string[];
  pricing: 'free' | 'freemium' | 'paid';
  url: string;
}

export const companies: Company[] = [
  {
    slug: 'cursor',
    name: 'Cursor',
    description:
      'Edytor kodu oparty na AI, który rozumie kontekst całego projektu. Pozwala pisać, refaktoryzować i debugować kod przez rozmowę z modelem GPT-4 lub Claude bezpośrednio w edytorze. Obsługuje wszystkie popularne języki programowania i integruje się z istniejącymi projektami bez konfiguracji.',
    category: 'Narzędzia deweloperskie',
    tags: ['IDE', 'AI copilot', 'GPT-4', 'refactoring', 'developer tools'],
    pricing: 'freemium',
    url: 'https://cursor.com',
  },
  {
    slug: 'perplexity',
    name: 'Perplexity AI',
    description:
      'Wyszukiwarka konwersacyjna korzystająca z dużych modeli językowych. Odpowiada na pytania w czasie rzeczywistym, cytując źródła — alternatywa dla tradycyjnych wyszukiwarek. Dostępna jako aplikacja webowa i mobilna, obsługuje tryb Pro z dostępem do GPT-4 i Claude.',
    category: 'Wyszukiwarki AI',
    tags: ['wyszukiwarka', 'LLM', 'cytowania', 'research', 'RAG'],
    pricing: 'freemium',
    url: 'https://perplexity.ai',
  },
  {
    slug: 'eleven-labs',
    name: 'ElevenLabs',
    description:
      'Platforma do syntezy i klonowania głosu z użyciem AI. Generuje realistyczną mowę w wielu językach — popularna w produkcji podcastów, audiobooków i narracji wideo. Oferuje API dla deweloperów oraz intuicyjny interfejs webowy do tworzenia narracji bez kodowania.',
    category: 'Audio i głos',
    tags: ['TTS', 'klonowanie głosu', 'synteza mowy', 'podcast', 'API'],
    pricing: 'freemium',
    url: 'https://elevenlabs.io',
  },
];

export function getCompanyBySlug(slug: string): Company | undefined {
  return companies.find((c) => c.slug === slug);
}
