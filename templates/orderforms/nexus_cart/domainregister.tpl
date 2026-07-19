{include file="orderforms/standard_cart/common.tpl"}

<div id="order-standard_cart">
    <div class="row">
        <div class="cart-sidebar">
            {include file="orderforms/standard_cart/sidebar-categories.tpl"}
        </div>
        <div class="cart-body">
            <div id="nexus-root" data-app="domain-module" data-init="{getNexusData}"></div>
        </div>
    </div>
</div>

<script type="text/javascript" src="{assetPath file="main.min.js"}"></script>
