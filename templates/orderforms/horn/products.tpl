{include file="orderforms/horn/common.tpl"}
<div id="order-standard_cart">
    <div class="row">
        <div class="col-md-12">
            <div class="header-lined">
                <h1>
                {if $productGroup.name}
                {$productGroup.name}
                {else}
                {$productGroup.name}
                {/if}
                </h1>
                <div class="product-tagline">
                 {if $productGroup.headline}
                 <p>{$productGroup.headline}</p>
                 {/if}
                </div>
                <div class="dropnav-header-lined">
                    <button id="dropside-content" type="button" class="drop-down-btn dropside-content" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <div class="menu-help">
                            <span class="menu-help">{$LANG.helpmenu}</span>
                            <span class="ico-menu"></span>
                        </div>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropside-content">
                        {*{include file="orderforms/horn/sidebar-categories.tpl"}*}
                        <div class="panel-heading">
                        <h3 class="panel-title">Servers</h3>
                        </div>
                        <a class="list-group-item" href="{$WEB_ROOT}/store/vmware-virtual-servers">Virtual Servers GR</a>
                        <a class="list-group-item" href="{$WEB_ROOT}/store/kvm-virtual-servers">Virtual Servers DE</a>
                        <a class="list-group-item" href="{$WEB_ROOT}/store/virtual-servers-location-in-germany">Virtual Servers DE <br>Container Resources</a>
                        <a class="list-group-item" href="{$WEB_ROOT}/store/cloudon-storage">Cloud On Storage</a>
                        <a class="list-group-item" href="{$WEB_ROOT}/store/web-hosting">Web Hosting</a>
                        <a class="list-group-item" href="{$WEB_ROOT}/store/collocation">Collocation</a>
                        <div class="panel-heading">
                        <h3 class="panel-title">Extra Services</h3>
                        </div>
                        <a class="list-group-item" href="{$WEB_ROOT}/store/voip-services">Voip Services</a>
                        <a class="list-group-item" href="{$WEB_ROOT}/store/3cx-ip-pbx-standard-edition">3CX IP PBX</a>
                    </div>
                </div>
                
            <div class="content-product-block">
             {if $productGroup.tagline}
                <p>{$productGroup.tagline}</p>
             {/if}
            </div>
            </div>
            {if $errormessage}
            <div class="alert alert-danger">
                {$errormessage}
            </div>
            {/if}
        </div>
        <div class="col-md-12">
            {include file="orderforms/horn/sidebar-categories-collapsed.tpl"}
            <div class="product" id="products">
                {foreach $products as $key => $product}
                
                <div class="col-md-4 plan-content {if $product.isFeatured}feature-plan{/if}">
                    <div class="clearfix" id="product{$product@iteration}">
                        {if $product.isFeatured}
                        <div class="badge feat tt-lower bg-puretheme">{$LANG.featuredProduct|upper}</div>
                        {/if}
                        <div class="header-content">
                            <header>
                                <span class="product-name" id="product{$product@iteration}-name">{$product.name}</span>
                                {if $product.stockControlEnabled}
                                <span class="qty">
                                    {$product.qty} {$LANG.orderavailable}
                                </span>
                                {/if}
                            </header>
                            <div class="product-pricing" id="product{$product@iteration}-price">
                                {if $product.bid}
                                {$LANG.bundledeal}<br />
                                {if $product.displayprice}
                                <span class="price">{$product.displayprice}</span>
                                {/if}
                                {else}
                                {if $product.pricing.hasconfigoptions}
                                {$LANG.startingfrom}
                                {/if}
                                <span class="price">{$product.pricing.minprice.price}</span>
                                <span class="period">{if $product.pricing.minprice.cycle eq "monthly"}
                                    {$LANG.orderpaymenttermmonthly}
                                    {elseif $product.pricing.minprice.cycle eq "quarterly"}
                                    {$LANG.orderpaymenttermquarterly}
                                    {elseif $product.pricing.minprice.cycle eq "semiannually"}
                                    {$LANG.orderpaymenttermsemiannually}
                                    {elseif $product.pricing.minprice.cycle eq "annually"}
                                    {$LANG.orderpaymenttermannually}
                                    {elseif $product.pricing.minprice.cycle eq "biennially"}
                                    {$LANG.orderpaymenttermbiennially}
                                    {elseif $product.pricing.minprice.cycle eq "triennially"}
                                    {$LANG.orderpaymenttermtriennially}
                                {/if}</span>
                                <br>
                                {/if}
                            </div>
                        </div>
                        <div class="product-desc">
                            {if $product.featuresdesc}
                            <div class="prod-desc-div" id="product{$product@iteration}-description">
                                {$product.featuresdesc}
                            </div>
                            {/if}
                            <ul class="prod-desc-ul">
                                {foreach $product.features as $feature => $value}
                                <li id="product{$product@iteration}-feature{$value@iteration}">
                                    <span class="feature-value">{$value}</span>
                                    {$feature}
                                </li>
                                {/foreach}
                            </ul>
                            {if $product.stockControlEnabled && $product.qty <= 0}
                            <span class="btn btn-default btn-sm disabled" id="product{$product@iteration}-order-button"
                                  style="cursor:not-allowed;opacity:.55" title="Currently unavailable">
                                Not Available
                            </span>
                            {else}
                            <a href="{$WEB_ROOT}/cart.php?a=add&{if $product.bid}bid={$product.bid}{else}pid={$product.pid}{/if}" class="btn btn-prussian btn-sm" id="product{$product@iteration}-order-button">
                                {$LANG.ordernowbutton}
                            </a>
                            {/if}
                            {if $product.pricing.minprice.setupFee}
                            <small class="setupfee">{$product.pricing.minprice.setupFee->toPrefixed()} {$LANG.ordersetupfee}</small>
                            {else}
                            <small class="setupfee">{$LANG.orderpromofreesetup}</small>
                            {/if}
                        </div>
                    </div>
                </div>
                {if $product@iteration % 3 == 0}
                <div class="row-eq-height">
                    {/if}
                    {/foreach}
                </div>
            </div>
        </div>
    </div>
