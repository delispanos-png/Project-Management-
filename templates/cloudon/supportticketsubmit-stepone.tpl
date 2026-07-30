<p>{$LANG.supportticketsheader}</p>
<br />
<div class="row hz-deptgrid">
    {foreach from=$departments key=num item=department}
        <div class="col-md-4 margin-bottom">
            <a class="hz-dept-card" href="{$smarty.server.PHP_SELF}?step=2&amp;deptid={$department.id}">
                <span class="hz-dept-ico" aria-hidden="true"><i class="fas fa-headset"></i></span>
                <span class="hz-dept-body">
                    <span class="hz-dept-name">{$department.name}</span>
                    {if $department.description}<span class="hz-dept-desc">{$department.description}</span>{/if}
                </span>
                <span class="hz-dept-arrow" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
            </a>
        </div>
    {foreachelse}
        {include file="$template/includes/alert.tpl" type="info" msg=$LANG.nosupportdepartments textcenter=true}
    {/foreach}
</div>
