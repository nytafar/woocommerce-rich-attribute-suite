# Opprinnelse-utvidelse — arkitekturanalyse

**Formål:** Utforsking av eksisterende Rich Attribute Suite (RAS) som grunnlag for
ny opprinnelses-modul (strukturerte felter på `pa_opprinnelse`, rikt arkiv,
CPT-side, variasjons-blurb + modal med smaksradar og swipe).

**Kildegrunnlag:** Analyse av plugin-kode i
`/var/www/staging.myrvann.no/htdocs/wp-content/plugins/woocommerce-rich-attribute-suite/`
og tema-koden i `/var/www/staging.myrvann.no/htdocs/wp-content/themes/ousia/`
(hovedtema, bekreftet via `style.css`) og `themes/myrvann/` (trolig child-tema).

**Status:** Ren analyse og plan. Ingen kode er endret; denne rapporten er det
eneste som er lagt til i repoet.

---

## 1. Sammendrag

### Hva kodebasen faktisk er

RAS er en ren PHP/WordPress-plugin uten byggesystem — ingen `composer.json`,
ingen `package.json`, ingen `node_modules`. JS er vanilla IIFE + jQuery der
WooCommerce krever det. Plugin bygger på WordPress-native primitiver:

- **Én CPT** `attribute_page` (registrert i `includes/cpt-attribute-page.php:15-56`).
- **Koblet til attributt-termer via slug-match** — ingen ORM, ingen
  relasjonstabell. Lookup går via `get_page_by_path($term->slug, OBJECT,
  'attribute_page')` både i sync-løkka (`cpt-attribute-page.php:155`) og i
  frontend-cache (`frontend-hooks.php:118`).
- **All rik data lagres på CPT-ens `post_meta`, ikke på `wp_termmeta`.**
  Bare to meta-nøkler er registrert i dag: `region` og `smak`, begge
  enkle strenger med `show_in_rest => true` (`cpt-attribute-page.php:108-134`).
- **Tre utvidelseskroker** for å registrere, rendere og lagre egne felter:
  `wc_ras_register_attribute_page_meta_fields`,
  `wc_ras_attribute_page_meta_box_fields`, `wc_ras_save_attribute_page_meta`.
- **Variasjons-description-fallback** skjer serverside via
  `woocommerce_available_variation`-filteret
  (`variation-improvements.php:33`). Dette er det naturlige
  integrasjonspunktet for den nye blurben og modalen.

### Hva som passer naturlig inn

Den nye `pa_opprinnelse`-utvidelsen passer ekstremt godt i det eksisterende
mønsteret — den er faktisk en større versjon av det `region` og `smak` gjør
i dag. De nye strukturerte feltene hører hjemme som flere `post_meta`-nøkler
på samme CPT, registrert gjennom det eksisterende hook-systemet. Arkiv-gridden
for opprinnelser finnes allerede delvis som
`wc_ras_render_attribute_term_index()` i `includes/attribute-term-index.php`.

### Hva som krever valg

Fire ting trenger eksplisitt avgjørelse før implementering (prioritert i §4):

1. **"CPT-single" er i dag taxonomy-arkivsiden, ikke en egen CPT-URL.**
   `attribute_page` har `publicly_queryable => false`
   (`cpt-attribute-page.php:40`). Det betyr at `/produkt-attributt/opprinnelse/
   colombia-betulia/` er en taxonomy-term-URL som injiserer CPT-innholdet —
   det er *ikke* en CPT-single. Hvis du vil ha den rike strukturen + Gutenberg-
   blokker på én URL, kan dagens mønster beholdes (og utvides), men begrepet
   "CPT-single" er misvisende. Avgjøres før template-arbeid.
2. **Sertifiseringer: `post_meta` (JSON-array av ID-er) eller egen taxonomy?**
   Avhenger av om sertifiseringer skal filtreres på tvers av produkter.
3. **Komposittdata (koordinater, høyde-range, gjæringsdager, 8-akset smak):**
   enkel meta-nøkkel med JSON-encoded verdi, eller flere atomære meta-nøkler?
   Plugin har ingen eksisterende presedens for verken, så valget definerer
   mønsteret framover.
4. **Modalens "Velg opprinnelse": skal swipe i modalen _umiddelbart_ endre
   produktets variasjon, eller først ved eksplisitt klikk?** Påvirker
   event-flyten.

### En korreksjon til antakelser i brief-en

> "RAS håndterer allerede viderelevering av attribute term description til
> variation description på variable products, med lenke til en CPT-side knyttet
> til term."

Første del stemmer, men formuleringen antyder at utvidelsene lagres på
**termen**. Det gjør de ikke — i RAS ligger alle strukturerte felter på
`attribute_page`-CPT-ens `post_meta`. Selve termens `description`-felt brukes
bare som et _fallback-nivå_ i prioriteringskjeden (se §2.3). Hele
implementeringsplanen under følger CPT-mønsteret fordi det er det plugin
allerede gjør.

---

## 2. Nåværende arkitektur

### 2.1 Datamodell

