/*
 * WHMCS Stripe Javascript
 *
 * @copyright Copyright (c) WHMCS Limited 2005-2019
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */
var stripeDynamicElementsDiv = null,
    modalInput = false,
    stripeFormLocked = false;

function resetStripeFormStateStripeDynamic(frm) {
    scrollToGatewayInputError();
    stripeFormLocked = false;
    frm.find('button[type="submit"],input[type="submit"]')
        .prop('disabled', false)
        .removeClass('disabled')
        .find('span').toggle();
}

function initStripeDynamic() {
    var paymentMethod = jQuery('input[name="paymentmethod"]'),
        frm = jQuery('#frmCheckout'),
        newCcForm = jQuery('.frm-credit-card-input'),
        paymentForm = jQuery('#frmPayment'),
        adminCreditCard = jQuery('#frmCreditCardDetails');
    if (paymentMethod.length && !newCcForm.length) {
        var newCcInputs = jQuery('#newCardInfo');

        stripeDynamicElementsDiv = jQuery('#stripeDynamicElements');
        if (!stripeDynamicElementsDiv.length) {
            let html = '<div id="stripeDynamicElements" class="form-group" style="display: none;">' +
                '<div id="payment-element"></div>' +
                '<input type="hidden" name="secret" value = "' + stripeDynamicClientSecret + '">' +
                '</div>';
            newCcInputs.after(html);
            stripeDynamicElementsDiv = jQuery('#stripeDynamicElements');
        }

        var newOrExisting = jQuery('input[name="ccinfo"]'),
            selectedCard = jQuery('input[name="ccinfo"]:checked'),
            selectedPaymentMethod = jQuery('input[name="paymentmethod"]:checked').val(),
            existingCvv = jQuery('#existingCardInfo');

        if (typeof selectedPaymentMethod == 'undefined') {
            selectedPaymentMethod = jQuery('input[name="paymentmethod"]').val();
        }

        if (selectedPaymentMethod === 'stripe_dynamic') {
            hideNewCardInputs();
            stripeDynamicPaymentElement.mount("#payment-element");
            enableStripeDynamic();

            if (selectedCard.val() !== 'new') {
                stripeDynamicGetExistingToken(selectedCard.val());
                stripeDynamicElementsDiv.slideUp();
                frm.off('submit.stripeDynamic');
                existingCvv.slideUp();
                if (amount !== '000') {
                    frm.on('submit.stripeDynamic', processExistingStripeDynamic);
                }
            }
        }

        paymentMethod.on('ifChecked', function(){
            selectedPaymentMethod = jQuery(this).val();
            if (selectedPaymentMethod === 'stripe_dynamic') {
                var newOrExistingValue = jQuery('input[name="ccinfo"]:checked').val();
                hideNewCardInputs();
                stripeDynamicPaymentElement.mount("#payment-element");
                enableStripeDynamic();
                if (newOrExistingValue !== 'new') {
                    stripeDynamicGetExistingToken(newOrExistingValue);
                    stripeDynamicElementsDiv.slideUp();
                    existingCvv.slideUp();
                    frm.off('submit.stripeDynamic');
                    if (amount !== '000') {
                        frm.on('submit.stripeDynamic', processExistingStripeDynamic);
                    }
                }
            } else {
                disableStripeDynamic();
            }
        });
        jQuery(document).on('ifChecked', 'input[name="ccinfo"]', function() {
            frm.off('submit.stripeDynamic');
            selectedPaymentMethod = jQuery('input[name="paymentmethod"]:checked').val();
            if (selectedPaymentMethod !== 'stripe_dynamic') {
                return;
            }
            hideNewCardInputs();
            if (jQuery(this).val() === 'new') {
                enableStripeDynamic();
            } else {
                stripeDynamicGetExistingToken(jQuery(this).val());
                stripeDynamicElementsDiv.slideUp();
                frm.off('submit.stripeDynamic');
                if (amount !== '000') {
                    frm.on('submit.stripeDynamic', processExistingStripeDynamic);
                }
            }
        });
    } else if (newCcForm.length) {
        if (jQuery('input[name="type"]:checked').data('gateway') === 'stripe_dynamic') {
            (() => {
                stripeDynamicElementsDiv = jQuery('#stripeDynamicElements');
                if (!stripeDynamicElementsDiv.length) {
                    const html = '<div id="stripeDynamicElements" class="form-group" style="display: none;">' +
                        '<div id="payment-element"></div>' +
                        '<input type="hidden" name="secret" value = "' + stripeDynamicClientSecret + '">' +
                        '</div>';
                    jQuery('.fieldgroup-auxfields').before(html);
                    stripeDynamicElementsDiv = jQuery('#stripeDynamicElements');
                    stripeDynamicPaymentElement.mount('#payment-element');
                    jQuery('#inputCardNumber, #inputCardExpiry').closest('div.cc-details').hide()
                    jQuery('#stripeDynamicElements').show();
                }
            })();

            newCcForm.on('submit.stripeDynamic', addNewCardClientSideStripeDynamic);
        }
        jQuery('input[name="type"]').on('ifChecked', function(){
            if (jQuery(this).data('gateway') === 'stripe_dynamic') {

                stripeDynamicElementsDiv = jQuery('#stripeDynamicElements');
                hideNewCardInputs();
                stripeDynamicElementsDiv.slideDown();

                newCcForm.off('submit.stripeDynamic');
                newCcForm.on('submit.stripeDynamic', addNewCardClientSideStripeDynamic);
            } else {
                disableStripeDynamic();
            }
        });
    } else if (paymentForm.length) {
        (() => {
            stripeDynamicElementsDiv = jQuery('#stripeDynamicElements');
            if (!stripeDynamicElementsDiv.length) {
                const html = '<div id="stripeDynamicElements" class="form-group" style="display: none;">' +
                    '<div id="payment-element"></div>' +
                    '<input type="hidden" name="secret" value = "' + stripeDynamicClientSecret + '">' +
                    '</div>';
                jQuery('#billingAddressChoice').before(html);
                stripeDynamicElementsDiv = jQuery('#stripeDynamicElements');
                stripeDynamicPaymentElement.mount('#payment-element');
                jQuery('#inputCardNumber, #inputCardExpiry').closest('div.cc-details').hide()
                jQuery('#stripeDynamicElements').show();
            }
        })();

        paymentForm.find('#inputCardCvv').closest('div.form-group').remove();
        paymentForm.off('submit', validateCreditCardInput);
        if (jQuery('input[name="ccinfo"]:checked').val() === 'new') {
            enablePaymentStripeDynamic();
        } else {
            stripeDynamicGetExistingToken(jQuery('input[name="ccinfo"]:checked').val());
            paymentForm.on('submit.stripeDynamic', processExistingStripeDynamic);
        }
        jQuery('input[name="ccinfo"]').on('ifChecked', function(){
            if (jQuery(this).val() === 'new') {
                enablePaymentStripeDynamic();
            } else {
                stripeDynamicGetExistingToken(jQuery(this).val());
                stripeDynamicElementsDiv.slideUp();
                paymentForm.off('submit.stripeDynamic');
                paymentForm.on('submit.stripeDynamic', processExistingStripeDynamic);
            }
        });
    }
}

