/**
 * WooCommerce Rich Attribute Suite - Variation Display
 * 
 * Handles the display of attribute meta data when variations change
 */
(function($) {
    'use strict';

    // Initialize on document ready
    $(document).ready(function() {
        // Initialize on variation form
        $('.variations_form').each(function() {
            var $form = $(this);
            
            // When found variation
            $form.on('found_variation', function(event, variation) {
                // Check for region data
                if (variation.attribute_region) {
                    // Create or update region display
                    displayVariationMeta('region', variation.attribute_region);
                } else {
                    // Remove region display if it exists
                    removeVariationMeta('region');
                }
                
                // Check for smak (taste) data
                if (variation.attribute_smak) {
                    // Create or update smak display
                    displayVariationMeta('smak', variation.attribute_smak);
                } else {
                    // Remove smak display if it exists
                    removeVariationMeta('smak');
                }
            });
            
            // When variation is reset
            $form.on('reset_data', function() {
                // Remove all meta displays
                removeVariationMeta('region');
                removeVariationMeta('smak');
            });
        });
    });
    
    /**
     * Display variation meta information
     * 
     * @param {string} type Meta type (region, smak)
     * @param {string} value Meta value
     */
    function displayVariationMeta(type, value) {
        var $summary = $('.product-summary-wrap').first();
        if (!$summary.length) {
            $summary = $('.summary').first();
        }
        
        var $container = $summary.find('.variation-meta-' + type);
        var label = type === 'region' ? 'Region' : 'Smak';
        
        if ($container.length) {
            // Update existing container
            $container.find('.variation-meta-value').text(value);
        } else {
            // Create new container
            var $meta = $('<div class="variation-meta variation-meta-' + type + '">' +
                '<span class="variation-meta-label">' + label + ': </span>' +
                '<span class="variation-meta-value">' + value + '</span>' +
                '</div>');
            
            // Find where to insert it
            var $insertAfter = $summary.find('.price');
            if (!$insertAfter.length) {
                $insertAfter = $summary.find('form.cart').prev();
            }
            
            if ($insertAfter.length) {
                $insertAfter.after($meta);
            } else {
                $summary.prepend($meta);
            }
        }
    }
    
    /**
     * Remove variation meta display
     * 
     * @param {string} type Meta type (region, smak)
     */
    function removeVariationMeta(type) {
        $('.variation-meta-' + type).remove();
    }
    
})(jQuery);
