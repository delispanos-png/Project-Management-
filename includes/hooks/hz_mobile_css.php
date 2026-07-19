<?php
/**
 * Central place for mobile/responsive CSS fixes for the custom "horn" theme.
 * Injected into every client-area page <head>. Keeping all mobile tweaks here
 * makes them maintainable and (being a hook) upgrade-safe.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    return <<<'HTML'
<style id="hz-mobile-fixes">
/* ---- Client-area panels: the blue link button overlaps the title on mobile.
       Hide that minor shortcut on small screens so titles read cleanly. ---- */
@media (max-width: 991px){
  .client-home-panels .panel-heading .badge.feat,
  .client-home-panels .panel-heading .panel-title .btn,
  .client-home-panels .panel-heading .panel-title > a.badge{ display: none !important; }
  .client-home-panels .panel-heading .panel-title{ padding: 0 15px !important; text-align: center; line-height: 1.35; word-break: break-word; }
}

/* ---- Active Products/Services list: show the full status label (was clipped
       to 3rem → "Ενε"/"Απε"). Keep the status column from shrinking. ---- */
@media (max-width: 991px){
  .div-service-status{ flex: 0 0 auto !important; }
  .div-service-status .label:not(.label-placeholder){
    width: auto !important; overflow: visible !important; text-overflow: clip !important;
    white-space: nowrap !important; padding: 4px 10px !important; font-size: 10px;
  }
  .div-service-status .label-placeholder{ display: none !important; }
}
</style>
HTML;
});
