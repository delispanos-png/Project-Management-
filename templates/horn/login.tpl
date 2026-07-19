<div class="loginpage">
    <div class="container">
        <div class="login-page-header maxw-500 row">
            <div class="col-md-10 col-xs-10">
                <a class="navbar-brand" href="{$WEB_ROOT}/index.php">
                    <img class="w-logo svg logo" src="{$WEB_ROOT}/templates/{$template}/assets/img/logo.svg" alt="{$companyname}" />
                    <span class="w-text">{$companyname}</span>
                </a>
            </div>
            <a href="{$WEB_ROOT}/register.php" aria-label="{$LANG.register}"> <i class="ico-user-plus" data-toggle="tooltip" data-placement="left" title="{$LANG.registerintro}"></i> </a>
        </div>
        <div class="logincontent">
            <div class="login-wrapper">
                <div class="login-form-container maxw-500">
                    {include file="$template/includes/flashmessage.tpl"}
                    
                    <h5 class="login-title">{$LANG.loginbutton}<span>{$LANG.restrictedpage}</span></h5>
                    <div class="{if !$linkableProviders}hidden{/if}">
                        {include file="$template/includes/linkedaccounts.tpl" linkContext="login" customFeedback=true}
                        <div class="divider">
                            <span></span>
                            <span>{$LANG.remoteAuthn.titleOr}</span>
                            <span></span>
                        </div>
                    </div>
                    <div class="providerLinkingFeedback"></div>
                    <form method="post" action="{routePath('login-validate')}" class="login-form" role="form">
                        <div class="form-group">
                            <label class="hz-sr-only" for="inputEmail">{$LANG.orderForm.emailAddress}</label>
                            <input type="email" name="username" class="form-control" id="inputEmail" placeholder="{$LANG.orderForm.emailAddress}" autocomplete="username" autofocus>
                        </div>

                        <div class="form-group">
                            <label class="hz-sr-only" for="inputPassword">{$LANG.hzPassword}</label>
                            <input type="password" name="password" class="form-control" id="inputPassword" placeholder="{$LANG.generatePassword.generatedPw}" autocomplete="current-password" >
                        </div>
                        <div class="row">
                            <div class="col-md-4 col-xs-6 mt-15 mb-15">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" name="rememberme" id="rememberme">
                                    <label class="custom-control-label" for="rememberme">{$LANG.loginrememberme}</label>
                                </div>
                            </div>
                            <div class="col-md-4 col-xs-6 mt-15 mb-15">
                                <a href="{routePath('password-reset-begin')}" class="forgotpw-txt">{$LANG.forgotpw}</a>
                            </div>

                            <div class="col-md-4 col-xs-12">
                                <input id="login" type="submit" class="btn btn-medium btn-prussian w-100 {$captcha->getButtonClass($captchaForm)}" value="{$LANG.loginbutton}" />
                            </div>
                        </div>
                        {if $captcha->isEnabled()}
                        <div class="text-center margin-bottom">
                            {include file="$template/includes/captcha.tpl"}
                        </div>
                        {/if}
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>