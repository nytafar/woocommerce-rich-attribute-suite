/**
 * WooCommerce Rich Attribute Suite - Inline Variation Description
 * 
 * Injects and updates an inline description row in the variations table.
 * This script works with the inline-variation-description.php class
 * to provide a CLS-free variation description experience.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 * @since 1.2.0
 */
(function() {
    'use strict';

    var config = window.wcRasInlineDesc || {
        animationDuration: 200,
        targetAttribute: '',
        autoDetect: true
    };

    var duration = config.animationDuration;

    // ── Animation helpers ────────────────────────────────────────

    /**
     * Slide an element down from height 0 (display:none → visible)
     */
    function slideDown(el, ms, callback) {
        el.style.display = '';
        el.style.clipPath = 'inset(0)';
        el.style.willChange = 'height';
        var target = el.scrollHeight;
        el.style.height = '0px';
        el.offsetHeight; // reflow
        el.style.transition = 'height ' + ms + 'ms ease';
        el.style.height = target + 'px';
        el.addEventListener('transitionend', function handler(e) {
            if (e.target !== el || e.propertyName !== 'height') return;
            el.removeEventListener('transitionend', handler);
            el.style.transition = '';
            el.style.height = '';
            el.style.clipPath = '';
            el.style.willChange = '';
            if (callback) callback();
        });
    }

    function normalizeDescriptionLayout(container) {
        container.style.boxSizing = 'border-box';
        container.style.display = 'flow-root';

        var linkWrapper = container.querySelector('p.term-page-link-wrapper');
        if (linkWrapper) {
            linkWrapper.style.clear = 'both';
        }
    }

    /**
     * Slide an element up to height 0, then display:none
     */
    function slideUp(el, ms, callback) {
        el.style.clipPath = 'inset(0)';
        el.style.willChange = 'height';
        el.style.height = el.scrollHeight + 'px';
        el.offsetHeight; // reflow
        el.style.transition = 'height ' + ms + 'ms ease';
        el.style.height = '0px';
        el.addEventListener('transitionend', function handler(e) {
            if (e.target !== el || e.propertyName !== 'height') return;
            el.removeEventListener('transitionend', handler);
            el.style.transition = '';
            el.style.height = '';
            el.style.clipPath = '';
            el.style.willChange = '';
            el.style.display = 'none';
            if (callback) callback();
        });
    }

    /**
     * Animate height of container from old to new value
     */
    function animateHeight(container, oldHeight, ms, callback) {
        container.style.boxSizing = 'border-box';
        container.style.height = 'auto';
        var newHeight = container.offsetHeight;
        if (oldHeight === newHeight) {
            container.style.height = '';
            if (callback) callback();
            return;
        }
        container.style.height = oldHeight + 'px';
        container.offsetHeight; // reflow
        container.style.transition = 'height ' + ms + 'ms ease';
        container.style.height = newHeight + 'px';
        container.addEventListener('transitionend', function handler(e) {
            if (e.target !== container || e.propertyName !== 'height') return;
            container.removeEventListener('transitionend', handler);
            container.style.transition = '';
            container.style.height = '';
            if (callback) callback();
        });
    }

    // ── Core logic ───────────────────────────────────────────────

    /**
     * Initialize inline description handling
     */
    function init() {
        var forms = document.querySelectorAll('.variations_form');
        forms.forEach(function(form) {
            if (form.dataset.wcRasInlineInit) return;
            form.dataset.wcRasInlineInit = 'true';

            var targetRow = findTargetAttributeRow(form);
            if (!targetRow) return;

            var descRow = createDescriptionRow();
            targetRow.after(descRow);

            var container = descRow.querySelector('.wc-ras-inline-description');

            // WooCommerce fires these as jQuery events
            jQuery(form).on('found_variation', function(event, variation) {
                updateDescription(descRow, container, variation);
            });

            jQuery(form).on('show_variation', function(event, variation) {
                updateDescription(descRow, container, variation);
            });

            jQuery(form).on('reset_data hide_variation', function() {
                hideDescription(descRow, container);
            });
        });
    }

    /**
     * Find the target attribute row to insert description after
     */
    function findTargetAttributeRow(form) {
        var tbody = form.querySelector('table.variations tbody');
        if (!tbody) return null;

        if (config.targetAttribute) {
            var row = tbody.querySelector('tr.attribute-' + config.targetAttribute)
                || tbody.querySelector('tr[class*="' + config.targetAttribute + '"]');
            if (row) return row;
        }

        return tbody.querySelector('tr');
    }

    /**
     * Create the description row element
     */
    function createDescriptionRow() {
        var tr = document.createElement('tr');
        tr.className = 'wc-ras-inline-description-row';
        tr.style.display = 'none';
        var td = document.createElement('td');
        td.colSpan = 2;
        var div = document.createElement('div');
        div.className = 'wc-ras-inline-description woocommerce-variation-description';
        td.appendChild(div);
        tr.appendChild(td);
        return tr;
    }

    /**
     * Update the description content
     */
    var lastDescription = '';

    function updateDescription(row, container, variation) {
        var description = variation.variation_description || '';
        if (!description) {
            lastDescription = '';
            hideDescription(row, container);
            return;
        }

        // Skip if content hasn't changed
        if (description === lastDescription) return;
        lastDescription = description;

        var isVisible = row.style.display !== 'none';

        if (!isVisible) {
            // First show
            container.innerHTML = description;
            normalizeDescriptionLayout(container);
            slideDown(row, duration);
        } else {
            // Already visible — animate height only
            var oldHeight = container.offsetHeight;
            container.innerHTML = description;
            normalizeDescriptionLayout(container);
            animateHeight(container, oldHeight, duration);
        }
    }

    /**
     * Hide the description row
     */
    function hideDescription(row, container) {
        if (row.style.display !== 'none') {
            slideUp(row, duration, function() {
                container.innerHTML = '';
            });
        } else {
            container.innerHTML = '';
        }
    }

    // ── Bootstrap ────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-init on WooCommerce AJAX (e.g. dynamically loaded content)
    document.addEventListener('ajaxComplete', function(e) {
        if (e.detail && e.detail.url && e.detail.url.indexOf('wc-ajax') !== -1) {
            init();
        }
    });
})();