function validateStripeDynamic(event) {
    if (
        typeof recaptchaValidationComplete !== 'undefined'
        && typeof recaptchaType !== 'undefined'
        && recaptchaType === 'invisible'
        && recaptchaValidationComplete === false
    ) {
        event.preventDefault();
        return;
    }
    let paymentMethod = jQuery('input[name="paymentmethod"]:checked'),
        frm = stripeDynamicElementsDiv.closest('form'),
        displayError = jQuery('.gateway-errors,.assisted-cc-input-feedback').first();

    if (paymentMethod.length && paymentMethod.val() !== 'stripe_dynamic') {
        return true;
    }
    event.preventDefault();
    // Disable the submit button to prevent repeated clicks:
    frm.find('button[type="submit"],input[type="submit"]')
        .prop('disabled', true)
        .addClass('disabled')
        .find('span').toggle();

    // TODO WHMCS-25532 Handle submit errors
    stripeDynamicElements.submit()
        .then(function(result) {
            WHMCS.http.jqClient.jsonPost({
                url: WHMCS.utils.getRouteUrl('/stripe_dynamic/payment/intent'),
                data: frm.serialize(),
                success: function (response) {
                    if (response.success) {
                        stripeDynamicResponseHandler(null);
                    } else if (response.two_factor) {
                        stripeDynamicResponseHandler(null);
                    } else if (response.validation_feedback) {
                        showCheckoutError(response.validation_feedback, displayError);
                        scrollToGatewayInputError();
                        WHMCS.form.reloadCaptcha();
                    } else if (response.requires_payment) {
                        stripeDynamic.confirmPayment(
                            {
                                elements: stripeDynamicElements,
                                clientSecret: response.token,
                                redirect: 'if_required',
                                ...confirmParams,
                            }
                        ).then(function (result) {
                            if (result.error) {
                                var error = result.error.message;
                                if (error) {
                                    showCheckoutError(error, displayError);
                                    scrollToGatewayInputError();
                                    WHMCS.form.reloadCaptcha();
                                }
                            } else {
                                stripeDynamicResponseHandler(null);
                            }
                        });
                    } else {
                        stripeDynamic.confirmPayment(
                            {
                                elements: stripeDynamicElements,
                                clientSecret: response.token,
                                redirect: 'if_required',
                                ...confirmParams,
                            }
                        ).then(function (result) {
                            if (result.error) {
                                showCheckoutError(result.error?.message, displayError);
                                scrollToGatewayInputError();
                                WHMCS.form.reloadCaptcha();
                            } else {
                                stripeDynamicResponseHandler(null);
                            }
                        });

                    }
                },
                warning: function (error) {
                    showCheckoutError(defaultErrorMessage, displayError);
                    scrollToGatewayInputError();
                    WHMCS.form.reloadCaptcha();
                },
                fail: function (error) {
                    showCheckoutError(defaultErrorMessage, displayError);
                    scrollToGatewayInputError();
                }
            })
        });

    // Prevent the form from being submitted:
    return false;
}

