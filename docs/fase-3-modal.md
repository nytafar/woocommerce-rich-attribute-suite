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
  rendres server-side som første barn av
  `.woocommerce-product-gallery__wrapper` ved å prependes til
  hovedbilde-HTML-en via
  `woocommerce_single_product_image_thumbnail_html`-filteret.
  **2026-09-12:** `origin-modal.js` flytter dialogen til
  `.woocommerce-product-gallery` (rot, `position:relative` i WC core) ved
  init. Inne i wrapperen havnet den i FlexSliders transformerte,
  `overflow:hidden` slide-track og ble malt under hovedbildet på desktop.
  Shell-en er ferdig, og kortet populeres med *default-variantens*
  opprinnelse.
- Kortet (`article[data-origin-modal-card]`) har `data-field="…"`-
  markører rundt alle hydrerbare felter. Elementer uten initialverdi
  rendres med `hidden`-attributt.
  **2026-09-12 (opprinnelseskort):** `.wc-ras-origin-modal__panel` holder
  chrome + kort (én sticky enhet på desktop, temaet capper den til
  viewport); kortet er selve *taggen* og scroller ikke;
  `.wc-ras-origin-modal__body` inni er scroll-regionen. Spec-feltene
  er en `<dl>` (`.wc-ras-origin-modal__row` med `data-field-group` per rad:
  region, variety, altitude, producers, fermentation, drying) i stedet for
  seksjonene producers/postharvest. Nye felt: `country` (stempel i hero),
  `region` (egen rad), `ref` (term-slug i stubben) og `position`
  («2 / 3» i chrome). Plugin-CSS-en skjuler en rad der alle felt er
  `hidden` (`:has()`); temaet (myrvann `scss/plugins/_rich-attribute-suite.scss`)
  eier alt visuelt: kraft-grunn, eyelet, ledger-leaders, stempel,
  cupping-chart, perforert stubb.
- Chrome: close-knapp (`data-origin-modal-close`). **Runde 2:** prev/next
  erstattet av opprinnelsesstripen (se nederst).
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
- [ ] Stripe-tiles nederst → samme oppførsel; valgt tile får `aria-current`.
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
- [ ] Produkt med bare én opprinnelse → stripen skjult, swipe no-op.
- [ ] Window-resize desktop ↔ mobil med åpen modal → lukker og
      gjenåpner i riktig modus.
- [ ] Flagg-pille: viser flagg-SVG hvis `country_flag_url` finnes,
      ellers bare landsnavn (ingen placeholder).

## Runde 2 (2026-09-12): arket er dialogen

Brukerfeedback på opprinnelseskortet (kraft-grunn med kort inni = «inception»,
prev/next feil UI, for lav tetthet, chart i boks i boks, sertifiseringer
trenger ikke seksjon, «Opprinnelseskort» redundant) ga en ny modell:

- **Dialogen ER papiret.** Ingen grunn, ingen indre kort, ingen padding
  rundt, ingen eyelet/eyebrow. Kraft-konseptet ligger i materialet
  (papirtone + korn) og trykket (blekklinjer, stempler, type).
- **Desktop-høyde = hovedbildet.** `origin-modal.js` (`sizeToImage`) setter
  inline `height` på dialogen til `.flex-viewport` (eller wrapperen uten
  slider), cappet til det som er synlig under galleriets toppkant. Panel/
  sticky er borte; `margin:0` i plugin-CSS fordi UA-`margin:auto` ellers
  sentrerer en cappet høyde.
- **Front-of-pack, ikke spec-ark.** Malen har nå: navn, stempelklynge
  (land + sertifiseringer), fire fakta (varietet, høyde, produsenter,
  fermentering) som `dl.wc-ras-origin-modal__facts` med `data-field-group`
  per celle, radar, smaksnotater som chart-caption, lenke. Region, tagline,
  produsentantall, fermenteringsmetode, tørking og referanse-slug er
  fjernet fra modalen (finnes på hele siden). Fjernede felt er ufarlige for
  eldre JS: `setField` er no-op når elementet mangler.
