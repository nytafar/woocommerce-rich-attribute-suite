/**
 * WooCommerce Rich Attribute Suite - Inline Variation Description
 * 
 * Injects and updates an inline description row in the variations table.
 * This script works with the inline-variation-description.php class
 * to provide a CLS-free variation description experience.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 * @since 1.1.0
 */
(function($) {
    'use strict';

    var config = window.wcRasInlineDesc || {
        animationDuration: 200,
        targetAttribute: '',
        autoDetect: true
    };

    /**
     * Initialize inline description handling
     */
    function init() {
        $('.variations_form').each(function() {
            var $form = $(this);
            
            // Skip if already initialized
            if ($form.data('wc-ras-inline-init')) {
                return;
            }
            $form.data('wc-ras-inline-init', true);

            // Find or determine target attribute row
            var $targetRow = findTargetAttributeRow($form);
            if (!$targetRow || !$targetRow.length) {
                return;
            }

            // Create and inject the description row (hidden initially)
            var $descRow = createDescriptionRow();
            $targetRow.after($descRow);

            var $descContainer = $descRow.find('.wc-ras-inline-description');

            // Handle variation found
            $form.on('found_variation', function(event, variation) {
                updateDescription($descRow, $descContainer, variation);
            });

            // Backup: also listen to show_variation for compatibility
            $form.on('show_variation', function(event, variation) {
                updateDescription($descRow, $descContainer, variation);
            });

            // Handle reset
            $form.on('reset_data', function() {
                hideDescription($descRow, $descContainer);
            });

            // Handle hide_variation (when selection becomes incomplete)
            $form.on('hide_variation', function() {
                hideDescription($descRow, $descContainer);
            });
        });
    }

    /**
     * Find the target attribute row to insert description after
     *
     * @param {jQuery} $form The variations form
     * @return {jQuery} The target row element
     */
    function findTargetAttributeRow($form) {
        var $table = $form.find('table.variations tbody');
        if (!$table.length) {
            return null;
        }

        // If target attribute is explicitly configured
        if (config.targetAttribute) {
            var $row = $table.find('tr.attribute-' + config.targetAttribute);
            if ($row.length) {
                return $row;
            }
            // Also try without 'attribute-' prefix (some themes use different classes)
            $row = $table.find('tr[class*="' + config.targetAttribute + '"]');
            if ($row.length) {
                return $row.first();
            }
        }

        // Auto-detect or fallback to first attribute row
        var $rows = $table.find('tr');
        if ($rows.length) {
            return $rows.first();
        }

        return null;
    }

    /**
     * Create the description row element
     *
     * @return {jQuery} The description row element
     */
    function createDescriptionRow() {
        return $('<tr class="wc-ras-inline-description-row" style="display:none;">' +
            '<td colspan="2">' +
            '<div class="wc-ras-inline-description woocommerce-variation-description"></div>' +
            '</td>' +
            '</tr>');
    }

    /**
     * Update the description content
     *
     * @param {jQuery} $row       The description row element
     * @param {jQuery} $container The description container element
     * @param {Object} variation  The variation data from WooCommerce
     */
    function updateDescription($row, $container, variation) {
        var description = variation.variation_description || '';

        if (description) {
            // Update content
            $container.html(description);

            // Show row with smooth transition (no CLS since row exists in DOM)
            if (!$row.is(':visible')) {
                $row.slideDown(config.animationDuration);
            }
        } else {
            hideDescription($row, $container);
        }
    }

    /**
     * Hide the description row
     *
     * @param {jQuery} $row       The description row element
     * @param {jQuery} $container The description container element
     */
    function hideDescription($row, $container) {
        if ($row.is(':visible')) {
            $row.slideUp(config.animationDuration, function() {
                $container.empty();
            });
        } else {
            $container.empty();
        }
    }

    // Initialize on document ready
    $(document).ready(init);

    // Also initialize on AJAX complete (for dynamically loaded content)
    $(document).ajaxComplete(function(event, xhr, settings) {
        // Only re-init if this looks like a WooCommerce AJAX call
        if (settings.url && settings.url.indexOf('wc-ajax') !== -1) {
            init();
        }
    });

})(jQuery);