function processExistingStripeDynamic(event)
{
    if (
        typeof recaptchaValidationComplete !== 'undefined'
        && typeof recaptchaType !== 'undefined'
        && recaptchaType === 'invisible'
        && recaptchaValidationComplete === false
    ) {
        event.preventDefault();
        return;
    }
    var frm = stripeDynamicElementsDiv.closest('form'),
        displayError = jQuery('.gateway-errors,.assisted-cc-input-feedback').first();

    frm.find('.gateway-errors').html('').slideUp();
    event.preventDefault();

    // Disable the submit button to prevent repeated clicks:
    frm.find('button[type="submit"],input[type="submit"]')
        .prop('disabled', true)
        .addClass('disabled')
        .find('span').toggle();

    // TODO WHMCS-25532 Handle submit errors.
    stripeDynamicElements.submit()
        .then(function(result) {
        WHMCS.http.jqClient.jsonPost({
            url: WHMCS.utils.getRouteUrl('/stripe_dynamic/payment/intent'),
            data: frm.serialize() + '&payment_method_id=' + existingToken,
            success: function (response) {
                if (response.success) {
                    stripeDynamicResponseHandler(null);
                } else if (response.validation_feedback) {
                    // An error has been received.
                    displayError.html(response.validation_feedback);
                    if (displayError.not(':visible')) {
                        displayError.slideDown();
                    }
                    scrollToGatewayInputError();
                    WHMCS.form.reloadCaptcha();
                } else {
                    stripeDynamic.confirmPayment(
                        {
                            clientSecret: response.token,
                            redirect: 'if_required',
                            ...confirmParams,
                        }
                    ).then(function (result) {
                        if (result.error) {
                            var error = result.error.message;
                            if (error) {
                                displayError.html(error);
                                if (displayError.not(':visible')) {
                                    displayError.slideDown();
                                }
                                scrollToGatewayInputError();
                                WHMCS.form.reloadCaptcha();
                            }
                        } else {
                            stripeDynamicResponseHandler(null);
                        }
                    });
                }
            },
            warning: function (error) {
                WHMCS.form.reloadCaptcha();
                displayError.html(defaultErrorMessage);
                if (displayError.not(':visible')) {
                    displayError.slideDown();
                }
                scrollToGatewayInputError();
            },
            fail: function (error) {
                displayError.html(defaultErrorMessage);
                if (displayError.not(':visible')) {
                    displayError.slideDown();
                }
                scrollToGatewayInputError();
            }
        })
    });
}

function stripeDynamicResponseHandler(token) {
    var frm = stripeDynamicElementsDiv.closest('form');
    frm.find('.gateway-errors,.assisted-cc-input-feedback').html('').slideUp();
    if (token !== null) {
        frm.append(jQuery('<input type="hidden" name="remoteStorageToken">').val(token));
    }
    frm.find('button[type="submit"],input[type="submit"]')
        .find('i.fas,i.far,i.fal,i.fab')
        .removeAttr('class')
        .addClass('fas fa-spinner fa-spin');

    if (!modalInput) {
        stripeDynamicElementsDiv.slideUp();
    }

    // Submit the form:
    frm.off('submit.stripeDynamic');

    frm.append('<input type="submit" id="hiddenSubmit" name="submit" value="Save Changes" style="display:none;">');
    var hiddenButton = jQuery('#hiddenSubmit');
    if (modalInput) {

        var modalFooter = jQuery('#modalAjaxFooter'),
            hiddenButton = modalFooter.find('#btnSave');
        hiddenButton.removeClass('disabled');
        jQuery('#modalAjax .loader').fadeOut();
        hiddenButton.off('click', validateChangeCard);
        hiddenButton.on('click', submitIdAjaxModalClickEvent);
    }
    hiddenButton.click();
}

