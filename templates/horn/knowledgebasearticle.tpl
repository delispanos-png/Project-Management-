<style>{literal}
    /* Article on the dark theme: light, readable title/body; dark code blocks. */
    .article-content .kb-article-title h2{color:#ffffff!important;}
    .article-content .kb-article-content{color:#dfe6ee!important;}
    .article-content .kb-article-content p,.article-content .kb-article-content li,
    .article-content .kb-article-content td,.article-content .kb-article-content ul,
    .article-content .kb-article-content ol,.article-content .kb-article-content span{color:#dfe6ee!important;}
    .article-content .kb-article-content h1,.article-content .kb-article-content h2,
    .article-content .kb-article-content h3,.article-content .kb-article-content h4,
    .article-content .kb-article-content h5,.article-content .kb-article-content h6,
    .article-content .kb-article-content strong,.article-content .kb-article-content b{color:#ffffff!important;}
    .article-content .kb-article-content a{color:#5db0ff!important;}
    .article-content .kb-article-content pre,.article-content .kb-article-content code{
        background:#0d1522!important;color:#e6edf3!important;border:1px solid rgba(255,255,255,.12)!important;border-radius:8px;}
    .article-content .kb-article-content pre{padding:12px 14px!important;overflow:auto;}
    .article-content .kb-article-content code{padding:2px 6px!important;}
{/literal}</style>

<div class="article-content">
<div class="kb-article-title">
    <h2>{$kbarticle.title}</h2>
</div>

{if $kbarticle.voted}
    {include file="$template/includes/alert.tpl" type="success alert-bordered-left" msg="{lang key="knowledgebaseArticleRatingThanks"}" textcenter=true}
{/if}

<div class="kb-article-content">
    {$kbarticle.text}
</div>

{if $kbarticle.editLink}
    <a href="{$kbarticle.editLink}" class="btn btn-default btn-sm pull-right">
        <i class="fas fa-pencil-alt fa-fw"></i>
        {$LANG.edit}
    </a>
{/if}
</div>
<ul class="kb-article-details">
    {if $kbarticle.tags }
        <li><i class="fas fa-tag"></i> {$kbarticle.tags}</li>
    {/if}
</ul>
<div class="clearfix"></div>

<div class="kb-rate-article hidden-print">
    <form action="{routePath('knowledgebase-article-view', {$kbarticle.id}, {$kbarticle.urlfriendlytitle})}" method="post" class="row">
	<div class="col-md-8 col-xs-12">
        <input type="hidden" name="useful" value="vote">
        <h6>{if $kbarticle.voted}{$LANG.knowledgebaserating}{else}{$LANG.knowledgebasehelpful}{/if} 
		<span><i class="ico-heart"></i> {$kbarticle.useful} {$LANG.knowledgebaseratingtext}</span>
		</h6>
	</div>
	
    <div class="col-md-4 text-right">
        {if $kbarticle.voted}
            <span class="user-votted">{$kbarticle.useful} {$LANG.knowledgebaseratingtext} ({$kbarticle.votes} {$LANG.knowledgebasevotes})</span>
        {else}
            <button type="submit" name="vote" value="yes" class="btn btn-yes"><i class="ico-user-check"></i> {$LANG.knowledgebaseyes}</button>
            <button type="submit" name="vote" value="no" class="btn btn-no"><i class="ico-user-minus"></i> {$LANG.knowledgebaseno}</button>
        {/if}
	</div>
    </form>
</div>

{if $kbarticles}
    <div class="kb-also-read">
        <h3>{$LANG.knowledgebaserelated}</h3>
        <div class="kbarticles">
            {foreach key=num item=kbarticle from=$kbarticles}
                <div>
                    <a href="{routePath('knowledgebase-article-view', {$kbarticle.id}, {$kbarticle.urlfriendlytitle})}">
                        <i class="glyphicon glyphicon-file"></i> {$kbarticle.title}
                    </a>
                    {if $kbarticle.editLink}
                        <a href="{$kbarticle.editLink}" class="admin-inline-edit">
                            <i class="fas fa-pencil-alt fa-fw"></i>
                            {$LANG.edit}
                        </a>
                    {/if}
                    <p>{$kbarticle.article|truncate:100:"..."}</p>
                </div>
            {/foreach}
        </div>
    </div>
{/if}
