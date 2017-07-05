{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

<div class="crm-block crm-form-block">

  <h3>Create a new contact with associated contract and mandate</h3>

  <hr/>

  <div class="crm-section">
    <div class="label">{$form.prefix_id.label}</div>
    <div class="content">{$form.prefix_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.first_name.label}</div>
    <div class="content">{$form.first_name.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.last_name.label}</div>
    <div class="content">{$form.last_name.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.phone.label}</div>
    <div class="content">{$form.phone.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.email.label}</div>
    <div class="content">{$form.email.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.street_address.label}</div>
    <div class="content">{$form.street_address.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.postal_code.label}</div>
    <div class="content">{$form.postal_code.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.city.label}</div>
    <div class="content">{$form.city.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.birth_date.label}</div>
    <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=birth_date}</div>
    <div class="clear"></div>
  </div>
  <hr/>

  <div class="crm-section">
    <div class="label">{$form.payment_option.label}</div>
    <div class="content">{$form.payment_option.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section payment-select">
    <div class="label">{$form.recurring_contribution.label}</div>
    <div class="content">{$form.recurring_contribution.html}</div>
    <div class="clear"></div>
    <div class="label"></div>
    <div class="clear"></div>
  </div>

  <div class="crm-section payment-create">
    <div class="label">{$form.cycle_day.label}</div>
    <div class="content">{$form.cycle_day.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.iban.label}</div>
    <div class="content">{$form.iban.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.bic.label}</div>
    <div class="content">{$form.bic.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.payment_amount.label}</div>
    <div class="content">{$form.payment_amount.html}&nbsp;EUR</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.payment_frequency.label}</div>
    <div class="content">{$form.payment_frequency.html}</div>
    <div class="clear"></div>
  </div>

  <hr />
  <div class="crm-section">
    <div class="label">{$form.join_date.label}</div>
    <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=join_date}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.start_date.label}</div>
    <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=start_date}</div>
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

  <hr/>

  <div class="crm-section">
    <div class="label">{$form.post_delivery_only_online.label}</div>
    <div class="content">{$form.post_delivery_only_online.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.interest.label}</div>
    <div class="content">{$form.interest.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.talk_topic.label}</div>
    <div class="content">{$form.talk_topic.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.tshirt_order.label}</div>
    <div class="content">{$form.tshirt_order.html}</div>
    <div class="clear"></div>
  </div>

  <div class="tshirt_order_fields">

    <div class="crm-section">
      <div class="label">{$form.shirt_type.label}</div>
      <div class="content">{$form.shirt_type.html}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.shirt_size.label}</div>
      <div class="content">{$form.shirt_size.html}</div>
      <div class="clear"></div>
    </div>

  </div>

  <hr/>

  <div class="crm-section">
    <div class="label">{$form.community_newsletter.label}</div>
    <div class="content">{$form.community_newsletter.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.groups.label}</div>
    <div class="content">{$form.groups.html}</div>
    <div class="clear"></div>
  </div>


  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>


{if $bic_lookup_accessible}
  {include file="CRM/Contract/Form/bic_lookup.tpl" location="bottom"}
{/if}
