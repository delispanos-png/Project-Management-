{assign var="ico" value="`$WEB_ROOT`/templates/`$template`/assets/fonts/icohorn"}

{* ==================== ΥΠΗΡΕΣΙΕΣ / ΑΓΟΡΑ (για όλους) ==================== *}
<li class="nav-menu-title"><span>{$LANG.clientareanavservices}</span></li>

<li data-username="Home" class="nav-item">
	<a href="{if $loggedin}{$WEB_ROOT}/clientarea.php{else}{$WEB_ROOT}/{/if}" aria-label="{$LANG.clientareanavhome}"><span class="icony"><img class="svg" src="{$ico}/home.svg" alt=""></span><span class="sideinfo">{$LANG.clientareanavhome}</span></a>
</li>

<li data-username="store" class="nav-item hasmenu">
	<a role="button" aria-label="{$LANG.hzBuyServices}"><span class="icony"><img class="svg" src="{$ico}/shopping-cart.svg" alt=""></span><span class="sideinfo">{$LANG.hzBuyServices}</span> <span class="labelinfo">{$LANG.top}</span></a>
	<ul class="submenu">
		{if !$hzAvail || in_array('virtual-servers-location-in-germany', $hzAvail)}<li><a href="{$WEB_ROOT}/store/virtual-servers-location-in-germany">{$LANG.hzProdCloudVpsDe}</a></li>{/if}
		{if !$hzAvail || in_array('kvm-virtual-servers', $hzAvail)}<li><a href="{$WEB_ROOT}/store/kvm-virtual-servers">High-Performance VPS</a></li>{/if}
		{if !$hzAvail || in_array('vmware-virtual-servers', $hzAvail)}<li><a href="{$WEB_ROOT}/store/vmware-virtual-servers">{$LANG.hzProdVpsGr}</a></li>{/if}
		{if !$hzAvail || in_array('web-hosting', $hzAvail)}<li><a href="{$WEB_ROOT}/store/web-hosting">Web Hosting</a></li>{/if}
		{if !$hzAvail || in_array('cloudon-storage', $hzAvail)}<li><a href="{$WEB_ROOT}/store/cloudon-storage">Cloud Storage</a></li>{/if}
		{if !$hzAvail || in_array('dedicated-servers', $hzAvail)}<li><a href="{$WEB_ROOT}/store/dedicated-servers">Dedicated Servers</a></li>{/if}
		{if !$hzAvail || in_array('collocation', $hzAvail)}<li><a href="{$WEB_ROOT}/store/collocation">Collocation</a></li>{/if}
		{if !$hzAvail || in_array('voip-services', $hzAvail)}<li><a href="{$WEB_ROOT}/store/voip-services">VoIP Services</a></li>{/if}
		{if !$hzAvail || in_array('3cx-ip-pbx-standard-edition', $hzAvail)}<li><a href="{$WEB_ROOT}/store/3cx-ip-pbx-standard-edition">3CX IP PBX</a></li>{/if}
		{if !$hzAvail || in_array('ssl-certificates', $hzAvail)}<li><a href="{$WEB_ROOT}/store/ssl-certificates">SSL Certificates</a></li>{/if}
		{if $registerdomainenabled}<li><a href="{$WEB_ROOT}/cart.php?a=add&domain=register">Domains</a></li>{/if}
		<li><a href="{$WEB_ROOT}/cart.php"><strong>{$LANG.hzSeeAllProducts}</strong></a></li>
	</ul>
</li>

{* ==================== Ο ΛΟΓΑΡΙΑΣΜΟΣ ΜΟΥ (μόνο συνδεδεμένος) ==================== *}
{if $loggedin}
<li class="nav-menu-title"><span>{$LANG.hzMyAccount}</span></li>

<li data-username="Services" class="nav-item">
	<a href="{$WEB_ROOT}/clientarea.php?action=services" aria-label="{$LANG.hzMyServices}"><span class="icony"><img class="svg" src="{$ico}/server.svg" alt=""></span><span class="sideinfo">{$LANG.hzMyServices}</span></a>
