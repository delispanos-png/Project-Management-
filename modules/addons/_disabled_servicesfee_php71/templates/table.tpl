<div id="wsservicesfee" style="display:none"> 
    <p style='text-align: center;margin-top: 10px'><b>{$WSLANG.tablefeetitle}</b></p>
    <table class="table table-striped">
        <thead>
        <th>{$WSLANG.tablegateway}</th>
        <th>{$WSLANG.tableprovision}</th>
        <th>{$WSLANG.tablecalcluted}</th>
        </thead>
        <tbody>
            {foreach from=$gateways item=gateway key=gkey}
                <tr>
                    <td>{$gateway.name}</td>
                    <td>{$gateway.fee}</td>
                    <td>{$gateway.amount}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
<script src="{$CONFIG.SystemURL}/modules/addons/servicesfee/assets/front.js"></script>