```
┌──────────────────────────────────────────────┐
│ WooCommerce attributt-taxonomy               │
│ (wp_terms + wp_term_taxonomy)                │
│                                              │
│  pa_opprinnelse                              │
│  └─ term: colombia-betulia                   │
│     ├─ name: "Colombia Betulia"              │
│     ├─ slug: "colombia-betulia"              │
│     └─ description: (fallback bare)          │
└──────────────────┬───────────────────────────┘
                   │ slug-match (kun én vei)
                   │ get_page_by_path($slug, OBJECT, 'attribute_page')
                   │ cpt-attribute-page.php:155, frontend-hooks.php:118
                   ▼
┌──────────────────────────────────────────────┐
│ CPT: attribute_page                          │
│ publicly_queryable: false                    │
│ show_in_rest: true                           │
│ supports: title, editor, thumbnail, excerpt  │
│                                              │
│  post (slug-match med termen)                │
│  ├─ post_title:   "Colombia Betulia"         │
│  ├─ post_name:    "colombia-betulia"         │
│  ├─ post_content: Gutenberg-blokker          │
│  ├─ post_excerpt: kort tekst (brukes som     │
│  │                fallback i variasjon)      │
│  ├─ post_thumbnail: featured image           │
│  └─ post_meta:                               │
│     ├─ region                    (string)    │
│     ├─ smak                      (string)    │
│     ├─ _attribute_taxonomy       (intern)    │
│     └─ _attribute_term_id        (intern)    │
└──────────────────┬───────────────────────────┘
                   │ registrert via filter
                   │ woocommerce_available_variation (prio 10)
                   │ variation-improvements.php:33,
                   │ frontend-hooks.php:284
                   ▼
┌──────────────────────────────────────────────┐
│ WC-variasjon (serverside JSON)               │
│                                              │
│  variation_data[]                            │
│  ├─ variation_description (fallback-kjede)   │
│  ├─ attribute_region                         │
│  ├─ attribute_smak                           │
│  └─ wc_ras_inline_description: true          │
└──────────────────┬───────────────────────────┘
                   │ jQuery found_variation/show_variation
                   ▼
┌──────────────────────────────────────────────┐
│ Klient-JS (inline-variation-description.js,  │
│ variation-display.js)                        │
└──────────────────────────────────────────────┘
```

**Merknader:**

- **`_attribute_term_id` og `_attribute_taxonomy`** lagres på CPT-en ved
  auto-opprettelse (`cpt-attribute-page.php:168-169`), men brukes kun for
  reverse-oppslag i admin. Alle frontend-oppslag går via slug. Hvis termen
  eller CPT-ens slug endres etter opprettelse, brytes koblingen.
- **Auto-opprettelse** skjer kun ved `created_{taxonomy}` (linje 143-185),
  ikke ved aktivering av pluginen. Eksisterende termer uten CPT blir ikke
  opprettet retroaktivt.
- **Ingen migrering/versjonering** av schema. Kun rewrite-rules flushes
  ved versjonsbump (`frontend-hooks.php:82-91`).
- **Ingen WPML/Polylang-kode** tross README-påstand. Flerspråklighet
  fungerer kun passivt via at slug matches per språk (hver oversettelse må
  ha egen CPT + egen term med samme slug).

### 2.2 Felt-registreringsmønsteret

Registrering, rendering og lagring er tre separate action-hooks som utvikleren
må binde seg på. Mønsteret fra `cpt-attribute-page.php:108-134` og
`admin-hooks.php:152-239`:

| Steg | Hook | Formål |
|---|---|---|
| 1 | `init` → `wc_ras_register_attribute_page_meta_fields` | `register_post_meta()` |
| 2 | `wc_ras_attribute_page_meta_box_fields` | Rendre `<tr>` i meta-boksen |
| 3 | `wc_ras_save_attribute_page_meta` | `update_post_meta()` ved lagring |

**Observerte konvensjoner:**

- Meta-nøkler har **ingen prefiks** (bare `region`, `smak`). README-eksempelet
  viser `altitude` — samme mønster.
- Alle felter bruker `single => true` og `show_in_rest => true`.
- Nonce + `current_user_can('edit_post')` håndteres sentralt i save-handleren
  (`admin-hooks.php:213-218`), deretter fires `wc_ras_save_attribute_page_meta`
  med `$post_id`. Utvidelser trenger derfor ikke duplisere kapabilitetssjekk,
  men må gjøre egen `sanitize_*` og `$_POST`-uthenting.
- Ingen eksisterende presedens for JSON-encoded verdier eller
  array-verdier (alt er `type => 'string'`). Dette mønsteret må etableres.

### 2.3 Term description → variation description

Prioriteringskjeden implementeres i
`variation-improvements.php:86-203` (`variation_description_fallback`):

1. Eksisterende `$variation_data['variation_description']` (WC-native) → brukes
   hvis satt.
2. `$term->description` (attributt-termens description-felt) → brukes som neste
   fallback, linje 119.
3. CPT `post_excerpt` → `post_content` trunkert til 30 ord, linje 148-151.

Ekstra meta-felter (`region`, `smak`) bakes inn som egne nøkler på
`$variation_data` via `frontend-hooks.php:271-283` — ikke inn i
description-feltet.

**Inline-varianten** (`inline-variation-description.php`) slår bare på et
annet _render-mønster_: WC-templaten `variation.php` overstyres til å fjerne
description-div-en, en skjult rad injiseres i variasjonstabellen, og
`inline-variation-description.js` fyller raden på `found_variation`. Samme
serverside-data; annen DOM-destinasjon. Aktiveres kun når temaet setter
`wc_ras_enable_inline_variation_description` til true.

### 2.4 Template-stacken

Plugin overstyrer taxonomy-templatene, men leverer også templates som tema
kan overstyre. Mønsteret ligger i
`frontend-hooks.php:132-161`
(`wc_ras_override_attribute_archive_template`):

1. Sjekk om temaet har `taxonomy-pa_{attribute}.php` eller
   `taxonomy-product-attribute.php` → bruk tema-versjonen.
2. Fallback til plugin-templaten `templates/taxonomy-product-attribute.php`.

Plugin-templaten bruker `get_header('shop')` / `get_footer('shop')`
(linje 13, 149). Ousia har ingen egen `header-shop.php`, så WP faller tilbake
til `header.php` — det funker, men skaper en myk kobling.

Kortgridden på arkivnivå finnes allerede som
`wc_ras_render_attribute_term_index()` i `includes/attribute-term-index.php`
(linje 186-211, hjelpere linje 74-106) — den er i dag innrettet mot
`pa_opprinnelse` som default, med filter-overstyring
`wc_ras_attribute_term_index_taxonomy`. Kort-datapakken kommer fra
`wc_ras_get_attribute_term_card_data()` (linje 74-106) og inneholder i dag
`image_html`, `name`, `region`, `smak`, `description`, `page_id`. Utvidelse
for nye felter er en én-liners endring av den funksjonen (eller et
`apply_filters` for å gjøre det åpent).

