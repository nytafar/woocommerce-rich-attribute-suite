# Fase 2 — Templates, variasjons-description-berikelse, CPT-arkiv og single

**Status:** Levert på branch `rich-gets-richer`.

## Sammendrag

Fase 2 utvider den eksisterende `wc-ras-inline-description`-raden på
produktsider (piller + smak-tekst + CTA), leverer CPT-arkiv og CPT-
single-templates, flytter "Learn more"-lenken til kanonisk CPT-URL, og
legger grunnlaget for modalen i fase 3.

Ingen parallell UI. Alt nytt innhold på produktsiden lever INNE i den
eksisterende `.wc-ras-inline-description.woocommerce-variation-description`-
containeren som `inline-variation-description.js` allerede eier. Eksisterende
height + stagger-fade-animasjoner fungerer uendret fordi vi bare sender
rikere HTML inn i den samme `innerHTML`-swap-pipelinen.

## Hva som faktisk ble bygget

### Variasjons-description-berikelse (produktsiden)

Målet var å utvide det brukeren allerede hadde — ikke erstatte. Raden
inneholder nå:

```
<div class="wc-ras-inline-description woocommerce-variation-description">
  <div class="origin-pills">         ← NY (betinget)
    <span class="pill flag">[flag] Peru, Cusco</span>
    <span class="pill variety">Chuncho</span>
    <span class="pill altitude">1450 moh</span>
  </div>
  <p class="smak">Mango, krem, macadamia</p>                ← NY (betinget)
  <p>Term/variation description text</p>                    ← BEHOLDES, null-handles
  <p class="term-page-link-wrapper">
    <a href="/opprinnelser/{slug}/"
       class="term-page-link"
       data-open-origin-modal>Lær mer</a>                   ← CTA til CPT-URL + modal-hook
  </p>
</div>
```

Rekkefølge: piller → smak → term-desc → CTA.

Leveransen består av:

- `templates/parts/variation-description.php` — tema-overstyrbar template-
  partial. Rendrer bare det indre innholdet i containeren (ikke containeren
  selv, fordi JS-swap eier den). Piller, smak-p, description-p og CTA
  renders betinget; tomme blokker skjules (null-handling).
- `includes/template-loader.php` — helperen `wc_ras_load_template($slug, $args)`.
  Sjekker `{theme}/woocommerce-rich-attribute-suite/{slug}.php` først,
  faller tilbake til `{plugin}/templates/{slug}.php`. Returnerer rendret
  HTML via `ob_start`. Argumenter extractes inn i template-scope.
- `includes/variation-improvements.php::variation_description_fallback`
  refaktorert. Samler `$origin` (via `wc_ras_build_origin_struct`),
  `$description_text` (prioritet: term-desc → CPT-excerpt → CPT-content
  trimmet), og `$cta_url` (prioritet: CPT-permalink → legacy term-meta
  `linked_page_id` / `custom_page_url` → term-arkiv-URL). Kaller
  template-partial og lagrer resultat i `variation_description`.
- `includes/variation-improvements.php::wc_ras_get_learn_more_url($term)` —
  resolver for kanonisk CTA-URL.

Alle eksisterende filtre bevares: `wc_ras_show_variation_description_links`
styrer om CTA rendres. Filteret `wc_ras_combine_all_term_descriptions` er
stille fjernet (var default-false og lite brukt; støtter ikke lenger flere
description-paragrafer i ny template-struktur).

### Navnekonvensjon

UI-elementet kalles *"variation description"* i kode og filnavn. Ordet
"blurb" / "origin panel" / "origin blurb" er avviklet — det skapte
forvirring og var hovedårsaken til parallell-UI-feilen. "Origin" refererer
til datakilden (opprinnelses-CPT-en), ikke til en egen UI-komponent.

### CPT-arkiv og CPT-single

- `templates/archive-attribute_page.php` — grid over alle opprinnelser på
  `/opprinnelser/`. Bruker `the_post()`-loop og inkluderer `origin-card.php`
  per post.
- `templates/single-attribute_page.php` — CPT-single på
  `/opprinnelser/{slug}/`. Strukturert header (hero → produsenter → post-
  harvest → flavour → sertifiseringer) → `the_content()` → hook
  `wc_ras_origin_single_after_content`.