- **Chartet er ankeret.** Begge renderere (`wc_ras_render_taste_radar_svg`,
  `WcRasOriginRadar.render`) tegner nå 5:4 viewBox (400×320), to ringer
  (50 % stiplet, 100 % heltrukken), spokes, tung polygon (fill-opacity .7,
  stroke 3, round joins), vertex-merker r 4.5 og labels 13 med klasser
  `wc-ras-radar__ring/spoke/polygon/vertex/label`. Temaet maler: stempel-
  blekk multiplisert i papiret, papirringede vertex-merker, Mendl-labels.
  Full bredde; på desktop flexer chartet til resthøyden.
  Paritetssjekk: `ras-port/radar-parity.php` (wp eval-file, PHP vs node,
  exit ≠ 0 ved avvik).
- **Stempler er én familie.** Landstempel (rektangel, flagg inni, −3.5°) og
  sertifiseringsstempler (runde, ikon inni, +5°), samme dobbeltring og
  blekk. Ingen seksjon, ingen overskrift; navnet ligger i `title`/`alt`.
- **Navigasjon = opprinnelseslisten.** `nav.wc-ras-origin-modal__strip`
  nederst, bygget av JS fra `ctx.origins` (`buildStrip`), én
  `button[data-origin-modal-select=slug]` per opprinnelse med rik side.
  Valgt tile = `aria-current="true"` = posisjonsindikator (`updateStrip`,
  scroller tilen i syne). Klikk går gjennom `selectOrigin(slug)`. Prev/
  next/«1 / 3» er borte; piltaster og swipe (`stepSelection`) beholdt.
  Strip skjules ved ≤ 1 opprinnelse.
- **Inline-beskrivelse.** `variation-description.php`: piller er borte;
  rekkefølgen er smak → tekst → `p.origin-meta` (flagg + land | varietet |
  høyde, Mendl 0.78em, dempet) → CTA. Stil i temaets
  `_rich-attribute-suite.scss`; de døde pille-reglene i `_variations.scss`
  er fjernet.

Verifisert i nettleser (staging, 1366×900 og 390×844): body scroller ikke
på noen av dem for Qori Inti, stripen bytter opprinnelse og speiler
`select`-verdien, ingen konsollfeil. Skjermbilder `ras-port/qa/100–105`.

## Avgrensning

- Flagg-SVG-er er ikke levert. `wc_ras_country_flag_url()` returnerer
  null, og modal-markup håndterer det — pille viser bare landsnavn.
- URL-param deep-link (`?attribute_pa_opprinnelse={slug}`) er fase 4
  — ikke i scope her. Modalen åpner kun på eksplisitt CTA-klikk.
- Hvis et tema rendrer `pa_opprinnelse` som noe annet enn en `<select>`
  (f.eks. via Variation Swatches-plugin), committer `selectOrigin` ved
  å oppdatere den skjulte selecten som de pluginene synkroniserer mot.

### Runde 3–4 (samme dag)

- Sertifiseringer: rene ikoner ved siden av landstempelet.
- Chart: hårlinjeringer 25/75 (prikket), 50 (stiplet), 100 (heltrukket); fyll .42, strek 1.75; toppaksen(e) får `wc-ras-radar__label--peak`; eyebrow «Smaksprofil» (`data-field="flavour-label"`) over chartet.
- Desktop: arket dimensjoneres etter innhold (`maxHeight` = bildeboksen), skygge + dempet foto under (`.woocommerce-product-gallery:has(> dialog[open]:not(:modal))::after`). To kolonner; full spec (region, antall, metode, tørking) vises. Stripen skjult.
- Mobil: bottom sheet (`--wc-ras-sheet-offset`, avrundede topphjørner, backdrop), håndtak (`[data-origin-modal-handle]`) i stedet for ×, faner nederst med linje på toppen, «more»-celler skjult. `wireDragToClose`: dra ned hvor som helst på kortet (body på scrolltopp, mer vertikalt enn horisontalt) lukker over 90 px; `close`-event scroller sidens egen tile inn i syne.