### 2.5 JavaScript-mønsteret og variasjons-events

Plugin-JS er tre frittstående IIFE-er med jQuery som avhengighet pga.
WooCommerce-kompatibilitet:

| Fil | Hendelser som lyttes på | Hva gjør den |
|---|---|---|
| `assets/js/inline-variation-description.js` | `found_variation`, `show_variation`, `reset_data`, `hide_variation` | Animerer inline-rad med variasjons-beskrivelse |
| `assets/js/variation-display.js` | `found_variation`, `reset_data` | Legger `.variation-meta-region` / `.variation-meta-smak` i `.summary` |
| `assets/js/admin-quick-edit.js` | Admin-only, irrelevant her | — |

**Viktig funn for modalens integrasjonspunkt:** WooCommerces egen
variasjonsselektor fyrer jQuery-eventer på `form.variations_form` med
hele variasjons-objektet som andre argument. Alle plugin-data som er lagt
til via `woocommerce_available_variation`-filteret ligger allerede på det
objektet. **Det trengs ingen ekstra REST-endepunkt for modalen** — dataen
er der når klienten får variasjonene.

Programmatisk valg av variasjon skjer ved å sette verdien på
`select[name="attribute_pa_opprinnelse"]` og trigge `.change()`. WC sin
interne lytter fyrer så `found_variation`/`show_variation` på
variations_form, som alle lyttere (inkludert plugin-JS og modal) plukker opp.
Dette er mønsteret "Velg opprinnelse"-knappen skal bruke.

### 2.6 Caching og invalidering

To cache-grupper med forskjellig TTL, ingen eksplisitt invalidering:

- `wc_ras_attribute_pages` (ingen TTL) — `cpt-attribute-page.php:290, 295`
- `wc_ras_attribute_page` (HOUR_IN_SECONDS) — `frontend-hooks.php:114-120`

Ingen `save_post`/`edit_term`-hooks kaller `wp_cache_delete`. Oppdateringer
forblir i cache til gruppen flushes (hele object-cachen) eller til
time-TTL-en går ut. Dette er greit for lesingsdominerte sider, men innfører
"stale-after-edit" som må dokumenteres — eller forbedres — når flere felter
kommer til.

### 2.7 Block editor / Gutenberg

Per grep i kildekoden er **ingen custom Gutenberg-blokker registrert**.
Redaksjonelt innhold ligger i CPT-ens `post_content` som vanlige
Gutenberg-blokker, med støtte aktivert via `show_in_rest => true`. Den
strukturerte header-delen er altså i dag ikke en blokk — den er i praksis
noe templaten/render-funksjoner må legge over content-en.

### 2.8 Eksisterende modal-/swipe-infrastruktur

Ingen modal-infrastruktur i pluginen. I Ousia/myrvann-temaet finnes
(iflg. agent-søk) et menu-drawer-mønster (`menu.js` linje 20-204) og
momentum-scroll i `main.js`. Det er nyttig inspirasjon, men hverken generisk
nok til gjenbruk eller nødvendig å reimplementere — native `<dialog>`
håndterer alt vi trenger uten avhengigheter. Carousel kan løses med
CSS `scroll-snap-type: x mandatory`.

---

## 3. Anbefalt implementeringsplan

Rekkefølge: **datamodell → templates → modal**. Hvert trinn kan merges
separat.

### 3.1 Fase 1 — Datamodell (trygt, lav risiko)

**Mål:** Registrere alle strukturerte felter som `post_meta` på
`attribute_page`-CPT-en, uten å bryte eksisterende `region`/`smak`.

**Gjøres i:** nytt filnavn `includes/opprinnelse-fields.php`
(inkluderes fra `woocommerce-rich-attribute-suite.php`). Isolerer alle
kakao-spesifikke felter bak en check som kun aktiverer seg for
`pa_opprinnelse`-koblede CPT-er (via `_attribute_taxonomy`-meta), slik
at andre attributter ikke forurenses.

**Felt-tabell (foreslåtte nøkler og lagringsform):**

| Gruppe | Meta-nøkkel | Form | Kommentar |
|---|---|---|---|
| Geografi | `country` | string | ISO-kode (`CO`) eller klartekst — se §4.5 |
| | `region` | string | **finnes allerede** — ikke endre |
| | `village` | string | |
| | `coordinates` | JSON-string `{"lat":5.2,"lng":-73.5}` | Atomær oppdatering, én kilde til sannhet |
| | `altitude` | JSON-string `{"min":1200,"max":1600,"unit":"m"}` | Unit-nøkkelen gjør flere enheter mulig senere |
| Varietet | `variety` | string | |
| | `classification` | string (enum) | Fast verdiliste via filter |
| | `harvest_season` | string | Klartekst (f.eks. "juli–september") |
| Produsent | `producer_type` | string (enum) | farm/cooperative/trader/... |
| | `producer_count` | int | `type => 'integer'` i register_post_meta |
| Fermentering | `fermentation_type` | string (enum) | |
| | `fermentation_days` | JSON-string `{"min":4,"max":7}` | |
| | `fermentation_method` | string | Fritekst/enum |
| Tørking | `drying_method` | string (enum) | |
| Smak | `taste_profile` | JSON-string, 8 verdier 0-10 | Se §4.4 om akser |
| Sertifisering | `certifications` | **se §4.2** | JSON-array av ID-er eller taxonomy-relasjon |
| Redaksjon | `post_content` | Gutenberg | Finnes allerede |
| | `post_thumbnail` | attachment ID | Finnes allerede |
| Eksterne ID-er | `id_archivo` | string | |
| | `kart_svg_id` | string | |
| | `smak` | string | **finnes allerede** — ikke endre (kan være legacy, se §4.6) |

