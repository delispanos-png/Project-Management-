{if $registrarcustombuttonresult=="success"}
{include file="$template/includes/alert.tpl" type="success" msg=$LANG.moduleactionsuccess textcenter=true}
{elseif $registrarcustombuttonresult}
{include file="$template/includes/alert.tpl" type="error" msg=$LANG.moduleactionfailed textcenter=true}
{/if}
{if $unpaidInvoice}
<div class="alert alert-{if $unpaidInvoiceOverdue}danger{else}warning{/if}" id="alert{if $unpaidInvoiceOverdue}Overdue{else}Unpaid{/if}Invoice">
    {$unpaidInvoiceMessage}
</div>
{/if}
<div class="tab-content margin-bottom">
    <div class="tab-pane fade in active" id="tabOverview">
        <div class="domain-previw-box">
            {if $alerts}
            {foreach $alerts as $alert}
            {include file="$template/includes/alert.tpl" type=$alert.type msg="<strong>{$alert.title}</strong><br>{$alert.description}" textcenter=true}
            {/foreach}
            {/if}
            {if $systemStatus != 'Active'}
            <div class="alert alert-warning text-center" role="alert">
                {$LANG.domainCannotBeManagedUnlessActive}
            </div>
            {/if}
            {if $lockstatus eq "unlocked"}
            {capture name="domainUnlockedMsg"}<strong>{$LANG.domaincurrentlyunlocked}</strong><br />{$LANG.domaincurrentlyunlockedexp}{/capture}
            {include file="$template/includes/alert.tpl" type="error" msg=$smarty.capture.domainUnlockedMsg}
            {/if}
            <div class="row">

                <div class="col-md-3">
                    <div class="right-overview-box-domain">
                        {if $unpaidInvoice}
                        <a href="viewinvoice.php?id={$unpaidInvoice}" class="badge feat bg-prussian" data-toggle="tooltip" data-placement="left" title="" data-original-title="{lang key='payInvoice'}"> <i class="ico-dollar-sign f-16"></i></a>
                        {/if}
                        <span class="domain-main-info"><strong>{$LANG.clientareahostingdomain}</strong><a href="http://{$domain}" target="_blank">{$domain} <i>{$status}</i></a></span>
                        {if $sslStatus->isActive()}<span class="expiry-date-ssl"><h4><strong>{$LANG.sslState.expiryDate}</strong></h4>{$sslStatus->expiryDate->toClientDateFormat()}</span>{/if}
                    </div>
                </div>

                {if $sslStatus->isActive() || $sslStatus->needsResync()}
                    <div class="col-md-6">
                        <div class="overview-box">
                            <span><strong>{$LANG.firstpaymentamount}:</strong><i>{$firstpaymentamount}</i></span>
                            <span><strong>{$LANG.clientareahostingregdate}:</strong><i>{$registrationdate}</i></span>
                            <span><strong>{$LANG.recurringamount}:</strong><i>{$recurringamount} {$LANG.every} {$registrationperiod} {$LANG.orderyears}</i></span>
                            <span><strong>{$LANG.clientareahostingnextduedate}:</strong><i>{$nextduedate}</i></span>
                            <span><strong>{$LANG.orderpaymentmethod}:</strong><i> {$paymentmethod}</i></span>
                            <span><strong>{$LANG.clientareastatus}:</strong><i class="{$status}">{$status}</i></span>
                        </div>
                    </div>
                {/if}

                {if $sslStatus}
                    <div class="col-md-3">
                        <div class="{if $sslStatus->isInactive()}ssl-inactive{else}ssl-active{/if}">
                            {$sslStatus->getStatusDisplayLabel()}

                            <h4><strong>{$LANG.sslState.sslStatus}</strong></h4> <img src="{$sslStatus->getImagePath()}" width="16" data-type="domain" data-domain="{$domain}" data-showlabel="1" class="{$sslStatus->getClass()}"/>
                            <span id="statusDisplayLabel">
                                {if !$sslStatus->needsResync()}
                                    {$sslStatus->getStatusDisplayLabel()}
                                {else}
                                    {$LANG.loading}
                                {/if}
                            </span>

                            {if $sslStatus->isActive() || $sslStatus->needsResync()}
                            <span><strong>{$LANG.sslState.startDate}</strong></span>
                            <span id="ssl-startdate">
                                {if !$sslStatus->needsResync() || $sslStatus->startDate}
                                    {$sslStatus->startDate->toClientDateFormat()}
                                {else}
                                    {$LANG.loading}
                                {/if}
                            </span>
                            <h4><strong>{$LANG.sslState.startDate}</strong></h4>
                            <span id="ssl-startdate">
                                {if !$sslStatus->needsResync() || $sslStatus->startDate}
                                    {$sslStatus->startDate->toClientDateFormat()}
                                {else}
                                    {$LANG.loading}
                                {/if}
                            </span>
                            {/if}
                            {if $sslStatus->isActive() || $sslStatus->needsResync()}
                            <span><strong>{$LANG.sslState.issuerName}</strong></span>
                            <span id="ssl-issuer">
                                {if !$sslStatus->needsResync() || $sslStatus->issuerName}
                                    {$sslStatus->issuerName}
                                {else}
                                    {$LANG.loading}
                                {/if}
                            </span>
                            {/if}
                        </div>
                    </div>
                {/if}



            </div>
            
            {if $registrarclientarea}
            <div class="moduleoutput">
                {$registrarclientarea|replace:'modulebutton':'btn'}
            </div>
            {/if}
            {foreach $hookOutput as $output}
            <div>
                {$output}
            </div>
            {/foreach}
        </div>
        {if $canDomainBeManaged
        and (
        $managementoptions.nameservers or
        $managementoptions.contacts or
        $managementoptions.locking or
        $renew)}
        {* No reason to show this section if nothing can be done here! *}
        <div class="domains-dotoday">
            <h4>{$LANG.doToday}</h4>
            <ul>
                {if $systemStatus == 'Active' && $managementoptions.nameservers}
                <li>
                    <a class="tabControlLink" data-toggle="tab" href="#tabNameservers">
                        {$LANG.changeDomainNS}
                    </a>
                </li>
                {/if}
                {if $systemStatus == 'Active' && $managementoptions.contacts}
                <li>
                    <a href="clientarea.php?action=domaincontacts&domainid={$domainid}">
                        {$LANG.updateWhoisContact}
                    </a>
                </li>
                {/if}
                {if $systemStatus == 'Active' && $managementoptions.locking}
                <li>
                    <a class="tabControlLink" data-toggle="tab" href="#tabReglock">
                        {$LANG.changeRegLock}
                    </a>
                </li>
                {/if}
                {if $renew}
                <li>
                    <a href="{routePath('domain-renewal', $domain)}">
                        {$LANG.renewYourDomain}
                    </a>
                </li>
                {/if}
            </ul>
        </div>
        {/if}
    </div>
    <div class="tab-pane fade" id="tabAutorenew">
        <h3>{$LANG.domainsautorenew}</h3>
        {if $changeAutoRenewStatusSuccessful}
        {include file="$template/includes/alert.tpl" type="success" msg=$LANG.changessavedsuccessfully textcenter=true}
        {/if}
        {include file="$template/includes/alert.tpl" type="info" msg=$LANG.domainrenewexp}
        <br />
        <h2 class="text-center">{$LANG.domainautorenewstatus}: <span class="label label-{if $autorenew}success{else}danger{/if}">{if $autorenew}{$LANG.domainsautorenewenabled}{else}{$LANG.domainsautorenewdisabled}{/if}</span></h2>
        <br />
        <br />
        <form method="post" action="{$smarty.server.PHP_SELF}?action=domaindetails#tabAutorenew">
            <input type="hidden" name="id" value="{$domainid}">
            <input type="hidden" name="sub" value="autorenew" />
            {if $autorenew}
            <input type="hidden" name="autorenew" value="disable">
            <p class="text-center">
                <input type="submit" class="btn btn-lg btn-danger" value="{$LANG.domainsautorenewdisable}" />
            </p>
            {else}
            <input type="hidden" name="autorenew" value="enable">
            <p class="text-center">
                <input type="submit" class="btn btn-lg btn-success" value="{$LANG.domainsautorenewenable}" />
            </p>
            {/if}
        </form>
    </div>
    <div class="tab-pane fade" id="tabNameservers">
        <h3>{$LANG.domainnameservers}</h3>
        {if $nameservererror}
        {include file="$template/includes/alert.tpl" type="error" msg=$nameservererror textcenter=true}
        {/if}
        {if $subaction eq "savens"}
        {if $updatesuccess}
        {include file="$template/includes/alert.tpl" type="success" msg=$LANG.changessavedsuccessfully textcenter=true}
        {elseif $error}
        {include file="$template/includes/alert.tpl" type="error" msg=$error textcenter=true}
        {/if}
        {/if}
        {include file="$template/includes/alert.tpl" type="info" msg=$LANG.domainnsexp}
        <form class="form-horizontal" role="form" method="post" action="{$smarty.server.PHP_SELF}?action=domaindetails#tabNameservers">
            <input type="hidden" name="id" value="{$domainid}" />
            <input type="hidden" name="sub" value="savens" />
            <div class="radio">
                <label>
                    <input type="radio" name="nschoice" value="default" onclick="disableFields('domnsinputs',true)"{if $defaultns} checked{/if} /> {$LANG.nschoicedefault}
                </label>
            </div>
            <div class="radio">
                <label>
                    <input type="radio" name="nschoice" value="custom" onclick="disableFields('domnsinputs',false)"{if !$defaultns} checked{/if} /> {$LANG.nschoicecustom}
                </label>
            </div>
            <br />
            {for $num=1 to 5}
            <div class="form-group">
                <label for="inputNs{$num}" class="col-sm-4 control-label">{$LANG.clientareanameserver} {$num}</label>
                <div class="col-sm-7">
                    <input type="text" name="ns{$num}" class="form-control domnsinputs" id="inputNs{$num}" value="{$nameservers[$num].value}" />
                </div>
            </div>
            {/for}
            <p class="text-center">
                <input type="submit" class="btn btn-primary" value="{$LANG.changenameservers}" />
            </p>
        </form>
    </div>
    <div class="tab-pane fade" id="tabReglock">
        <h3>{$LANG.domainregistrarlock}</h3>
        {if $subaction eq "savereglock"}
        {if $updatesuccess}
        {include file="$template/includes/alert.tpl" type="success" msg=$LANG.changessavedsuccessfully textcenter=true}
        {elseif $error}
        {include file="$template/includes/alert.tpl" type="error" msg=$error textcenter=true}
        {/if}
        {/if}
        {include file="$template/includes/alert.tpl" type="info" msg=$LANG.domainlockingexp}
        <br />
        <h2 class="text-center">{$LANG.domainreglockstatus}: <span class="label label-{if $lockstatus == "locked"}success{else}danger{/if}">{if $lockstatus == "locked"}{$LANG.domainsautorenewenabled}{else}{$LANG.domainsautorenewdisabled}{/if}</span></h2>
        <br />
        <br />
        <form method="post" action="{$smarty.server.PHP_SELF}?action=domaindetails#tabReglock">
            <input type="hidden" name="id" value="{$domainid}">
            <input type="hidden" name="sub" value="savereglock" />
            {if $lockstatus=="locked"}
            <p class="text-center">
                <input type="submit" class="btn btn-lg btn-danger" value="{$LANG.domainreglockdisable}" />
            </p>
            {else}
            <p class="text-center">
                <input type="submit" class="btn btn-lg btn-success" name="reglock" value="{$LANG.domainreglockenable}" />
            </p>
            {/if}
        </form>
    </div>
    <div class="tab-pane fade" id="tabRelease">
        <h3>{$LANG.domainrelease}</h3>

        {if $releaseDomainSuccessful}
            {include file="$template/includes/alert.tpl" type="success" msg="{lang key='changessavedsuccessfully'}" textcenter="true"}
        {elseif !empty($error)}
            {include file="$template/includes/alert.tpl" type="error" msg="$error" textcenter="true"}
        {/if}
        
        {include file="$template/includes/alert.tpl" type="info" msg=$LANG.domainreleasedescription}
        <form class="form-horizontal" role="form" method="post" action="{$smarty.server.PHP_SELF}?action=domaindetails#tabRelease">
            <input type="hidden" name="sub" value="releasedomain">
            <input type="hidden" name="id" value="{$domainid}">
            <div class="form-group">
                <label for="inputReleaseTag" class="col-xs-4 control-label">{$LANG.domainreleasetag}</label>
                <div class="col-xs-6 col-sm-5">
                    <input type="text" class="form-control" id="inputReleaseTag" name="transtag" />
                </div>
            </div>
            <p class="text-center">
                <input type="submit" value="{$LANG.domainrelease}" class="btn btn-primary" />
            </p>
        </form>
    </div>
    <div class="tab-pane fade" id="tabAddons">
        <h3>{$LANG.domainaddons}</h3>
        <p>
            {$LANG.domainaddonsinfo}
        </p>
        <div class="row">
            {if $addons.idprotection}
            <div class="col-md-4">
                <div class="addon-domains-box">
                    <i class="fas fa-shield-alt fa-3x"></i>
                    <strong>{$LANG.domainidprotection}</strong>
                    {$LANG.domainaddonsidprotectioninfo}
                    <form action="clientarea.php?action=domainaddons" method="post">
                        <input type="hidden" name="id" value="{$domainid}"/>
                        {if $addonstatus.idprotection}
                        <input type="hidden" name="disable" value="idprotect"/>
                        <input type="submit" value="{$LANG.disable}" class="btn btn-danger"/>
                        {else}
                        <input type="hidden" name="buy" value="idprotect"/>
                        <input type="submit" value="{$LANG.domainaddonsbuynow} {$addonspricing.idprotection}" class="btn btn-success"/>
                        {/if}
                    </form>
                </div>
            </div>
            {/if}
            {if $addons.dnsmanagement}
            <div class="col-md-4">
                <div class="addon-domains-box">
                    <i class="fas fa-cloud fa-3x"></i>
                    <strong>{$LANG.domainaddonsdnsmanagement}</strong>
                    {$LANG.domainaddonsdnsmanagementinfo}
                    <form action="clientarea.php?action=domainaddons" method="post">
                        <input type="hidden" name="id" value="{$domainid}"/>
                        {if $addonstatus.dnsmanagement}
                        <input type="hidden" name="disable" value="dnsmanagement"/>
                        <a class="btn btn-success" href="clientarea.php?action=domaindns&domainid={$domainid}">{$LANG.manage}</a> <input type="submit" value="{$LANG.disable}" class="btn btn-danger"/>
                        {else}
                        <input type="hidden" name="buy" value="dnsmanagement"/>
                        <input type="submit" value="{$LANG.domainaddonsbuynow} {$addonspricing.dnsmanagement}" class="btn btn-success"/>
                        {/if}
                    </form>
                </div>
            </div>
            {/if}
            {if $addons.emailforwarding}
            <div class="col-md-4">
                <div class="addon-domains-box">
                    <i class="fas fa-envelope fa-3x">&nbsp;</i>
                    <strong>{$LANG.domainemailforwarding}</strong>
                    {$LANG.domainaddonsemailforwardinginfo}
                    <form action="clientarea.php?action=domainaddons" method="post">
                        <input type="hidden" name="id" value="{$domainid}"/>
                        {if $addonstatus.emailforwarding}
                        <input type="hidden" name="disable" value="emailfwd"/>
                        <a class="btn btn-success" href="clientarea.php?action=domainemailforwarding&domainid={$domainid}">{$LANG.manage}</a> <input type="submit" value="{$LANG.disable}" class="btn btn-danger"/>
                        {else}
                        <input type="hidden" name="buy" value="emailfwd"/>
                        <input type="submit" value="{$LANG.domainaddonsbuynow} {$addonspricing.emailforwarding}" class="btn btn-success"/>
                        {/if}
                    </form>
                </div>
            </div>
            {/if}
        </div>
    </div>
</div>