<div class="container-fluid main-fluid normal-sec pb-0">
{if $twitterusername}
	<div class="row">
		<div class="col-md-12">
			<div class="main-title center-text">
                <h2>{$LANG.twitterlatesttweets}</h2>
                <p>{$LANG.announcementsdescription}</p>
            </div>
		</div>
	</div>
    <div id="twitterFeedOutput">
        <p class="text-center"><img src="{$BASE_PATH_IMG}/loading.gif" /></p>
    </div>
    <script type="text/javascript" src="{assetPath file='twitter.js'}"></script>
{elseif $announcements}
	<div class="row">
		<div class="col-md-12">
			<div class="main-title center-text">
                <h2>{$LANG.latestannouncements}</h2>
                <p>{$LANG.announcementsdescription}</p>
            </div>
		</div>
	</div>
	<div class="row" style="margin-bottom:35px">
	{foreach $announcements as $announcement}
	{if $announcement@index < 3}
		<div class="col-md-4 col-sm-6" style="margin-bottom:24px">
			<div class="hz-news-card">
				<h5>{$announcement.title}</h5>
				<p>{$announcement.summary}</p>
				<div class="hz-news-foot">
					<span class="hz-news-date"><i class="fas fa-calendar-alt"></i> {$carbon->translatePassedToFormat($announcement.rawDate, 'd/m/Y')}</span>
					<a class="btn btn-prussian btn-sm" href="{routePath('announcement-view', $announcement.id, $announcement.urlfriendlytitle)}">{$LANG.readmore} <i class="ico-eye f-14 w-icon"></i></a>
				</div>
			</div>
		</div>
	{/if}
	{/foreach}
	</div>
	{literal}<style>
	.hz-news-card{ background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.09); border-radius:14px;
	    padding:26px 24px; height:100%; display:flex; flex-direction:column;
	    transition:transform .15s ease, border-color .15s ease, background .15s ease; }
	.hz-news-card:hover{ transform:translateY(-4px); border-color:rgba(47,111,214,.55); background:rgba(47,111,214,.08); }
	.hz-news-card h5{ color:#fff; font-weight:700; font-size:17px; margin:0 0 12px; line-height:1.35; }
	.hz-news-card p{ color:#c3c7cc; font-size:14px; line-height:1.65; margin:0 0 18px; flex:1 1 auto; }
	.hz-news-foot{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:auto;
	    padding-top:14px; border-top:1px solid rgba(255,255,255,.08); }
	.hz-news-date{ color:#9aa0a6; font-size:12.5px; white-space:nowrap; }
	.hz-news-date i{ color:#4f8cf0; margin-right:4px; }
	</style>{/literal}
{/if}
</div>