**JSON-i-streng-konvensjon:** Alle kompositte verdier lagres som
JSON-encoded strenger i en enkelt meta-nøkkel, med `type => 'string'`,
`single => true`, `show_in_rest => true`, og `sanitize_callback` som
valider-og-normaliserer JSON-en (parse, valider keys, re-encode).
Begrunnelse:

- Atomær oppdatering (ingen ut-av-synk-tilstand mellom `latitude`/`longitude`).
- Enkel serialisering til variasjons-filter og modal-klient.
- Mønsteret er nytt for pluginen — dokumenter det i fil-header-kommentar.

**Cache-hensyn:** Legg til en `save_post`-hook for `attribute_page` som
kaller `wp_cache_delete()` på begge cache-gruppene med
`md5($post->post_name)` som nøkkel. Dagens mangel på invalidering blir
smertefull når flere felter oppdateres oftere.

**Migrering for eksisterende termer:** Bygg et admin-verktøy
(Tools → "RAS: Backfill attribute pages") som itererer over alle termer
i `pa_opprinnelse` og oppretter CPT-er for de som mangler. Alternativt:
kjør sync-funksjonen (`wc_ras_sync_attribute_pages_on_term_create`) på
etterspørsel via admin-knapp. **Ikke bygg automatisk backfill ved
plugin-aktivering** — for risikabelt på store sites.

**Trygt å gjøre nå:** felt-registrering, sanitiseringsfunksjoner,
admin-UI for enkle string-felter.

**Krever avklaring før kode:** sertifiseringsmodell (§4.2),
JSON-vs-atomær (§4.3), akse-navn på smaksprofil (§4.4).

### 3.2 Fase 2 — Templates og rendering (middels risiko)

Fase 2 deles i tre leveranser som kan landes uavhengig.

#### 3.2a Arkiv-grid

**Gjenbruker:** `wc_ras_render_attribute_term_index()` i
`includes/attribute-term-index.php:186-211`. Den eksisterer allerede.

**Endringer:**

1. `wc_ras_get_attribute_term_card_data()` (linje 74-106): legg til
   `country`, `altitude`, `variety`, kort `tagline` (hentet fra
   CPT `post_excerpt`). Implementer som `apply_filters('wc_ras_card_fields',
   $data, $term, $page_id)` så kortet er utvidbart uten flere endringer her.
2. `templates/attribute-term-index.php`: legg til markup for de nye feltene
   (flagg via enten SVG-assets eller `<img>` mot en `country`-kode).
3. `assets/css/attribute-term-index.css`: utvid uten å duplisere —
   CSS-custom-properties (`--term-cards-*`) er allerede etablert.

**Ikke gjør:** Ikke bytt ut shortcode-mønsteret med et nytt.
`[attribute_term_index]` er allerede publiserbart og skal fortsatt virke.

#### 3.2b "CPT-single" (taxonomy-arkivet med strukturert topp)

**Viktig:** Som flagget i §1 er dette *taxonomy-term-arkivsiden*, ikke en
egen CPT-URL. Beholder du dagens mønster, rendrer siden:

1. Den nye _strukturerte header-en_ (flagg, region-breadcrumb, koordinater,
   høyde, varietet, klassifisering, produsent-info, gjæring, tørking,
   sertifiserings-badges, 8-akset radar-SVG).
2. CPT-ens `post_content` (Gutenberg-blokker, fritt redaksjonelt innhold).
3. Woo-produktloopen (hvis ønskelig — den er der i dag).

**Anbefalt implementering:**

- Legg strukturert header som en PHP-hjelperfunksjon
  `wc_ras_render_opprinnelse_header($post_id)` i `includes/opprinnelse-fields.php`.
  Hjelperen leser alle nye meta-nøkler og skriver ut semantisk HTML med
  BEM-klasser (f.eks. `.ras-opprinnelse-header__altitude`).
- Kall den i `templates/taxonomy-product-attribute.php` før `the_content()`,
  innpakket i `if (taxonomy === 'pa_opprinnelse')`-guard så andre attributter
  ikke påvirkes.
- Radar-diagrammet: **inline SVG generert serverside** av en funksjon
  `wc_ras_render_taste_radar_svg($taste_profile_array)`. Begrunnelse:
  - Pluginen har ingen byggeprosess.
  - Diagrammet er statisk (ingen interaktivitet trengt for MVP).
  - Genereres én gang per request, sendes inline → ingen JS-parsing på klient.
  - Kan senere beriges med JS-animasjon (progressivt) uten å bytte arkitektur.
- Feltstøtte i block editor: vurder i fase 2.5 en valgfri Gutenberg-blokk
  "Opprinnelse-header" som kaller samme PHP-render-funksjon via
  `render_callback`. Ikke kritisk for MVP.

**Avgjørelse som må tas:** Skal vi flippe `publicly_queryable => true` på
CPT-en og dermed gi den en egen URL (`/attribute-page/colombia-betulia/`),
eller beholde dagens mønster hvor taxonomy-URL-en er den offentlige siden?
Se §4.1.

#### 3.2c Variasjons-blurb

**Integrasjonspunkt:** `woocommerce_available_variation`-filteret
(`variation-improvements.php:33`). Utvid `variation_data`-arrayen med
nye nøkler for blurben og modalen:

```
$variation_data['wc_ras_origin'] = [
    'country'       => ...,
    'country_code'  => ...,  // for flagg-render
    'region'        => ...,
    'variety'       => ...,
    'altitude'      => decode(altitude),
    'taste_top3'    => top-3-aksene fra taste_profile,
    'modal_url'     => permalink til term/CPT,
    // alt modalen trenger:
    'taste_profile' => full 8-akset array,
    'radar_svg'     => wc_ras_render_taste_radar_svg(...),
    'producer'      => [...],
    'fermentation'  => [...],
    'drying'        => ...,
    'certifications'=> [...],
    'excerpt'       => wp_trim_words($page->post_content, 50),
];
```

