# COMPLIANCE-EVIDENCE.md — rejestr weryfikacji RODO / DPA / hostingu w UE

**Po co ten plik.** Pola `rodo_compliant`, `dpa_available`, `eu_data_hosting` w tabeli `tools` nie mają miejsca na źródło ani datę sprawdzenia. Ten plik jest tym miejscem, dopóki nie powstaną kolumny w bazie. **Zasada dla każdej sesji: wpis wymieniony poniżej jest już sprawdzony na stronie producenta. Nie weryfikuj go od nowa**, chyba że minął rok od daty sprawdzenia albo producent ogłosił zmianę (np. DeepL, luty 2026). Nowe sprawdzenia dopisuj na dole tabeli, nie edytuj wstecz.

Dziennik zmian i decyzji: `CHANGELOG.md`, `DECISIONS.md`. Ten plik to rejestr dowodów, nie dziennik.

## Reguły znaczenia pól (ustalone 6.11.2026)

- **`dpa_available` ✓** — strona producenta (Legal / Trust / Security) zawiera własny dokument DPA/Data Processing Addendum. Sama deklaracja „zgodni z RODO" to za mało → NULL.
- **`eu_data_hosting` (reguła A, decyzja Pabla)** — ✓, gdy region UE można wybrać przy zakładaniu konta lub jest domyślny. ✗, gdy UE to płatny dodatek / tylko enterprise albo domyślnie dane są poza UE. NULL, gdy strona nie mówi nic o hostingu.
- **`rodo_compliant` ✓** — dla starszych wpisów ręcznych (ok. 80) nie ma udokumentowanego dowodu. Definicja tego pola czeka na decyzję (zadanie w Notion: „zdecydować definicję RODO ✓"). Do tego czasu ✓ przy RODO nie oznacza dowodu, a ✓ przy DPA/UE z tego rejestru tak.
- Brak informacji = NULL, nigdy ✓.

## Rejestr — przegląd 6.11.2026 (strony producentów, Chrome)

Stan pól po przeglądzie: ✓ = true, ✗ = false, — = NULL.

| slug | DPA | UE | Dowód |
|---|---|---|---|
| apify | — | ✗ | trust.apify.com: podprocesorzy w USA (AWS, MongoDB, Snowflake, Mezmo) |
| deepl | ✓ | ✗ | trust center deepl.safebase.us, komunikat z 6.02.2026: domyślnie globalnie (AWS UE/US/JP), Data Residency UE jako płatny dodatek; dokument DPA w trust center |
| pigment | ✓ | ✓ | pigment.com/security: klient wybiera europe-west3 (Frankfurt) lub us-west1 (Oregon); samego DPA na stronie nie widziano |
| relevance-ai | ✓ | ✓ | trust.relevanceai.com: „Data Processing Addendum", podprocesorzy AWS/MongoDB, regiony US/EU/AU |
| otter-ai | ✓ | — | Otter, strona privacy-security: oświadczenie GDPR, AWS S3, bez informacji o regionie |
| fathom | ✓ | — | fathom.ai/dpa (DPA), fathom.ai/privacy (bez regionu) |
| descript | — | — | brak własnego DPA na stronie producenta |
| legalsifter | — | — | tylko oświadczenie GDPR i EU-US DPF, bez DPA |
| langfuse | ✓ | ✓ | langfuse.com/security: DPA, regiony US/EU/JP |
| gladia | ✓ | ✓ | gladia.io/security: domyślnie europejski dostawca (Francja), DPA w stopce |
| survicate | ✓ | ✓ | survicate.com/gdpr: AWS Irlandia, link do DPA |
| leexi | ✓ | ✓ | FAQ compliance-sovereignty: AWS Paryż, sekcja DPA |
| edrone | ✓ | ✓ | help.edrone.me, artykuł 832954: AWS Dublin + Frankfurt. DPA (PDF na wp.edrone.me) znaleziony wyszukiwarką, **nie otwarty** |
| loom | ✓ | — | support.atlassian.com/loom/docs/understand-loom-data-residency/: rezydencja Niemcy ustawiana przy provisioningu od maja 2026 (UE zostawione NULL) |
| typeform | ✓ | — | typeform.com/legal: „Data Processing Agreement" |
| zapier | ✓ | — | zapier.com/legal/data-processing-addendum |
| zendesk | ✓ | — | zendesk.com/company/data-processing-agreement/; regiony hostingu EEA (Irlandia/Frankfurt) i dodatek Data Center Location opisane w artykule pomocy (dokładny URL nie zapisany) |
| exact | ✓ | — | exact.com/trust/privacy: „Data Processing Addendum", lista podprocesorów |
| perplexity | ✓ | — | perplexity.ai/hub/legal/dpa |
| glean | ✓ | — | glean.com/legal/dpa (PDF „DPA Mar 6 2026") |
| retool | ✓ | — | docs.retool.com/legal/privacy-protection-security (sekcja DPA) |
| apollo | ✓ | — | apollo.io/dpa |
| looker-studio | ✓ | — | support.google.com/looker-studio/answer/7657915 (Data Processing Amendment) |
| ironclad | ✓ | — | legal.ironcladapp.com („Data Processing Addendum") |
| paradox | ✓ | — | paradox.ai/legal/us-dpa |
| clearbit | ✓ | — | clearbit.com/Clearbit_DPA_20MAR2024.pdf (dokument z marca 2024) |
| photoroom | ✓ | — | photoroom.com/legal/data-processing-agreement; /legal/security: GCP/AWS/Cloudflare |
| zowie | ✓ | — | getzowie.com/data-processing-agreement |

Wpisy bez zmian, ale zgodne z regułą A: linear (UE ✓), qdrant (UE ✓), tldv (UE ✓).

Pole „slug" w tabeli to nazwa narzędzia; część slugów (np. exact, zapier, zendesk, perplexity, apollo, looker-studio, paradox, clearbit) trzeba sprawdzić w bazie, bo nie wszystkie są w katalogu pod taką nazwą.

## Zmiany z partii 1–2 (wcześniej tego samego wieczoru)

Zmieniono tagi: make-integromat, onetrust, rossum, mixpanel, n8n, crisp, framer-ai (UE → NULL), fathom-analytics (UE → NULL), assembled (DPA i UE → NULL), fieldguide (poprawiony link i logo, UE ✗). Mixpanel: UE ✗ (domyślnie poza UE). **Adresy stron dla tych wpisów nie zostały wtedy zapisane** — jeśli ktoś ma wątpliwość co do któregoś z nich, to jedyny przypadek, gdzie warto sprawdzić ponownie.

## Nie potwierdzone (bez zmian, do dokończenia)

SentiOne, Senuto, Lex Expert AI, UiPath, Weaviate, Parloa, Brand24, Grammarly Business (Chrome blokuje odczyt strony), humai. Szukać od stopki Legal / Trust / Privacy; zgadywane adresy URL zwykle dają 404.

## Dopisywanie nowych sprawdzeń

Wiersz: slug, wartości DPA/UE po sprawdzeniu, URL strony producenta + co na niej jest, data (RRRR-MM-DD). Jeśli sprawdzona strona nie mówi nic o polu, wpisz NULL i napisz to w dowodzie.
