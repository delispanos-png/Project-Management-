{* White-label Storage Box client area *}
<div class="hz-storage">
    <h3 style="margin-top:0">{$brand}</h3>
    {if $notice}<div class="alert alert-success" style="white-space:pre-wrap">{$notice}</div>{/if}
    {if $error}<div class="alert alert-danger">{$error}</div>{/if}

    {if !$ready}
        <div class="alert alert-info">Your storage is being prepared. Please check back shortly.</div>
    {else}
    <table class="table table-condensed">
        <tr><th style="width:30%">Host</th><td><code>{$server}</code></td></tr>
        <tr><th>Username</th><td><code>{$username}</code></td></tr>
        <tr><th>Location</th><td>{$location}</td></tr>
        <tr><th>Protocols</th><td>
            {if $ssh}<span class="label label-success">SSH/SFTP</span> {/if}
            {if $samba}<span class="label label-success">Samba</span> {/if}
            {if $webdav}<span class="label label-success">WebDAV</span> {/if}
        </td></tr>
    </table>
    <form method="post" onsubmit="return confirm('Reset the storage password?');">
        <input type="hidden" name="hzaction" value="resetpw">
        <input type="hidden" name="token" value="{$token}">
        <button class="btn btn-default">Reset Password</button>
    </form>
    {/if}
</div>
