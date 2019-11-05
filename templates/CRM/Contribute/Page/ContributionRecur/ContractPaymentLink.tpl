{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

{* this no-table is needed so the TRs get generated properly *}
<table style="display: none;">

{if $contract_payment_links_active}
    <tr class="contract-payment-link-row">
        <td class="label">{ts domain="de.systopia.contract"}Pays for Contract{/ts}</td>
        <td>
            {foreach from=$contract_payment_links_active item=link}
                <a href="{$link.link}">{$link.text}</a>
            {/foreach}
        </td>
    </tr>
    {/if}

    {if $contract_payment_links_inactive}
        <tr class="contract-payment-link-row">
            <td class="label">{ts domain="de.systopia.contract"}Used to Pay for Contract{/ts}</td>
            <td>
                {foreach from=$contract_payment_links_inactive item=link}
                    <a href="{$link.link}">{$link.text}</a>
                {/foreach}
            </td>
        </tr>
    {/if}
</table>

{literal}
<script type="application/javascript">
    // simply append the generated rows to the main block
    cj("div.crm-recurcontrib-view-block table tbody").append(cj("tr.contract-payment-link-row"));
</script>
{/literal}