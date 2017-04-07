<div class="crm-block crm-form-block">

  <h3>Create a new contract for {$contact.display_name}</h3>

  {include file="CRM/Contract/Form/MandateBlock.tpl"}
  <hr />
  <div class="crm-section">
    <div class="label">{$form.activity_date.label}</div>
    <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=activity_date}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.join_date.label}</div>
    <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=join_date}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.end_date.label}</div>
    <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=end_date}</div>
    <div class="clear"></div>
  </div>
  <hr />
  <div class="crm-section">
    <div class="label">{$form.campaign_id.label}</div>
    <div class="content">{$form.campaign_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.membership_type_id.label}</div>
    <div class="content">{$form.membership_type_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.activity_medium.label}</div>
    <div class="content">{$form.activity_medium.html}</div>
    <div class="clear"></div>
  </div>
  <hr />
  <div class="crm-section">
    <div class="label">{$form.membership_reference.label}</div>
    <div class="content">{$form.membership_reference.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.membership_contract.label}</div>
    <div class="content">{$form.membership_contract.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.membership_dialoger.label}</div>
    <div class="content">{$form.membership_dialoger.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.membership_channel.label}</div>
    <div class="content">{$form.membership_channel.html}</div>
    <div class="clear"></div>
  </div>
  <hr />
  <div class="crm-section">
    <div class="label">{$form.activity_details.label}</div>
    <div class="content">{$form.activity_details.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>

</div>
