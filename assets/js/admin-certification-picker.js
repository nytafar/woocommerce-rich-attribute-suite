/**
 * Certification icon picker — wraps wp.media to pick an attachment for the
 * certification taxonomy's icon_svg_id term meta. Vanilla JS; no jQuery
 * required. wp.media is loaded by wp_enqueue_media() from the PHP side.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */
(function () {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        if (!window.wp || !wp.media) {
            return;
        }
        const l10n = window.wcRasCertPicker || {};

        document.querySelectorAll('[data-wc-ras-cert-icon]').forEach(function (container) {
            const chooseBtn = container.querySelector('[data-wc-ras-cert-choose]');
            const removeBtn = container.querySelector('[data-wc-ras-cert-remove]');
            const preview   = container.querySelector('[data-wc-ras-cert-preview]');
            const input     = container.querySelector('[data-wc-ras-cert-input]');

            if (!chooseBtn || !input) { return; }

            let frame = null;

            chooseBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (!frame) {
                    frame = wp.media({
                        title: l10n.frameTitle || 'Velg ikon',
                        button: { text: l10n.frameButton || 'Bruk' },
                        library: { type: l10n.mimeTypes || 'image' },
                        multiple: false
                    });
                    frame.on('select', function () {
                        const attachment = frame.state().get('selection').first().toJSON();
                        setAttachment(container, attachment);
                    });
                }
                frame.open();
            });

            if (removeBtn) {
                removeBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    clearAttachment(container);
                });
            }
        });
    }

    function setAttachment(container, attachment) {
        const input   = container.querySelector('[data-wc-ras-cert-input]');
        const preview = container.querySelector('[data-wc-ras-cert-preview]');
        const chooseBtn = container.querySelector('[data-wc-ras-cert-choose]');
        const removeBtn = container.querySelector('[data-wc-ras-cert-remove]');
        const l10n = window.wcRasCertPicker || {};

        input.value = attachment.id;
        if (preview) {
            preview.innerHTML = '';
            if (attachment.url) {
                const img = document.createElement('img');
                img.src = attachment.url;
                img.alt = '';
                img.style.maxWidth = '64px';
                img.style.maxHeight = '64px';
                preview.appendChild(img);
            }
        }
        if (chooseBtn && l10n.replaceLabel) {
            chooseBtn.textContent = l10n.replaceLabel;
        }
        if (removeBtn) {
            removeBtn.hidden = false;
        }
    }

    function clearAttachment(container) {
        const input     = container.querySelector('[data-wc-ras-cert-input]');
        const preview   = container.querySelector('[data-wc-ras-cert-preview]');
        const chooseBtn = container.querySelector('[data-wc-ras-cert-choose]');
        const removeBtn = container.querySelector('[data-wc-ras-cert-remove]');
        const l10n = window.wcRasCertPicker || {};

        input.value = '';
        if (preview) { preview.innerHTML = ''; }
        if (chooseBtn && l10n.chooseLabel) {
            chooseBtn.textContent = l10n.chooseLabel;
        }
        if (removeBtn) { removeBtn.hidden = true; }
    }
})();