- `templates/parts/origin-card.php` — arkivkort (featured image, tittel,
  piller, tagline, producer-ikon, "Brukes i N sjokolader"-count).

Tema kan overstyre via standard WP template-hierarki:
`archive-attribute_page.php` og `single-attribute_page.php` i tema →
brukes. `origin-frontend.php::wc_ras_origin_template_include` håndterer
fallbacken til plugin-templates.

CSS-klassenavn er kontekstuelle (descendant-selektorer scopet av parent),
ikke BEM. F.eks. `.wc-ras-origin-card .media`, `.wc-ras-origin-single
.hero .meta`. Minimal strukturell CSS i `assets/css/origin.css` — kun for
CPT-kontekster, ingen styling av produktsiden.

### Origin-hjelpere (`includes/origin/origin-render.php`)

Uendret fra forrige pass:
- `wc_ras_country_flag_url($slug)` — fil-basert resolver med
  `wc_ras_country_flag_map`-filter-override.
- `wc_ras_get_origin_url($slug)` — kanonisk CPT-URL.
- `wc_ras_format_altitude($altitude)` → `"1200–1450 moh"`.
- `wc_ras_origin_icon($key)` — inline SVG-glyfer (fermentation, drying,
  producer, altitude) med `currentColor`.
- `wc_ras_render_postharvest_item($icon, $label, $value, $freetext)` —
  hybrid ikon-label-value-freetext-boks.
- `wc_ras_format_fermentation_value($type, $days)` — "Sentralisert · 6 dager".
- `wc_ras_render_taste_radar_svg($profile, $options)` — PHP-radar.
- `wc_ras_origin_product_count($slug)` — cached produkt-antall.

### Sertifiserings-ikon-admin

- `includes/origin/origin-admin-certifications.php` — media-frame-picker
  på `certification`-taxonomy-term-edit/add-skjermene.
- `assets/js/admin-certification-picker.js` — vanilla `wp.media`-wrapper.

### Block-pattern

- `includes/origin/block-patterns.php` — starter-pattern
  `wc-ras/origin-starter` (3-kolonne: Produsentene / Stedet / Håndverket).

### JavaScript

- `assets/js/variation-display.js` — refaktorert fra jQuery til vanilla.
  Leser `variation.wc_ras_origin.region` / `.taste_notes`. jQuery kun som
  bro for `found_variation` + `reset_data`.
- `assets/js/origin-radar.js` — `window.WcRasOriginRadar.render(profile)`.
  Speilvender PHP-radarens semantikk (null-skip: færre-sidet polygon,
  grid for alle akser, labels kun for ratede akser).
- `assets/js/inline-variation-description.js` — uendret. Allerede vanilla
  fra commit `6fbf9f3`. Swap-pipelinen er kanalen — vi sender bare rikere
  HTML inn.
- `assets/js/admin-certification-picker.js` — vanilla, wp.media-wrapper.

### Data-lag

- `includes/frontend-hooks.php::wc_ras_build_origin_struct($page)` — én
  kilde til sannhet for payload-formen. Brukes av både variasjons-preload
  og templates. Full modal-ready payload (`country`, `country_flag_url`,
  `variety`, `altitude`, `producer_*`, `fermentation_*`, `drying_method`,
  `taste_profile`, `certifications`, `featured_image_url`, `has_rich_page`).
- `wc_ras_origin_struct`-filter lar site endre payload.

## Hva som ble slettet / pensjonert

- `templates/parts/origin-blurb.php` — den parallelle blurb-partialen
  (fase 2 første forsøk).
- `assets/js/origin-blurb.js` — den parallelle hydrerings-JS-en.
- `templates/taxonomy-product-attribute.php` — gammel taxonomy-arkiv-
  override (ble erstattet av CPT-arkiv).
- Fra `includes/frontend-hooks.php`:
  - `wc_ras_override_attribute_archive_template` (gammel
    `template_include`-override).
  - `wc_ras_add_attribute_content_to_archive`
    (`woocommerce_taxonomy_archive_description`-filter).
  - `wc_ras_is_product_attribute()` (kun brukt av ovenstående).
  - `wc_ras_enqueue_variation_scripts` (duplikat av
    `WC_RAS_Variation_Improvements::enqueue_variation_display_script`).
  - `wc_ras_create_assets_directory` (unødvendig).