**Render av blurb:** Ny fil `assets/js/origin-blurb.js`, mønsteret
kopiert fra `variation-display.js:17-34`. Lytter på `found_variation`,
rendrer en `<div class="ras-origin-blurb">` i en container som plugin
setter inn i produktsummaryen — helst via et `woocommerce_single_product_summary`-action på en rimelig prioritet, eller via ren JS-injeksjon
hvis hook-plassering er usikker.

**Serverside-tilstandsgardering:** Kun rendres hvis produktet har
`pa_opprinnelse` blant variasjons-attributtene. Sjekk dette i enqueue-
funksjonen og kort ut hvis ikke.

### 3.3 Fase 3 — Modal og swipe (høyest risiko, krever mest avklaring)

**Design-grunn:** Alle data modalen trenger kommer allerede inline i
`form.variations_form[data-product_variations]` etter fase 2c. Ingen REST,
ingen wp_localize_script for modal-spesifikk data — det WC allerede gjør er
nok.

**Anbefalt stack:**

| Komponent | Valg | Begrunnelse |
|---|---|---|
| Modal-container | Native `<dialog>` + `showModal()` | Gratis fokus-trap, Escape-binding, background-inert; ingen JS-lib |
| Swipe-carousel | CSS `scroll-snap-type: x mandatory` + prev/next-knapper | 0 KB lib; tastatur-bruker får knapper; touch får native snap |
| Drag-variant (valgfritt) | Vanilla touch-handlers, samme mønster som temaets `main.js` | Kun hvis brukertesting sier scroll-snap alene er for subtilt |
| Radar-diagram | Samme inline-SVG fra serverside (fase 2b) | Samme render-funksjon, samme data-kilde |
| State-sync | jQuery events fra `form.variations_form` | Plugin-JS, inline-description-JS og modal lytter på samme `found_variation` → alt synkes gratis |

**Event-flyt ved "Velg opprinnelse"-klikk i modalen:**

```
Klikk på "Velg opprinnelse" i modal-kortet for Peru
  ↓
modal-JS: form.find('select[name="attribute_pa_opprinnelse"]')
          .val('peru-qoriinti').change()
  ↓
WC interne add-to-cart-variation.js plukker opp change
  ↓
Ajax → server → woocommerce_available_variation-filter fyrer på nytt
  ↓
WC fyrer jQuery found_variation + show_variation på form
  ↓
├── inline-variation-description.js oppdaterer inline-raden
├── origin-blurb.js oppdaterer blurben under variasjonsvelgeren
└── origin-modal.js oppdaterer modal-kortet (viser nå Peru)
```

Modalen _velger_ ikke variasjonen direkte; den trigger en endring på selven,
og mottar resultatet på samme måte som alle andre lyttere. Det gir én kilde
til sannhet og tåler at WC oppdaterer sin interne ajax-flyt.

**A11y-sjekkliste:**

- `<dialog>` + `showModal()` gir focus-trap og Escape.
- Første fokus settes eksplisitt (close-knapp eller "Velg"-knapp).
- `aria-live="polite"` på en skjult region for å annonsere "Nå viser opprinnelse X".
- Knapper, ikke `<a>` eller `<div>`, for alt interaktivt.

**Deep-linking (valgfritt, men billig):** På `found_variation` kan URL-en
oppdateres med `history.replaceState` så variasjonsvalget er shareable.
Ikke kritisk for MVP — se §4.10.

**Trygt å gjøre nå:** bygge `<dialog>` + kortmarkup + scroll-snap + lytter
på `found_variation`. Samme avhengigheter som alle andre plugin-JS-filer.

**Krever avklaring:** §4.7–§4.10.

---

## 4. Åpne spørsmål

Prioritert etter hva som blokkerer hvilken fase.

### Prioritet 1 — blokkerer fase 2 (templates)

**4.1 Skal `attribute_page` CPT bli `publicly_queryable => true`?**

I dag: taxonomy-URL-en (`/produkt-attributt/opprinnelse/colombia-betulia/`)
er den offentlige adressen, CPT-en har ingen egen URL.

Alternativer:

- **A. Behold som i dag.** Strukturert header + Gutenberg-blokker rendres
  på taxonomy-arkivsiden. SEO-kanonisk URL er term-URL-en.
- **B. Flipp til `true`.** CPT får egen URL `/attribute-page/colombia-betulia/`
  (eller custom rewrite). Term-URL og CPT-URL blir to forskjellige sider —
  må håndtere canonical redirect for å unngå duplikatinnhold.

Anbefaling: **A**, siden alle lenker som genereres i dag går til term-URL-en
(via `get_term_link()`) og hele pluginens logikk er bygget rundt det.
Kostnaden ved B er kompleks rewrite + canonical-håndtering uten tydelig
gevinst.

**4.2 Sertifiseringer: `post_meta` JSON-array eller egen taxonomy?**

- **A. JSON-array i `certifications`-meta.** Enkelt, matcher andre
  JSON-felter. Ingen native WP-filtrering på tvers av produkter.
- **B. Ny taxonomy `pa_certification` eller `certification`.** Gjør det
  mulig å filtrere produkter etter sertifisering, få arkivsider, bruke
  samme RAS-mønster (CPT bak termen).
- **C. WPML-safe hybrid:** Taxonomy + språk-per-term.

Anbefaling: **B** hvis sertifiseringer noensinne skal vises på kort-kort
eller filtreres i shop-listen. **A** hvis sertifiseringer kun vises i
modalen/CPT-single og aldri filtreres. Spør kunde/produkteier.

**4.3 Komposittdata: JSON-i-streng eller atomære felter?**

Koordinater, høyde-range, gjæringsdager — én meta-nøkkel med JSON eller
to med `_min`/`_max`? Rapporten anbefaler **JSON**, men dette etablerer et
helt nytt mønster for pluginen (alt i dag er atomære strenger).

