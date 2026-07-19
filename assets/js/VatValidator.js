let countriesVatRegex = {
    AT: /^ATU\d{8}$/,                       // Austria
    BE: /^BE0?\d{9}$/,                      // Belgium
    BG: /^BG\d{9,10}$/,                     // Bulgaria
    CY: /^CY\d{8}[A-Z]$/,                   // Cyprus
    CZ: /^CZ\d{8,10}$/,                     // Czechia
    DE: /^DE\d{9}$/,                        // Germany
    DK: /^DK\d{8}$/,                        // Denmark
    EE: /^EE\d{9}$/,                        // Estonia
    EL: /^EL\d{9}$/,                        // Greece
    GR: /^GR\d{9}$/,                        // Greece (ISO country)
    ES: /^ES[A-Z0-9]\d{7}[A-Z0-9]$/,        // Spain
    FI: /^FI\d{8}$/,                        // Finland
    FR: /^FR[A-Z0-9]{2}\d{9}$/,             // France
    HR: /^HR\d{11}$/,                       // Croatia
    HU: /^HU\d{8}$/,                        // Hungary
    IE: /^IE\d[A-Z0-9]\d{5}[A-Z]?$|^IE\d{7}[A-Z]{1,2}$/, // Ireland (2 formats)
    IT: /^IT\d{11}$/,                       // Italy
    LT: /^LT\d{9}$|^LT\d{12}$/,             // Lithuania
    LU: /^LU\d{8}$/,                        // Luxembourg
    LV: /^LV\d{11}$/,                       // Latvia
    MT: /^MT\d{8}$/,                        // Malta
    NL: /^NL[A-Z0-9]{12}$/,                 // Netherlands
    PL: /^PL\d{10}$/,                       // Poland
    PT: /^PT\d{9}$/,                        // Portugal
    RO: /^RO\d{2,10}$/,                     // Romania
    SE: /^SE\d{12}$/,                       // Sweden
    SI: /^SI\d{8}$/,                        // Slovenia
    SK: /^SK\d{10}$/,                       // Slovakia
    XI: /^XI(\d{3}\s?\d{4}\s?\d{2}|\d{3}\s?\d{4}\s?\d{2}\s?\d{3}|GD\d{3}|HA\d{3})$/, // Northern Ireland
    GB: /^GB\d{9}(\d{3})?$/,               // United Kingdom (ISO country)
}

let vatValidationState = {
    isValid: true,
    errorMessage: '',
    currentCountry: '',
    currentVatNumber: ''
};

$(document).ready(function() {
    initializeVatValidation();
});

function initializeVatValidation() {
    if ($('#inputTaxId').length === 0) {
        return;
    }

    $('#btnCompleteOrder').on('click', function(event) {
        const $button = $(this);
        const loadingClasses = 'disable-on-click spinner-on-click';

        if (!validateTaxField() || !validateDomainContactTaxField()) {
            event.preventDefault();
            $button.removeClass(loadingClasses);
        } else {
            $button.addClass(loadingClasses);
        }
    });
}

function validateTaxField() {
    if ($('#inputTaxId').length === 0) {
        return true;
    }
    const countryCode = $('#inputCountry').val();
    const vatNumber = $('#inputTaxId').val()?.trim() ?? '';

    return performVatValidation(countryCode, vatNumber, '#inputTaxId');
}

function validateDomainContactTaxField() {
    if ($('#inputDCTaxId').length === 0) {
        return true;
    }
    const countryCode = $('#inputDCCountry').val();
    const vatNumber = $('#inputDCTaxId').val()?.trim() ?? '';

    return performVatValidation(countryCode, vatNumber, '#inputDCTaxId');
}

function performVatValidation(countryCode, vatNumber, inputSelector) {
    clearVatError();

    if (!isValidVatFormat(countryCode, vatNumber, inputSelector)) {
        handleVatError(countryCode);

        return false;
    }

    return true;
}

function isValidVatFormat(countryCode, vatNumber, inputSelector) {
    if ($('#validation_tax_id').val() === undefined) {
        return true;
    }
    const countryVatRegex = countriesVatRegex[countryCode];

    // Skip validation for countries not in the list
    if (!countryVatRegex) {
        return true;
    }

    // Normalize VAT number (remove spaces, convert to uppercase)
    const normalizedVat = vatNumber.replace(/\s/g, '').toUpperCase();

    if (inputSelector && normalizedVat !== vatNumber) {
        $(inputSelector).val(normalizedVat);
    }

    return countryVatRegex.test(normalizedVat);
}

function handleVatError(countryCode) {
    const countryName = getCountryNameFromSelect(countryCode);
    const errorMessage = getVatErrorMessage(countryName);

    showVatError(errorMessage);

    vatValidationState.isValid = false;
    vatValidationState.errorMessage = errorMessage;
}

function getCountryNameFromSelect(countryCode) {
    let countrySelect = $('#inputCountry');
    let selectedOption = countrySelect.find('option[value="' + countryCode + '"]');

    if (selectedOption.length) {
        return selectedOption.text().trim();
    }

    countrySelect = $('#inputDCCountry');
    selectedOption = countrySelect.find('option[value="' + countryCode + '"]');

    if (selectedOption.length) {
        return selectedOption.text().trim();
    }

    return "";
}

function getVatErrorMessage(countryName) {
    return window.langVatErrorInvalidFormat?.replace(':countryName', countryName)
}

function showVatError(message) {
    let errorContainer = $('.checkout-error-feedback');
    let vatErrorElement = errorContainer.find('.vat-error');

    if (errorContainer.length && vatErrorElement.length) {
        vatErrorElement.html(message).removeClass('d-none');
        errorContainer.removeClass('d-none');

        $('html, body').animate({
            scrollTop: errorContainer.offset().top - 120
        }, 500);
    }
}

function clearVatError() {
    const errorContainer = $('.checkout-error-feedback');
    const vatErrorElement = errorContainer.find('.vat-error');

    if (vatErrorElement.length > 0) {
        vatErrorElement.html('').addClass('d-none');

        const hasOtherErrors = errorContainer.find('li:not(.vat-error):not(.d-none)').length > 0;

        if (!hasOtherErrors) {
            errorContainer.addClass('d-none');
        }
    }

    vatValidationState.isValid = true;
    vatValidationState.errorMessage = '';
}

function isTaxEUTaxExempt() {
    return $('#isTaxEUTaxExempt').val() === 'true';
}

function isTaxTypeInclusive() {
    return $('#taxType').val() === 'Inclusive';
}

function isTaxInclusiveDeduct() {
    return $('#isTaxInclusiveDeduct').val() === 'true';
}
