<div class="loginpage">
    <div class="container">
        <div class="login-page-header maxw-500 row" style="align-items:center">
            <div class="col-xs-6">
                <a class="navbar-brand" href="{$WEB_ROOT}/index.php" style="display:inline-block">
                    <img src="{$WEB_ROOT}/templates/{$template}/assets/img/logo-cloudon.png" alt="{$companyname}" style="height:42px;width:auto;display:block" />
                </a>
            </div>
            <div class="col-xs-6" style="display:flex;justify-content:flex-end;align-items:center">
                <span class="login-lang" style="font-weight:700;font-size:14px;white-space:nowrap">
                    <a href="{$WEB_ROOT}/login?language=greek" style="text-decoration:none;padding:4px 8px;color:{if $language=='greek'}#ffffff{else}#8aa0b8{/if}">ΕΛ</a><span style="color:#5f7799">|</span><a href="{$WEB_ROOT}/login?language=english" style="text-decoration:none;padding:4px 8px;color:{if $language=='english'}#ffffff{else}#8aa0b8{/if}">EN</a>
                </span>
            </div>
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

                    <div class="login-register-cta" style="margin-top:24px;padding-top:20px;border-top:1px solid rgba(255,255,255,.08);text-align:center">
                        <div style="opacity:.7;font-size:13px;margin-bottom:10px">{$LANG.registerintro}</div>
                        <a href="{$WEB_ROOT}/register.php" class="btn btn-medium btn-prussian w-100"><i class="ico-user-plus"></i> {$LANG.newcustomer} — {$LANG.register}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>