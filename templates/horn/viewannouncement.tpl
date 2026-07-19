{if $twittertweet}
    <div class="pull-right">
        <a href="https://twitter.com/share" class="twitter-share-button" data-count="vertical" data-via="{$twitterusername}">Tweet</a><script type="text/javascript" src="//platform.twitter.com/widgets.js"></script>
    </div>
{/if}

<strong class="pull-left">{$carbon->createFromTimestamp($timestamp)->format('l, F j, Y')}</strong><br><br>

{$text}

<br />

{if $editLink}
    <p>
        <a href="{$editLink}" class="btn btn-default btn-sm pull-right">
            <i class="fas fa-pencil-alt fa-fw"></i>
            {$LANG.edit}
        </a>
    </p>
{/if}


{if $googleplus1}
    <br />
    <br />
    <g:plusone annotation="inline"></g:plusone>
    {literal}<script type="text/javascript">
    (function() {
        var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
        po.src = 'https://apis.google.com/js/plusone.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
    })();
    </script>{/literal}
{/if}

{if $facebookrecommend}
    <br />
    <br />
    {literal}
    <div id="fb-root">
    </div>
    <script>(function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) {return;}
        js = d.createElement(s); js.id = id;
        js.src = "//connect.facebook.net/en_US/all.js#xfbml=1";
        fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));</script>
    {/literal}
    <div class="fb-like" data-href="{fqdnRoutePath('announcement-view', $id, $urlfriendlytitle)}" data-send="true" data-width="450" data-show-faces="true" data-action="recommend">
    </div>
{/if}

{if $facebookcomments}
    <br />
    <br />
    {literal}
    <div id="fb-root">
    </div>
    <script>(function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) {return;}
        js = d.createElement(s); js.id = id;
        js.src = "//connect.facebook.net/en_US/all.js#xfbml=1";
        fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));</script>
    {/literal}
    <fb:comments href="{fqdnRoutePath('announcement-view', $id, $urlfriendlytitle)}" num_posts="5" width="500"></fb:comments>
{/if}

<a href="{routePath('announcement-index')}" class="btn btn-medium btn-prussian">{$LANG.clientareabacklink}</a>
