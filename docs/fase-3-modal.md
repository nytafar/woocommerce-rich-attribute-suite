# Fase 3 — Modal

**Status:** Levert.

## Mål

Native `<dialog>`-basert modal som åpnes fra CTA-lenken på produktsiden
(`<a data-open-origin-modal>` i variation-description). Viser full
opprinnelses-info — featured image, hero, producers, post-harvest,
klientside-rendret taste-radar, sertifiseringer — og lar kunden navigere
mellom tilgjengelige opprinnelser for produktet.

## Kjernemodell

**Én modal. Ett kort. Hydreres.**

Modalens kort er ikke et carousel av N kort. Det er ett kort hvis innhold
alltid speiler nåværende `pa_opprinnelse`-valg. Modalen har ingen egen
state — den er en visning av systemets nåværende opprinnelses-valg.

Dette matcher mønsteret variation-description-raden bruker fra fase 2:
server-render én gang med default-variant, JS swapper innhold ved
selection-endring via samme `found_variation`-pipeline.

### Konsekvenser

- **Ingen "Velg opprinnelse"-knapp.** Swipe, piltaster og prev/next-
  knapper trigger selection direkte — samme code path som om brukeren
  hadde klikket en attribute-button.
- **Ingen utkast-tilstand.** Modalen kan alltid lukkes. Siden er alltid
  konsistent med valgt opprinnelse.
- **Bidireksjonell sync gratis.** Både attribute-buttons og
  modal-navigering går gjennom samme selection-kanal (WC's
  variation-form). Variation-description-raden og modal-kortet hydreres
  av samme event-pipeline.

## Forutsetninger fra fase 1 + 2

- **Variasjons-preload**: `wc_ras_build_origin_struct` i
  `includes/frontend-hooks.php` leverer full `wc_ras_origin`-struct per
  variasjon via `woocommerce_available_variation`-filteret. Fase 3
  utvidet structen med pre-formaterte label-felt
  (`region_label`, `altitude_label`, `producer_type_label`,
  `producer_count_label`, `fermentation_value`) slik at JS-hydreringen
  ikke trenger enum-maps eller pluralform-logikk.
- **CTA-hook**: `data-open-origin-modal`-attributt på CTA-lenken i
  `templates/parts/variation-description.php`. Modal-JS-en fanger klikk
  (event-delegering), `preventDefault()`, åpner modal. Uten JS:
  lenken navigerer til CPT-siden.
- **Radar-JS**: `window.WcRasOriginRadar.render(profile)` registrert i
  `includes/origin/origin-frontend.php`. Modal-enqueue bruker
  `wc-ras-origin-radar` som dependency, så radaren får auto-enqueue
  på produktsider med modalen.

## Arkitektur

### DOM
- Ett `<dialog class="wc-ras-origin-modal" data-wc-ras-origin-modal>`
  rendres server-side inne i `.woocommerce-product-gallery` via
  `woocommerce_product_thumbnails`-hooken (pri 100). Shell-en er
  ferdig, og kortet populeres med *default-variantens* opprinnelse.
- Kortet (`article[data-origin-modal-card]`) har `data-field="…"`-
  markører rundt alle hydrerbare felter. Elementer uten initialverdi
  rendres med `hidden`-attributt.
- Chrome: prev-knapp, close-knapp, next-knapp (`data-origin-modal-nav`,
  `data-origin-modal-close`).
- Seksjoner: hero, producers, postharvest, flavour (radar + notes),
  certifications, permalink.

### Hydrering
JS lytter på `found_variation`/`show_variation`-events på
`form.variations_form` (samme events `inline-variation-description.js`
bruker). Handler leser `variation.wc_ras_origin` og kjører en `setField`-
pipeline over `[data-field]`-markørene. Radar re-rendres via
`window.WcRasOriginRadar.render(origin.taste_profile)` og settes som
`innerHTML` på `[data-field="radar"]`.

Null-håndtering:
- Enkeltfelter skjules via `hidden`-attributtet.
- Hele seksjoner skjules via `[data-section]`-markøren når minimumsdata
  mangler (matcher mønsteret i `templates/single-attribute_page.php`).
- `[hidden] { display: none !important }` i `origin-modal.css` sikrer at
  hidden vinner over våre flex/grid-regler.

### Åpning / lukking

- **Klikk på CTA** → `preventDefault()`, `openDialog()`. Klikk en gang
  til mens modal er åpen lukker (toggle).
- **Mobil** (`max-width: 1023px`): `dialog.showModal()` (blocking +
  backdrop).
- **Desktop** (`min-width: 1024px`): `dialog.show()` (non-blocking) —
  fyller `.woocommerce-product-gallery`-containeren via UA-default
  dialog-positioning + width/height 100%.
- **Escape** lukker (native `<dialog>`-støtte).
- **Backdrop-klikk** (`e.target === dialog`) lukker.
- **Close-knapp** (`[data-origin-modal-close]`) lukker.
- **Uten JS**: CTA-lenken navigerer til `/opprinnelser/{slug}/`.

