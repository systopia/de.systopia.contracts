{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

<div class="crm-info">
    <p>{$infotext}</p>
</div>

<div class="crm-section">
    <div class="label">{$form.detach_recur.label}</div>
    <div class="content">{$form.detach_recur.html}</div>
    <div class="clear"></div>
</div>

<div class="crm-section">
    <div class="label">{$form.change_financial_type.label}</div>
    <div class="content">{$form.change_financial_type.html}</div>
    <div class="clear"></div>
</div>

<div class="crm-section">
    <div class="label">{$form.change_recur_financial_type.label}</div>
    <div class="content">{$form.change_recur_financial_type.html}</div>
    <div class="clear"></div>
</div>

<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
