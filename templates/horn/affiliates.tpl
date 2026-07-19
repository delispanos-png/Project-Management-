{if $inactive}
    {include file="$template/includes/alert.tpl" type="danger" msg=$LANG.affiliatesdisabled textcenter=true}
{else}
    {include file="$template/includes/flashmessage.tpl"}
    <div class="row">
        <div class="col-sm-4">
            <div class="affiliate-stat affiliate-stat-green alert-warning">
                <i class="ico-globel bg-prussian-extralight p-5 br-50"></i>
                <span>{$visitors} <small class="c-grey">{$LANG.affiliatesclicks}</small</span>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="affiliate-stat affiliate-stat-green alert-info">
                <i class="ico-user-plus bg-prussian-extralight p-5 br-50"></i>
                <span>{$signups} <small class="c-grey">{$LANG.affiliatessignups}</small></span>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="affiliate-stat affiliate-stat-green alert-success">
                <i class="ico-pie-chart bg-prussian-extralight p-5 br-50"></i>
                <span>{$conversionrate}% <small class="c-grey">{$LANG.affiliatesconversionrate}</small></span>
            </div>
        </div>
    </div>

    <div class="affiliate-referral-link">

        <h5>{$LANG.affiliatesreferallink}</h5>
        <span>{$referrallink}</span>

    </div>

    <div class="row">
        <div class="col-md-12 table-responsive">
            <table class="table table-striped table-rounded">
                <tr>
                    <td>{$LANG.affiliatescommissionspending}:</td>
                    <td><strong>{$pendingcommissions}</strong></td>
                </tr>
                <tr>
                    <td>{$LANG.affiliatescommissionsavailable}:</td>
                    <td><strong>{$balance}</strong></td>
                </tr>
                <tr>
                    <td>{$LANG.affiliateswithdrawn}:</td>
                    <td><strong>{$withdrawn}</strong></td>
                </tr>
            </table>
        </div>
    </div>

    {if $withdrawrequestsent}
        <div class="alert alert-success">
            <p>{$LANG.affiliateswithdrawalrequestsuccessful}</p>
        </div>
    {else}
        <p>
            <a href="{$smarty.server.PHP_SELF}?action=withdrawrequest" class="btn btn-lg btn-danger{if !$withdrawlevel} disabled" disabled="disabled{/if}">
                <i class="fas fa-university"></i> {$LANG.affiliatesrequestwithdrawal}
            </a>
        </p>
        {if !$withdrawlevel}
            <p class="text-muted mb-50">{lang key="affiliateWithdrawalSummary" amountForWithdrawal=$affiliatePayoutMinimum}</p>
        {/if}
    {/if}

    {include file="$template/includes/subheader.tpl" title=$LANG.affiliatesreferals}

    {include file="$template/includes/tablelist.tpl" tableName="AffiliatesList"}
    <script type="text/javascript">
        jQuery(document).ready( function ()
        {
            var table = jQuery('#tableAffiliatesList').removeClass('hidden').DataTable();
            {if $orderby == 'regdate'}
                table.order(0, '{$sort}');
            {elseif $orderby == 'product'}
                table.order(1, '{$sort}');
            {elseif $orderby == 'amount'}
                table.order(2, '{$sort}');
            {elseif $orderby == 'status'}
                table.order(4, '{$sort}');
            {/if}
            table.draw();
            jQuery('#tableLoading').addClass('hidden');
        });
    </script>
    <div class="table-container clearfix">
        <table id="tableAffiliatesList" class="table table-list hidden">
            <thead>
                <tr>
                    <th>{$LANG.affiliatessignupdate}</th>
                    <th>{$LANG.orderproduct}</th>
                    <th>{$LANG.affiliatesamount}</th>
                    <th>{$LANG.affiliatescommission}</th>
                    <th>{$LANG.affiliatesstatus}</th>
                </tr>
            </thead>
            <tbody>
            {foreach from=$referrals item=referral}
                <tr class="text-center">
                    <td><span class="hidden">{$referral.datets}</span>{$referral.date}</td>
                    <td>{$referral.service}</td>
                    <td data-order="{$referral.amountnum}">{$referral.amountdesc}</td>
                    <td data-order="{$referral.commissionnum}">{$referral.commission}</td>
                    <td><span class='label status status-{$referral.rawstatus|strtolower}'>{$referral.status}</span></td>
                </tr>
            {/foreach}
            </tbody>
        </table>
        <div class="text-center" id="tableLoading">
            <p><i class="fas fa-spinner fa-spin"></i> {$LANG.loading}</p>
        </div>
    </div>

    {if $affiliatelinkscode}
        {include file="$template/includes/subheader.tpl" title=$LANG.affiliateslinktous}
        <div class="margin-bottom text-center">
            {$affiliatelinkscode}
        </div>
    {/if}

{/if}