Anbefaling: **JSON** for komposittene ovenfor, med en
`sanitize_callback` som parser + re-encoder. Grunner: atomær oppdatering,
utvidbarhet (enhet kan legges til senere), klient trenger uansett
JSON-parsing. Men dokumenter mønsteret i `includes/opprinnelse-fields.php`
header-kommentar.

**4.4 Smaksprofil: antall akser og navn?**

Brief-en sier 8-akset. Vi trenger en ordnet liste med nøkler og etiketter.
Eksempel: `[syrlighet, sødme, kropp, bitterhet, frukt, blomst, jord, krydder]`.

Anbefaling: definer et *default sett* hardkodet i pluginen men
gjør det filtrerbart:

```php
apply_filters('wc_ras_taste_axes', [
    'acidity'    => __('Syrlighet',  'wc-rich-attribute-suite'),
    'sweetness'  => __('Sødme',      'wc-rich-attribute-suite'),
    // ...
]);
```

Dette gjør det mulig for tema/kunde å overstyre etiketter uten plugin-endring.

**4.5 Flagg-rendering: SVG-assets, emoji, eller `<img>` mot country-code?**

Trivielt visuelt, men påvirker hva som lagres i `country`. Emoji
(`🇨🇴`) er enkelt men har inkonsistent rendering på tvers av plattformer.
SVG-bundle gir kontroll men øker asset-størrelse. Anbefaling: **SVG-sprite
av ~20 flagg** (de produsentlandene som er aktuelle) pluss ISO-kode som
`country_code`-meta. Definer hvilke land som er i scope før fase 2a.

**4.6 Legacy `smak`-feltet: hva skal skje med det?**

`smak` er i dag en enkel streng. Med 8-akset smaksprofil blir det delvis
overflødig. Alternativer:

- Behold som kort tagline for kort-gridden.
- Deprekér og migrer innholdet inn i `tagline` + slett fra admin-UI.

Anbefaling: behold midlertidig som "kort smaksbeskrivelse" (fritekst),
uavhengig av den 8-aksede profilen. Ikke migrer nå.

### Prioritet 2 — blokkerer fase 3 (modal)

**4.7 Variasjons-blurbens plassering i produktsummaryen.**

Over eller under add-to-cart? Over variasjonstabellen eller under?
Påvirker hvor hook-en festes:
`woocommerce_single_variation` (inni varianten),
`woocommerce_before_add_to_cart_button`,
`woocommerce_after_add_to_cart_form`.

Anbefaling: **under add-to-cart-formen** (hook
`woocommerce_after_add_to_cart_form`), så blurben blir "lære-mer"-ruta
etter den primære handlingen. Men dette er ren UX-avgjørelse.

**4.8 Modalens trigger: auto-åpne eller eksplisitt knapp?**

- A. Knapp i blurben ("Se opprinnelse") — eksplisitt
- B. Auto-åpne første gang en opprinnelse velges — påtrengende
- C. Hover/fokus-preview → klikk for full modal

Anbefaling: **A**. Respekterer bruker.

**4.9 Swipe-semantikk i modalen: endrer umiddelbart eller venter på "Velg"?**

- A. Hver swipe fyrer umiddelbart change på variasjonsvelgeren → produktet
  oppdateres i sanntid mens brukeren swiper. Kan bli overveldende.
- B. Swipe endrer bare modalens viste kort; først når "Velg opprinnelse"
  klikkes, committes valget til produktsvelgeren.

Anbefaling: **B**. Brief-en beskriver "Velg opprinnelse" som eksplisitt
handling — det antyder B. Men bekreft.

**4.10 URL deep-linking?**

Skal `?variation=peru-qoriinti` eller `?opprinnelse=peru-qoriinti` oppdateres
i URL når valg committes, slik at lenker kan deles? Modal-åpen-state
i URL (`?origin_modal=1`)?

Anbefaling: oppdater variasjons-slug i URL (billig, shareable), men
ikke modal-åpen-state (overkomplisert, MVP kan klare seg uten).

### Prioritet 3 — ikke-blokkerende, men bør vurderes før produksjon

**4.11 Cache-invalidering på save_post.**

I dag: ingen. Med 17 nye felter blir stale-cache-problemet merkbart.
Legg til `save_post_attribute_page`-hook som sletter begge cache-gruppene.

**4.12 Migrering/backfill for eksisterende termer.**

Hvordan opprette CPT-er for termer som ble opprettet _før_
sync-hooken ble registrert? Admin-knapp under Tools anbefales. Ingen
auto-kjøring ved aktivering (for risikabelt).

**4.13 WPML/Polylang-eksplisitt støtte?**

Hvis dette er en flerspråklig side (norsk + engelsk?), vurder om
RAS skal få eksplisitte hooks (`pll_translate_url`, `wpml_object_id`)
eller om slug-matching per språk er tilstrekkelig. Stillingstaking
påvirker hvor mye arbeid som skal til for ikke-norske opprinnelsessider.

**4.14 Variasjonsdata-størrelse.**

Per fase 2c legger vi ~10 nye nøkler på hver variasjon, inkludert
en inline radar-SVG (~500-1000 bytes) og en 50-ords excerpt. Estimat:
+1-1.5 KB per variasjon. For produkter med 10+ variasjoner blir det
10-15 KB ekstra i sideladning. Ikke kritisk, men mål før lansering; hvis
for tungt, flytt SVG + excerpt til lazy-load på modal-åpning via REST.

---

## 5. Appendiks per spor

Alle filreferanser er relativt til plugin-rot
`/var/www/staging.myrvann.no/htdocs/wp-content/plugins/woocommerce-rich-attribute-suite/`
med mindre annet er oppgitt.

### 5.1 Appendiks A — Datamodell (detalj)

**CPT-registrering:**
`includes/cpt-attribute-page.php:15-56` — registrerer `attribute_page` med
`publicly_queryable => false`, `show_in_rest => true`, støtte for title,
editor, thumbnail, excerpt.

