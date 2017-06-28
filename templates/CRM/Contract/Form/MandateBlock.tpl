{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

{* DEPRECATED *}
<div class="crm-section payment-select">
  <div class="label">{$form.recurring_contribution.label}</div>
  <div class="content">{$form.recurring_contribution.html} <a href="{crmURL p='civicrm/contract/mandate' q="cid=`$cid`"}" class="create-mandate"><i class="crm-i fa-plus-circle"></i> new mandate</a></div>
  <div class="clear"></div>
  <div class="label"></div>
  <div class="content"><p class=recurring-contribution-summary-text></p></div>
  <div class="clear"></div>
</div>
