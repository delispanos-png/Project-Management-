/**
 * General utilities module
 *
 * @copyright Copyright (c) WHMCS Limited 2005-2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */
(function(module) {
    if (!WHMCS.hasModule('adminUtils')) {
        WHMCS.loadModule('adminUtils', module);
    }
})(
function () {
    this.getAdminRouteUrl = function (path) {
        return whmcsBaseUrl + "/index.php?rp=" + adminBaseRoutePath + path;
    };

    this.normaliseStringValue = function(status) {
        return status ? status.toLowerCase().replace(/\s/g, '-') : '';
    }

    /**
     * The length bounds the password generator's length field carries.
     *
     * Read off the field rather than held here so that the floor is whatever minimum password
     * length the server rendered into the markup. A pair of constants in this file would be a
     * second, independent copy of the policy's numbers and would go stale the moment an
     * administrator raised the configured minimum, which is exactly how this check came to reject
     * only what an obsolete floor rejected.
     *
     * A bound the field does not carry at all, or carries with no leading digits, comes back null;
     * a caller treats that as unbounded rather than substituting a number of its own, because a
     * guessed bound is what this reads the markup to avoid.
     *
     * That is parseInt's numeric-prefix parsing and not validation: "16.5" and "16junk" both read
     * as 16, and only a value with nothing numeric at the front - "", "abc", an absent attribute -
     * is rejected. Nothing checks these attributes for well-formedness, so the guarantee is the
     * server's: it writes an integer taken from the password policy, which is why parsing loosely
     * is enough here rather than something to be tightened.
     *
     * @param field The length input, as a selector or element jQuery accepts
     *
     * @return {{minimum: ?number, maximum: ?number}}
     */
    this.getPasswordLengthBounds = function(field) {
        var read = function(attribute) {
            var bound = parseInt(jQuery(field).attr(attribute), 10);

            return isNaN(bound) ? null : bound;
        };

        return {minimum: read('min'), maximum: read('max')};
    };

    this.generatePassword = function(len) {
        var self = this;
        var charset = this.getPasswordCharacterSet();
        // Drawing every character independently from a mixed alphabet does not guarantee a
        // digit or a letter, and the password policy requires both. Seed the result with one
        // of each (when there is room for them), fill the rest from the full charset, then
        // shuffle so the guaranteed characters aren't always in the same position.
        var digits = charset.filter(function(c) { return c >= '0' && c <= '9'; });
        var letters = charset.filter(function(c) { return /[a-zA-Z]/.test(c); });
        var result = len >= 2
            ? [digits[this.randomInt(digits.length)], letters[this.randomInt(letters.length)]]
            : [];
        for (var i = result.length; i < len; i++)
            result.push(charset[this.randomInt(charset.length)]);
        for (var j = result.length - 1; j > 0; j--) {
            var k = self.randomInt(j + 1);
            var tmp = result[j];
            result[j] = result[k];
            result[k] = tmp;
        }
        return result.join("");
    };
    this.getPasswordCharacterSet = function() {
        var rawCharset = '0123456789'
            + 'abcdefghijklmnopqrstuvwxyz'
            + 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
            + '!#$%()*+,-.:;=@_|{ldelim}{rdelim}~';

        // Parse UTF-16, remove duplicates, convert to array of strings
        var charset = [];
        for (var i = 0; rawCharset.length > i; i++) {
            var c = rawCharset.charCodeAt(i);
            if (0xD800 > c || c >= 0xE000) {  // Regular UTF-16 character
                var s = rawCharset.charAt(i);
                if (charset.indexOf(s) == -1)
                    charset.push(s);
                continue;
            }
            if (0xDC00 > c ? rawCharset.length > i + 1 : false) {  // High surrogate
                var d = rawCharset.charCodeAt(i + 1);
                if (d >= 0xDC00 ? 0xE000 > d : false) {  // Low surrogate
                    var s = rawCharset.substring(i, i + 2);
                    i++;
                    if (charset.indexOf(s) == -1)
                        charset.push(s);
                    continue;
                }
            }
            throw new Error("Invalid UTF-16");
        }
        return charset;
    };
    this.randomInt = function(n) {
        var x = this.randomIntMathRandom(n);
        x = (x + this.randomIntBrowserCrypto(n)) % n;
        return x;
    };
    this.randomIntMathRandom = function(n) {
        var x = Math.floor(Math.random() * n);
        if (0 > x || x >= n)
            throw new Error("Arithmetic exception");
        return x;
    };
    this.randomIntBrowserCrypto = function(n) {
        var cryptoObject = null;

        if ("crypto" in window)
            cryptoObject = crypto;
        else if ("msCrypto" in window)
            cryptoObject = msCrypto;
        else
            return 0;

        if (!("getRandomValues" in cryptoObject) || !("Uint32Array" in window) || typeof Uint32Array != "function")
            cryptoObject = null;

        if (cryptoObject == null)
            return 0;

        // Generate an unbiased sample
        var x = new Uint32Array(1);
        do cryptoObject.getRandomValues(x);
        while (x[0] - x[0] % n > 4294967296 - n);
        return x[0] % n;
    };

    this.ensureElementHasId = function (element, prefix) {
        element = jQuery(element);
        if (typeof element.attr('id') === "undefined") {
            element.attr('id', this.generateUniqueIdentifier(prefix));
        }
        return this;
    };

    this.generateUniqueIdentifier = function(prefix)
    {
        let id = Math
            .random()
            .toString(36)
            .replace('0.',prefix || 'x_');
        if (this.generatedIds.includes(id)) {
            return this.generateUniqueIdentifier(prefix);
        }
        this.generatedIds.push(id);
        return id;
    };

    this.generatedIds = [];

    this.clearLocalStorage = function (element) {
        if (typeof (Storage) === "undefined") {
            return;
        }
        if (element.data('noClear') === true) {
            return;
        }
        localStorage.removeItem(element.data('localStorageId'));
    };

    return this;
});
