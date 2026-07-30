<!-- Styling -->
{*<link href="//fonts.googleapis.com/css?family=Open+Sans:300,400,600|Raleway:400,700" rel="stylesheet">*}
<link rel="preconnect" href="//fonts.googleapis.com">
<link rel="preconnect" href="//fonts.gstatic.com" crossorigin>
<link href="//fonts.googleapis.com/css2?family=Comfortaa:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- Cloud On Design Tokens (source of truth — must load before framework CSS) -->
<link href="{assetPath file='tokens.css'}?v={$versionHash}" rel="stylesheet">
<link href="{assetPath file='all.min.css'}?v={$versionHash}" rel="stylesheet">
<link href="{$WEB_ROOT}/assets/css/fontawesome-all.min.css" rel="stylesheet">

<!-- Mandatory Styling -->
<link href="{$WEB_ROOT}/templates/{$template}/assets/css/flickity.min.css" rel="stylesheet" >
<link href="{$WEB_ROOT}/templates/{$template}/assets/css/aos.min.css" rel="stylesheet" >
<link href="{$WEB_ROOT}/templates/{$template}/assets/css/main.min.css" rel="stylesheet">

<!-- Icons Styling -->
<link href="{$WEB_ROOT}/templates/{$template}/assets/fonts/evafeat/evafeat.css" rel="stylesheet">
<link href="{$WEB_ROOT}/templates/{$template}/assets/fonts/cloudicon/cloudicon.css" rel="stylesheet">

<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
<!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
  <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
<![endif]-->

<script type="text/javascript">
    var csrfToken = '{$token}',
        markdownGuide = '{lang|addslashes key="markdown.title"}',
        locale = '{if !empty($mdeLocale)}{$mdeLocale}{else}en{/if}',
        saved = '{lang|addslashes key="markdown.saved"}',
        saving = '{lang|addslashes key="markdown.saving"}',
        whmcsBaseUrl = "{\WHMCS\Utility\Environment\WebHelper::getBaseUrl()}",
        requiredText = '{lang|addslashes key="orderForm.required"}',
        recaptchaSiteKey = "{if $captcha}{$captcha->recaptcha->getSiteKey()}{/if}";
</script>

<!-- Multilingual Condition to RTL & LTR Language -->
{if $language eq 'arabic' || $language eq 'farsi' || $language eq 'hebrew'}<html dir="rtl">
<link href="{$WEB_ROOT}/templates/{$template}/assets/css/auto-rtl/bootstrap-rtl.min.css?v={$versionHash}" rel="stylesheet">
<link href="{$WEB_ROOT}/templates/{$template}/assets/css/auto-rtl/rtl.css?v={$versionHash}" rel="stylesheet">
<link href="{$WEB_ROOT}/templates/{$template}/assets/css/auto-rtl/custom-rtl.css" rel="stylesheet">
{else}
<!-- duplicate main.min.css removed (R7 perf) -->
{assetExists file="custom.css"}
<link href="{$__assetPath__}" rel="stylesheet">
{/assetExists}
<html>
{/if}

<!-- Cloud On Foundation layer moved below to load LAST (after the color themes) -->

{if $templatefile == "viewticket" && !$loggedin}
  <meta name="robots" content="noindex" />
{/if}

<script src="{assetPath file='scripts.min.js'}?v={$versionHash}"></script>
<script defer src="{$WEB_ROOT}/templates/{$template}/assets/js/jquery.slimscroll.min.js"></script>
<script defer src="{$WEB_ROOT}/templates/{$template}/assets/js/flickity.pkgd.min.js"></script>
<script defer src="{$WEB_ROOT}/templates/{$template}/assets/js/flickity-fade.min.js"></script>
<script defer src="{$WEB_ROOT}/templates/{$template}/assets/js/aos.min.js"></script>
<script defer src="{$WEB_ROOT}/templates/{$template}/assets/js/md5.min.js"></script>
<script defer src="{$WEB_ROOT}/templates/{$template}/assets/js/main.js"></script>

<!-- Horn custom color styles -->
<link href="{$WEB_ROOT}/templates/{$template}/assets/css/colors/dark.css" rel="stylesheet" title="dark"/>
<link href="{$WEB_ROOT}/templates/{$template}/assets/css/colors/blue.css" rel="stylesheet" title="blue"/>
<link href="{$WEB_ROOT}/templates/{$template}/assets/css/colors/pink.css" rel="stylesheet" title="pink"/>
<link href="{$WEB_ROOT}/templates/{$template}/assets/css/colors/green.css" rel="stylesheet" title="green"/>

<!-- Cloud On Foundation layer — LAST so its correctness fixes win the cascade -->
<link href="{assetPath file='foundation.css'}?v={$versionHash}" rel="stylesheet">
