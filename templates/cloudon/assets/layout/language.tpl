<li>
	<div class="dropdown lang-drop">
		<a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-label="{$LANG.chooselanguage}">
			<img src="{$WEB_ROOT}/templates/{$template}/assets/img/flags/{$language}.svg" class="br-50 img-20" alt="{$activeLocale.localisedName}">
		</a>
		<div class="dropdown-menu dropdown-menu-right notification lang-container">
			<div class="notify-header">
				<h6>{$LANG.chooselanguage}</h6>
				<a>{$activeLocale.localisedName}</a>
			</div>
			<ul>
				{foreach $locales as $locale}
				<li><a href="{$currentpagelinkback}language={$locale.language}">{$locale.localisedName}</a></li>
				{/foreach}
			</ul>
		</div>
	</div>
</li>