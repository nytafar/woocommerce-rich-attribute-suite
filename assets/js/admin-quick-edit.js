/**
 * WooCommerce Rich Attribute Suite - Admin Quick Edit
 * 
 * Handles the description field in quick edit for product attribute terms.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 * @since 1.2.0
 */
(function($) {
    'use strict';

    // Store the original inlineEditTax.edit function
    var originalEdit = null;

    /**
     * Initialize quick edit description support
     */
    function init() {
        // Check if inlineEditTax exists
        if (typeof inlineEditTax === 'undefined') {
            return;
        }

        // Store original edit function
        originalEdit = inlineEditTax.edit;

        // Override the edit function
        inlineEditTax.edit = function(id) {
            // Call original function
            originalEdit.apply(this, arguments);

            // Get the term ID
            if (typeof id === 'object') {
                id = this.getId(id);
            }

            // Get the row being edited
            var $row = $('#tag-' + id);
            var $editRow = $('#edit-' + id);

            if (!$row.length || !$editRow.length) {
                return;
            }

            // Get the description from the hidden field in our custom column
            var description = '';
            
            // Try to get from our hidden field first
            var $hiddenDesc = $row.find('.term-description-full');
            if ($hiddenDesc.length) {
                description = $hiddenDesc.val();
            }

            // Set the description in the quick edit textarea
            var $textarea = $editRow.find('textarea[name="description"]');
            if ($textarea.length) {
                $textarea.val(description);
            }
        };
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
