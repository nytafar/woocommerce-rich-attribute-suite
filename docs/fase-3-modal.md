# Fase 3 — Modal

**Status:** Ikke startet.

## Mål

Native `<dialog>`-basert modal som åpnes fra CTA-lenken på produktsiden
(`<a data-open-origin-modal>` i variation-description). Viser full
opprinnelses-info — featured image, hero, producers, post-harvest,
klientside-rendret taste-radar, sertifiseringer — og lar kunden sveipe
mellom tilgjengelige opprinnelser for produktet før de committer et valg.

## Forutsetninger fra fase 1 + 2

Det meste av infrastrukturen er på plass:

- **Variasjons-preload** leverer full `wc_ras_origin`-struktur per variasjon
  (`wc_ras_build_origin_struct` i `frontend-hooks.php`). Modalen trenger
  ingen ekstra data-henting — alt ligger på `form.variations_form`
  `dataset.product_variations`.
- **CTA-hook**: `data-open-origin-modal`-attributt på CTA-lenken i
  `templates/parts/variation-description.php`. Fase 3-JS fanger klikk,
  `preventDefault()`, åpner modal. Uten JS: lenken navigerer til CPT-
  siden (fallback).
- **Radar-JS**: `window.WcRasOriginRadar.render(profile)` registrert via
  `origin-frontend.php::wc_ras_origin_register_radar_script` (handle
  `wc-ras-origin-radar`). `wcRasTasteAxes` localized globalt. Fase 3
  enqueuer handle der modalen rendres.
- **Render-hjelpere**: PHP-side har `wc_ras_render_postharvest_item`,
  `wc_ras_origin_icon`, flagg-URL, altitude-formatter. JS-side har
  radar-render. Kan gjenbrukes / re-implementeres i modal-JS.
- **Origin-siden**: `/opprinnelser/{slug}/` eksisterer og viser samme
  strukturelle innhold (hero, producers, post-harvest, radar,
  certifications). Modalen er i praksis en in-place variant av single-
  siden.

## Arkitektur-skisse

### DOM

Ett `<dialog>`-element injiseres i footer (eller inne i produktformen),
én instans per produktside. Kort for hver tilgjengelig opprinnelse
rendres som siblings inne i en swipe-container med
`scroll-snap-type: x mandatory`.

```
<dialog class="wc-ras-origin-modal" id="wc-ras-origin-modal">
  <button class="close" data-close-origin-modal>×</button>
  <div class="swipe-track">
    <article class="card" data-origin-slug="peru-qoriinti">
      <!-- full origin content: hero, producers, post-harvest, radar, certs -->
    </article>
    <article class="card" data-origin-slug="nicaragua-san-pedro">
      <!-- ... -->
    </article>
    <!-- ... -->
  </div>
  <div class="actions">
    <button data-select-origin>Velg opprinnelse</button>
  </div>
</dialog>
```

### Rendering-strategi

To alternativer:

**A. Server-rendret (som fase 2 variation-description):**
- PHP-rendrer hver `article.card` ved initial page load, én per unik
  `wc_ras_origin` i variasjons-listen.
- JS håndterer kun: `showModal()`, swipe, "Velg opprinnelse"-klikk.
- Fordeler: SEO, no-JS fallback (ish — dialog krever JS for å åpne),
  enklere JS, konsistent med radar-paritet.
- Ulemper: tyngre initial HTML.

**B. Klient-rendret (lazy):**
- Ingen HTML rendres før modalen åpnes første gang.
- JS bygger `article.card` fra `wc_ras_origin`-dataen som allerede
  ligger på preloaden.
- Fordeler: ingen HTML-tyngde for brukere som aldri åpner modal.
- Ulemper: radar må rendres klientside (allerede forberedt), post-
  harvest-markup må re-implementeres i JS.

**Anbefaling:** B (lazy klient-render), memoizert så gjen-åpning er
gratis. Variant A er et fallback hvis kompleksiteten viser seg for høy.

### Swipe + commit-semantikk

