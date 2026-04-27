# Datamodell for kakao-opprinnelse i Rich Attribute Suite (v4)

## Kontekst

Rich Attribute Suite (RAS) utvides med en opprinnelses-modul. Modulen legger
strukturerte felter på attributtet `pa_opprinnelse`, rendrer en rik
variasjons-blurb + modal på produktsiden, og leverer et dedikert arkiv og en
dedikert enkeltside per opprinnelse.

Datagrunnlaget er Silva Cacaos leverandørportefølje. Empirisk analyse av 9
gårder har avdekket hvilke felter som konsekvent finnes vs. mangler — dette
former hva som er strukturert vs. narrativt i modellen.

Denne versjonen erstatter v1, v2 og v3.

---

## 1. Arkitekturvalg

### 1.1 Lagringssted: alt på CPT

RAS har etablert at rik metadata bor på CPT-en `attribute_page`, ikke på
`wp_termmeta`. Termen er identitet, CPT-en er datakilde.

- Alle nye felter registreres som `post_meta` på `attribute_page`-CPT-en
- Ingen data på `wp_termmeta`
- Én entitet å eksportere/importere — hele CPT-en inkludert `post_content`,
  `post_excerpt`, featured image og alle meta-nøkler
- Term description deprekéres som kilde; fallback-koden beholdes for
  bakoverkompatibilitet

### 1.2 CPT blir offentlig

`attribute_page` flippes til `publicly_queryable => true`,
`has_archive => 'opprinnelser'`.

- `/opprinnelser/` som dedikert arkiv-URL
- `/opprinnelser/{slug}/` som dedikert enkeltside
- Native `WP_Query`, REST-endepunkter, WP-filter-hooks gratis
- Templates: `archive-attribute_page.php`, `single-attribute_page.php`

Ingen canonical-håndtering fra gammel taxonomy-URL — staging er uten
SEO-historikk, ren overgang. Taxonomy-URL kan settes `noindex` eller fjernes
fra publikum helt.

### 1.3 Sertifiseringer som taxonomy, på opprinnelsen

Ny taxonomy `certification` registrert på `attribute_page`-CPT-en. Ikke på
produkter.

**Regulatorisk begrunnelse:** Ved å holde sertifisering på bønnenivå
(opprinnelse) og ikke på sluttprodukt, unngås Debios markedsføringskrav for
økologiske produkter. Sporbarheten er ærlig og presis uten å utløse
produktsertifiseringskrav.

Badges dukker opp på modal, arkivkort og opprinnelsesside. **Ikke** på
produktkort eller produktsider.

### 1.4 Land som taxonomy

Ny taxonomy `origin_country` på `attribute_page`-CPT-en. Terms representerer
land (Peru, Tanzania, Nicaragua, Brazil, Colombia, Madagascar, Philippines,
India, Venezuela, …).

**Begrunnelse:** Kanonisk navnestyring (ingen "Brasil vs Brazil"-variasjoner),
gratis oppslag via `get_term`, slug driver flagg-lookup naturlig. Med 5–15
terms er vedlikeholdskostnaden null. Fremtidssikret hvis filtrering dukker
opp.

Flagg mappes fra term-slug (`peru` → `/flags/pe.svg`). Mapping er filtrerbar
via `apply_filters('wc_ras_country_flag_map', $map)`.

### 1.5 Preload-strategi

All data klient trenger for variasjons-blurb *og* modal preloades i
`data-product_variations`-attributtet på `form.variations_form`.

- Videreføring av RAS-mønster via `woocommerce_available_variation`-filteret
- Null nettverks-latens ved variantbytte eller åpning av modal
- Radar-diagram genereres **klientside** fra preloadet `taste_profile` (8 tall
  ≈ 80 bytes)
- Samme radar genereres **serverside** i PHP for CPT-siden (SEO + no-JS)

### 1.6 Halvstrukturerte blokker på CPT-siden