**Meta-registrering (eksisterende felter):**
`cpt-attribute-page.php:108-134` — `region` og `smak` registreres med
`type => 'string'`, `single => true`, `show_in_rest => true`,
`sanitize_callback => 'sanitize_text_field'`. Linje 132 fyrer
`do_action('wc_ras_register_attribute_page_meta_fields', 'attribute_page')`
for utvidelser.

**Auto-sync term → CPT:**
`cpt-attribute-page.php:143-185` — `wc_ras_sync_attribute_pages_on_term_create`,
hook `created_{taxonomy}`. Oppretter CPT med `post_name = term->slug`
(linje 160), setter `_attribute_taxonomy` og `_attribute_term_id` som
reverse-referanser (linje 168-169).

**Slug-oppslag term → CPT:**
`cpt-attribute-page.php:288-299` (`wc_ras_get_attribute_page`) og
`frontend-hooks.php:113-124` (`wc_ras_get_cached_attribute_page`).
Begge bruker `get_page_by_path($slug, OBJECT, 'attribute_page')`.

**Admin meta-boks:**
`admin-hooks.php:152-162` (registrering),
`admin-hooks.php:169-204` (rendering, med `do_action(
'wc_ras_attribute_page_meta_box_fields', $post)` linje 203),
`admin-hooks.php:211-239` (lagring, med
`do_action('wc_ras_save_attribute_page_meta', $post_id)` linje 237).

**Variation description-fallback:**
`variation-improvements.php:86-203` (`variation_description_fallback`).
Fallback-prioritet: eksisterende `variation_description` → `term->description`
→ CPT `post_excerpt` → CPT `post_content` trunkert til 30 ord (linje 151).
"Learn more"-lenke default til term-arkiv (linje 139-140), med
legacy-overstyring via `linked_page_id`/`custom_page_url` term-meta
(linje 128-136).

**Variation meta-injeksjon:**
`frontend-hooks.php:271-283` — `wc_ras_add_attribute_meta_to_variation`
bakker `attribute_region` og `attribute_smak` inn på variasjons-JSON-en.
Filter: `woocommerce_available_variation`, prioritet 10.

**Cache:**
`cpt-attribute-page.php:289-295` (gruppe `wc_ras_attribute_pages`, ingen TTL)
og `frontend-hooks.php:114-120` (gruppe `wc_ras_attribute_page`,
`HOUR_IN_SECONDS`). Ingen `wp_cache_delete`-kall noe sted.

**Migrering:**
`frontend-hooks.php:82-91` — `wc_ras_check_rewrite_rules_version` flusher
bare rewrite-rules ved versjonsbump; ingen schema-oppdatering.

**WPML/Polylang:** Ingen `wpml_*`- eller `pll_*`-hooks i kodebasen.
README (linje 160) hevder kompatibilitet; den er passiv, ikke aktiv.

### 5.2 Appendiks B — Templates (detalj)

**Template-override:**
`frontend-hooks.php:132-161` (`wc_ras_override_attribute_archive_template`),
hook `template_include`. Sjekker tema-overstyringer først
(`taxonomy-pa_{attribute}.php`, `taxonomy-product-attribute.php`), faller
tilbake til `WC_RAS_PLUGIN_DIR . 'templates/taxonomy-product-attribute.php'`
(linje 151).

**Arkivinnhold-injeksjon:**
`frontend-hooks.php:169-217` (`wc_ras_add_attribute_content_to_archive`),
filter `woocommerce_taxonomy_archive_description`. Henter CPT via slug,
returnerer `apply_filters('the_content', $page->post_content)`.

**Plugin-templater:**
- `templates/taxonomy-product-attribute.php` — arkiv med meta (region, smak),
  term-description, rik CPT-content, produktloop. Bruker `get_header('shop')`
  og `get_footer('shop')`.
- `templates/attribute-term-index.php` — tynn shell, delegerer til
  `wc_ras_render_attribute_term_index()`. For sider som bruker "Attribute
  Term Index"-templaten.
- `templates/variation-no-description.php` — WC-override når inline-variation-
  description er aktivert; fjerner description-div-en.

**Grid-rendering:**
`includes/attribute-term-index.php`:
- `wc_ras_resolve_index_taxonomy()` (linje 29-46) — defaulter til
  `pa_opprinnelse`, filter `wc_ras_attribute_term_index_taxonomy`.
- `wc_ras_get_attribute_term_card_data()` (linje 74-106) — kort-data.
- `wc_ras_render_attribute_term_index_grid()` (linje 117-172) —
  gridden uten wrapper.
- `wc_ras_render_attribute_term_index()` (linje 186-211) — med wrapper.
- Shortcode `[attribute_term_index]` (linje 216-227).
- Asset-enqueue `wc_ras_enqueue_attribute_index_assets()` (linje 290-303).

**Inline-description:**
`includes/inline-variation-description.php`:
- Aktiveringssjekk linje 65.
- Template-override linje 95 (filter `wc_get_template`, erstatter
  `single-product/add-to-cart/variation.php` med plugin-versjonen).
- Script-enqueue linje 122-128, `wp_localize_script` linje 131.
- Variasjons-data-nøkler (sjekkflagg `wc_ras_inline_description`) linje
  149-155 (serverside filter på `woocommerce_available_variation`).

**Block-editor:** Ingen `register_block_type` i plugin-koden.
Redaksjonelt innhold via CPT-ens `post_content` med `show_in_rest => true`.

**Tema-kobling:** Plugin bruker `get_header('shop')`/`get_footer('shop')`
og `.woocommerce`-klasse-wrappere. Ousia har ingen
`header-shop.php`/`footer-shop.php`, WP faller tilbake. Funker, men
dokumentert antakelse.

### 5.3 Appendiks C — JS / modal-integrasjon (detalj)

**Eksisterende JS-filer:**
- `assets/js/inline-variation-description.js` (12.5 KB, IIFE, jQuery) —
  lytter på `found_variation`, `show_variation`, `reset_data`,
  `hide_variation`; animerer inline-rad i variasjonstabellen. DOM-init
  på `DOMContentLoaded` (linje 334) og `ajaxComplete` (linje 341).
  Idempotency-guard `form.dataset.wcRasInlineInit` linje 220-221.