- Horisontal scroll-snap mellom kort (native CSS, ingen bibliotek).
- Ved scroll-slutt: oppdater en indikator ("Peru Qori Inti — 2 av 4").
- "Velg opprinnelse" committer:
  1. Finn nåværende kort-slug.
  2. Trigger `change`-event på pa_opprinnelse-select/radio med den
     verdien.
  3. WC oppdaterer resten av formen; `inline-variation-description.js`
     re-swapper description-raden.
  4. `dialog.close()`.

### URL-param deep-link (forhold til fase 4)

Hvis URL-en har `?attribute_pa_opprinnelse={slug}`, skal modalen åpne
med det kortet som initial slide. Bygg logikk inn i init — enkelt
matching mot `dataset`-attributtet.

## JS-kontrakt

Foreslåtte filer:

- `assets/js/origin-modal.js` — hoved-JS, vanilla. Lytter på
  `[data-open-origin-modal]`-klikk, håndterer
  `showModal()`, swipe, commit.
- (Eksisterende) `assets/js/origin-radar.js` — enqueues som dependency.

Foreslåtte PHP-endringer:

- `includes/origin/origin-frontend.php` — enqueue modal-JS på
  produktsider (med `wc-ras-origin-radar` som dependency), registrer
  modal-container-render-hook (via `wp_footer` eller template-hook).
- `templates/parts/origin-modal.php` — server-rendret dialog-shell
  (selv om kort bygges klientside, shell-en med close-knapp + actions
  er server-rendret for enkel styling).

## CSS-strategi

Følger fase 2-mønsteret:
- Ingen plugin-CSS for modal-utseende — tema eier.
- Plugin leverer *kun* kritisk strukturell CSS som får modalen til å
  fungere (scroll-snap, dialog-display, swipe-track-overflow).
- Klassenavn kontekstuelle, ikke BEM.

## Åpne spørsmål

1. **Hvilke opprinnelser vises i swipen?**
   - Alle unike `wc_ras_origin` fra product's variations? (vanligste
     ønske)
   - Eller globalt alle opprinnelser på siten? (cross-product discovery)
   - Default: produktets egne.

2. **Close-semantikk.** Klikk utenfor kortet → close? Esc-tasten
   (native dialog-støtte)? "Avbryt"-knapp?

3. **"Velg opprinnelse"-knappen — trengs den egentlig?**
   Alternativt: swipe og klikk utenfor committer det synlige kortet;
   egen knapp forvirrer når kortet allerede er synlig. Bekreft UX.

4. **Fokushåndtering.** Native dialog gir auto-focus; vi må kanskje
   overstyre for å fokusere nåværende swipe-kortets heading.

5. **Flagg-SVG-er**. Fortsatt ikke innlevert (se fase 2-rapporten).
   Hvis modal åpner før flagg finnes, pille viser bare tekst.

6. **Performance.** Ved 10+ variasjoner duplikeres wc_ras_origin i
   preloaden — ~900 bytes × N per variasjon. Bør modal dedupe
   klientside før render? Eller utsette som fase 4+?

## Testscenarioer (manuell QA)

- Klikk "Lær mer" → modal åpner med riktig opprinnelse.
- Swipe venstre/høyre → kort snapper, indikator oppdateres.
- "Velg opprinnelse" → modal lukker, pa_opprinnelse oppdateres, pris/
  stock/blurb reflekterer valget.
- URL med `?attribute_pa_opprinnelse={slug}` → modalen kan åpnes og
  initial slide er det kortet.
- Uten JS → klikk på CTA navigerer til CPT-single.
- Radar i modal matcher radar på CPT-single visuelt (paritet PHP ↔ JS).
- Esc-tasten lukker modal.

## Leveranse-sjekkliste

- [ ] `assets/js/origin-modal.js` (vanilla, native dialog, swipe,
      commit).
- [ ] `templates/parts/origin-modal.php` (shell, optional).
- [ ] Enqueue-regler i `origin-frontend.php`.
- [ ] Close-handler + fokushåndtering.
- [ ] Dogfood test-scenarioer over.
- [ ] Oppdater `docs/fase-3-implementasjon.md` (tilsvarende fase 2-
      rapporten).
