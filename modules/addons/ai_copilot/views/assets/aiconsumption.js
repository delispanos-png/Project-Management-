/**
 * AI Consumption Dashboard JavaScript
 *
 * Handles checkbox functionality and export button visibility
 *
 * @package WHMCS\Module\Addon\AiCopilot
 */

jQuery(document).ready(function($) {
    'use strict';

    /**
     * Remove "With Selected:" text node that appears in the form
     */
    $('form').contents().filter(function() {
        return this.nodeType === 3 && this.textContent.indexOf('With Selected') !== -1;
    }).remove();

    /**
     * Toggle export button visibility based on checkbox selection
     */
    function toggleExportButton() {
        var checkedCount = $('.datatable input[type="checkbox"][name="selected[]"]:checked').length;
        var $exportButton = $('button[name="export"]');

        if (checkedCount > 0) {
            $exportButton.show();
        } else {
            $exportButton.hide();
        }
    }

    // Initial state - hide export button
    toggleExportButton();

    /**
     * Handle "select all" checkbox functionality
     */
    $(document).on('change', '#checkall1', function() {
        var isChecked = $(this).prop('checked');
        $('.datatable input[type="checkbox"][name="selected[]"]').prop('checked', isChecked);
        toggleExportButton();
    });

    /**
     * Update "select all" checkbox state when individual checkboxes change
     */
    $(document).on('change', '.datatable input[type="checkbox"][name="selected[]"]', function() {
        var totalCheckboxes = $('.datatable input[type="checkbox"][name="selected[]"]').length;
        var checkedCheckboxes = $('.datatable input[type="checkbox"][name="selected[]"]:checked').length;
        $('#checkall1').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
        toggleExportButton();
    });
});

