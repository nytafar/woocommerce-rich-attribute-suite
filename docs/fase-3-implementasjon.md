# Fase 3 — Modal (implementasjon)

**Status:** Levert på branch `rich-gets-richer`.

## Sammendrag

Fase 3 legger til en native `<dialog>`-basert modal som åpnes fra CTA-
lenken `<a data-open-origin-modal>` i variation-description-raden. Modalen
inneholder ett kort som alltid speiler nåværende `pa_opprinnelse`-valg.
Navigering (swipe / piltaster / prev-next-knapper) committer et nytt
opprinnelses-valg gjennom WC's variation-form — samme code path som et
attribute-klikk. Det finnes ingen parallell UI og ingen lokal
modal-state; alt hydreres via `found_variation`-pipelinen som fase 2
bygde.

## Hva som faktisk ble bygget

### Modal-template (server-rendret)

`templates/parts/origin-modal.php` — tema-overstyrbar via
`{theme}/woocommerce-rich-attribute-suite/parts/origin-modal.php`.
Rendrer `<dialog>`-shell (chrome + kort-skall) med default-variantens
opprinnelse. Hvert hydrerbart element har `data-field="…"` eller
`data-field-group="…"`; seksjoner har `data-section="…"`. Elementer uten
verdi ved server-render får `hidden`-attributtet, så initial-state og
hydrert state ser identisk ut.

Gjenbruker alle eksisterende PHP-helpers fra
`includes/origin/origin-render.php`:
`wc_ras_render_taste_radar_svg`, `wc_ras_origin_icon`. Alle andre
formaterte verdier (`region_label`, `altitude_label`,
`producer_type_label`, `producer_count_label`, `fermentation_value`)
leses fra utvidet origin-struct — se under.

### Utvidet origin-struct

`includes/frontend-hooks.php::wc_ras_build_origin_struct` fikk fem nye
felter, alle pre-formaterte og translated:

| Felt                   | Eksempel                         |
|------------------------|----------------------------------|
| `region_label`         | `Peru, Cusco, Quillabamba`       |
| `altitude_label`       | `1200–1450 moh`                  |
| `producer_type_label`  | `Cooperative`                    |
| `producer_count_label` | `45 produsenter` (_n-pluralized_) |
| `fermentation_value`   | `Sentralisert · 6 dager`         |

Dette lar JS-hydreringen unngå å replikere enum-oversettelser og
pluralform-logikk. Fields er alltid strings (empty string når kilden er
null), så JS-en har én uniform null-sjekk.

### Enqueue + render-hook

`includes/origin/origin-frontend.php` fikk:

- `wc_ras_origin_modal_product()` — guard som returnerer `WC_Product`
  når vi er på en produktside for et variable product med
  `pa_opprinnelse`-variasjoner.
- `wc_ras_origin_modal_initial_origin($product)` — henter default-
  variantens opprinnelse (via `get_default_attributes()`), med fallback
  til første variasjon som har en matchende attribute_page.
- `wc_ras_origin_enqueue_modal_assets()` — enqueuer
  `assets/css/origin-modal.css` og `assets/js/origin-modal.js`.
  Script har `wc-ras-origin-radar` som dependency, så radar-JS-en
  får auto-enqueue på produktsider som faktisk har modalen. Kjører på
  `wp_enqueue_scripts` pri 20 og er no-op hvis guarden feiler.
- `wc_ras_origin_render_modal()` — hooket på
  `woocommerce_product_thumbnails` pri 100. Rendrer modal-shellet som
  søsken av gallery-figure inne i `.woocommerce-product-gallery`, slik
  at UA-default dialog-positioning + width/height 100% fyller
  gallery-containeren på desktop uten at plugin-CSS-en må overstyre
  inset/margin.

### Modal-JS

`assets/js/origin-modal.js` — vanilla med jQuery-bro for WC-events.

Init iterrerer over alle `form.variations_form` på siden og binder hver
til det ene `[data-wc-ras-origin-modal]`-elementet. Idempotent via
`form.dataset.wcRasOriginModalInit`-sentinel, og re-initialiseres på
`ajaxComplete` for wc-ajax-URL-er.

Hydrering lytter på `jQuery(form).on('found_variation show_variation',
…)`, leser `variation.wc_ras_origin` og oppdaterer kortet i én
`setField`-pipeline. `reset_data`/`hide_variation` skjuler alle
seksjoner (men lukker ikke modal).

Navigasjon (`stepSelection`) finner current slug fra den skjulte
`<select>`-en eller fra `data-current-slug` på kortet (tracked ved
siste hydrering), computer sirkulær prev/next index i
`getProductOrigins(form)`-listen, og committer via
`selectOrigin(slug)`:

```js
select.value = slug;
if (window.jQuery) window.jQuery(select).trigger('change');
else select.dispatchEvent(new Event('change', { bubbles: true }));
```

WC plukker opp endringen og fyrer `found_variation` → hydrateCard
runner.

