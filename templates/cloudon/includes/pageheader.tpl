<div class="header-lined">
	<h1>{$title}{if $desc} <small>{$desc}</small>{/if}</h1>
	{if !$inShoppingCart && ($primarySidebar->hasChildren() || $secondarySidebar->hasChildren())}
	<div class="dropnav-header-lined">
		<button id="dropside-content" type="button" class="drop-down-btn dropside-content" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
			<div class="menu-help">
				<span class="menu-help">{$LANG.helpmenu}</span>
				<span class="ico-menu"></span>
			</div>
		</button>
		<div class="dropdown-menu" aria-labelledby="dropside-content">
			{include file="$template/includes/sidebar.tpl" sidebar=$primarySidebar}
			{include file="$template/includes/sidebar.tpl" sidebar=$secondarySidebar}
		</div>
	</div>
	{/if}
</div>