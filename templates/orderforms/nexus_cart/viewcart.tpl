{if $checkout}
    {include file="orderforms/$carttpl/checkout.tpl"}
{else}
    {include file="orderforms/standard_cart/common.tpl"}

    <div id="order-standard_cart">
        <div id="nexus-root" data-app="cart-module"  data-init="{getNexusData}"></div>
    </div>

    <script type="text/javascript" src="{assetPath file="main.min.js"}"></script>

    <style>
        /* Redefining template styles only for this page */

        .main-navbar-wrapper, [data-target="#mainNavbar"] {
            display: none;
        }

        section#main-body {
            padding: 0 !important;
            background: #fff !important;
        }

        section#main-body .container {
            xmax-width: 100% !important;
        }

        section#main-body .primary-content p {
            display: none !important;
        }

        #order-standard_cart {
            padding: 0 !important;
        }
    </style>
{/if}
