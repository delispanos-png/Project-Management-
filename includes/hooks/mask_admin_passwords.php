<?php
/**
 * Mask plaintext password fields across the WHMCS admin area.
 *
 * WHMCS shows service/account passwords as plain text on order and service
 * pages. This hook converts any password-like text input to a masked field
 * (dots) and adds a small eye toggle so staff can reveal it deliberately.
 * It only changes display — values and form submission are untouched.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('AdminAreaFooterOutput', 1, function ($vars) {
    return <<<'HTML'
<script>
(function () {
    function isPwName(n) {
        n = (n || '').toLowerCase();
        return n.indexOf('password') !== -1
            || n.indexOf('accesshash') !== -1
            || n.indexOf('passwd') !== -1;
    }
    function maskFields() {
        var inputs = document.querySelectorAll('input[type="text"], input:not([type])');
        for (var i = 0; i < inputs.length; i++) {
            var inp = inputs[i];
            if (inp.dataset.hzMasked) continue;
            if (!isPwName(inp.getAttribute('name'))) continue;
            inp.dataset.hzMasked = '1';
            inp.type = 'password';
            inp.setAttribute('autocomplete', 'off');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = '\u{1F441}';
            btn.title = 'Show / hide';
            btn.style.cssText = 'margin-left:4px;padding:1px 5px;cursor:pointer;line-height:1;vertical-align:middle;';
            // Bind the field to its own button (avoids the var-in-loop closure bug).
            btn.hzField = inp;
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var f = this.hzField;
                f.type = (f.type === 'password') ? 'text' : 'password';
            });
            if (inp.parentNode) {
                inp.parentNode.insertBefore(btn, inp.nextSibling);
            }
        }
    }
    maskFields();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', maskFields);
    }
    // Catch fields added after AJAX table/tab loads.
    setTimeout(maskFields, 600);
    setTimeout(maskFields, 1500);
    if (window.MutationObserver) {
        var mo = new MutationObserver(function () { maskFields(); });
        mo.observe(document.body || document.documentElement, { childList: true, subtree: true });
    }
})();
</script>
HTML;
});
