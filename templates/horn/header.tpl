<!DOCTYPE html>
<html lang="{if $language == 'greek'}el{else}en{/if}">
    <head>
	{literal}
	<script type="text/javascript">
		(function(c,l,a,r,i,t,y){
		c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
		t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
		y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
		})(window, document, "clarity", "script", "odcbh6rlie");
	</script>
	{/literal}
		<!-- Start cookieyes banner -->
    <script id="cookieyes" type="text/javascript" src="https://cdn-cookieyes.com/client_data/219c2db3108c3b5c91fa7327/script.js"></script>
    <!-- End cookieyes banner --> 
    <meta charset="{$charset}" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="icon" href="{$WEB_ROOT}/templates/{$template}/assets/img/logo.svg">
    {* ==================== SEO (Cloud On) ==================== *}
    {capture assign=hzBase}https://{$smarty.server.HTTP_HOST}{/capture}
    {if $templatefile == 'products' && $productGroup.name}
        {* Store product-group page: unique title/description/canonical per group *}
        {capture assign=hzTitle}{$productGroup.name} - {$companyname}{/capture}
        {if $productGroup.tagline}{capture assign=hzDescRaw}{$productGroup.tagline}{/capture}
        {elseif $productGroup.headline}{capture assign=hzDescRaw}{$productGroup.headline}{/capture}
        {else}{capture assign=hzDescRaw}{$LANG.hzMetaDesc}{/capture}{/if}
        {capture assign=hzCanon}{$hzBase}{$smarty.server.REQUEST_URI|regex_replace:'/[?].*$/':''|regex_replace:'#^/index\.php#':''}{/capture}
    {else}
        {capture assign=hzTitle}{$pagetitle} - {$LANG.titlecompany}{/capture}
        {capture assign=hzDescRaw}{$LANG.hzMetaDesc}{/capture}
        {if $templatefile == 'homepage'}{capture assign=hzCanon}{$hzBase}/{/capture}{else}{assign var=hzCanon value=''}{/if}
    {/if}
    {assign var=hzDesc value=$hzDescRaw|strip_tags|regex_replace:'/\s+/':' '|regex_replace:'/^ | $/':''|truncate:158:"…"}
    <title>{$hzTitle}</title>
    <meta name="description" content="{$hzDesc|escape}">
    {if $hzCanon}<link rel="canonical" href="{$hzCanon}">{/if}
    {if $templatefile == 'viewcart' || $templatefile == 'cart' || $templatefile == 'clientarea' || $templatefile == 'clientareahome' || $templatefile == 'clientareaproducts' || $templatefile == 'clientareainvoices' || $templatefile == 'clientareadomains' || $templatefile == 'clientregister' || $loginpage}<meta name="robots" content="noindex, follow">{/if}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{$companyname}">
    <meta property="og:title" content="{$hzTitle|escape}">
    <meta property="og:description" content="{$hzDesc|escape}">
    <meta property="og:url" content="{if $hzCanon}{$hzCanon}{else}{$hzBase}/{/if}">
    <meta property="og:image" content="{$hzBase}/templates/{$template}/assets/img/og-cloudon.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="{if $language == 'greek'}el_GR{else}en_US{/if}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{$hzTitle|escape}">
    <meta name="twitter:description" content="{$hzDesc|escape}">
    <meta name="twitter:image" content="{$hzBase}/templates/{$template}/assets/img/og-cloudon.png">
    <link rel="alternate" hreflang="el" href="{$hzBase}/?language=greek">
    <link rel="alternate" hreflang="en" href="{$hzBase}/?language=english">
    <link rel="alternate" hreflang="x-default" href="{$hzBase}/">
    {literal}<script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":"Cloud On","url":"https://my.cloudon.gr/","logo":"https://my.cloudon.gr/templates/horn/assets/img/logo-cloudon.png","telephone":"+302102241998","contactPoint":{"@type":"ContactPoint","telephone":"+302102241998","contactType":"customer service","areaServed":"GR","availableLanguage":["el","en"]},"sameAs":["https://cloudon.gr/"]}</script>{/literal}
    {if $templatefile == 'homepage'}{literal}<script type="application/ld+json">{/literal}{ldelim}"@context":"https://schema.org","@type":"WebSite","name":"{$companyname|escape:'json'}","url":"{$hzBase}/","inLanguage":"{if $language == 'greek'}el{else}en{/if}","potentialAction":{ldelim}"@type":"SearchAction","target":{ldelim}"@type":"EntryPoint","urlTemplate":"{$hzBase}/knowledgebase.php?search={literal}{search_term_string}{/literal}"{rdelim},"query-input":"required name=search_term_string"{rdelim}{rdelim}</script>{/if}
    {if $templatefile == 'products' && $productGroup.name}{literal}<script type="application/ld+json">{/literal}{ldelim}"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[{ldelim}"@type":"ListItem","position":1,"name":"{$companyname|escape:'json'}","item":"{$hzBase}/"{rdelim},{ldelim}"@type":"ListItem","position":2,"name":"{$productGroup.name|escape:'json'}","item":"{$hzCanon}"{rdelim}]{rdelim}</script>
    {literal}<script type="application/ld+json">{/literal}{ldelim}"@context":"https://schema.org","@type":"Service","serviceType":"{$productGroup.name|escape:'json'}","name":"{$productGroup.name|escape:'json'}","description":"{$hzDesc|escape:'json'}","provider":{ldelim}"@type":"Organization","name":"{$companyname|escape:'json'}","url":"{$hzBase}/"{rdelim},"areaServed":{ldelim}"@type":"Country","name":"GR"{rdelim},"url":"{$hzCanon}"{rdelim}</script>{/if}
    {* ==================== /SEO ==================== *}
    {include file="$template/includes/head.tpl"}
	{$headoutput}
    </head>
    <body id="layout01"{if $loggedin && $templatefile != 'homepage' && $templatefile != 'products' && $templatefile != 'viewcart'} class="hz-portal"{/if} data-phone-cc-input="{$phoneNumberInputStyle}">
    <a class="hz-skip-link" href="#main-content">{$LANG.hzSkipToContent}</a>
    <img class="svg" src="{$WEB_ROOT}/templates/{$template}/assets/img/bgbody.svg" id="bgbody" alt="">
    {$headeroutput}
    {* include file="$template/assets/layout/settings.tpl" *}
    
	<!-- ***** LOADING PAGE ****** -->
    <div id="spinner-area">
      <div class="spinner">
        <div class="double-bounce1"></div>
        <div class="double-bounce2"></div>
        <div class="spinner-txt">{$LANG.loading}</div>
      </div>
    </div>
	{if $loginpage eq 0 and $templatefile ne "clientregister"}<!-- login and register page without the default header and footer -->
    <nav class="navbar default navbar-collapsed" aria-label="{$LANG.hzMainNav}">
        <div class="navbar-wrapper">
            <div class="navbar-brand header-logo">
                <a class="mobmenu on" id="mobcol"><span></span></a>
                <a href="https://cloudon.gr/" class="logo-content hz-brand">
                    <img class="hz-logo hz-logo-full" src="{$WEB_ROOT}/templates/{$template}/assets/img/logo-cloudon.png" alt="{$companyname}"><!-- Cloud On full logo (open) -->
                    <img class="hz-logo hz-logo-on" src="{$WEB_ROOT}/templates/{$template}/assets/img/logo.svg" alt="{$companyname}"><!-- cloud symbol (collapsed) -->
                </a>
                {literal}<style>
                /* Brand: full wordmark when the sidebar is open, "on" mark when collapsed */
                .logo-content.hz-brand{width:auto;overflow:visible;}
                .hz-brand .hz-logo{width:auto;}
                .hz-brand .hz-logo-on{display:none;}
                .hz-brand .hz-logo-full{display:inline-block;height:48px;width:auto;max-width:210px;margin-left:6px;vertical-align:middle;}
                .navbar-collapsed .hz-brand .hz-logo-full{display:none !important;}
                .navbar-collapsed .logo-content.hz-brand{position:absolute;left:0;right:0;top:6px;margin:0 auto;width:auto !important;padding:0;text-align:center;overflow:visible;}
                .navbar-collapsed .hz-brand .hz-logo-on{display:inline-block !important;width:auto !important;height:34px !important;border-radius:0;vertical-align:middle;}
                /* ---- Mobile header: μικρότερο logo, χωρίς overlap με τις γλώσσες ---- */
                @media (max-width:991px){
                  .m-header .hz-logo-full{height:32px !important;max-width:120px;margin-left:22px !important;}
                }
                @media (max-width:600px){
                  .m-header .hz-logo-full{height:28px !important;max-width:100px;margin-left:22px !important;}
                  .header .call-us-phone{font-size:0 !important;}
                  .header .call-us-phone img{margin:0 !important;}
                }
                </style>{/literal}
            </div>
            <div class="navbar-content scroll-div">
                <ul class="nav inner-navbar">
                    {include file="$template/assets/layout/menu.tpl"}<!-- the main menu -->
                </ul>
            </div>
        </div>
    </nav>
	
	
	
    <header class="navbar header navbar-expand-lg navbar-light">

        <div class="m-header">
            <a class="mobmenu" id="mobcols" href="javascript:" aria-label="{$LANG.hzMenu}"><span></span></a>
            <a href="https://cloudon.gr/" class="logo-content hz-brand">
                <img class="hz-logo hz-logo-full" src="{$WEB_ROOT}/templates/{$template}/assets/img/logo-cloudon.png" alt="{$companyname}"><!-- Cloud On logo -->
            </a>
        </div>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav">
                <li class="nav-item search-term">
                    <form class="main-search" method="post" action="{routePath('knowledgebase-search')}">
                        <a href="{$WEB_ROOT}/knowledgebase.php" aria-label="{$LANG.hzSearch}">
                            <img class="svg icohorn" src="{$WEB_ROOT}/templates/{$template}/assets/fonts/icohorn/plus-circle.svg" alt="">
                        </a>
                        <input type="text" class="form-control" placeholder="{$LANG.tableentersearchterm}">
                    </form>
                </li>
            </ul>
            <ul class="navbar-nav ml-auto">
                <li><a class="call-us-phone f-15" href="tel:+302102241998"><img class="svg icohorn" src="{$WEB_ROOT}/templates/{$template}/assets/fonts/icohorn/phone.svg" > (+30) 210 22 41 998</a></li>
                {if $languagechangeenabled && count($locales) > 1}
    			{include file="$template/assets/layout/language.tpl"}<!-- language selector -->
    			{/if}
    			<li><a class="f-15 cnp-cart-link" href="{$WEB_ROOT}/cart.php?a=view" aria-label="{$LANG.hzCart}{if $cnpCartCount} ({$cnpCartCount}){/if}"><img class="svg icohorn" src="{$WEB_ROOT}/templates/{$template}/assets/fonts/icohorn/shopping-cart.svg" alt="">{if $cnpCartCount > 0}<span class="cnp-cart-badge">{$cnpCartCount}</span>{/if}</a></li>
                {if $loggedin}
    			{include file="$template/assets/layout/notifications.tpl"}<!-- the notifications -->
                {/if}
                {if $adminMasqueradingAsClient || $adminLoggedIn}
                <li class="notify-container">
                    <div class="dropdown">
                        <a class="dropdown-toggle f-15" href="" data-toggle="dropdown" aria-label="{$LANG.hzAdminArea}"><img class="svg icohorn" src="{$WEB_ROOT}/templates/{$template}/assets/fonts/icohorn/settings.svg" alt=""></a>
                        <div class="dropdown-menu dropdown-menu-right notification">
                            <div class="notify-header">
                                <h6 class="d-inline-block m-b-0">WHMCS Admin Panel</h6>
                            </div>
                            <div class="notify-content">
                                <p>
                                {if $adminMasqueradingAsClient}{$LANG.adminmasqueradingasclient} {$LANG.logoutandreturntoadminarea}{else}{$LANG.adminloggedin} {$LANG.returntoadminarea}{/if}
                                </p>
                            </div>
                            <div class="notify-footer">
                                <a href="{$WEB_ROOT}/logout.php?returntoadmin=1" class="btn btn-extrasmall btn-prussian-light"> {$LANG.admin.returnToAdmin} <i class="ico-arrow-right f-14 w-icon"></i></a>
                            </div>
                        </div>
                    </div>
                </li>
                {/if} 
    			{include file="$template/assets/layout/login.tpl"}<!-- account informations -->
            </ul>
        </div>
    </header>
    <div class="header-hight-fixed"></div>
	{if $templatefile == 'homepage'}
	{include file="$template/assets/layout/sections/slider.tpl"}
	{/if}
    <div class="main-container">
    {include file="$template/includes/verifyemail.tpl"}
    <div class="wrapper">
        <div class="content main" id="main-content" role="main">
            <div class="inner-content">
                <div class="main-body">
                 <div class="page-wrapper">
                    {if $templatefile == 'homepage'}
                    <h1 class="hz-sr-only">{$LANG.hzHomeH1}</h1>
                    <!-- Full Background Video -->
                    <!-- Section Plans -->
	                {include file="$template/assets/layout/sections/plans.tpl"}
                    <!-- Section Features -->
					{include file="$template/assets/layout/sections/features.tpl"}
	                {/if}	
                    {include file="$template/includes/validateuser.tpl"}			
                            <section id="{if $templatefile == 'homepage'} {else}main-body{/if}">
                                <div class="{if $skipMainBodyContainer}-fluid without-padding{/if}">
                                    <div class="row">
                                        {if !$inShoppingCart && ($primarySidebar->hasChildren() || $secondarySidebar->hasChildren())} {if $primarySidebar->hasChildren() && !$skipMainBodyContainer}
                                        <div class="col-md-12">
                                            {if $templatefile != 'clientareahome' && $templatefile != 'clientareaproducts' && $templatefile != 'clientareainvoices' && $templatefile != 'clientareadomains'}{include file="$template/includes/pageheader.tpl" title=$displayTitle desc=$tagline showbreadcrumb=true}{/if}
                                        </div>
                                        {/if} 
										{/if}<!-- Container for main page display content -->
                                        <div class="{if !$inShoppingCart && ($primarySidebar->hasChildren() || $secondarySidebar->hasChildren())}col-md-12{else}col-xs-12{/if} main-content">
                                            {if !$primarySidebar->hasChildren() && !$showingLoginPage && !$inShoppingCart && $templatefile != 'homepage' && !$skipMainBodyContainer}
											{if $templatefile != 'clientareahome' && $templatefile != 'clientareaproducts' && $templatefile != 'clientareainvoices' && $templatefile != 'clientareadomains'}{include file="$template/includes/pageheader.tpl" title=$displayTitle desc=$tagline showbreadcrumb=false}{/if}
											{/if} 
											{/if}<!-- login and register page without the default header and footer -->