function hideNewCardInputs() {
    var frm = stripeDynamicElementsDiv.closest('form'),
        newCardInputs = jQuery('#newCardInfo,.cc-details');

    if (newCardInputs.is(':visible')) {
        newCardInputs.slideUp('fast');
    }
}

function enableStripeDynamic() {
    var frm = stripeDynamicElementsDiv.closest('form'),
        inputDescriptionContainer = jQuery('#inputDescriptionContainer');

    hideNewCardInputs();
    stripeDynamicElementsDiv.slideDown();
    frm.off('submit.stripeDynamic');
    if (amount === '000') {
        frm.on('submit.stripeDynamic', addNewCardClientSideStripeDynamic);
    } else {
        frm.on('submit.stripeDynamic', validateStripeDynamic);
    }
    inputDescriptionContainer.addClass('offset-md-3');
}

function disableStripeDynamic() {
    var frm = stripeDynamicElementsDiv.closest('form'),
        cardInputs = jQuery('#newCardInfo,.cc-details'),
        showLocal = true,
        inputDescriptionContainer = jQuery('#inputDescriptionContainer');

    if (jQuery('input[name="paymentmethod"]:checked').data('remote-inputs') === 1) {
        showLocal = false;
    }

    stripeDynamicElementsDiv.hide('fast', function() {
        var firstVisible = jQuery('input[name="ccinfo"]:visible').first();
        if (firstVisible.val() === 'new') {
            if (showLocal) {
                cardInputs.slideDown();
            }
        } else {
            firstVisible.click();
        }
    });

    frm.off('submit.stripeDynamic');
    inputDescriptionContainer.removeClass('offset-md-3');
}

function enablePaymentStripeDynamic() {
    var paymentForm = stripeDynamicElementsDiv.closest('form');

    paymentForm.find('#inputCardNumber').closest('div.form-group').remove();
    paymentForm.find('#inputCardExpiry').closest('div.form-group').remove();
    stripeDynamicElementsDiv.slideDown();
    paymentForm.off('submit.stripeDynamic');
    paymentForm.on('submit.stripeDynamic', validateStripeDynamic);
}

function addNewCardClientSideStripeDynamic(event)
{
    if (recaptchaType === 'invisible' && !recaptchaValidationComplete) {
        event.preventDefault();
        return;
    }
    event.preventDefault();
    if (stripeFormLocked) {
        return;
    }
    stripeFormLocked = true;

    var frm = stripeDynamicElementsDiv.closest('form'),
        displayError = jQuery('.gateway-errors,.assisted-cc-input-feedback').first();
    event.preventDefault();
    // Disable the submit button to prevent repeated clicks:
    frm.find('button[type="submit"],input[type="submit"]')
        .prop('disabled', true)
        .addClass('disabled')
        .find('span').toggle();

    // TODO WHMCS-25532 Handle submit errors.
    stripeDynamicElements.submit()
        .then(function(result) {
            // We need to submit first to our endpoint to start a SetupIntent
            WHMCS.http.jqClient.jsonPost({
                url: WHMCS.utils.getRouteUrl('/stripe_dynamic/setup/intent'),
                data: frm.serialize(),
                success: function (response) {
                    if (response.validation_feedback) {
                        displayError.text(response.validation_feedback);
                        if (displayError.not(':visible')) {
                            displayError.slideDown();
                        }
                        resetStripeFormStateStripeDynamic(frm);
                    }
                    if (response.success) {
                        stripeDynamic.confirmSetup(
                            {
                                elements: stripeDynamicElements,
                                clientSecret: response.setup_intent,
                                redirect: 'if_required',
                                ...confirmParams,
                            }
                        ).then(function (result) {
                            if (result.error) {
                                displayError.html(result.error.message);
                                if (displayError.not(':visible')) {
                                    displayError.slideDown();
                                }
                                WHMCS.form.reloadCaptcha();
                                resetStripeFormStateStripeDynamic(frm);
                            } else {
                                stripeDynamicResponseHandler(null);
                            }
                        });
                    }
                },
                warning: function (error) {
                    displayError.html(error);
                    if (displayError.not(':visible')) {
                        displayError.slideDown();
                    }
                    resetStripeFormStateStripeDynamic(frm);
                },
                fail: function (error) {
                    displayError.html(error);
                    if (displayError.not(':visible')) {
                        displayError.slideDown();
                    }
                    resetStripeFormStateStripeDynamic(frm);
                }
            })
        });
}