Prinsipp: informasjon som er genuint narrativ eller inkonsistent i form bør
ikke tvinges inn i strukturerte meta-felt. Den bor som Gutenberg-blokker på
CPT-siden.

Dette gjelder særlig:
- Sosial struktur / produsentfortelling
- Geografisk/klimatisk kontekst
- Kartdata (SVG eller Google Maps-embed, per side)
- Bilder av produsenter, landskap, prosess
- Sitater, intervjuer, historikk

Implementering overlates til redaksjonelt team — gjenbrukbare blokker og
block patterns som utgangspunkt, fri redigering per side. Ikke i teknisk scope.

---

## 2. Datamodell

### 2.1 Taxonomies (nye)

```
Taxonomy: origin_country
  tilknyttet: attribute_page-CPT
  typisk antall terms: 5–15
  formål: kanonisk landnavn, driver flagg-mapping
  term meta (valgfri, for senere):
    official_name, alternative_names

Taxonomy: certification
  tilknyttet: attribute_page-CPT (ikke produkter)
  typisk antall terms: 6–8
  formål: badges + fremtidig filtrering
  term meta:
    icon_svg_id, full_name, description, external_url
```

### 2.2 CPT `attribute_page` — eksisterende felter (uendret)

```
wp_posts (post_type = 'attribute_page')
├── post_title         "Peru Qori Inti"
├── post_name          "peru-qori-inti"  (match med term-slug på pa_opprinnelse)
├── post_content       Gutenberg-blokker (historie, kolonnekort, galleri, etc.)
├── post_excerpt       Kort tagline (brukes i blurb, kort og fallback)
├── post_thumbnail     Featured image

Eksisterende meta (behold):
├── region             (string)
├── smak               (string — frihåndsnotater, f.eks. "Mango, krem, macadamia")
├── _attribute_taxonomy       (intern)
└── _attribute_term_id        (intern)
```

### 2.3 Nye meta-felter

Empirisk validert mot 9 Silva-gårder. Felter som hadde 0/9 dekning er kuttet
(coordinates, harvest_season, classification, village). Felter som var
narrative i natur er flyttet til Gutenberg-blokker (social_structure,
lang geografisk kontekst).

```
# Cultivation
├── variety              string   "Chuncho"
│                                  (9/9 dekning)

# People & Producers
├── producer_type        enum     "single_estate" | "family_farm" |
│                                 "cooperative" | "social_enterprise" |
│                                 "smallholders"
│                                  (9/9 dekning, 5 verdier, filtrerbar via
│                                   apply_filters('wc_ras_producer_types'))
├── producer_count       int      null | 1 | 45 | 700
│                                  (3/9 dekning; null skipper visning.
│                                   Vises betinget — ikke for single_estate)

# Post-harvest
├── fermentation_type    enum     "centralized" | "decentralized" | "mixed"
│                                  (9/9 dekning, filtrerbar)
├── fermentation_days    int      null | 4 | 6 | 7
│                                  (8/9 dekning; null skipper visning)
├── fermentation_method  string   "3-tier cascade wooden boxes with pre-drainage"
│                                  (9/9 dekning som fritekst — ikke enum.
│                                   Kort setning, fra Silva-data)
├── drying_method        string   "Sun-dried on raised beds"
│                                  (9/9 dekning som fritekst)

# Flavour
├── taste_profile        json     {"acidity":6, "sweetness":7, "bitterness":4,
│                                  "body":5, "fruit":8, "floral":6,
│                                  "earth":3, "spice":4}
│                                  (0–10 per akse, 8 akser,
│                                   filtrerbar via apply_filters('wc_ras_taste_axes'))

# Fremtidig bruk (lagres, vises ikke i frontend før data er rik nok)
├── altitude             json     {"min":1200, "max":1450, "unit":"m"}
│                                  (0/9 per-gård i Silva-data. Lagres for
│                                   direkte-handel-partnere senere. Null
│                                   skipper visning, som producer_count)

# Referanser
└── id_archivo           string   "GT-047"
```