</div>
{*{if $productGroup.id == 25 || $productGroup.id == 5 || $productGroup.id == 26}
<div class="content-block-features" id="features">
            <div class="container-features">
                <h2>{$LANG.whycloudonTitle}</h2>
                <h3>{$LANG.whycloudonSubtitle}</h3>
                <br>
                <div class="row">
                    <div class="col-md-6">
                        <div class="feature-wrapper">
                            <i class="fa fa-cloud-upload"></i>
                            <div class="content">
                                <h4>{$LANG.highavailability}</h4>
                                <p>{$LANG.highavailabilitySubtitle}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="feature-wrapper">
                            <i class="fa fa-globe"></i>
                            <div class="content">
                                <h4>{$LANG.location}</h4>
                                <p>{$LANG.locationSubtitle}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="feature-wrapper">
                            <i class="fa fa-server"></i>
                            <div class="content">
                                <h4>{$LANG.managed}</h4>
                                <p>{$LANG.managedSubtitle}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="feature-wrapper">
                            <i class="fa fa-cogs"></i>
                            <div class="content">
                                <h4>{$LANG.powerful}</h4>
                                <p>{$LANG.powerfulSubtitle}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="feature-wrapper">
                            <i class="fa fa-certificate"></i>
                            <div class="content">
                                <h4>{$LANG.network}</h4>
                                <p>{$LANG.networkSubtitle}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="feature-wrapper">
                            <i class="fa fa-life-ring"></i>
                            <div class="content">
                                <h4>{$LANG.support}</h4>
                                <p>{$LANG.supportSubtitle}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</div>
{else}
<div class="none">
</div>
{/if} *}
<script type="text/javascript" src="{$WEB_ROOT}/templates/orderforms/horn/js/main.js?v={$versionHash}"></script>
{include file="orderforms/horn/recommendations-modal.tpl"}