<!-- Plans Section -->
<div class="container-fluid main-fluid bottom-sec">
    <div class="row">
        <div class="col-md-12">
            <div class="main-title center-text">
                <h2>{$LANG.plantitle}</h2>
                <p>{$LANG.plantitleText}</p>
            </div>
        </div>
    </div>
    <div class="row">
    {if $hzCategories}
        {* Dynamic: one card per sellable category (auto price + icon). *}
        {foreach $hzCategories as $cat}
        <div class="col-md-4 col-sm-6">
            <div class="wrapper-plan" data-aos="fade-up" data-aos-duration="800">
                <div class="top-content">
                    <img class="svg mb-10 wh-50" src="templates/{$template}/assets/fonts/svg/{$cat.svg}">
                    <div class="plan-title">{$cat.label} <span>{$LANG.pricestarts}</span></div>
                    {if $cat.from}<div class="plan-price">{$cat.from|string_format:"%.2f"} € <span>/{if $cat.cycle == 'year'}{$LANG.hzPerYear}{else}{$LANG.hzPerMonth}{/if}</span></div>{else}<div class="plan-price" style="font-size:20px">{$LANG.hzContactForPrice}</div>{/if}
                </div>
                <ul class="specs-content {cycle values="bg-lighttheme,bg-tuscanytheme"}">
                    <li><i class="fas fa-bolt"></i> {$LANG.hzInstantSetup}</li>
                    <li><i class="fas fa-headset"></i> {$LANG.hzSupport247}</li>
                    <li><i class="fas fa-arrow-up"></i> {$LANG.hzFlexUpgrade}</li>
                    <li class="specs-cta"><a class="btn btn-prussian mt-15" href="{$WEB_ROOT}/{$cat.url}"><span>{$LANG.seemore}</span></a></li>
                </ul>
            </div>
        </div>
        {/foreach}
    {else}
        {* Fallback: original hand-designed cards. *}
        <!-- 1 Plan -->
        <div class="col-md-4">
            <div class="wrapper-plan" data-aos="fade-up" data-aos-duration="800">
                <div class="top-content">
                    <img class="svg mb-10 wh-50" src="templates/{$template}/assets/fonts/svg/servers.svg">
                    <div class="plan-title">Web Hosting <span>{$LANG.pricestarts}</span></div>
                    <div class="plan-price">2.95 € <span>{$LANG.vpsnetmonthly}</span></div>
                </div>
                <ul class="specs-content bg-lighttheme">
                    <li><i class="icon-drivessd"></i> 100MB {$LANG.diskspacembytes}</li>
                    <li><i class="icon-network"></i> <s>750MB</s> 1.5GB {$LANG.monthlyTraffic}</li>
                    <li><i class="icon-domainserver"></i> 1 {$LANG.websitesplan}</li>
                    <li><i class="icon-email"></i> 1 {$LANG.emailsplan}</li>
                    <li class="specs-cta"><a class="btn btn-prussian mt-15" href="{$WEB_ROOT}/store/web-hosting"><span>{$LANG.seemore}</span></a></li>
                </ul>
            </div>
        </div>
        <!-- 2 Plan -->
        <div class="col-md-4" >
            <div class="wrapper-plan" data-aos="fade-up" data-aos-duration="1100">
                <div class="top-content">
                    <img class="svg mb-10 wh-50" src="templates/{$template}/assets/fonts/svg/cloudserver.svg">
                    <div class="plan-title">Cloud On Storage <span>{$LANG.pricestarts}</span></div>
                    <div class="plan-price">6.80 € <span>{$LANG.vpsnetmonthly}</span></div>
                </div>
                <ul class="specs-content bg-tuscanytheme">
                    <li><i class="icon-network"></i> 1 TB {$LANG.monthlyTraffic}</li>
                    <li><i class="icon-cloudconnect"></i> 10 {$LANG.connectionsCloud}</li>
                    <li><i class="icon-browser"></i> 2 {$LANG.snapshotsCloud}</li>
                    <li><i class="icon-add"></i> {$LANG.manymore}</li>
                    <li class="specs-cta"><a class="btn btn-prussian mt-15" href="{$WEB_ROOT}/store/cloudon-storage"><span>{$LANG.seemore}</span></a></li>
                </ul>
            </div>
        </div>
        <!-- 3 Plan -->
        <div class="col-md-4">
            <div class="wrapper-plan" data-aos="fade-up" data-aos-duration="800">
                <div class="top-content">
                    <img class="svg mb-10 wh-50" src="templates/{$template}/assets/fonts/svg/vps.svg">
                    <div class="plan-title">Virtual Servers <span>{$LANG.pricestarts}</span></div>
                    <div class="plan-price">17.90€ <span>{$LANG.vpsnetmonthly}</span></div>
                </div>
                <ul class="specs-content bg-lighttheme">
                    <li><i class="icon-cpu"></i>1 {$LANG.cpucores} 3700Mhz</li>
                    <li><i class="icon-ram"></i> {$LANG.ramsbytes} 1 GB</li>
                    <li><i class="icon-location"></i> {$LANG.locationServers}</li>
                    <li><i class="icon-add"></i> {$LANG.manymore}</li>
                    <li class="specs-cta"><a class="btn btn-prussian mt-15" href="{$WEB_ROOT}/store/vmware-virtual-servers"><span >{$LANG.seemore}</span></a></li>
                </ul>
            </div>
        </div>
    {/if}
    </div>
</div>