### 2.4 Hybrid rendering — strukturert felt + narrativ

For felter der vi har både et strukturert datapunkt *og* en fritekstbeskrivelse
(fermentering, tørking), rendres de i to-kolonne-layout i modal og på
CPT-side:

```
[ikon]  Label              Strukturert verdi
         (i18n)              (meta)
                            Fritekstbeskrivelse
                             (meta, fritekst)
```

Eksempel:

```
🫘  Fermentering          6 dager
                           3-tier cascade wooden boxes with pre-drainage

☀️  Tørking
                           Sun-dried on raised beds
```

Ikonene er hardkodede per felt-type i template. Labels er oversatte strings.
Verdier kommer fra meta.

### 2.5 Felter som er kuttet

Fra tidligere versjoner av modellen:

- `coordinates` — 0/9 dekning, dekkes av land+region
- `country_code` — erstattet av `origin_country`-taxonomy-slug
- `village` — 0/9 eksplisitt, implisitt i brødtekst når relevant
- `harvest_season` — 0/9, ikke forbrukerrelevant
- `classification` — 0/9, overflødig med `variety`
- `social_structure` — 9/9 dekning men narrativ; flyttet til Gutenberg-blokker
- `kart_svg_id` — håndteres per side via Gutenberg-blokk (SVG eller Gmaps)

### 2.6 Data-flyt til variasjon (uendret arkitektur)

```
Bruker velger variant
  ↓
WC-core ser select.change() på form.variations_form
  ↓
Serverside: woocommerce_available_variation-filter fyrer
  ├── variation-improvements.php (eksisterende, prio 10)
  ├── frontend-hooks.php (eksisterende, prio 10)
  └── origin-fields.php (ny, prio 20) ← wc_ras_origin-struktur
  ↓
Variasjons-JSON til klient, preloaded i data-product_variations
  ↓
WC fyrer found_variation + show_variation
  ↓
├── eksisterende RAS-lyttere
├── origin-blurb.js (ny)
└── origin-modal.js (ny)
```

**`wc_ras_origin`-struktur på variasjonsdata:**

```
wc_ras_origin = {
  // Identitet
  name, slug, excerpt, permalink,

  // For blurb (alltid)
  country,              // fra origin_country-term name
  country_flag_url,     // utledet fra term-slug
  region,
  variety,
  taste_notes,          // = eksisterende 'smak'-felt

  // For modal (alltid når modal åpnes)
  producer_type,
  producer_count,       // null mulig
  fermentation_type,
  fermentation_days,    // null mulig
  fermentation_method,  // fritekst
  drying_method,        // fritekst
  taste_profile,        // 8 verdier, JS genererer radar
  certifications,       // [{slug, name, icon_url}, ...]
  featured_image_url,

  // Metadata
  has_rich_page: true
}
```

---

## 3. Informasjonsarkitektur — hva vises hvor

### 3.1 Variasjonsblurb (produktside, i kjøpsflyten)

Vises når en variant er valgt. Oppdateres ved variantbytte.

- Flagg + land/region
- Varietet
- Frihåndsnotater (`smak`)
- Knapp: "Se opprinnelse" → modal
- Diskret lenke: "Les hele historien →" → CPT-side

Ikke: smaksradar, fermentering, sertifiseringer, altitude, producer_count.

### 3.2 Modal (åpnes fra blurb)

Native `<dialog>` med `showModal()`. Swipe mellom tilgjengelige opprinnelser
for produktet. "Velg opprinnelse" committer valget.

