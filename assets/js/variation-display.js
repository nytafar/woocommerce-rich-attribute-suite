/**
 * WooCommerce Rich Attribute Suite — variation meta display (legacy mode).
 *
 * Opt-in via filter `wc_ras_enable_variation_meta_display` (default false).
 * Renders region and taste notes as standalone rows in the product summary
 * area when a variation is selected. This is separate from the richer
 * origin blurb (see origin-blurb.js) — it exists for sites that only want
 * the minimal summary strip without the full blurb UI.
 *
 * Data source: variation.wc_ras_origin (replaces the legacy flat
 * attribute_region / attribute_smak keys).
 *
 * Vanilla JS for all DOM work; jQuery only as the bridge for WooCommerce's
 * found_variation / reset_data events (jQuery trigger via triggerHandler
 * does not bubble as native DOM events).
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */
(function () {
    'use strict';

    const LABELS = {
        region:      'Region',
        taste_notes: 'Smak'
    };

    function displayMeta(type, value) {
        const summary = document.querySelector('.product-summary-wrap')
            || document.querySelector('.summary');
        if (!summary) { return; }

        const className = 'variation-meta-' + type;
        let container = summary.querySelector('.' + className);
        const label = LABELS[type] || type;

        if (container) {
            const valueEl = container.querySelector('.variation-meta-value');
            if (valueEl) { valueEl.textContent = value; }
            return;
        }

        container = document.createElement('div');
        container.className = 'variation-meta ' + className;

        const labelEl = document.createElement('span');
        labelEl.className = 'variation-meta-label';
        labelEl.textContent = label + ': ';

        const valueEl = document.createElement('span');
        valueEl.className = 'variation-meta-value';
        valueEl.textContent = value;

        container.appendChild(labelEl);
        container.appendChild(valueEl);

        const priceEl = summary.querySelector('.price');
        if (priceEl && priceEl.parentNode) {
            priceEl.parentNode.insertBefore(container, priceEl.nextSibling);
        } else {
            summary.insertBefore(container, summary.firstChild);
        }
    }

    function removeMeta(type) {
        document.querySelectorAll('.variation-meta-' + type).forEach(function (el) {
            el.remove();
        });
    }

    function applyVariation(variation) {
        const origin = variation && variation.wc_ras_origin;
        if (origin && origin.region) {
            displayMeta('region', origin.region);
        } else {
            removeMeta('region');
        }
        if (origin && origin.taste_notes) {
            displayMeta('taste_notes', origin.taste_notes);
        } else {
            removeMeta('taste_notes');
        }
    }

    function resetAll() {
        removeMeta('region');
        removeMeta('taste_notes');
    }

    // jQuery bridge — ONLY for WC variation events.
    function init() {
        if (!window.jQuery) { return; }
        const $ = window.jQuery;
        $('form.variations_form').each(function () {
            const $form = $(this);
            $form.on('found_variation', function (event, variation) {
                applyVariation(variation);
            });
            $form.on('reset_data', resetAll);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
