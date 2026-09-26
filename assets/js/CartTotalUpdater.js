const cartTotalForm = jQuery('#frmCheckout');
const cartInputCountry = jQuery('#inputCountry');
const cartTotalInputDebounce = 300;

let cartTotalUpdateQueued = false;
let cartTotalRequestInFlight = false;
let cartTotalInputTimer = null;

function shouldSetCountryParam(country, vatNumber) {
    if (!(country in countriesVatRegex) || !isTaxEUTaxExempt()) {
        return true;
    }

    const hasValidVatFormat = isValidVatFormat(country, vatNumber, '#inputTaxId');

    if (isTaxTypeInclusive()) {
        return hasValidVatFormat || isTaxInclusiveDeduct();
    }

    return !hasValidVatFormat;
}

/**
 * Queues a cart total update.
 *
 * Only one update is ever in flight. A call made while one is running queues a
 * successor instead of starting a second, so the last request to be asked for
 * always wins on both the cart session and the displayed total.
 */
function updateCartTotal() {
    cartTotalUpdateQueued = true;

    if (cartTotalRequestInFlight) {
        return;
    }

    sendCartTotalRequest();
}

async function sendCartTotalRequest() {
    if (!cartTotalUpdateQueued) {
        return;
    }

    cartTotalUpdateQueued = false;

    try {
        if (!cartTotalForm.length || cartTotalForm.data('submitting')) {
            return;
        }

        const accountId = jQuery('.account-select:checked').val();

        if (accountId && accountId !== 'new') {
            return;
        }

        const country = cartInputCountry.val();
        const params = new URLSearchParams({
            token: csrfToken,
            state: cartTotalForm.find('[name="state"]').val(),
            ajax: 1,
        });
        const vatNumber = jQuery('#inputTaxId').val()?.trim() ?? '';
        if (shouldSetCountryParam(country, vatNumber)) {
            params.set('country', country);
        }

        const setCountryUrl = new URL(location.href);
        const getTotalUrl = new URL(location.href);

        setCountryUrl.searchParams.set('a', 'setstateandcountry');
        getTotalUrl.searchParams.set('a', 'getCartTotal');
        getTotalUrl.searchParams.set('token', csrfToken);

        cartTotalRequestInFlight = true;

        const setCountryResponse = await fetch(setCountryUrl.toString(), {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params,
        });

        // A redirect means the token was rejected and the session is gone, so
        // the state and country did not stick and any total would be stale.
        if (!setCountryResponse.ok || setCountryResponse.redirected) {
            return;
        }

        const totalResponse = await fetch(getTotalUrl.toString());

        if (!totalResponse.ok || totalResponse.redirected) {
            return;
        }

        const data = await totalResponse.json();

        if (data && data.total !== undefined) {
            document.getElementById('totalCartPrice').textContent = data.total;
        }
    } catch (error) {
        console.error('[WHMCS] Unable to update the cart total:', error);
    } finally {
        cartTotalRequestInFlight = false;
        sendCartTotalRequest();
    }
}

cartTotalForm.on('change changed.bs.select', '[name="state"]', updateCartTotal);

cartInputCountry.on('state:rendered', updateCartTotal);

jQuery('#inputCompanyName, #inputTaxId').on('input', function() {
    clearTimeout(cartTotalInputTimer);
    cartTotalInputTimer = setTimeout(updateCartTotal, cartTotalInputDebounce);
});

cartTotalForm.on('submit', function() {
    cartTotalForm.data('submitting', true);
    clearTimeout(cartTotalInputTimer);
    cartTotalUpdateQueued = false;
});
