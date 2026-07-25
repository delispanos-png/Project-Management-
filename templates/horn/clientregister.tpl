{if in_array('state', $optionalFields)}
<script>
var statesTab = 10;
var stateNotRequired = true;
</script>
{/if}
<script type="text/javascript" src="{$BASE_PATH_JS}/StatesDropdown.js"></script>
<script type="text/javascript" src="{$BASE_PATH_JS}/PasswordStrength.js"></script>
<script>
window.langPasswordStrength = "{$LANG.pwstrength}";
window.langPasswordWeak = "{$LANG.pwstrengthweak}";
window.langPasswordModerate = "{$LANG.pwstrengthmoderate}";
window.langPasswordStrong = "{$LANG.pwstrengthstrong}";
jQuery(document).ready(function()
{
jQuery("#inputNewPassword1").keyup(registerFormPasswordStrengthFeedback);
});
</script>
<div class="loginpage">
    <div class="container">
        <div class="login-page-header maxw-800 row" style="align-items:center">
            <div class="col-xs-6">
                <a class="navbar-brand" href="{$WEB_ROOT}/index.php" style="display:inline-block">
                    <img src="{$WEB_ROOT}/templates/{$template}/assets/img/logo-cloudon.png" alt="{$companyname}" style="height:42px;width:auto;display:block" />
                </a>
            </div>
            <div class="col-xs-6" style="display:flex;justify-content:flex-end;align-items:center;gap:26px">
                <span class="login-lang" style="font-weight:700;font-size:14px;white-space:nowrap;position:relative;top:10px;right:26px">
                    <a href="{$WEB_ROOT}/register.php?language=greek" style="text-decoration:none;padding:4px 8px;color:{if $language=='greek'}#ffffff{else}#8aa0b8{/if}">ΕΛ</a><span style="color:#5f7799">|</span><a href="{$WEB_ROOT}/register.php?language=english" style="text-decoration:none;padding:4px 8px;color:{if $language=='english'}#ffffff{else}#8aa0b8{/if}">EN</a>
                </span>
                <a href="{$WEB_ROOT}/login.php" title="{$LANG.alreadyregistered}"> <i class="ico-unlock" data-toggle="tooltip" data-placement="left" title="{$LANG.alreadyregistered}"></i> </a>
            </div>
        </div>

        <div class="logincontent">
            <div class="login-wrapper">
                <div class="login-form-container maxw-800">
                    {if $registrationDisabled}
                    {include file="$template/includes/alert.tpl" type="error" msg=$LANG.registerCreateAccount|cat:' <strong><a href="'|cat:"$WEB_ROOT"|cat:'/cart.php" class="alert-link">'|cat:$LANG.registerCreateAccountOrder|cat:'</a></strong>'}
                    {/if}
                    {if $errormessage}
                    {include file="$template/includes/alert.tpl" type="error" errorshtml=$errormessage}
                    {/if}
                    {if !$registrationDisabled}
                    
                    <h5 class="login-title">{$LANG.register}<span>{$LANG.subtitleRegisterpage}</span></h5>
                    
                    <div id="registration">
                        <form method="post" class="using-password-strength" action="{$smarty.server.PHP_SELF}" role="form" name="orderfrm" id="frmCheckout">
                            
                            <input type="hidden" name="register" value="true"/>
                            <div id="containerNewUserSignup">
                                {include file="$template/includes/linkedaccounts.tpl" linkContext="registration"}

                                <div class="cnp-persontype" style="margin:6px 0 20px;text-align:center">
                                    <div style="font-weight:600;font-size:14px;margin-bottom:10px;opacity:.85">{$LANG.cnp_pt_label}</div>
                                    <div style="display:inline-flex;border:1px solid rgba(255,255,255,.18);border-radius:10px;overflow:hidden">
                                        <label style="margin:0;padding:10px 24px;cursor:pointer;display:flex;align-items:center;gap:8px">
                                            <input type="radio" name="customfield[153]" value="Ιδιώτης"{if $smarty.post.customfield.153 != 'Επιχείρηση'} checked{/if} style="margin:0"> {$LANG.cnp_pt_person}
                                        </label>
                                        <label style="margin:0;padding:10px 24px;cursor:pointer;display:flex;align-items:center;gap:8px;border-left:1px solid rgba(255,255,255,.18)">
                                            <input type="radio" name="customfield[153]" value="Επιχείρηση"{if $smarty.post.customfield.153 == 'Επιχείρηση'} checked{/if} style="margin:0"> {$LANG.cnp_pt_company}
                                        </label>
                                    </div>
                                </div>

                                <div class="divider mb-15">
                                    <span></span>
                                    <span>{$LANG.orderForm.personalInformation}</span>
                                    <span></span>
                                </div>

                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <input type="text" name="firstname" id="inputFirstName" class="field form-control" placeholder="{$LANG.orderForm.firstName}*" value="{$clientfirstname}" {if !in_array('firstname', $optionalFields)}required{/if} autofocus>
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <input type="text" name="lastname" id="inputLastName" class="field form-control" placeholder="{$LANG.orderForm.lastName}*" value="{$clientlastname}" {if !in_array('lastname', $optionalFields)}required{/if}>
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <input type="email" name="email" id="inputEmail" class="field form-control" placeholder="{$LANG.orderForm.emailAddress}*" value="{$clientemail}">
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <input type="tel" name="phonenumber" id="inputPhone" class="field form-control" placeholder="{$LANG.orderForm.phoneNumber}" value="{$clientphonenumber}">
                                        </div>
                                    </div>
                                </div>

                                <div class="divider mb-15">
                                    <span></span>
                                    <span>{$LANG.orderForm.billingAddress}</span>
                                    <span></span>
                                </div>

                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <input type="text" name="companyname" id="inputCompanyName" class="field form-control" placeholder="{$LANG.orderForm.companyName}" value="{$clientcompanyname}">
                                        </div>
                                    </div>
                                    <div class="col-sm-12 cnp-afm-row" id="cnpAfmRow" style="display:none">
                                        <div class="form-group">
                                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                                <input type="text" id="cnpBizAfm" class="field form-control" placeholder="{$LANG.cnp_afm_ph}" autocomplete="off" inputmode="numeric" maxlength="9" style="flex:1;min-width:180px">
                                                <button type="button" id="cnpAfmBtn" class="btn btn-prussian" style="white-space:nowrap">{$LANG.cnp_afm_fetch}</button>
                                            </div>
                                            <span class="field-help-text" style="display:block;margin-top:6px;opacity:.7">{$LANG.cnp_afm_help}</span>
                                            <div id="cnpAfmMsg" style="margin-top:6px;font-size:13px;display:none"></div>
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <input type="text" name="address1" id="inputAddress1" class="field form-control" placeholder="{$LANG.orderForm.streetAddress}" value="{$clientaddress1}"  {if !in_array('address1', $optionalFields)}required{/if}>
                                        </div>
                                    </div>
                                    {*<div class="col-sm-12">
                                        <div class="form-group">
                                            <input type="text" name="address2" id="inputAddress2" class="field" placeholder="{$LANG.orderForm.streetAddress2}" value="{$clientaddress2}">
                                        </div>
                                    </div>*}
                                </div>
                                
                                <div class="row">
                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <input type="text" name="city" id="inputCity" class="field form-control" placeholder="{$LANG.orderForm.city}" value="{$clientcity}"  {if !in_array('city', $optionalFields)}required{/if}>
                                        </div>
                                    </div>
                                    {*<div class="col-sm-5">
                                        <div class="form-group">
                                            <input type="text" name="state" id="state" class="field form-control" placeholder="{$LANG.orderForm.state}" value="{$clientstate}"  {if !in_array('state', $optionalFields)}required{/if}>
                                        </div>
                                    </div>*}
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <input type="text" name="postcode" id="inputPostcode" class="field form-control" placeholder="{$LANG.orderForm.postcode}" value="{$clientpostcode}" {if !in_array('postcode', $optionalFields)}required{/if}>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <select name="country" id="inputCountry" class="field form-control">
                                                {foreach $clientcountries as $countryCode => $countryName}
                                                <option value="{$countryCode}"{if (!$clientcountry && $countryCode eq $defaultCountry) || ($countryCode eq $clientcountry)} selected="selected"{/if}>
                                                    {$countryName}
                                                </option>
                                                {/foreach}
                                            </select>
                                        </div>
                                    </div>
                                    {if $showTaxIdField}
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <input type="text" name="tax_id" id="inputTaxId" class="field" placeholder="{$taxLabel} ({$LANG.orderForm.optional})" value="{$clientTaxId}">
                                        </div>
                                    </div>
                                    {/if}
                                </div>
                            </div>
                            {if $customfields || $currencies}

                            <div class="divider mb-15">
                                <span></span>
                                <span>{$LANG.orderadditionalrequiredinfo}{*<br><i><small>{lang key='orderForm.requiredField'}</small></i>*}</span>
                                <span></span>
                            </div>

                            <div class="row">
                                {if $customfields}
                                {foreach $customfields as $customfield}
                                {if $customfield.id != 153}
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        {assign var=cfkey value="cnp_cf_`$customfield.id`"}
                                        <label for="customfield{$customfield.id}">{if $LANG.$cfkey}{$LANG.$cfkey}{else}{$customfield.name}{/if} {$customfield.required}</label>
                                        <div class="control">
                                            {$customfield.input}
                                            {if $customfield.description}
                                            <span class="field-help-text">{$customfield.description}</span>
                                            {/if}
                                        </div>
                                    </div>
                                </div>
                                {/if}
                                {/foreach}
                                {/if}
                                {if $customfields && count($customfields)%2 > 0 }
                                <div class="clearfix"></div>
                                {/if}
                                {if $currencies}
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <select id="inputCurrency" name="currency" class="field form-control">
                                            {foreach from=$currencies item=curr}
                                            <option value="{$curr.id}"{if !$smarty.post.currency && $curr.default || $smarty.post.currency eq $curr.id } selected{/if}>{$curr.code}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                </div>
                                {/if}
                            </div>
                            {/if}
                            <div id="containerNewUserSecurity" {if $remote_auth_prelinked && !$securityquestions } class="hidden"{/if}>

                                <div class="divider mb-15">
                                    <span></span>
                                    <span>{$LANG.orderForm.accountSecurity}</span>
                                    <span></span>
                                </div>

                                <div id="containerPassword" class="row{if $remote_auth_prelinked && $securityquestions} hidden{/if}">
                                    <div id="passwdFeedback" style="display: none;" class="alert alert-info text-center col-sm-12"></div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <input type="password" name="password" id="inputNewPassword1" data-error-threshold="{$pwStrengthErrorThreshold}" data-warning-threshold="{$pwStrengthWarningThreshold}" class="field form-control" placeholder="{$LANG.clientareapassword}" autocomplete="off"{if $remote_auth_prelinked} value="{$password}"{/if}>
                                            <button data-toggle="tooltip" data-placement="left" title="" data-original-title="{$LANG.generatePassword.btnLabel}" type="button" class="generate-password" data-targetfields="inputNewPassword1,inputNewPassword2"><i class="icon-lock"></i></button>
                                            <div class="password-strength-meter">
                                                <div class="progress">
                                                    <div class="progress-bar progress-bar-success progress-bar-striped" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" id="passwordStrengthMeterBar">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <input type="password" name="password2" id="inputNewPassword2" class="field form-control" placeholder="{$LANG.clientareaconfirmpassword}" autocomplete="off"{if $remote_auth_prelinked} value="{$password}"{/if}>
                                        </div>
                                    </div>
                                    
                                </div>
                                {if $securityquestions}
                                <div class="row">
                                    <div class="form-group col-sm-12">
                                        <select name="securityqid" id="inputSecurityQId" class="field form-control">
                                            <option value="">{$LANG.clientareasecurityquestion}</option>
                                            {foreach $securityquestions as $question}
                                            <option value="{$question.id}"{if $question.id eq $securityqid} selected{/if}>
                                                {$question.question}
                                            </option>
                                            {/foreach}
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <input type="password" name="securityqans" id="inputSecurityQAns" class="field form-control" placeholder="{$LANG.clientareasecurityanswer}" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                                {/if}
                            </div>
                            {if $showMarketingEmailOptIn}
                            <div class="marketing-email-optin">
                                <h4>{lang key='emailMarketing.joinOurMailingList'}</h4>
                                {*<p>{$marketingEmailOptInMessage}</p>*}                                
                                <p>{$LANG.newslettermessage}</p>

                                <input type="checkbox" name="marketingoptin" value="1"{if $marketingEmailOptIn} checked{/if} class="no-icheck toggle-switch-success" data-size="small" data-on-text="{lang key='yes'}" data-off-text="{lang key='no'}">
                            </div>
                            {/if}
                            {include file="$template/includes/captcha.tpl"}
                            <br/>
                            {if $accepttos}
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="panel panel-danger tospanel">
                                        <div class="panel-heading">
                                            <h3 class="panel-title"><span class="fas fa-exclamation-triangle tosicon"></span> &nbsp; {$LANG.ordertos}</h3>
                                        </div>
                                        <div class="panel-body">
                                            <div class="col-md-12">
                                                <label class="checkbox">
                                                    <input type="checkbox" name="accepttos" class="accepttos">
                                                    {$LANG.ordertosagreement} <a href="{$tosurl}" target="_blank">{$LANG.ordertos}</a>.
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {/if}
                            <p align="center">

                                <input class="btn btn-medium btn-prussian w-100 {$captcha->getButtonClass($captchaForm)}" type="submit" value="{$LANG.clientregistertitle}"/>

                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    var CNP_AFM = new Object();
    CNP_AFM.web        = "{$WEB_ROOT}";
    CNP_AFM.biz        = "Επιχείρηση";
    CNP_AFM.t_fetch    = "{$LANG.cnp_afm_fetch}";
    CNP_AFM.t_fetching = "{$LANG.cnp_afm_fetching}";
    CNP_AFM.t_ok       = "{$LANG.cnp_afm_ok}";
    CNP_AFM.t_err      = "{$LANG.cnp_afm_err}";
    CNP_AFM.t_inactive = "{$LANG.cnp_afm_inactive}";
    CNP_AFM.t_notco    = "{$LANG.cnp_afm_notcompany}";
    </script>
    <script>{literal}
    (function(){
        function byId(i){ return document.getElementById(i); }
        var radios  = document.querySelectorAll('input[name="customfield[153]"]');
        var afmRow  = byId('cnpAfmRow');
        var company = byId('inputCompanyName');
        var afmOff  = byId('customfield1');   // ΑΦΜ (επίσημο custom field)
        var doyOff  = byId('customfield82');  // ΔΟΥ
        var bizAfm  = byId('cnpBizAfm');
        var btn     = byId('cnpAfmBtn');
        var msg     = byId('cnpAfmMsg');

        function isBiz(){
            var r = document.querySelector('input[name="customfield[153]"]:checked');
            return !!(r && r.value === CNP_AFM.biz);
        }
        function afmCol(){
            if(!afmOff) return null;
            var g = afmOff.closest('.form-group');
            return g ? (g.closest('.col-sm-6') || g) : null;
        }
        function applyMode(){
            var biz = isBiz();
            if(afmRow) afmRow.style.display = biz ? 'block' : 'none';
            if(company){
                company.placeholder = company.placeholder.replace(/\s*\*$/, '');
                if(biz) company.placeholder += ' *';
            }
            // Όταν επιχείρηση: το ΑΦΜ δηλώνεται μέσω του helper (κρύβουμε το διπλό επίσημο πεδίο)
            var col = afmCol();
            if(col) col.style.display = biz ? 'none' : '';
            if(biz && bizAfm && afmOff && afmOff.value && !bizAfm.value) bizAfm.value = afmOff.value;
        }
        function showMsg(t, color){
            if(!msg) return;
            msg.textContent = t; msg.style.color = color || '#8aa0b8';
            msg.style.display = t ? 'block' : 'none';
        }
        function doFetch(){
            if(!bizAfm) return;
            var afm = (bizAfm.value || '').replace(/\D/g, '');
            if(afm.length !== 9){ showMsg(CNP_AFM.t_err, '#e07a5f'); return; }
            btn.disabled = true; var old = btn.textContent; btn.textContent = CNP_AFM.t_fetching; showMsg('', '');
            fetch(CNP_AFM.web + '/projectmanagement/afm.php?afm=' + afm, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    btn.disabled = false; btn.textContent = old;
                    if(!d || !d.ok){ showMsg((d && d.error) || CNP_AFM.t_err, '#e07a5f'); return; }
                    var x = d.data || {};
                    if(company && x.name) company.value = x.name;
                    var a1 = byId('inputAddress1'); if(a1 && x.street) a1.value = x.street;
                    var ci = byId('inputCity');     if(ci && x.city) ci.value = x.city;
                    var pc = byId('inputPostcode');  if(pc && x.postcode) pc.value = x.postcode;
                    if(afmOff) afmOff.value = afm;
                    if(doyOff && x.doy) doyOff.value = x.doy;
                    var extra = '';
                    if(x.active === false) extra += ' ' + CNP_AFM.t_inactive;
                    if(x.is_company === false) extra += ' ' + CNP_AFM.t_notco;
                    showMsg(CNP_AFM.t_ok + extra, (x.active === false || x.is_company === false) ? '#e0a458' : '#3ec46d');
                })
                .catch(function(){ btn.disabled = false; btn.textContent = old; showMsg(CNP_AFM.t_err, '#e07a5f'); });
        }
        if(radios) radios.forEach(function(r){ r.addEventListener('change', applyMode); });
        if(bizAfm){
            bizAfm.addEventListener('input', function(){
                this.value = this.value.replace(/\D/g, '').slice(0, 9);
                if(afmOff) afmOff.value = this.value;
            });
            bizAfm.addEventListener('keydown', function(e){ if(e.key === 'Enter'){ e.preventDefault(); doFetch(); } });
        }
        if(btn) btn.addEventListener('click', doFetch);
        applyMode();
    })();
    {/literal}</script>
    {/if}