</li>
<li data-username="domains" class="nav-item">
	<a href="{$WEB_ROOT}/clientarea.php?action=domains" aria-label="{$LANG.hzMyDomains}"><span class="icony"><img class="svg" src="{$ico}/globel.svg" alt=""></span><span class="sideinfo">{$LANG.hzMyDomains}</span></a>
</li>
<li data-username="billing" class="nav-item hasmenu">
	<a role="button" aria-label="{$LANG.navbilling}"><span class="icony"><img class="svg" src="{$ico}/credit-card.svg" alt=""></span><span class="sideinfo">{$LANG.navbilling}</span></a>
	<ul class="submenu">
		<li><a href="{$WEB_ROOT}/clientarea.php?action=invoices">{$LANG.invoices}</a></li>
		<li><a href="{$WEB_ROOT}/clientarea.php?action=quotes">{$LANG.quotestitle}</a></li>
		<li><a href="{$WEB_ROOT}/clientarea.php?action=masspay&all=true">{$LANG.masspaytitle}</a></li>
		{if $renewalsenabled}<li><a href="{$WEB_ROOT}/cart.php?gid=renewals">{$LANG.domainrenewals}</a></li>{/if}
		<li><a href="{$WEB_ROOT}/cart.php?gid=addons">{$LANG.clientareaviewaddons}</a></li>
	</ul>
</li>
{/if}

{* ==================== ΥΠΟΣΤΗΡΙΞΗ (για όλους) ==================== *}
<li class="nav-menu-title"><span>{$LANG.navsupport}</span></li>

<li data-username="support" class="nav-item hasmenu">
	<a role="button" aria-label="{$LANG.navsupport}"><span class="icony"><img class="svg" src="{$ico}/helpdesk.svg" alt=""></span><span class="sideinfo">{$LANG.navsupport}</span></a>
	<ul class="submenu">
		<li><a href="{$WEB_ROOT}/submitticket.php">{$LANG.navopenticket}</a></li>
		<li><a href="{$WEB_ROOT}/supporttickets.php">{$LANG.navtickets}</a></li>
	</ul>
</li>
<li data-username="knowledgebase" class="nav-item">
	<a href="{$WEB_ROOT}/knowledgebase.php" aria-label="{$LANG.knowledgebasetitle}"><span class="icony"><img class="svg" src="{$ico}/file-text.svg" alt=""></span><span class="sideinfo">{$LANG.knowledgebasetitle}</span></a>
</li>
<li data-username="announcements" class="nav-item">
	<a href="{$WEB_ROOT}/announcements.php" aria-label="{$LANG.announcementstitle}"><span class="icony"><img class="svg" src="{$ico}/announcements.svg" alt=""></span><span class="sideinfo">{$LANG.announcementstitle}</span></a>
</li>
<li data-username="Network" class="nav-item">
	<a href="{$WEB_ROOT}/serverstatus.php" aria-label="{$LANG.networkstatustitle}"><span class="icony"><img class="svg" src="{$ico}/pie-chart.svg" alt=""></span><span class="sideinfo">{$LANG.networkstatustitle}</span></a>
</li>

{* ==================== ΕΠΙΚΟΙΝΩΝΙΑ ==================== *}
<li class="nav-menu-title"><span>{$LANG.contactus}</span></li>

<li data-username="contact" class="nav-item">
	<a href="{$WEB_ROOT}/contact.php" aria-label="{$LANG.contactus}"><span class="icony"><img class="svg" src="{$ico}/mail.svg" alt=""></span><span class="sideinfo">{$LANG.contactus}</span></a>
</li>
<li data-username="affiliates" class="nav-item">
	<a href="{$WEB_ROOT}/affiliates.php" aria-label="{$LANG.affiliatestitle}"><span class="icony"><img class="svg" src="{$ico}/users.svg" alt=""></span><span class="sideinfo">{$LANG.affiliatestitle}</span> <span class="labelinfo custom">{$LANG.domainCheckerSalesGroup.new}</span></a>
</li>
{if !$loggedin}
<li data-username="register" class="nav-item">
	<a href="{$WEB_ROOT}/register.php" aria-label="{$LANG.register}"><span class="icony"><img class="svg" src="{$ico}/unlock.svg" alt=""></span><span class="sideinfo">{$LANG.register}</span></a>
</li>
{/if}
