{if !$loggedin}
<style>{literal}
	/* Login popup polish: readable on the blue popup body. */
	.user-login .login-content form .form-group i{color:#fff!important;border-right-color:rgba(255,255,255,.28)!important;}
	.user-login .login-content .login-links{text-align:center;margin-top:16px;padding-top:14px;
		border-top:1px solid rgba(255,255,255,.20);font-size:13px;}
	.user-login .login-content .login-links a{color:#fff!important;font-weight:600;text-decoration:none;opacity:.95;}
	.user-login .login-content .login-links a:hover{text-decoration:underline;opacity:1;}
	.user-login .login-content .login-links .sep{color:rgba(255,255,255,.5);margin:0 8px;}
	/* Darker pill + white text = readable on the blue popup (WCAG AA). */
	.user-login .login-content form .form-control{background-color:rgba(0,0,0,.24)!important;color:#fff!important;}
	.user-login .login-content form .form-control::placeholder{color:#d6e4f1!important;opacity:1;}
	.user-login .login-content form .form-control:-webkit-autofill{
		-webkit-box-shadow:0 0 0 1000px #00588d inset!important;box-shadow:0 0 0 1000px #00588d inset!important;
		-webkit-text-fill-color:#fff!important;}
{/literal}</style>
{/if}
{if $loggedin}
<style>{literal}
	/* Logged-in menu — aligned with the login popup's clean blue aesthetic. */
	/* Stat tiles: one consistent translucent-white surface, crisp white content. */
	.user-login .user-info-content .user-info{background:rgba(0,0,0,.18)!important;border-radius:12px!important;color:#fff!important;}
	.user-login .user-info-content .user-info:hover{background:rgba(0,0,0,.30)!important;color:#fff!important;}
	.user-login .user-info-content .user-info i{color:#fff!important;opacity:.45!important;}
	.user-login .user-info-content .number-services,
	.user-login .user-info-content .number-services span{color:#fff!important;}
	/* Quicklinks: readable white text + subtle dividers/hover on the blue body. */
	.user-login .user-quicklinks li a{color:#fff!important;border-bottom-color:rgba(255,255,255,.14)!important;}
	.user-login .user-quicklinks li a span{color:rgba(255,255,255,.72)!important;}
	.user-login .user-quicklinks li:hover{background:rgba(255,255,255,.08)!important;}
{/literal}</style>
{/if}
<li>
	<div class="dropdown user-login {if !$loggedin}not-login{/if}">
		<a href="javascript:" class="dropdown-toggle f-15" data-toggle="dropdown" aria-label="{$LANG.hzMyAccount}">
			{if $loggedin}
			<img src="{$WEB_ROOT}/templates/{$template}/assets/img/gravatar.png" class="br-50 img-30" alt="User-Profile-Image">
			{else}
			<img class="svg icohorn mt_3" src="{$WEB_ROOT}/templates/{$template}/assets/fonts/icohorn/lock.svg" >
			{/if}
		</a>
		<div class="dropdown-menu dropdown-menu-right profile-notification{if $loggedin} logined-user-drop-down{/if}">
			{if $loggedin}
			<div class="login-header on">
				<span class="user-info-avatar">
					<img src="{$WEB_ROOT}/templates/{$template}/assets/img/gravatar.png" class="br-50" alt="User-Profile-Image">
				</span>
				<h6>{$LANG.welcomeback}, {$clientsdetails.firstname}<br>
				 {$LANG.affiliatesbalance}, <span class="c-white">{$clientsstats.creditbalance}</span></h6>
			 	<a class="logout-btn" data-toggle="tooltip" data-placement="left" title="{$LANG.logouttitle}" href="{$WEB_ROOT}/logout.php" > <i class="ico-power"></i></a>
			</div>
			<div class="user-info-content">
				<a href="{$WEB_ROOT}/clientarea.php?action=services" class="user-info bg-pratalight">
				<i class="ico-settings"></i> <div class="number-services">{$clientsstats.productsnumactive} <span>{$LANG.navservices}</span></div></a>
				<a href="{$WEB_ROOT}/supportickets.php" class="user-info bg-prussian-extralight">
				<i class="ico-globe"></i> <div class="number-services">{$clientsstats.numactivetickets} <span>{$LANG.navtickets}</span></div></a>
				<a href="{$WEB_ROOT}/clientarea.php?action=invoices" class="user-info bg-prussian-light">
				<i class="ico-credit-card"></i> <div class="number-services">{$clientsstats.numunpaidinvoices} <span>{$LANG.navinvoices}</span></div></a>
			</div>
			<ul class="user-quicklinks">
				<li><a href="{$WEB_ROOT}/clientarea.php?action=details"><i class="ico-user-check"></i> {$LANG.accountinfo} 
				<span>{$LANG.clientareadescription}</span></a></li>
				<li><a href="{$WEB_ROOT}/supporttickets.php"><i class="ico-mail"></i> {$LANG.supportticketspagetitle} 
				<span>{$LANG.subaccountpermstickets}</span></a></li>
				<li><a href="{$WEB_ROOT}/clientarea.php?action=changepw"><i class="ico-unlock"></i> {$LANG.generatePassword.generatedPw} 
				<span>{$LANG.generatePassword.generateNew}</span></a></li>
			</ul>
			{else}
			<div class="login-header"><h6><b class="f-13">{$LANG.login}</b> - {$LANG.restrictedpage}</h6></div>
			<div class="login-content">

				<form method="post" action="{$systemurl}dologin.php" class="login-form">
					<div class="form-group">
						<i class="fas fa-user" aria-hidden="true"></i>
						<input type="email" name="username" class="form-control" id="inputEmail" placeholder="{$LANG.enteremail}" autofocus>
					</div>
					<div class="form-group">
						<i class="fas fa-lock" aria-hidden="true"></i>
						<input type="password" name="password" class="form-control" id="inputPassword" placeholder="{$LANG.clientareapassword}" autocomplete="off" >
					</div>
					<div class="row mr-bt-20">
						<div class="col-md-6 col-sm-6 col-xs-7">
							<div class="custom-control custom-checkbox p-t-5">
								<input type="checkbox" class="custom-control-input" name="rememberme" id="rememberme">
								<label class="custom-control-label" for="rememberme">{$LANG.loginrememberme}</label>
							</div>
						</div>
						<div class="col-md-6 col-sm-6 col-xs-5 float-right">
							<input id="login" type="submit" class="btn btn-block btn-extrasmall btn-prussian" value="{$LANG.loginbutton}" />
						</div>
					</div>
				</form>

				<div class="login-links">
					<a href="{$systemurl}pwreset.php">{$LANG.forgotpw}</a>
					<span class="sep">·</span>
					<a href="{$systemurl}register.php">{$LANG.register}</a>
				</div>
			</div>
			{/if}
		</div>
	</div>
</li>