- Featured image
- Finca-navn + flagg/land/region
- Varietet
- Produsenttype + antall produsenter (hvis ≠ null og ≠ single_estate)
- Fermentering: type + dager + metode (hybrid-layout)
- Tørking: metode (hybrid-layout)
- 8-akset smaksradar (klientside fra `taste_profile`)
- Frihåndsnotater
- Sertifiserings-badges
- CTA: "Velg opprinnelse"
- Sekundær: "Les hele historien →"

Ikke: pris, lager (avhengig av størrelse-attributt), altitude (skjult til
data er rik nok).

**Swipe-semantikk:** endrer kun modalens viste kort. Committ skjer ved
eksplisitt "Velg opprinnelse"-klikk.

### 3.3 Arkivkort (på `/opprinnelser/`)

CPT-arkiv. Gjenbruker komponent-pattern fra
`wc_ras_render_attribute_term_index_grid()`, nå med CPT som datakilde.

- Featured image
- Finca-navn
- Flagg + land/region
- Varietet
- Tagline (`post_excerpt`)
- Produsenttype-ikon
- "Brukes i N sjokolader"

Hele kortet er klikkbart → CPT-side.

### 3.4 CPT-enkeltside (`/opprinnelser/{slug}/`)

**Strukturert header (PHP-rendret):**
1. Hero: featured image + navn + tagline + flagg/land/region
2. Producers & Cultivation: produsenttype + antall + varietet
3. Post-harvest: fermentering + tørking (hybrid-layout)
4. Flavour: 8-akset radar (PHP-rendret) + frihåndsnotater
5. Certifications: badges

**Gutenberg-innhold (`post_content`):**
- Halvstrukturerte kolonne-kort øverst (sted/produsenter/håndverk — fritt
  redaksjonelt)
- Kart (SVG eller Gmaps-embed)
- Historie, intervjuer, galleri, sitater
- Andre frie blokker

**Bunn:**
- "Sjokolader fra denne opprinnelsen" — relaterte produkter (eksisterende
  mønster, ikke del av denne modulen)

---

## 4. Implementeringsplan

Tre faser, mergeable uavhengig.

### Fase 1 — Datamodell og admin

1. Registrer nye `post_meta`-felter via RAS-hook-systemet
2. Registrer `origin_country`-taxonomy med flagg-lookup-helper
3. Registrer `certification`-taxonomy
4. Flipp CPT til `publicly_queryable => true`,
   `has_archive => 'opprinnelser'`
5. Admin-UI med grupperte meta-bokser (§5)
6. Cache-invalidering på `save_post_attribute_page`,
   `edited_origin_country`, `edited_certification`
7. Backfill-verktøy under Tools for eksisterende termer uten CPT
8. Migrer eksisterende term descriptions manuelt til `post_excerpt`

### Fase 2 — Templates

2a. **Arkiv** (`archive-attribute_page.php` + utvid kort-data-funksjonen med
    nye felt)
2b. **CPT-enkeltside** (`single-attribute_page.php` + hjelpere for strukturert
    header + PHP-render av radar-SVG)
2c. **Variasjonsblurb** (utvid `woocommerce_available_variation`, ny
    `origin-blurb.js` basert på `variation-display.js`)

### Fase 3 — Modal

- Native `<dialog>` + `showModal()`
- CSS `scroll-snap-type: x mandatory` for swipe
- Klientside radar-render fra preloadet `taste_profile`
- Event-flyt: swipe → oppdater modal, klikk "Velg" → `select.change()` → WC
  fyrer `found_variation` → alle lyttere synkes

### Fase 4 (relatert, ikke strengt del av RAS)

URL deep-linking: produktlenker fra lister skal bære
`?attribute_pa_opprinnelse={slug}` slik at variant forhåndsvelges. På
produktsiden oppdateres URL via `history.replaceState` ved variantbytte.
Dette er brukeropplevelses-arbeid som bør følge med lanseringen.

---

## 5. Admin-UX

Grupperte meta-bokser per tematisk område:

1. **Opprinnelse: Cultivation** (variety)
2. **Opprinnelse: People & Producers** (producer_type, producer_count)
3. **Opprinnelse: Post-harvest** (fermentation_type, fermentation_days,
   fermentation_method, drying_method)
4. **Opprinnelse: Flavour** (8-akset smaksprofil som number-inputs 0–10,
   eksisterende `smak`-felt for frihåndsnotater)
5. **Opprinnelse: Fremtidig** (altitude — lagres, vises ikke)
6. **Opprinnelse: Referanser** (id_archivo)

Land og sertifiseringer håndteres via standard taxonomy-UI (metabox til
høyre, som kategorier/tags).

**Custom field-typer:**

- **JSON-composite**: altitude, fermentation_days hvis range vurderes. To
  number-inputs som saneres til JSON
- **Enum/select** med filtrerbar verdiliste: producer_type, fermentation_type
- **8-akset smaksprofil**: number-inputs 0–10 per akse (ikke sliders — raskere
  ved masse-inntasting), aksenavn fra `apply_filters('wc_ras_taste_axes')`.
  Eventuelt live mini-radar som feedback
- **Fritekst kort**: fermentation_method, drying_method (vanlig text input,
  max ~120 tegn)

---

## 6. Ytelsesbudsjett

- Blurb-data per variasjon: ~200 bytes
- Modal-data per variasjon (inkl. taste_profile, certifications, excerpt):
  ~700 bytes
- **Totalt: ~900 bytes per variasjon, ~9 KB for 10 varianter**

Ingen inline SVG i variasjonsdata. Radar genereres klientside fra 8 tall.

Cache invalideres på save. Slug-lookup via `get_page_by_path()` uendret —
O(1) med indeks + object-cache.

---

## 7. Gjenværende spørsmål

Designer-/produkteier-avgjørelser:

1. **Smaksakser (navn og antall)** — 8 foreslåtte akser (acidity, sweetness,
   bitterness, body, fruit, floral, earth, spice). Ingen enhetlig
   bransjestandard finnes; ICCO, SCA og bean-to-bar-miljøet varierer fra
   8–14 akser. Bekreft med produkteier/smakspanel. Revisjon billig
   (filterbart).
2. **Flagg-SVG-sprite** — hvilke ~15 land, hvor ligger SVG-ene? Lanseringskost,
   ikke blokkerende for fase 1
3. **Variasjonsblurb-plassering** — `woocommerce_single_variation` (inne i
   variant) vs. `woocommerce_after_add_to_cart_form` (under skjema). Designer
   avgjør
4. **Kolonne-kort-pattern** på CPT-siden — gjenbrukbare blokker og/eller
   block patterns. Redaksjonell kultur-avgjørelse, ikke teknisk scope

---

## 8. Endringer fra v3

- Land blir egen taxonomy (`origin_country`) — erstatter `country` + `country_code`
- `fermentation_method` og `drying_method` blir fritekst, ikke enum (Silva-data
  er setninger, ikke verdier)
- `coordinates`, `harvest_season`, `classification`, `village` kuttet fra
  modellen helt (0/9 dekning)
- `social_structure` fjernet som meta, flyttet til Gutenberg-blokker
- `kart_svg_id` fjernet, håndteres som Gutenberg-blokk per side
- `altitude` beholdt i datamodell men ikke eksponert frontend (0/9 i Silva,
  relevant for direkte-handel senere)
- `producer_type` utvidet med `social_enterprise` og `family_farm`
- Hybrid rendering-mønster (felt + ikon + string) dokumentert for fermentering
  og tørking
- Halvstrukturerte blokker som prinsipp på CPT-siden
- Admin-UX spesifiserer number-inputs, enum-verdier listet, filterbarhet
  dokumentert
- WPML/Polylang-kompatibilitet eksplisitt ute av scope (norsk site,
  engelske interne slugs, POT-fil)
- URL deep-linking dokumentert som relatert arbeid
