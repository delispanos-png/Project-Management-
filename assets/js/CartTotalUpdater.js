const cartTotalForm = jQuery('#frmCheckout');
const cartInputCountry = jQuery('#inputCountry');

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

function updateCartTotal(country, state) {
    if (!cartTotalForm.length || cartTotalForm.data('submitting')) {
        return;
    }

    const accountId = jQuery('.account-select:checked').val();

    if (accountId && accountId !== 'new') {
        return;
    }

    const params = new URLSearchParams({
        token: csrfToken,
        state: state,
        ajax: 1,
    });
    const vatNumber = jQuery('#inputTaxId').val()?.trim() ?? '';
    if (shouldSetCountryParam(country, vatNumber)) {
        params.set('country', country);
    }

    const baseUrl = new URL(location.href);
    const setCountryUrl = new URL(baseUrl);

    setCountryUrl.searchParams.set('a', 'setstateandcountry');

    fetch(setCountryUrl.toString(), {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params,
    })
        .then(() => {
            const getTotalUrl = new URL(baseUrl);
            getTotalUrl.searchParams.set('a', 'getCartTotal');
            getTotalUrl.searchParams.set('token', csrfToken);

            return fetch(getTotalUrl.toString());
        })
        .then(response => {
            if (!response.ok) {
                return null;
            }
            return response.json();
        })
        .then(data => {
            if (data && data.total !== undefined) {
                document.getElementById('totalCartPrice').textContent = data.total;
            }
        });
}

cartTotalForm.on('change changed.bs.select', '[name="state"]', function () {
    updateCartTotal(cartInputCountry.val(), this.value);
});

cartInputCountry.on('state:rendered', function () {
    updateCartTotal(cartInputCountry.val(), cartTotalForm.find('[name="state"]').val());
});

jQuery('#inputCompanyName, #inputTaxId').on('input', function() {
    updateCartTotal(cartInputCountry.val(), cartTotalForm.find('[name="state"]').val());
});

cartTotalForm.on('submit', function() {
    cartTotalForm.data('submitting', true);
});