- Fra `origin-frontend.php`:
  - `wc_ras_origin_render_variation_blurb()` + hooken på
    `woocommerce_single_variation` (parallell UI).
  - `wc_ras_origin_resolve_initial_slug()`.
  - `wc_ras_origin_enqueue_blurb_script()`.
- Død kode i `variation-improvements.php`: `attribute_region` /
  `attribute_smak`-flate nøkler-referanser.

## Kanonisk URL

`/opprinnelser/{slug}/` (CPT) er kanonisk. Alle plugin-genererte lenker
peker dit via `wc_ras_get_learn_more_url($term)` (som bruker
`wc_ras_get_cached_attribute_page` + `get_permalink`). Gammel
taxonomy-URL er ikke lenger generert fra plugin, men fortsatt funksjonell
(for WC-produktfiltrering).

## CSS-strategi

- **Produktside (variation-description-berikelse):** ingen plugin-CSS.
  Tema eier styling. Klassenavn i template-partialen er enkle og
  kontekstuelle (`.origin-pills`, `.pill.flag`, `.pill.variety`,
  `.pill.altitude`, `.smak`) med containerens eksisterende
  `wc-ras-inline-description`-klasse som scoping-anker.
- **CPT-arkiv/single/taxonomier:** minimal strukturell plugin-CSS i
  `assets/css/origin.css`. Descendant-selektorer scopet av
  `.wc-ras-origin-archive`, `.wc-ras-origin-card`, `.wc-ras-origin-single`.
  Ingen visuelle verdier (farger, spacing, typografi) — tema eier det.

## Vanilla-mønster og jQuery-kontakt

All plugin-DOM-manipulasjon er vanilla. jQuery brukes kun som event-bro
for WooCommerce-variasjonsevents som ikke bobler som native DOM-events
(`jQuery.triggerHandler()`).

Filer med jQuery-bro nederst:
- `variation-display.js` (found_variation, reset_data)
- `inline-variation-description.js` (found_variation, show_variation,
  reset_data, hide_variation)

Filer uten jQuery-avhengighet:
- `origin-radar.js` (rent bibliotek)
- `admin-certification-picker.js` (wp.media, vanilla wrapper)

## Template-override-punkter for tema

| Type | Plugin-path | Tema-override |
|---|---|---|
| CPT-arkiv | `templates/archive-attribute_page.php` | `{theme}/archive-attribute_page.php` |
| CPT-single | `templates/single-attribute_page.php` | `{theme}/single-attribute_page.php` |
| Arkivkort | `templates/parts/origin-card.php` | N/A (include'd av archive) |
| Variation-description (produktside) | `templates/parts/variation-description.php` | `{theme}/woocommerce-rich-attribute-suite/parts/variation-description.php` |

CPT-templates bruker standard WP-template-hierarki via
`origin-frontend.php::wc_ras_origin_template_include`. Variation-description
bruker `wc_ras_load_template`-helperen som leter i
`{theme}/woocommerce-rich-attribute-suite/`-undermappen.

## Manuell verifisering

Utført mot staging-data (Myrvann Cacao-produkt med `pa_opprinnelse` +
`pa_mengde`):

- [x] Variasjons-raden viser piller + smak + term-desc + CTA.
- [x] Kun én rad på produktsiden (ingen parallell blokk).
- [x] CTA-lenken peker til `/opprinnelser/{slug}/` med
      `data-open-origin-modal`-attributt.
- [x] Eksisterende height + fade-stagger-animasjoner fungerer ved
      variantbytte.
- [x] CPT-single og CPT-arkiv responderer på URL-er.
- [x] Alle PHP-filer lint-rene (`php -l`).

## Åpne spørsmål / forbehold

- **Flagg-SVG-filer:** `assets/flags/{slug}.svg` katalogen er ikke
  committet. Flagg rendres ikke før filer legges inn. Null-handling
  gracefull — piller viser tekst uten flagg.
- **Taste-akse-labels:** beholdt som i fase 1. Bekreftelse fra
  produkteier/smakspanel kan komme senere; filter-basert, ikke-
  blokkerende.
- **`wc_ras_combine_all_term_descriptions`:** filteret er fjernet uten
  notis. Var default-false og lite brukt.
