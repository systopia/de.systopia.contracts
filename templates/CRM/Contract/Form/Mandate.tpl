<div class="crm-block crm-form-block">
  <h3>Create a new mandate</h3>
  <div class="crm-section">
    <div class="label">{$form.iban.label}</div>
    <div class="content">{$form.iban.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.bic.label}</div>
    <div class="content">{$form.bic.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.amount.label}</div>
    <div class="content">{$form.amount.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.frequency_interval.label}</div>
    <div class="content">{$form.frequency_interval.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.cycle_day.label}</div>
    <div class="content">{$form.cycle_day.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.start_date.label}</div>
    <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=start_date}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>

</div>

{if $bic_lookup_accessible}
  {include file="CRM/Contract/Form/bic_lookup.tpl" location="bottom"}
{/if}