### Navigasjon

Swipe, piltaster og prev/next-knapper kjører alle gjennom `stepSelection`
→ `selectOrigin(slug)`:

```js
function selectOrigin(ctx, slug) {
    var sel = ctx.form.querySelector('select[name="attribute_pa_opprinnelse"]');
    sel.value = slug;
    if (window.jQuery) window.jQuery(sel).trigger('change');
    else sel.dispatchEvent(new Event('change', { bubbles: true }));
}
```

Unike opprinnelser dedupes klientside fra
`form.dataset.product_variations` på `wc_ras_origin.slug`. Navigasjon
er sirkulær. Prev/next-knapper og piltast-respons disables når
produktet bare har én opprinnelse. Swipe-gesture krever minst 50 px
horisontal bevegelse og at |dx| > |dy| slik at vertikal scroll i kortet
vinner når det er intensjonen.

### Window-resize

Hvis modalen er åpen og viewport krysser desktop/mobile-breakpoint
(debounced 200 ms), lukker JS-en modalen og gjenåpner den med riktig
`show`/`showModal`-modus.

## Filer

### Nye
- `templates/parts/origin-modal.php` — server-rendret dialog-shell +
  hydrerbart kort. Tema-override via
  `{theme}/woocommerce-rich-attribute-suite/parts/origin-modal.php`.
- `assets/js/origin-modal.js` — vanilla JS med jQuery-bro for
  WC-events.
- `assets/css/origin-modal.css` — minimum strukturell CSS
  (positioning per modus, scroll-container, `[hidden]`-precedence).

### Endrede
- `includes/origin/origin-frontend.php` — enqueue-funksjon og
  render-hook.
- `includes/frontend-hooks.php` — `wc_ras_build_origin_struct`
  utvidet med pre-formaterte label-felter (`region_label`,
  `altitude_label`, `producer_type_label`, `producer_count_label`,
  `fermentation_value`).

### Uberørte
- `assets/js/inline-variation-description.js`
- `assets/js/variation-display.js`
- `assets/js/origin-radar.js`
- `includes/origin/origin-render.php` (render-helpers gjenbrukt)
- `templates/parts/variation-description.php` (CTA-markup fra fase 2)

## CSS-strategi

Følger fase 2-mønsteret. Plugin leverer kun strukturell CSS som får
modalen til å fungere — positioning per breakpoint, scroll-container,
dialog-reset (fjerne UA-border/padding), `[hidden]`-precedence. Alt
visuelt (farger, typografi, spacing) eier temaet via
klasse-selektorene.

Breakpoint satt til 1024 px. Kan refaktoreres til filter eller CSS
custom property senere hvis tema trenger annen verdi.

## Testscenarioer (manuell QA)

- [ ] Klikk "Lær mer" → modal åpner med valgt opprinnelse.
- [ ] Desktop: non-blocking over `.product`-containeren; mobil:
      full-viewport blocking.
- [ ] Swipe høyre/venstre (mobil) → opprinnelse endres, både
      variation-description-raden og modal-kortet oppdateres.
- [ ] Prev/next-knapper (desktop) → samme oppførsel.
- [ ] ArrowLeft/ArrowRight mens modal har fokus → samme oppførsel.
- [ ] Klikk attribute-knapp i formen med modal åpen (desktop) →
      modal-kortet oppdateres.
- [ ] Esc / backdrop-klikk / close-knapp / CTA-re-klikk → lukker.
- [ ] Uten JS → CTA navigerer til CPT-single.
- [ ] Radar i modal = radar på CPT-single visuelt.
- [ ] Opprinnelse uten `drying_method` → tørke-boks skjult.
- [ ] Opprinnelse uten `taste_profile` → flavour-seksjon skjult når
      heller ikke taste-notes finnes.
- [ ] Opprinnelse uten `certifications` → sertifiserings-seksjon skjult.
- [ ] Featured image bytter korrekt ved selection-endring.
- [ ] "Se hele siden →"-lenken oppdateres til riktig permalink.
- [ ] Produkt med bare én opprinnelse → prev/next disabled, swipe no-op.
- [ ] Window-resize desktop ↔ mobil med åpen modal → lukker og
      gjenåpner i riktig modus.
- [ ] Flagg-pille: viser flagg-SVG hvis `country_flag_url` finnes,
      ellers bare landsnavn (ingen placeholder).

## Avgrensning

- Flagg-SVG-er er ikke levert. `wc_ras_country_flag_url()` returnerer
  null, og modal-markup håndterer det — pille viser bare landsnavn.
- URL-param deep-link (`?attribute_pa_opprinnelse={slug}`) er fase 4
  — ikke i scope her. Modalen åpner kun på eksplisitt CTA-klikk.
- Hvis et tema rendrer `pa_opprinnelse` som noe annet enn en `<select>`
  (f.eks. via Variation Swatches-plugin), committer `selectOrigin` ved
  å oppdatere den skjulte selecten som de pluginene synkroniserer mot.
