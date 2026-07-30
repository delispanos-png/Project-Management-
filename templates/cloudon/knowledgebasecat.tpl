<style>{literal}
    /* KB cards dark-theme consistent → readable titles/descriptions. */
    .know-bgbox-container{background:rgba(255,255,255,.05)!important;border:1px solid rgba(255,255,255,.10)!important;box-shadow:none!important;}
    .know-bgbox-container .col-sm-12{border-bottom-color:rgba(255,255,255,.10)!important;}
    .know-bgbox-container .col-sm-12 a,.know-bgbox-container .kbarticles a{color:#ffffff!important;}
    .know-bgbox-container .col-sm-12 p,.know-bgbox-container .kbarticles p{color:#c2ccd6!important;}
    .know-bgbox-container .col-sm-12 a span{background:#5c65ae!important;}
    .know-bgbox-container .col-sm-12 a span:hover{background:#4d5699!important;}
{/literal}</style>

<form role="form" method="post" action="{routePath('knowledgebase-search')}">
    <div class="input-group input-group-lg kb-search overlay">
        <div class="col-md-8 col-md-offset-2 col-xs-10 col-xs-offset-1">
            <input type="text"  id="inputKnowledgebaseSearch" name="search" class="form-control" placeholder="{$LANG.clientHomeSearchKb}" value="{$searchterm}" />
            <span class="input-group-btn">
                <input type="submit" id="btnKnowledgebaseSearch" class="btn btn-primary btn-input-padded-responsive" value="{$LANG.search}" />
            </span>
        </div>
    </div>
</form>


    <div class="row kbcategories">
	<div class="know-bgbox-container">
	{if $kbcats}
        {foreach from=$kbcats name=kbcats item=kbcat}
            <div class="col-sm-12">
                <a href="{routePath('knowledgebase-category-view', {$kbcat.id}, {$kbcat.urlfriendlyname})}">
                    <i class="ico-file"></i>
                    {$kbcat.name} <span>{$kbcat.numarticles} {$LANG.knowledgebasearticles}</Span>
                </a>
                <p>{$kbcat.description}</p>
            </div>
        {/foreach}
	{/if}
	
	
	{if $kbarticles || !$kbcats}

    <div class="kbarticles">
        {foreach from=$kbarticles item=kbarticle}
            <a href="{routePath('knowledgebase-article-view', {$kbarticle.id}, {$kbarticle.urlfriendlytitle})}">
                <span class="glyphicon glyphicon-file"></span>&nbsp;{$kbarticle.title}
            </a>
            
            <p>{$kbarticle.article|truncate:100:"..."}</p>
        {foreachelse}
            {include file="$template/includes/alert.tpl" type="info" msg=$LANG.knowledgebasenoarticles textcenter=true}
        {/foreach}
    </div>
    {/if}

	
    </div>
	<div class="col-md-4">
	</div>
	</div>




