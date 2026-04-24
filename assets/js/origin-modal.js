/**
 * WooCommerce Rich Attribute Suite — origin modal.
 *
 * Model:
 *   One native <dialog>, one hydratable card. The card's content always
 *   mirrors the current pa_opprinnelse selection. Prev/next/swipe are
 *   gesture shortcuts that commit a new selection through the same
 *   code path an attribute click uses — there is no local modal state.
 *
 * Pipeline:
 *   CTA click → open dialog (mobile showModal / desktop show).
 *   found_variation/show_variation → hydrateCard(origin).
 *   prev/next/arrow/swipe → selectOrigin(slug) → WC fires
 *     found_variation → hydrateCard runs again.
 *
 * jQuery is used only as a bridge for WC variation events and for the
 * canonical .val().trigger('change') commit. All other DOM work is
 * vanilla.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */
(function () {
    'use strict';

    var DESKTOP_MQ = '(min-width: 1024px)';
    var SWIPE_THRESHOLD = 50;

    /**
     * Initialise every variations form on the page exactly once.
     */
    function init() {
        var forms = document.querySelectorAll('form.variations_form');
        forms.forEach(function (form) {
            if (form.dataset.wcRasOriginModalInit === 'true') {
                return;
            }
            form.dataset.wcRasOriginModalInit = 'true';
            setupForm(form);
        });
    }

    /**
     * Wire up a single form + its dialog.
     */
    function setupForm(form) {
        var dialog = document.querySelector('[data-wc-ras-origin-modal]');
        if (!dialog) {
            return;
        }

        var card = dialog.querySelector('[data-origin-modal-card]');
        if (!card) {
            return;
        }

        var ctx = {
            form: form,
            dialog: dialog,
            card: card,
            origins: readOrigins(form),
            lastMode: null
        };

        // Delegated CTA click — variation-description inner HTML is swapped
        // on every variation change by inline-variation-description.js, so
        // we can't bind directly to the <a>.
        document.addEventListener('click', function (e) {
            var cta = e.target && e.target.closest
                ? e.target.closest('[data-open-origin-modal]')
                : null;
            if (!cta || !form.contains(cta)) {
                return;
            }
            e.preventDefault();
            if (dialog.open) {
                dialog.close();
            } else {
                openDialog(ctx);
            }
        });

        // WC variation events → hydrate
        if (window.jQuery) {
            window.jQuery(form).on('found_variation show_variation', function (_event, variation) {
                var origin = variation && variation.wc_ras_origin;
                if (origin) {
                    hydrateCard(ctx, origin);
                }
            });
            window.jQuery(form).on('reset_data hide_variation', function () {
                hideAllSections(ctx.card);
                updateNavDisabled(ctx);
            });
        }

        // Close semantics
        dialog.addEventListener('click', function (e) {
            // Backdrop: clicking the dialog element itself (outside the card).
            if (e.target === dialog) {
                dialog.close();
                return;
            }
            var closer = e.target.closest && e.target.closest('[data-origin-modal-close]');
            if (closer) {
                e.preventDefault();
                dialog.close();
            }
        });

        // Prev / next navigation
        dialog.addEventListener('click', function (e) {
            var nav = e.target.closest && e.target.closest('[data-origin-modal-nav]');
            if (!nav) {
                return;
            }
            e.preventDefault();
            var dir = nav.getAttribute('data-origin-modal-nav') === 'next' ? 1 : -1;
            stepSelection(ctx, dir);
        });

        // Arrow keys while dialog is open
        dialog.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                stepSelection(ctx, 1);
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                stepSelection(ctx, -1);
            }
        });

        // Swipe gesture on the card
        wireSwipe(ctx);

        // Window resize → re-evaluate desktop/mobile mode
        var resizeTimer = null;
        window.addEventListener('resize', function () {
            if (resizeTimer) {
                window.clearTimeout(resizeTimer);
            }
            resizeTimer = window.setTimeout(function () {
                if (dialog.open && currentMode() !== ctx.lastMode) {
                    dialog.close();
                    openDialog(ctx);
                }
            }, 200);
        });

        // Seed nav-disabled based on the initial origin count.
        updateNavDisabled(ctx);
    }

    // ── Data helpers ────────────────────────────────────────────

    /**
     * Read the preloaded variation list and dedupe on origin slug.
     *
     * @return {Array<{slug:string,[key:string]:any}>}
     */
    function readOrigins(form) {
        var raw = form.dataset.product_variations;
        if (!raw) {
            return [];
        }
        var variations;
        try {
            variations = JSON.parse(raw);
        } catch (e) {
            return [];
        }
        if (!Array.isArray(variations)) {
            return [];
        }
        var seen = {};
        var out = [];
        for (var i = 0; i < variations.length; i++) {
            var v = variations[i];
            var origin = v && v.wc_ras_origin;
            if (origin && origin.slug && !seen[origin.slug]) {
                seen[origin.slug] = true;
                out.push(origin);
            }
        }
        return out;
    }

    function findOriginSelect(form) {
        return form.querySelector('select[name="attribute_pa_opprinnelse"]')
            || form.querySelector('[name="attribute_pa_opprinnelse"]');
    }

    function currentOriginSlug(ctx) {
        var sel = findOriginSelect(ctx.form);
        if (sel && sel.value) {
            return sel.value;
        }
        // Fallback: the card's currently hydrated slug.
        return ctx.card.getAttribute('data-current-slug') || '';
    }

    /**
     * Commit a new origin choice through the WC variation pipeline.
     * found_variation will fire and hydrateCard runs.
     */
    function selectOrigin(ctx, slug) {
        if (!slug) {
            return;
        }
        var sel = findOriginSelect(ctx.form);
        if (!sel) {
            return;
        }
        if (sel.value === slug) {
            return;
        }
        sel.value = slug;
        if (window.jQuery) {
            window.jQuery(sel).trigger('change');
        } else {
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function stepSelection(ctx, dir) {
        var origins = ctx.origins;
        if (!origins.length || origins.length <= 1) {
            return;
        }
        var current = currentOriginSlug(ctx);
        var idx = 0;
        for (var i = 0; i < origins.length; i++) {
            if (origins[i].slug === current) {
                idx = i;
                break;
            }
        }
        var nextIdx = (idx + dir + origins.length) % origins.length;
        selectOrigin(ctx, origins[nextIdx].slug);
    }

    // ── Open / close ────────────────────────────────────────────

    function currentMode() {
        return window.matchMedia(DESKTOP_MQ).matches ? 'desktop' : 'mobile';
    }

    function openDialog(ctx) {
        var mode = currentMode();
        ctx.lastMode = mode;
        // Re-evaluate nav-disabled every time we open.
        updateNavDisabled(ctx);
        try {
            if (mode === 'desktop') {
                ctx.dialog.show();
            } else {
                ctx.dialog.showModal();
            }
        } catch (_err) {
            // Fallback: if showModal() throws (already open, etc.), force open attribute.
            ctx.dialog.setAttribute('open', '');
        }
    }

    // ── Hydration ───────────────────────────────────────────────

    /**
     * Set the text content / attribute of a [data-field] element and
     * hide it when the value is empty.
     *
     * @param {HTMLElement} card
     * @param {string} field
     * @param {string} value
     * @param {'text'|'src'|'href'|'alt'} [mode='text']
     */
    function setField(card, field, value, mode) {
        var el = card.querySelector('[data-field="' + field + '"]');
        if (!el) {
            return;
        }
        var hasValue = value !== null && value !== undefined && value !== '';
        if (!hasValue) {
            el.hidden = true;
            return;
        }
        if (mode === 'src') {
            el.setAttribute('src', value);
        } else if (mode === 'href') {
            el.setAttribute('href', value);
        } else if (mode === 'alt') {
            el.setAttribute('alt', value);
        } else {
            el.textContent = value;
        }
        el.hidden = false;
    }

    function setGroup(card, groupName, visible) {
        var el = card.querySelector('[data-field-group="' + groupName + '"]');
        if (!el) {
            return;
        }
        el.hidden = !visible;
    }

    function setSection(card, sectionName, visible) {
        var el = card.querySelector('[data-section="' + sectionName + '"]');
        if (!el) {
            return;
        }
        el.hidden = !visible;
    }

    function s(v) {
        return (v === null || v === undefined) ? '' : String(v);
    }

    function hydrateCard(ctx, origin) {
        var card = ctx.card;

        // Track current slug so nav math works without relying on the
        // select's state (some themes swap select for buttons).
        if (origin.slug) {
            card.setAttribute('data-current-slug', origin.slug);
        }

        // Hero
        var imageUrl = s(origin.featured_image_url);
        var imageEl = card.querySelector('[data-field="featured-image"]');
        if (imageEl) {
            if (imageUrl) {
                imageEl.setAttribute('src', imageUrl);
                imageEl.setAttribute('alt', s(origin.name));
                imageEl.hidden = false;
            } else {
                imageEl.hidden = true;
            }
        }
        setField(card, 'name', s(origin.name));
        setField(card, 'excerpt', s(origin.excerpt));

        // Pills
        var regionLabel = s(origin.region_label);
        var flagUrl = s(origin.country_flag_url);
        setGroup(card, 'flag', regionLabel !== '');
        var flagImgEl = card.querySelector('[data-field="flag-image"]');
        if (flagImgEl) {
            if (flagUrl) {
                flagImgEl.setAttribute('src', flagUrl);
                flagImgEl.setAttribute('alt', s(origin.country));
                flagImgEl.hidden = false;
            } else {
                flagImgEl.hidden = true;
            }
        }
        var flagLabelEl = card.querySelector('[data-field="flag-label"]');
        if (flagLabelEl) {
            flagLabelEl.textContent = regionLabel;
        }
        setGroup(card, 'variety', s(origin.variety) !== '');
        setField(card, 'variety', s(origin.variety));
        setGroup(card, 'altitude', s(origin.altitude_label) !== '');
        setField(card, 'altitude', s(origin.altitude_label));

        // Producers
        var producerTypeLabel = s(origin.producer_type_label);
        var producerCountLabel = s(origin.producer_count_label);
        var variety = s(origin.variety);
        setGroup(card, 'producer-type', producerTypeLabel !== '');
        setField(card, 'producer-type', producerTypeLabel);
        setField(card, 'producer-count', producerCountLabel);
        setGroup(card, 'variety-line', variety !== '');
        setField(card, 'variety-line', variety);
        setSection(card, 'producers',
            producerTypeLabel !== '' || producerCountLabel !== '' || variety !== '');

        // Post-harvest
        var fermValue = s(origin.fermentation_value);
        var fermMethod = s(origin.fermentation_method);
        var dryMethod = s(origin.drying_method);
        setGroup(card, 'fermentation', fermValue !== '' || fermMethod !== '');
        setField(card, 'fermentation-value', fermValue);
        setField(card, 'fermentation-method', fermMethod);
        setGroup(card, 'drying', dryMethod !== '');
        setField(card, 'drying-method', dryMethod);
        setSection(card, 'postharvest',
            fermValue !== '' || fermMethod !== '' || dryMethod !== '');

        // Flavour
        var radarEl = card.querySelector('[data-field="radar"]');
        var radarSvg = '';
        if (origin.taste_profile && window.WcRasOriginRadar && typeof window.WcRasOriginRadar.render === 'function') {
            radarSvg = window.WcRasOriginRadar.render(origin.taste_profile) || '';
        }
        if (radarEl) {
            if (radarSvg) {
                radarEl.innerHTML = radarSvg;
                radarEl.hidden = false;
            } else {
                radarEl.innerHTML = '';
                radarEl.hidden = true;
            }
        }
        var tasteNotes = s(origin.taste_notes);
        setGroup(card, 'notes', tasteNotes !== '');
        setField(card, 'taste-notes', tasteNotes);
        setSection(card, 'flavour', radarSvg !== '' || tasteNotes !== '');

        // Certifications
        renderCertifications(card, Array.isArray(origin.certifications) ? origin.certifications : []);

        // Permalink
        setField(card, 'permalink', s(origin.permalink), 'href');

        // Nav state (enable/disable prev/next if only one origin exists)
        updateNavDisabled(ctx);
    }

    function renderCertifications(card, certs) {
        var list = card.querySelector('[data-field="certifications"]');
        if (!list) {
            return;
        }
        list.innerHTML = '';
        certs.forEach(function (cert) {
            var li = document.createElement('li');
            if (cert.slug) {
                li.setAttribute('data-cert-slug', cert.slug);
            }
            if (cert.icon_url) {
                var img = document.createElement('img');
                img.setAttribute('src', cert.icon_url);
                img.setAttribute('alt', '');
                li.appendChild(img);
            }
            var label = document.createElement('span');
            label.textContent = cert.name || '';
            li.appendChild(label);
            list.appendChild(li);
        });
        setSection(card, 'certifications', certs.length > 0);
    }

    function hideAllSections(card) {
        ['producers', 'postharvest', 'flavour', 'certifications'].forEach(function (name) {
            setSection(card, name, false);
        });
    }

    function updateNavDisabled(ctx) {
        var disabled = ctx.origins.length <= 1;
        var buttons = ctx.dialog.querySelectorAll('[data-origin-modal-nav]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].disabled = disabled;
        }
    }

    // ── Swipe ───────────────────────────────────────────────────

    function wireSwipe(ctx) {
        var startX = null;
        var startY = null;

        ctx.card.addEventListener('touchstart', function (e) {
            if (!e.touches || e.touches.length !== 1) {
                startX = null;
                return;
            }
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });

        ctx.card.addEventListener('touchend', function (e) {
            if (startX === null) {
                return;
            }
            var touch = (e.changedTouches && e.changedTouches[0]) || null;
            if (!touch) {
                startX = null;
                return;
            }
            var dx = touch.clientX - startX;
            var dy = touch.clientY - startY;
            startX = null;
            startY = null;
            if (Math.abs(dx) < SWIPE_THRESHOLD) {
                return;
            }
            // Require mostly-horizontal gesture so vertical scroll wins
            // when the intent is scrolling the card.
            if (Math.abs(dx) < Math.abs(dy)) {
                return;
            }
            stepSelection(ctx, dx < 0 ? 1 : -1);
        }, { passive: true });
    }

    // ── Bootstrap ───────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-init on WC AJAX fragments (e.g. dynamically loaded variation forms).
    document.addEventListener('ajaxComplete', function (e) {
        if (e.detail && e.detail.url && e.detail.url.indexOf('wc-ajax') !== -1) {
            init();
        }
    });
})();
