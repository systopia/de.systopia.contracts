{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}
{$form.cid.html}

<div>
  <div id="help">
    This will assign the selected contribution to the membership select below.
  </div>

  <div class="crm-section">
    <div class="label">{$form.membership_id.label}</div>
    <div class="content">{$form.membership_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.adjust.label}</div>
    <div class="content">{$form.adjust.html}</div>
    <div class="clear"></div>
  </div>

  {if $assigned_to}
  <div id="help">
    This contribution is already assigned to another membership. This connection will be removed if you press 'assign'. A contribution can only be assigned to one membership.
  </div>
  {/if}
<div>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
