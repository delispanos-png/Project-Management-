{if $announcementsFbRecommend}
    <script>
        (function(d, s, id) {
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) {
                return;
            }
            js = d.createElement(s); js.id = id;
            js.src = "//connect.facebook.net/{$LANG.locale}/all.js#xfbml=1";
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));
    </script>
{/if}

{literal}<style>
.announcement-single{ background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.09); border-radius:14px; margin-bottom:26px;
    overflow:hidden; position:relative; transition:transform .15s ease, border-color .15s ease, background .15s ease; }
.announcement-single:hover{ transform:translateY(-4px); border-color:rgba(47,111,214,.55); background:rgba(47,111,214,.08); }
.announcement-single:before{ display:none !important; }
.announcement-single .title{ background:transparent !important; color:#fff !important; font-weight:700; font-size:17px; line-height:1.4;
    padding:24px 24px 10px !important; border-radius:0 !important; display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.announcement-single .title:hover{ color:#4f8cf0 !important; }
.announcement-single .title .badge{ background:#2f6fd6 !important; color:#fff !important; border-radius:50%; width:34px; height:34px;
    display:inline-flex !important; align-items:center; justify-content:center; flex:0 0 auto; margin:0 !important; padding:0 !important; }
.announcement-single .title .badge i{ margin:0; font-size:15px; }
.announcement-single p{ color:#c3c7cc !important; font-size:14px; line-height:1.65; padding:0 24px 24px !important; position:relative; z-index:1; margin:0; }
</style>{/literal}
<div class="row">
{foreach from=$announcements item=announcement}
        <div class="col-md-4 col-sm-6">
            <div class="announcement-single">

                <a href="{routePath('announcement-view', $announcement.id, $announcement.urlfriendlytitle)}" class="title">
                    {$announcement.title} <span class="badge feat bg-puretheme mr-20 mt-10" data-toggle="tooltip" data-placement="left" title="" data-original-title="{$carbon->createFromTimestamp($announcement.timestamp)->format('jS M Y')}">
                    <i class="ico-calendar f-18"></i> </span>
                </a>

                {if $announcement.text|strip_tags|strlen < 350}
                    <p>{$announcement.text}</p>
                {else}
                    <p>{$announcement.summary}</p>
                {/if}

                {if $announcementsFbRecommend}
                    <div class="fb-like hidden-sm hidden-xs" data-layout="standard" data-href="{fqdnRoutePath('announcement-view', $announcement.id, $announcement.urlfriendlytitle)}" data-send="true" data-width="450" data-show-faces="true" data-action="recommend"></div>
                    <div class="fb-like hidden-lg hidden-md" data-layout="button_count" data-href="{fqdnRoutePath('announcement-view', $announcement.id, $announcement.urlfriendlytitle)}" data-send="true" data-width="450" data-show-faces="true" data-action="recommend"></div>
                {/if}

            </div>
        </div>
{foreachelse}
</div>

    {include file="$template/includes/alert.tpl" type="info" msg="{$LANG.noannouncements}" textcenter=true}

{/foreach}

{if $prevpage || $nextpage}

    <div class="col-xs-12 margin-bottom">
        <form class="form-inline" role="form">
            <div class="form-group">
                <div class="input-group">
                    <span class="btn-group">
                        {foreach $pagination as $item}
                            <a href="{$item.link}" class="btn btn-default{if $item.active} active{/if}"{if $item.disabled} disabled="disabled"{/if}>{$item.text}</a>
                        {/foreach}
                    </span>
                </div>
            </div>
        </form>
    </div>
    <div class="clearfix"></div>
{/if}
