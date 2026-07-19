<p>{$LANG.pwresetemailneeded}</p>

<form method="post" action="{routePath('password-reset-validate-email')}" role="form">
    <input type="hidden" name="action" value="reset" />

    <div class="row">
        <div class="col-md-8">
            <div class="form-group">
                <input type="email" name="email" class="form-control" id="inputEmail" placeholder="{$LANG.enteremail}" autofocus>
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <button type="submit" class="btn btn-block btn-medium btn-prussian {$captcha->getButtonClass($captchaForm)}">
                    {$LANG.pwresetsubmit}
                </button>
            </div>
        </div>

        {if $captcha->isEnabled()}
            <div class="text-center margin-bottom">
                {include file="$template/includes/captcha.tpl"}
            </div>
        {/if}

    </div>
</form>
