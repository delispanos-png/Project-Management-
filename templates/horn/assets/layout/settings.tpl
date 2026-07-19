<div class="setmenu">
	<div class="body-setmenu">
		<button type="button" class="setbtn dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		<i class="fa fa-cog" aria-hidden="true"></i>
		</button>
		<div class="dropdown-menu settings">

			{if $language eq 'chinese' || $language eq 'danish' || $language eq 'estonian' || $language eq 'german' || $language eq 'italian' || $language eq 'portuguese-br' || $language eq 'russian' || $language eq 'turkish' || $language eq 'azerbaijani' || $language eq 'croatian' || $language eq 'dutch' || $language eq 'macedonian' || $language eq 'portuguese-pt' || $language eq 'spanish' || $language eq 'ukranian' || $language eq 'catalan' || $language eq 'czech' || $language eq 'english' || $language eq 'french' || $language eq 'hungarian' || $language eq 'norwegian' || $language eq 'romanian' || $language eq 'swedish'}<html dir="ltr">
				<!-- Set Switch -->
				<div class="set-modes">
					<a style="direction:rtl" class="setswitch barmenu active" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="left" data-content="Collapse Sidebar" href="#"></a><br>
					<a class="setswitch setstick" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="left" data-content="Sticky Menu" href="#"></a><br>
					<a class="setswitch navoff" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="left" data-content="Hide Menu" href="#"></a><br>
				</div>
				<!-- Color Switch -->
				<div class="color-scheme">
					<div class="blue" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="left" data-content="Blue Mode"><a class="styleswitch" href="#" data-rel="blue" ></a></div><br>
					<div class="dark" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="left" data-content="Dark Mode"><a class="styleswitch" href="#" data-rel="dark" ></a></div><br>
					<div class="pink" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="left" data-content="Rose Mode"><a class="styleswitch" href="#" data-rel="pink"></a></div><br>
					<div class="green" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="left" data-content="Green Mode"><a class="styleswitch" href="#" data-rel="green" ></a></div><br>
				</div>
				<!-- LTR & RTL Version -->
				<div class="dir-version">
					<div class="btnv" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="left" data-content="LTR Version"><a href="{$WEB_ROOT}/cart.php?carttpl=horn&systpl=horn&language=english">L</a></div>
					<div class="btnv" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="left" data-content="RTL Version"><a href="{$WEB_ROOT}/cart.php?carttpl=horn&systpl=horn&language=arabic">R</a></div>
				</div>
			<html>
			{/if}

			{if $language eq 'arabic' || $language eq 'farsi' || $language eq 'hebrew'}<html dir="rtl">
				<!-- Set Switch -->
				<div class="set-modes">
					<a style="direction:rtl" class="setswitch barmenu active" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="right" data-content="Collapse Sidebar" href="#"></a><br>
					<a class="setswitch setstick" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="right" data-content="Sticky Menu" href="#"></a><br>
					<a class="setswitch navoff" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="right" data-content="Hide Menu" href="#"></a><br>
				</div>
				<!-- Color Switch -->
				<div class="color-scheme">
					<div class="dark" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="right" data-content="Dark Mode"><a class="styleswitch" href="#" data-rel="dark" ></a></div><br>
					<div class="pink" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="right" data-content="Rose Mode"><a class="styleswitch" href="#" data-rel="pink"></a></div><br>
					<div class="blue" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="right" data-content="Blue Mode"><a class="styleswitch" href="#" data-rel="blue" ></a></div><br>
					<div class="green" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="right" data-content="Green Mode"><a class="styleswitch" href="#" data-rel="green" ></a></div><br>
				
				</div>
				<!-- LTR & RTL Version -->
				<div class="dir-version">
					<div class="btnv" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="right" data-content="LTR Version"><a href="{$WEB_ROOT}/cart.php?carttpl=horn&systpl=horn&language=english">L</a></div>
					<div class="btnv" data-container="body" data-toggle="popover" data-trigger="hover" data-placement="right" data-content="RTL Version"><a href="{$WEB_ROOT}/cart.php?carttpl=horn&systpl=horn&language=arabic">R</a></div>
				</div>

			<html>
			{/if}

		</div>
	</div>
</div>