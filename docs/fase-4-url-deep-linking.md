# Fase 4 — URL deep-linking (relatert, utenfor kjerne-RAS-scope)

**Status:** Ikke startet. Vurderes etter fase 3-modal er landet.

## Mål

La produktlenker fra eksterne kilder (produktkort i lister, nyhetsbrev,
sosiale medier) bære `?attribute_pa_opprinnelse={slug}` slik at varianten
forhåndsvelges når kunden lander på produktsiden. Ved variantbytte på
produktsiden oppdateres URL-en via `history.replaceState` slik at
direkte-deling alltid gir riktig variant.

## Kontekst

WooCommerce leser allerede `?attribute_*`-parametre fra URL-en og
forhåndsvelger tilsvarende variant. Bevis: test
`/produkt/myrvann-cacao/?attribute_pa_opprinnelse=peru-qoriinti` og
bekreft at Qori Inti-radio er valgt ved page load. Dette er WC-default.

Det som ikke fungerer out-of-the-box:

1. **Lenker fra produktlistene genereres ikke med parametre.** Arkiv-
   og shop-kort lenker til bare `/produkt/{slug}/` — varianten er
   tilfeldig (avhenger av `default_attributes` eller første tilgjengelige).
2. **URL oppdateres ikke ved variantbytte.** Klikker kunden seg rundt
   på produktsiden for å sammenligne opprinnelser, reflekterer ikke
   URL-en siste valg. Deling av lenken viser ikke det kunden så.

## Leveranse

### Del A: URL-generering på produktkort

Filter produktlenker i lister/shops/widgets. Per produkt velg en
"kanonisk" opprinnelse (typisk `default_attributes['pa_opprinnelse']`
eller første tilgjengelige) og append
`?attribute_pa_opprinnelse={slug}`.

Primær hook: `woocommerce_loop_product_link`
(`$link, $product`). Filter returnerer modified URL.

Hvis produktet ikke har `pa_opprinnelse` eller ikke er variabel →
return URL uendret.

### Del B: URL-oppdatering ved variantbytte

JS-lytter på `found_variation` (jQuery-bro) eller på native `change`
på `pa_opprinnelse`-select/radio. Oppdater URL:

```js
const url = new URL(window.location.href);
url.searchParams.set('attribute_pa_opprinnelse', slug);
history.replaceState(null, '', url);
```

`replaceState` (ikke `pushState`) fordi vi ikke vil spamme browser-
historien ved variantbytte.

Scope: kun på single-produkt-sider.

## Implementering-skisse

### PHP

`includes/origin/origin-url-deep-linking.php`:

```php
function wc_ras_add_origin_param_to_product_link($link, $product) {
    if (!$product->is_type('variable')) return $link;
    $defaults = $product->get_default_attributes();
    $slug = $defaults['pa_opprinnelse'] ?? '';
    if (!$slug) {
        foreach ($product->get_available_variations() as $v) {
            if (!empty($v['attributes']['attribute_pa_opprinnelse'])) {
                $slug = $v['attributes']['attribute_pa_opprinnelse'];
                break;
            }
        }
    }
    if (!$slug) return $link;
    return add_query_arg('attribute_pa_opprinnelse', $slug, $link);
}
add_filter('woocommerce_loop_product_link', 'wc_ras_add_origin_param_to_product_link', 10, 2);
```

### JS

`assets/js/origin-url-sync.js`:

```js
(function() {
    'use strict';
    
    const form = document.querySelector('form.variations_form');
    if (!form) return;
    
    function updateUrl(slug) {
        if (!slug) return;
        const url = new URL(window.location.href);
        url.searchParams.set('attribute_pa_opprinnelse', slug);
        history.replaceState(null, '', url);
    }
    
    const select = form.querySelector('select[name="attribute_pa_opprinnelse"]');
    if (select) select.addEventListener('change', e => updateUrl(e.target.value));
    
    form.querySelectorAll('input[name="attribute_pa_opprinnelse"]').forEach(input => {
        input.addEventListener('change', e => updateUrl(e.target.value));
    });
})();
```

Enqueue på produktsider via `origin-frontend.php`.

## Avhengigheter

- **Fase 1:** `pa_opprinnelse`-attributt eksisterer på produkter.
- **Fase 2:** WC-variasjons-preload fungerer (uendret — WC-default).
- **Fase 3:** Modal kan lese `?attribute_pa_opprinnelse`-param for å
  åpne med riktig kort — men det er en fase 3-internt ansvar, ikke en
  fase 4-avhengighet.

## Åpne spørsmål

1. **Canonical URL for SEO.** Hvis produktet `/produkt/myrvann-cacao/`
   serveres med ulike `?attribute_pa_opprinnelse`-verdier, indekseres
   duplikater. Sett `<link rel="canonical">` til URL uten param?
   Eller ignorer — Google håndterer query-params ok i praksis?
2. **Hvilken "kanonisk" opprinnelse skal produktkort-lenker bære?**
   - `default_attributes` (redaksjonelt valg).
   - Første tilgjengelige (tilfeldig).
   - Den med lagerbeholdning.
   - Velg eksplisitt per produkt (admin-felt).
3. **Bakoverkompatibilitet.** Endrer vi lenker på produktkort, kan det
   påvirke analytics (URL-dimensjon blir fragmentert). Verdt å flagge.
4. **Fungerer på alle tema-lister?** `woocommerce_loop_product_link`-
   filteret dekker WC-lister. Custom tema-lister kan omgå filteret;
   dokumenter dette.

## Testscenarioer

- Besøk produktliste → klikk produkt → URL har
  `?attribute_pa_opprinnelse={slug}` → opprinnelse forhåndsvalgt.
- På produktside, bytt opprinnelse → URL reflekterer.
- Kopier URL fra adressefeltet, åpne i ny fane → samme opprinnelse
  preselected.
- Bytt størrelse (`pa_mengde`) → URL uendret (kun opprinnelse syncet).
- Produkt uten `pa_opprinnelse` → ingen URL-endring, ingen feil.

## Leveranse-sjekkliste

- [ ] `includes/origin/origin-url-deep-linking.php`.
- [ ] `assets/js/origin-url-sync.js`.
- [ ] Enqueue + `require_once` i bootstrap.
- [ ] Canonical-URL-avgjørelse.
- [ ] Dogfood test-scenarioer.
- [ ] `docs/fase-4-implementasjon.md`.
