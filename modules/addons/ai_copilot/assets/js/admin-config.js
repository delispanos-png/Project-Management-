/**
 * AI Support Copilot - Admin Configuration Page JavaScript
 */
(function($) {
    'use strict';

    let ajaxUrl = '';

    /**
     * Initialize the module
     */
    function init(url) {
        ajaxUrl = url;
        interceptShowConfig();
        handlePageLoad();
        clearUrlParameters();
    }

    /**
     * Intercept WHMCS showConfig function
     */
    function interceptShowConfig() {
        const originalShowConfig = window.showConfig;

        window.showConfig = function(module) {
            if (originalShowConfig) {
                originalShowConfig(module);
            }

            if (module === 'ai_copilot') {
                checkAiCopilotLicense();
            }
        };
    }

    /**
     * Check license status via AJAX
     */
    function checkAiCopilotLicense() {
        if ($('#ai_copilot-license-status').length === 0) {
            createStatusRow();
        }

        $.ajax({
            url: ajaxUrl,
            method: 'GET',
            dataType: 'json',
            success: handleLicenseCheckSuccess,
            error: handleLicenseCheckError
        });
    }

    /**
     * Create license status row in config table
     */
    function createStatusRow() {
        $('#ai_copilotconfig table.form').prepend(
            '<tr id="ai_copilot-license-status-row">' +
                '<td class="fieldlabel">License Status</td>' +
                '<td class="fieldarea" id="ai_copilot-license-status">' +
                    '<span class="label label-info"><i class="fas fa-spinner fa-spin"></i> Checking license...</span>' +
                '</td>' +
            '</tr>'
        );
    }

    /**
     * Handle successful license check response
     */
    function handleLicenseCheckSuccess(response) {
        const statusContainer = $('#ai_copilot-license-status');
        let statusHtml;
        let iconClass;

        switch (response.status) {
            case 'active':
                iconClass = 'fas fa-check-circle';
                statusHtml = '<span class="label label-success"><i class="' + iconClass + '"></i> ' + response.message + '</span>';
                break;
            case 'not_configured':
                iconClass = 'fas fa-exclamation-triangle';
                statusHtml = '<span class="label label-warning"><i class="' + iconClass + '"></i> ' + response.message + '</span>';
                break;
            case 'inactive':
            case 'error':
                iconClass = 'fas fa-times-circle';
                statusHtml = '<span class="label label-danger"><i class="' + iconClass + '"></i> ' + response.message + '</span>';
                break;
            default:
                statusHtml = '<span class="label label-default">' + response.message + '</span>';
        }

        statusContainer.html(statusHtml);
    }

    /**
     * Handle license check error
     */
    function handleLicenseCheckError() {
        const statusContainer = $('#ai_copilot-license-status');
        statusContainer.html('<span class="label label-danger"><i class="fas fa-times-circle"></i> Error checking license status</span>');
    }

    /**
     * Handle page load - check license if on ai_copilot config
     */
    function handlePageLoad() {
        $(document).ready(function() {
            if (window.location.hash === '#ai_copilot' && $('#ai_copilotconfig').is(':visible')) {
                checkAiCopilotLicense();
            }
        });
    }

    /**
     * Clear URL parameters after showing success/error message
     */
    function clearUrlParameters() {
        $(document).ready(function() {
            if (window.location.search.indexOf('saved=true') !== -1 || window.location.search.indexOf('error=') !== -1) {
                const hash = window.location.hash;
                const newUrl = window.location.pathname + hash;
                window.history.replaceState({}, document.title, newUrl);
            }
        });
    }

    // Expose init function globally
    window.AiCopilotAdmin = {
        init: init
    };

})(jQuery);