function validateChangeCard(event)
{
    var frm = stripeDynamicElementsDiv.closest('form'),
        displayError = jQuery('.gateway-errors,.assisted-cc-input-feedback').first();
    event.preventDefault();
    // Disable the submit button to prevent repeated clicks:
    frm.find('button[type="submit"],input[type="submit"]')
        .prop('disabled', true)
        .addClass('disabled')
        .find('span').toggle();

    stripeDynamic.createPaymentMethod(
        'card',
        card
    ).then(function(result) {
        if (result.error) {
            var error = result.error.message;
            if (error) {
                displayError.html(error);
                if (displayError.not(':visible')) {
                    displayError.slideDown();
                }
                scrollToGatewayInputError();
            }
        } else {
            if (modalInput) {
                var btnSubmit = jQuery('#btnSave');
                btnSubmit.addClass('disabled');
                jQuery('#modalAjax .loader').slideDown();
            }
            if (typeof WHMCS.utils !== 'undefined') {
                var url = WHMCS.utils.getRouteUrl('/stripe_dynamic/payment/add');
            } else {
                var url = WHMCS.adminUtils.getAdminRouteUrl('/stripe_dynamic/payment/admin/add');
            }
            WHMCS.http.jqClient.jsonPost({
                url: url,
                data: frm.serialize()
                    + '&payment_method_id=' + result.paymentMethod.id,
                success: function(response) {
                    if (response.success) {
                        stripeDynamicResponseHandler(response.token);
                    }
                    if (response.validation_feedback) {
                        displayError.text(response.validation_feedback);
                        if (displayError.not(':visible')) {
                            displayError.slideDown();
                        }
                    }
                },
                warning: function(error) {
                    displayError.html(error);
                    if (displayError.not(':visible')) {
                        displayError.slideDown();
                    }
                    scrollToGatewayInputError();
                },
                fail: function(error) {
                    displayError.html(error);
                    if (displayError.not(':visible')) {
                        displayError.slideDown();
                    }
                    scrollToGatewayInputError();
                },
                always: function() {
                    if (modalInput) {
                        btnSubmit.removeClass('disabled');
                        jQuery('#modalAjax .loader').fadeOut();
                    }
                }
            });
        }
    });
    // Prevent the form from being submitted:
    return false;
}

function stripeDynamicGetExistingToken(tokenId)
{
    if (typeof tokenId === 'undefined') {
        var input = jQuery('input[name="ccinfo"]:visible:first');
        input.iCheck('check');
        tokenId = input.val();
        if (tokenId === 'new') {
            return;
        }
    }
    var displayError = jQuery('.gateway-errors,.assisted-cc-input-feedback').first(),
        frm = displayError.closest('form');

    frm.find('button[type="submit"],input[type="submit"]')
        .prop('disabled', true)
        .addClass('disabled')
        .find('span').toggle();
    WHMCS.http.jqClient.jsonPost({
        url: WHMCS.utils.getRouteUrl('/payment/stripe_dynamic/token/get'),
        data: 'paymethod_id=' + tokenId + '&token=' + csrfToken,
        success: function(response) {
            existingToken = response.token;
            frm.find('button[type="submit"],input[type="submit"]')
                .prop('disabled', false)
                .removeClass('disabled')
                .find('span').toggle();
        },
        warning: function(error) {
            displayError.html(error);
            if (displayError.not(':visible')) {
                displayError.slideDown();
            }
            scrollToGatewayInputError();
            resetInputToNewStripeDynamic();
        },
        fail: function(error) {
            displayError.html(error);
            if (displayError.not(':visible')) {
                displayError.slideDown();
            }
            scrollToGatewayInputError();
            resetInputToNewStripeDynamic();
        }
    });
}

function resetInputToNewStripeDynamic()
{
    jQuery('input[name="ccinfo"][value="new"]').iCheck('check');
    if (jQuery('#existingCardInfo').is(':visible')) {
        jQuery('#existingCardInfo').slideUp();
    }

    setTimeout(function() {
        jQuery('.gateway-errors,.assisted-cc-input-feedback').slideUp();
    }, 4000);
}