Close-semantikk:
- Escape (native `<dialog>`).
- Backdrop-klikk (`e.target === dialog`).
- `[data-origin-modal-close]`-knapp.
- CTA-re-klikk toggler (samme delegering som åpner).

Desktop vs mobil:
- `matchMedia('(min-width: 1024px)')` avgjør modus.
- Desktop: `dialog.show()` (non-blocking).
- Mobil: `dialog.showModal()` (blocking med backdrop).
- Window-resize (debounced 200 ms) som krysser breakpoint mens modal
  er åpen → `dialog.close()` + gjenåpne i riktig modus.

Swipe-gesture (touchstart/touchend) på kortet krever |dx| ≥ 50 px og
|dx| > |dy| — den siste betingelsen lar vertikal scroll vinne når det
er intensjonen.

### Strukturell CSS

`assets/css/origin-modal.css` — minimum for at modalen fungerer:

- Dialog-reset (fjerner UA-border, padding, max-width).
- Mobil (`max-width: 1023px`): `position: fixed; inset: 0; width: 100vw;
  height: 100dvh; z-index: 1000`.
- Desktop (`min-width: 1024px`): `position: absolute; inset: 0; width:
  100%; height: 100%`. Backdrop transparent (ikke blocking).
- Scroll-container på `.wc-ras-origin-modal__card`.
- `[hidden] { display: none !important }` for å vinne over våre flex/
  grid-regler.

Tema eier all visuell styling (farger, typografi, spacing, hero-
layout). Plugin har ingen designopinion ut over "dialog'en må funke".

## Koblinger til eksisterende kode

- **Variation-description-raden** (fase 2) endrer seg ikke. Den
  hydreres fortsatt via `inline-variation-description.js`-pipelinen
  med full HTML som kommer fra `variation_description`-feltet på
  variasjonen. Modal-JS-en kjører på de samme `found_variation`-
  eventsa, i tillegg til variation-description-oppdateringen.
- **Origin-radar**: modal-enqueue har `wc-ras-origin-radar` som
  dependency. På produktsider som har modalen får vi dermed både
  radar-JS (brukt av modal) og radar-localize (`wcRasTasteAxes`).
- **Template-loader**: `wc_ras_load_template` gjør at tema-overstyring
  av `parts/origin-modal.php` fungerer automatisk.
- **Origin-struct**: én felles payload-form brukes av variation-
  description (fase 2) og modal-kort (fase 3). Nye label-felter
  kommer gratis for alle fremtidige konsumenter.

## Testing

Manuelt kjørte scenarioer:

- Klikk "Lær mer" → modal åpner med valgt opprinnelse.
- Desktop: non-blocking over `.product`; mobil: full-viewport blocking.
- Swipe/prev/next/piltaster → selection endres, både variation-
  description-raden og modal-kortet oppdateres.
- Klikk attribute-knapp i formen med modal åpen (desktop) →
  modal-kortet oppdateres.
- Esc / backdrop-klikk / close-knapp / CTA-re-klikk lukker.
- Radar i modal matcher radar på CPT-single visuelt.
- Opprinnelse uten `drying_method` / `taste_profile` /
  `certifications` → respektive seksjoner skjules.
- Featured image swapper korrekt.
- Produkt med bare én opprinnelse → prev/next disabled, swipe no-op.
- Window-resize desktop ↔ mobil med åpen modal → lukker og gjenåpner
  i riktig modus.
- Uten JS → CTA navigerer til CPT-single.

## Avgrensning og åpne punkter

- **Flagg-SVG-er**: `assets/flags/`-mappen finnes ikke.
  `wc_ras_country_flag_url()` returnerer null, og markup håndterer det
  (bare landsnavn i pille).
- **URL-param deep-link** (`?attribute_pa_opprinnelse={slug}`) er
  fase 4. Modalen åpner kun via eksplisitt CTA-klikk.
- **Breakpoint 1024 px** er hardkodet som media query. Kan
  refaktoreres til filter eller CSS custom property senere hvis tema
  trenger annen verdi.
- **Theme-overridable select**: `selectOrigin` targeter
  `select[name="attribute_pa_opprinnelse"]`. Plugins som rendrer
  attributten som buttons/radios (f.eks. Variation Swatches)
  synkroniserer normalt mot den samme skjulte selecten, så committen
  vinner. Ikke verifisert i live-tema ennå.
- **Desktop-plassering**: modal-shellet rendres inne i
  `.woocommerce-product-gallery` via `woocommerce_product_thumbnails`-
  hooken. Temaer som overstyrer product-image.php og ikke fyrer denne
  hooken (eller ikke rendrer gallery-wrapperet) vil ikke få modalen.
  Fallback-strategi (alternativ hook eller portal-via-CSS) er ikke
  implementert.
- **Ingen JS-test-suite**. Plugin har ingen PHPUnit- eller Jest-oppsett
  i dag — QA er manuell.
