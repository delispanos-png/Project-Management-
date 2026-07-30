{if $invalidTicketId}
    {include file="$template/includes/alert.tpl" type="danger" title=$LANG.thereisaproblem msg=$LANG.supportticketinvalid textcenter=true}
{else}
    {if $closedticket}
        {include file="$template/includes/alert.tpl" type="warning" msg=$LANG.supportticketclosedmsg textcenter=true}
    {/if}
    {if $errormessage}
        {include file="$template/includes/alert.tpl" type="error" errorshtml=$errormessage}
    {/if}
{/if}
{if !$invalidTicketId}
    {* ===== Always-visible ticket action bar — key actions no longer buried in the "Μενού" dropdown ===== *}
    <div class="co-ticket-actionbar hidden-print" role="group" aria-label="{$LANG.supportticketsviewticket} #{$tid}">
        <div class="co-tab-context">
            <span class="co-tab-id">#{$tid}</span>
            <span class="co-tab-subject">{$subject}</span>
        </div>
        <div class="co-tab-actions">
            <button type="button" class="btn btn-success co-tab-btn" onclick="cloudonTicketReply();return false;">
                <i class="fas fa-reply" aria-hidden="true"></i> {$LANG.supportticketsreply}
            </button>
            {if $showCloseButton}
                {if $closedticket}
                    <button type="button" class="btn btn-default co-tab-btn" disabled="disabled">
                        <i class="fas fa-lock" aria-hidden="true"></i> {$LANG.supportticketsstatusclosed}
                    </button>
                {else}
                    <button type="button" class="btn btn-danger co-tab-btn" data-toggle="modal" data-target="#coCloseModal">
                        <i class="fas fa-times-circle" aria-hidden="true"></i> {$LANG.supportticketsclose}
                    </button>
                {/if}
            {/if}
            <a class="btn btn-default co-tab-btn" href="{$WEB_ROOT}/supporttickets.php">
                <i class="fas fa-inbox" aria-hidden="true"></i> {$LANG.clientareanavsupporttickets}
            </a>
        </div>
    </div>

    {if $showCloseButton and !$closedticket}
    {* Themed close-ticket confirmation (replaces the native browser confirm popup) *}
    <div class="modal fade co-close-modal" id="coCloseModal" tabindex="-1" role="dialog" aria-labelledby="coCloseModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{$LANG.cancel}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="coCloseModalLabel"><i class="fas fa-times-circle" aria-hidden="true"></i> &nbsp;{$LANG.supportticketsclose}</h4>
                </div>
                <div class="modal-body">
                    <p style="margin:0 0 6px"><strong>#{$tid}</strong> — {$subject}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        <i class="fas fa-times" aria-hidden="true"></i> {$LANG.cancel}
                    </button>
                    <a class="btn btn-danger co-modal-confirm" href="?tid={$tid}&amp;c={$c}&amp;closeticket=true&amp;token={$token}">
                        <i class="fas fa-times-circle" aria-hidden="true"></i> {$LANG.supportticketsclose}
                    </a>
                </div>
            </div>
        </div>
    </div>
    {/if}
    <style>{literal}
    .co-ticket-actionbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;
        padding:14px 16px;margin:0 0 18px;border-radius:14px;
        background:rgba(127,127,127,.09);border:1px solid rgba(127,127,127,.24);}
    .co-tab-context{display:flex;flex-direction:column;min-width:0;gap:2px;}
    .co-tab-id{font-weight:700;font-size:13px;opacity:.7;letter-spacing:.02em;}
    .co-tab-subject{font-weight:600;font-size:16px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:46vw;}
    .co-tab-actions{display:flex;flex-wrap:wrap;gap:10px;}
    .co-tab-btn{min-height:44px;display:inline-flex;align-items:center;gap:8px;font-weight:600;padding:0 18px;border-radius:10px;}
    .co-tab-btn i{font-size:.95em;}
    /* WCAG AA (4.5:1 on white): darken success/danger. Extra specificity + !important
       to beat the theme's ".hz-portal .btn-success{...!important}" R8 override. */
    .co-ticket-actionbar .co-tab-btn.btn-success{background-color:#157347!important;border-color:#157347!important;color:#fff!important;}
    .co-ticket-actionbar .co-tab-btn.btn-success:hover,.co-ticket-actionbar .co-tab-btn.btn-success:focus{background-color:#125e39!important;border-color:#125e39!important;color:#fff!important;}
    .co-ticket-actionbar .co-tab-btn.btn-danger{background-color:#c0392b!important;border-color:#c0392b!important;color:#fff!important;}
    .co-ticket-actionbar .co-tab-btn.btn-danger:hover,.co-ticket-actionbar .co-tab-btn.btn-danger:focus{background-color:#a12f23!important;border-color:#a12f23!important;color:#fff!important;}
    .co-close-modal .co-modal-confirm.btn-danger{background-color:#c0392b!important;border-color:#c0392b!important;color:#fff!important;}
    .co-close-modal .co-modal-confirm.btn-danger:hover,.co-close-modal .co-modal-confirm.btn-danger:focus{background-color:#a12f23!important;border-color:#a12f23!important;color:#fff!important;}
    /* modal-content is white (Bootstrap default) but the theme forces white text → make body text dark */
    .co-close-modal .modal-body{color:#212529!important;padding:18px;}
    .co-close-modal .modal-body strong{color:#111!important;}
    @media (max-width:600px){
        .co-ticket-actionbar{flex-direction:column;align-items:stretch;}
        .co-tab-subject{max-width:100%;white-space:normal;}
        .co-tab-actions{width:100%;}
        .co-tab-btn{flex:1 1 auto;justify-content:center;}
    }
    {/literal}</style>
    <script>
    {literal}
    function cloudonTicketReply(){
        var h = jQuery('#ticketReply');
        if (h.length){
            if (h.closest('.panel').hasClass('panel-collapsed')){ h.click(); }
            jQuery('html,body').animate({scrollTop: h.offset().top - 16}, 350);
            setTimeout(function(){ jQuery('#inputMessage').focus(); }, 420);
        }
    }
    {/literal}
    </script>

    <div class="panel panel-info panel-collapsable{if !$postingReply} panel-collapsed{/if} hidden-print">
        <div class="panel-heading" id="ticketReply">
            <div class="collapse-icon pull-right">
                <i class="fas fa-{if !$postingreply}plus{else}minus{/if}"></i>
            </div>
            <h3 class="panel-title">
                <i class="fas fa-pencil-alt"></i> &nbsp; {$LANG.supportticketsreply}
            </h3>
        </div>
        <div class="panel-body{if !$postingReply} panel-body-collapsed{/if}">
            <form method="post" action="{$smarty.server.PHP_SELF}?tid={$tid}&amp;c={$c}&amp;postreply=true" enctype="multipart/form-data" role="form" id="frmReply">

                <div class="row">
                    <div class="form-group col-sm-4">
                        <label for="inputName">{$LANG.supportticketsclientname}</label>
                        <input class="form-control" type="text" name="replyname" id="inputName" value="{$replyname}"{if $loggedin} disabled="disabled"{/if}>
                    </div>
                    <div class="form-group col-sm-5">
                        <label for="inputEmail">{$LANG.supportticketsclientemail}</label>
                        <input class="form-control" type="text" name="replyemail" id="inputEmail" value="{$replyemail}"{if $loggedin} disabled="disabled"{/if}>
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputMessage">{$LANG.contactmessage}</label>
                    <textarea name="replymessage" id="inputMessage" rows="12" class="form-control markdown-editor" data-auto-save-name="ctr{$tid}">{$replymessage}</textarea>
                </div>

                <div class="row form-group">
                    <div class="col-sm-12">
                        <label for="inputAttachments">{$LANG.supportticketsticketattachments}</label>
                    </div>
                    <div class="col-sm-9">
                        <input type="file" name="attachments[]" id="inputAttachments" class="form-control" />
                        <div id="fileUploadsContainer"></div>
                    </div>
                    <div class="col-sm-3">
                        <button type="button" class="btn btn-default btn-block" onclick="extraTicketAttachment()">
                            <i class="fas fa-plus"></i> {$LANG.addmore}
                        </button>
                    </div>
                    <div class="col-xs-12 ticket-attachments-message text-muted">
                        {$LANG.supportticketsallowedextensions}: {$allowedfiletypes} ({lang key="maxFileSize" fileSize="$uploadMaxFileSize"})
                    </div>
                </div>

                <div class="form-group text-center">
                    <input class="btn btn-primary" type="submit" name="save" value="{$LANG.supportticketsticketsubmit}" />
                    <input class="btn btn-default" type="reset" value="{$LANG.cancel}" onclick="jQuery('#ticketReply').click()" />
                </div>

            </form>
        </div>
    </div>
    
    <div class="panel panel-info visible-print-block">
        <div class="panel-heading">
            <h3 class="panel-title">
                {$LANG.ticketinfo}
            </h3>
        </div>
        <div class="panel-body container-fluid">
            <div class="row">
                <div class="col-md-2 col-xs-6">
                    <b>{$LANG.supportticketsticketid}</b><br />{$tid}
                </div>
                <div class="col-md-4 col-xs-6">
                    <b>{$LANG.supportticketsticketsubject}</b><br />{$subject}
                </div>
                <div class="col-md-2 col-xs-6">
                    <b>{$LANG.supportticketspriority}</b><br />{$urgency}
                </div>
                <div class="col-md-4 col-xs-6">
                    <b>{$LANG.supportticketsdepartment}</b><br />{$department}
                </div>
            </div>
        </div>
    </div>

    {foreach $descreplies as $reply}
        <div class="ticket-reply markdown-content{if $reply.admin} staff{/if}">
            <div class="user">
                <img src="{$WEB_ROOT}/templates/{$template}assets/img/gravatar.jpg" class="gravatar" alt="User-Profile-Image">
                <span class="name">
                {$reply.requestor.name}
                    <span class="label requestor-type-{$reply.requestor.type_normalised}">
                        {if $reply.requestor.type_normalised eq 'operator'}
                            {lang key='support.requestor.operator'}
                        {elseif $reply.requestor.type_normalised eq 'owner'}
                            {lang key='support.requestor.owner'}
                        {elseif $reply.requestor.type_normalised eq 'authorizeduser'}
                            {lang key='support.requestor.authorizeduser'}
                        {elseif $reply.requestor.type_normalised eq 'registereduser'}
                            {lang key='support.requestor.registereduser'}
                        {elseif $reply.requestor.type_normalised eq 'subaccount'}
                            {lang key='support.requestor.subaccount'}
                        {elseif $reply.requestor.type_normalised eq 'guest'}
                            {lang key='support.requestor.guest'}
                        {/if}
                    </span>
                </span>
                <span class="type">
                    {if $reply.admin}
                        {$LANG.supportticketsstaff}
                    {$reply.requestor.email}
                    {/if}
                </span>
                <span class="date">{$reply.date}</span>
            </div>
            <div class="message">
                {$reply.message}
                {if $reply.ipaddress}
                    <hr>
                    {lang key='support.ipAddress'}: {$reply.ipaddress}
                {/if}
                {if $reply.id && $reply.admin && $ratingenabled}
                    <div class="clearfix">
                        {if $reply.rating}
                            <div class="rating-done">
                                {for $rating=1 to 5}
                                    <span class="star{if (5 - $reply.rating) < $rating} active{/if}"></span>
                                {/for}
                                <div class="rated">{$LANG.ticketreatinggiven}</div>
                            </div>
                        {else}
                            <div class="rating" ticketid="{$tid}" ticketkey="{$c}" ticketreplyid="{$reply.id}">
                                <span class="star" rate="5"></span>
                                <span class="star" rate="4"></span>
                                <span class="star" rate="3"></span>
                                <span class="star" rate="2"></span>
                                <span class="star" rate="1"></span>
                            </div>
                        {/if}
                    </div>
                {/if}
                
                {if $reply.attachments}
                <div class="attachments">
                    <strong>{$LANG.supportticketsticketattachments} ({$reply.attachments|count})</strong>
                    {if $reply.attachments_removed}({lang key='support.attachmentsRemoved'}){/if}
                    <ul>
                        {foreach $reply.attachments as $num => $attachment}
                        {if $reply.attachments_removed}
                                <li>
                                    <i class="far fa-file-minus"></i>
                                    {$attachment}
                                </li>
                            {else}
                                <li>
                                    <i class="far fa-file"></i>
                                    <a href="dl.php?type={if $reply.id}ar&id={$reply.id}{else}a&id={$id}{/if}&i={$num}">
                                        {$attachment}
                                    </a>
                                </li>
                            {/if}
                        {/foreach}
                    </ul>
                </div>
            {/if}
            </div>
            
        </div>
    {/foreach}

{/if}
