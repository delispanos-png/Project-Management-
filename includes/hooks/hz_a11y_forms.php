<?php
/**
 * Adds accessible names (aria-label) to form controls that are rendered by
 * WHMCS core / JS libraries and therefore cannot be labelled from the custom
 * theme templates — the checkout country/state selects, DataTables search
 * boxes, and any empty home link. Progressive enhancement only: it sets
 * aria-label on elements that lack an accessible name, never changing layout
 * or behaviour. Bilingual via the <html lang> we already set (el/en).
 *
 * Upgrade-safe: a hook, no core edits. Fixes axe rules label / select-name /
 * link-name on core-rendered elements.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    return <<<'HTML'
<script>
(function () {
  var isEl = (document.documentElement.getAttribute('lang') || '').toLowerCase().indexOf('el') === 0;
  var T = {
    country: isEl ? 'Χώρα' : 'Country',
    state:   isEl ? 'Νομός / Περιοχή' : 'State / Region',
    search:  isEl ? 'Αναζήτηση' : 'Search',
    home:    isEl ? 'Αρχική' : 'Home'
  };
  function setName(el, text) {
    if (!el || el.getAttribute('aria-label')) return;
    if (el.id && document.querySelector('label[for="' + el.id + '"]')) return; // already labelled
    el.setAttribute('aria-label', text);
  }
  function run() {
    document.querySelectorAll('#inputCountry, select[name="country"]').forEach(function (e) { setName(e, T.country); });
    document.querySelectorAll('#stateinput, #state, input[name="state"]').forEach(function (e) { setName(e, T.state); });
    document.querySelectorAll('input[type="search"], .main-search input, .dataTables_filter input').forEach(function (e) { setName(e, T.search); });
    document.querySelectorAll('a[href="/"]').forEach(function (e) {
      if (!e.textContent.trim()) setName(e, T.home);
    });
    // Icon-only links that only carry a Bootstrap tooltip (data-original-title
    // / title) get that text as their accessible name.
    document.querySelectorAll('a[data-original-title], a[title]').forEach(function (a) {
      if (a.textContent.trim() || a.getAttribute('aria-label')) return;
      var t = (a.getAttribute('data-original-title') || a.getAttribute('title') || '').trim();
      if (t) a.setAttribute('aria-label', t);
    });
    // SSL status indicators (services table) are AJAX-rendered <img> without alt.
    document.querySelectorAll('img[class*="ssl"]:not([alt])').forEach(function (img) {
      img.setAttribute('alt', 'SSL');
    });
  }
  // Run now + a few delayed passes to catch AJAX-rendered content (DataTables/SSL).
  function boot() { run(); setTimeout(run, 1200); setTimeout(run, 2800); }
  if (document.readyState !== 'loading') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
</script>
HTML;
});