- `assets/js/variation-display.js` (3.2 KB, IIFE, jQuery) —
  lytter på `found_variation` og `reset_data`; injiserer
  `.variation-meta-region`/`.variation-meta-smak` i `.summary` etter `.price`
  (linje 52-82). Aktiveres via filter `wc_ras_enable_variation_meta_display`
  (enqueue i `frontend-hooks.php:296-302`).
- `assets/js/admin-quick-edit.js` — bare admin.

**Serverside data-baking:** Tre lag legger på
`woocommerce_available_variation`:

| Fil | Prioritet | Hva den legger til |
|---|---|---|
| `variation-improvements.php:33` | 10 | `variation_description` (fallback-kjede) |
| `frontend-hooks.php:284` | 10 | `attribute_region`, `attribute_smak` |
| `inline-variation-description.php:149-155` | (prio 15, iflg. agent-funn) | `wc_ras_inline_description: true`-flagg |

Ny modal-data bør legges på prio 20 for å garantere at tidligere lag
har kjørt. Prefiks alle nye nøkler med `wc_ras_origin_` (eller liknende)
for å unngå kollisjoner med framtidige WC-versjoner.

**Hvordan WC signaliserer variasjonsvalg:**

1. Bruker endrer `<select>` inne i `form.variations_form`.
2. WCs `add-to-cart-variation.js` (core-fil) ser endring, gjør ajax-oppslag.
3. `woocommerce_available_variation`-filtrene fyrer serverside, bygger
   variation-objekt.
4. WC trigger jQuery-events på form:
   `form.trigger('found_variation', [variation])` etterfulgt av
   `form.trigger('show_variation', [variation, purchasable])`.
5. `form.trigger('reset_data')` / `'hide_variation'` ved fjerning av valg.

Alle plugin-JS-lyttere binder seg til disse fire eventene. Modal-JS skal
følge samme mønster.

**Programmatisk valg:**

```javascript
$form.find('select[name="attribute_pa_opprinnelse"]').val(slug).change();
```

`.change()` er essensielt — det er det som trigger WCs interne flyt.
Rett attributt-setting uten trigger gjør ingenting.

**Modal-infrastruktur i dag:** Ingen. Må bygges. Anbefaling: native
`<dialog>` + `showModal()` (ingen avhengigheter, gratis focus-trap + Escape).

**Carousel-/swipe-infrastruktur:** Ingen i plugin. Temaet har (iflg.
agentens funn) momentum-scroll i `themes/ousia` eller `themes/myrvann`
`main.js` rundt linje 71-167, bundet til `#soppscroll`-containeren.
Verdt å titte på for inspirasjon; ikke nødvendig å gjenbruke.
CSS `scroll-snap-type: x mandatory` + knapper dekker MVP.

**Radar-chart:** Ingen eksisterende lib. Anbefaling: serverside-generert
inline SVG. PHP-funksjon `wc_ras_render_taste_radar_svg($profile_array)`
tar 8 verdier, returnerer `<svg>`-streng. Brukes både i template (fase 2b)
og i variasjons-data (fase 3).

**A11y-infrastruktur:** Ingen fokus-trap i plugin eller tema (kontrollert
ved grep for `focus-trap`, `inert`, `aria-modal`). Native `<dialog>`
gir dette gratis ved `showModal()`.

**Risikopunkter å vokte på:**

1. WCs `add-to-cart-variation.js` er core og endres over tid. Plugin
   er tilstrekkelig koblet til jQuery-events som har vært stabile siden
   WC 3.0; likevel: test etter hver WC-oppgradering.
2. Temaets GSAP/Lenis (hvis brukt på produktsider) kan forstyrre
   modal-scroll. `<dialog>` med `showModal()` setter `inert` på bakgrunn,
   men GSAP ScrollTrigger bør disables manuelt ved modal-open for sikkerhets
   skyld.
3. Idempotency-guard må brukes også på modal-JS
   (`form.dataset.wcRasOriginModalInit`) i tilfelle ajax-reload av produktside
   (quick-view-modaler e.l.).

**Antakelser (ikke eksplisitt verifisert i koden av meg):**

- Agent-funn om temaets `main.js` med momentum-scroll og Lenis er ikke
  verifisert i denne rapporten — bare agentens analyse. Hvis det blir
  relevant for modal-implementasjonen, bekreft før du bygger på det.
- Radar-SVG-genererings-tilnærmingen er et forslag; ingen presedens i
  plugin i dag.
- Agentens linjeangivelser på noen hendelses-filter-prioriteter
  (spesielt `inline-variation-description.php:81` prio 15) er basert på
  agentens lesning — verifiser ved implementasjon.

---

## 6. Verifikasjons-sjekkliste før implementering

Før fase 1 starter:

- [ ] Bekreft §4.1 (CPT publicly_queryable)
- [ ] Bekreft §4.2 (sertifiseringsmodell)
- [ ] Bekreft §4.3 (JSON-konvensjon for kompositter)
- [ ] Bekreft §4.4 (akse-navn for smaksprofil)

Før fase 2b starter:

- [ ] Bekreft §4.5 (flagg-strategi)
- [ ] Bekreft §4.6 (hva skjer med legacy `smak`)

Før fase 3 starter:

- [ ] Bekreft §4.7 (blurb-plassering)
- [ ] Bekreft §4.8 (modal-trigger)
- [ ] Bekreft §4.9 (swipe-semantikk)
- [ ] Bekreft §4.10 (URL deep-linking)

Ikke-blokkerende, men ideelt avklart før prod:

- [ ] §4.11 (cache-invalidering)
- [ ] §4.12 (backfill-verktøy)
- [ ] §4.13 (WPML-eksplisitt?)
- [ ] §4.14 (mål variasjonsdata-størrelse)
