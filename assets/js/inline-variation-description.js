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
        container.style.display = '';
    }

    function animateLinkWrapperPosition(container, oldTop, ms) {
        if (oldTop == null) return;

        var linkWrapper = container.querySelector('p.term-page-link-wrapper');
        if (!linkWrapper) return;

        var newTop = linkWrapper.getBoundingClientRect().top;
        var deltaY = oldTop - newTop;

        if (Math.abs(deltaY) < 1) return;

        linkWrapper.style.willChange = 'transform';
        linkWrapper.style.transition = 'none';
        linkWrapper.style.transform = 'translateY(' + deltaY + 'px)';
        linkWrapper.offsetHeight; // reflow
        linkWrapper.style.transition = 'transform ' + ms + 'ms ease';
        linkWrapper.style.transform = 'translateY(0)';

        linkWrapper.addEventListener('transitionend', function handler(e) {
            if (e.target !== linkWrapper || e.propertyName !== 'transform') return;
            linkWrapper.removeEventListener('transitionend', handler);
            linkWrapper.style.transition = '';
            linkWrapper.style.transform = '';
            linkWrapper.style.willChange = '';
        });
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

    function getCssVarMs(name, fallback) {
        var raw = window.getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        if (!raw) return fallback;

        var value;
        if (raw.slice(-2) === 'ms') {
            value = parseFloat(raw);
        } else if (raw.slice(-1) === 's') {
            value = parseFloat(raw) * 1000;
        } else {
            value = parseFloat(raw);
        }

        return Number.isFinite(value) ? value : fallback;
    }

    function getCssVarNumber(name, fallback) {
        var raw = window.getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        if (!raw) return fallback;
        var value = parseFloat(raw);
        return Number.isFinite(value) ? value : fallback;
    }

    function fadeOutContentStagger(container, ms, callback) {
        var elements = Array.prototype.slice.call(container.children).filter(function(el) {
            return !(el.matches && el.matches('p.term-page-link-wrapper'));
        });

        if (!elements.length) {
            if (callback) callback();
            return;
        }

        var fadeDuration = getCssVarMs('--wc-inline-desc-fade-out-ms', Math.max(80, Math.min(140, ms)));
        var stagger = getCssVarMs('--wc-inline-desc-fade-out-stagger-ms', 25);
        var tailDelay = getCssVarMs('--wc-inline-desc-fade-out-tail-ms', 20);
        var maxDelay = 0;

        elements.forEach(function(el, index) {
            var delay = index * stagger;
            if (delay > maxDelay) {
                maxDelay = delay;
            }

            el.style.willChange = 'opacity';
            el.style.transition = 'opacity ' + fadeDuration + 'ms ease';
            el.style.transitionDelay = delay + 'ms';
            el.style.opacity = '0';
        });

        window.setTimeout(function() {
            if (callback) callback();
        }, fadeDuration + maxDelay + tailDelay);
    }

    function fadeInContentStagger(container, heightDuration) {
        var elements = Array.prototype.slice.call(container.children).filter(function(el) {
            return !(el.matches && el.matches('p.term-page-link-wrapper'));
        });

        if (!elements.length) {
            return;
        }

        var fadeInDuration = getCssVarMs('--wc-inline-desc-fade-in-ms', Math.max(110, Math.min(190, Math.round(heightDuration * 0.45))));
        var fadeInStagger = getCssVarMs('--wc-inline-desc-fade-in-stagger-ms', 20);
        var growRatio = getCssVarNumber('--wc-inline-desc-fade-in-grow-ratio', 0.2);
        var normalizedGrowRatio = Math.max(0, Math.min(1, growRatio));
        var fadeInBaseDelay = Math.round(heightDuration * normalizedGrowRatio);

        elements.forEach(function(el) {
            el.style.opacity = '0';
            el.style.transition = 'none';
            el.style.transitionDelay = '0ms';
        });

        container.offsetHeight; // reflow

        elements.forEach(function(el, index) {
            var delay = fadeInBaseDelay + (index * fadeInStagger);
            el.style.willChange = 'opacity';
            el.style.transition = 'opacity ' + fadeInDuration + 'ms ease';
            el.style.transitionDelay = delay + 'ms';
            el.style.opacity = '1';
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
            // Already visible — quick staggered fade-out, then animate height
            var oldHeight = container.offsetHeight;
            var oldLinkWrapper = container.querySelector('p.term-page-link-wrapper');
            var oldLinkTop = oldLinkWrapper ? oldLinkWrapper.getBoundingClientRect().top : null;

            fadeOutContentStagger(container, Math.round(duration * 0.35), function() {
                container.innerHTML = description;
                normalizeDescriptionLayout(container);
                animateHeight(container, oldHeight, duration);
                animateLinkWrapperPosition(container, oldLinkTop, duration);
                fadeInContentStagger(container, duration);
            });
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
