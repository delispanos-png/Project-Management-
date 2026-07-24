{include file="$template/includes/tablelist.tpl" tableName="TicketsList" filterColumn="2"}
<script type="text/javascript">
    jQuery(document).ready( function ()
    {
        var table = jQuery('#tableTicketsList').removeClass('hidden').DataTable();
        {if $orderby == 'did' || $orderby == 'dept'}
            table.order(0, '{$sort}');
        {elseif $orderby == 'subject' || $orderby == 'title'}
            table.order(1, '{$sort}');
        {elseif $orderby == 'status'}
            table.order(2, '{$sort}');
        {elseif $orderby == 'lastreply'}
            table.order(3, '{$sort}');
        {else}
            /* Triage default: most-urgent first (attention score on the status cell) */
            table.order(2, 'desc');
        {/if}
        table.draw();
        jQuery('#tableLoading').addClass('hidden');
    });
</script>
<div class="table-container clearfix">
    <table id="tableTicketsList" class="table table-list hidden">
        <thead>
            <tr>
                <th>{$LANG.supportticketsdepartment}</th>
                <th>{$LANG.supportticketssubject}</th>
                <th>{$LANG.supportticketsstatus}</th>
                <th>{$LANG.supportticketsticketlastupdated}</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$tickets item=ticket}
                <tr class="hz-ticket-row {if is_null($ticket.statusColor)}status-{$ticket.statusClass}{else}status-custom{/if}" onclick="window.location='viewticket.php?tid={$ticket.tid}&amp;c={$ticket.c}'">
                    <td>
                        {$ticket.department}
                    </td>
                    <td>
                        <a href="viewticket.php?tid={$ticket.tid}&amp;c={$ticket.c}" class="border-left">
                            <span class="ticket-number">#{$ticket.tid}</span>
                            <span class="ticket-subject{if $ticket.unread} unread{/if}">{$ticket.subject}</span>
                        </a>
                        {if isset($hzTicketMeta[$ticket.tid])}
                            {assign var=m value=$hzTicketMeta[$ticket.tid]}
                            <div class="hz-tk-flags">
                                {if $m.overdue}
                                    <span class="hz-fl hz-fl--overdue"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> {$LANG.hzTkOverdue}</span>
                                {/if}
                                {if $m.prio > 0}
                                    <span class="hz-fl hz-fl--contract"><i class="fas {if $m.prio == 2}fa-bolt{else}fa-headset{/if}" aria-hidden="true"></i> {if $m.prio == 2}{$LANG.scPrioCritical}{else}{$LANG.hzTkPriorityCust}{/if}</span>
                                {/if}
                                {if $m.waitingOnUs && !$m.closed}
                                    <span class="hz-fl hz-fl--need"><i class="fas fa-reply" aria-hidden="true"></i> {$LANG.hzTkNeedsReply}</span>
                                {/if}
                                <span class="hz-fl hz-fl--prio hz-prio--{$m.urgency|lower}" title="{$LANG.supportticketspriority}">
                                    <i class="fas fa-flag" aria-hidden="true"></i>
                                    {if $m.urgency eq 'High'}{$LANG.supportticketsticketurgencyhigh}
                                    {elseif $m.urgency eq 'Low'}{$LANG.supportticketsticketurgencylow}
                                    {else}{$LANG.supportticketsticketurgencymedium}{/if}
                                </span>
                                {if $m.vip}
                                    <span class="hz-fl hz-fl--vip" title="{$LANG.hzTkVipHint}"><i class="fas fa-star" aria-hidden="true"></i> VIP</span>
                                {/if}
                                {if $m.waitingOnUs && !$m.closed}
                                    <span class="hz-fl hz-fl--wait"><i class="fas fa-clock" aria-hidden="true"></i> {$m.waitLabel}</span>
                                {/if}
                            </div>
                        {/if}
                    </td>
                    <td{if isset($hzTicketMeta[$ticket.tid])} data-order="{$hzTicketMeta[$ticket.tid].score}"{/if}>
                        <span class="label status {if is_null($ticket.statusColor)}status-{$ticket.statusClass}"{else}status-custom" style="border-color: {$ticket.statusColor}; color: {$ticket.statusColor}"{/if}>
                            {$ticket.status|strip_tags}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="hidden">{$ticket.normalisedLastReply}</span>
                        {$ticket.lastreply}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
    <div class="text-center" id="tableLoading">
        <p><i class="fas fa-spinner fa-spin"></i> {$LANG.loading}</p>
    </div>
</div>
