<p>{$LANG.supportticketsheader}</p>
<br />
<div class="row">
    <div class="col-md-12">
        <div class="row">
            {foreach from=$departments key=num item=department}
                <div class="col-md-4 margin-bottom">
				    <div class="departments-ticket-show">
                        <a href="{$smarty.server.PHP_SELF}?step=2&amp;deptid={$department.id}">{$department.name}</a>
                        {if $department.description}
                            <p>{$department.description}</p>
                        {/if}
					   <span class="departments-ticket-show-icon"></span>
				    </div>
                </div>
            {foreachelse}
                {include file="$template/includes/alert.tpl" type="info" msg=$LANG.nosupportdepartments textcenter=true}
            {/foreach}
        </div>
    </div>
</div>
