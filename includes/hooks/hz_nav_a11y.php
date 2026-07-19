<?php
/**
 * Navigation accessibility — additive, isolated JS. Does NOT touch the theme's
 * main.js. Adds:
 *   • aria-current="page" on the active nav link (WCAG — announce current page)
 *   • aria-expanded on the mobile drawer + submenu toggles (kept in sync)
 *   • ESC closes an open mobile drawer
 * Upgrade-safe (a hook). Fails silently if the markup differs.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    return <<<'HTML'
<script>
(function () {
  function run() {
    try {
      // Current page in the sidebar nav.
      document.querySelectorAll('.inner-navbar a.active').forEach(function (a) {
        a.setAttribute('aria-current', 'page');
      });

      // Mobile drawer toggles — expose and keep aria-expanded in sync.
      document.querySelectorAll('#mobcols, .mobmenu').forEach(function (t) {
        if (!t.hasAttribute('aria-expanded')) t.setAttribute('aria-expanded', 'false');
        t.addEventListener('click', function () {
          setTimeout(function () {
            var exp = t.getAttribute('aria-expanded') === 'true';
            t.setAttribute('aria-expanded', (!exp).toString());
          }, 60);
        });
      });

      // Accordion submenu toggles reflect the theme's .trigger state.
      document.querySelectorAll('.hasmenu > a[role="button"]').forEach(function (a) {
        var li = a.parentElement;
        a.setAttribute('aria-expanded', li.classList.contains('trigger') ? 'true' : 'false');
        a.addEventListener('click', function () {
          setTimeout(function () {
            a.setAttribute('aria-expanded', li.classList.contains('trigger') ? 'true' : 'false');
          }, 60);
        });
      });

      // ESC closes an open mobile drawer.
      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var open = document.querySelector('#mobcols[aria-expanded="true"], .mobmenu[aria-expanded="true"]');
        if (open) open.click();
      });
    } catch (err) { /* never break the page for a nav enhancement */ }
  }
  if (document.readyState !== 'loading') run();
  else document.addEventListener('DOMContentLoaded', run);
})();
</script>
HTML;
});
