{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

<div class="crm-block crm-form-block">

  <h3>Create a new contract for {$contact.display_name}</h3>

  <hr/>

  <div class="crm-section">
    <div class="label">Current Payment Option</div>
    <div class="content recurring-contribution-summary-text">None</div>
    <div class="clear"></div>
  </div>

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


{if $bic_lookup_accessible}
  {include file="CRM/Contract/Form/bic_lookup.tpl" location="bottom"}
{/if}

{literal}
<script type="text/javascript">
// add listener to exsting selector
cj('[name=recurring_contribution]').change(updatePaymentSummaryText);

// add listener to payment_option selector
cj("#payment_option").change(function() {
  updatePaymentSummaryText();
  var new_mode = cj("#payment_option").val();
  if (new_mode == "select") {
    cj("div.payment-select").show(300);
    cj("div.payment-create").hide(300);
  } else if (new_mode == "create") {
    cj("div.payment-select").hide(300);
    cj("div.payment-create").show(300);
  }
});

/**
 * update the payment info shown
 */
function updatePaymentSummaryText() {
  var mode = cj("#payment_option").val();
  if (mode == "select") {
    // display the selected recurring contribution
    var recurring_contributions = CRM.vars['de.systopia.contract'].recurring_contributions;
    var key = cj('[name=recurring_contribution]').val();
    if (key) {
      cj('.recurring-contribution-summary-text').html(recurring_contributions[key].text_summary);
    } else {
      cj('.recurring-contribution-summary-text').html('None');
    }
  } else if (mode == "create") {
    // render the current SEPA values
    var creditor = CRM.vars['de.systopia.contract'].creditor;
    var cycle_day       = cj('[name=cycle_day]').val();
    var iban            = cj('[name=iban]').val();
    var annual          = parseMoney(cj('[name=payment_amount]').val());
    var freqency        = cj('[name=payment_frequency]').val();
    var freqency_label  = CRM.vars['de.systopia.contract'].frequencies[freqency];
    var next_collection = CRM.vars['de.systopia.contract'].next_collections[cycle_day];
    var installment     = 0.0;

    // TODO: sanitise annual
    // caculcate the installment
    if (isNaN(annual)) {
      annual = 0.0;
    } else {
      installment = (annual.toFixed(2) / parseFloat(freqency)).toFixed(2);
    }

    // TODO: use template
    cj('.recurring-contribution-summary-text').html(
      "Creditor name: " + creditor.name + "<br/>" +
      "Payment method: SEPA Direct Debit<br/>" +
      "Frequency: " + freqency_label + "<br/>" +
      "Annual contract amount: " + annual.toFixed(2) + " EUR<br/>" +
      "Frequency contract amount: " + installment + " EUR<br/>" +
      "Organisational account: " + creditor.iban + "<br/>" +
      "Debitor account: " + iban + "<br/>" +
      "Next debit: " + next_collection + "<br/>"
      );
  }
}

/**
 * formats a value to the CiviCRM failsafe format: 0.00 (e.g. 999999.90)
 * even if there are ',' in there, which are used in some countries
 * (e.g. Germany, Austria,) as a decimal point.
 * @see CRM_Contract_SepaLogic::formatMoney
 */
function parseMoney(raw_value) {
  if (raw_value.length == 0) {
    return 0.0;
  }

  // find out if there's a problem with ','
  var stripped_value = raw_value.replace(' ', '');
  if (stripped_value.includes(',')) {
    // if there are at least three digits after the ','
    //  it's a thousands separator
    if (stripped_value.match('#,\d{3}#')) {
      // it's a thousands separator -> just strip
      stripped_value = stripped_value.replace(',', '');
    } else {
      // it has to be interpreted as a decimal
      // first remove all other decimals
      stripped_value = stripped_value.replace('.', '');
      stripped_value = stripped_value.replace(',', '.');
    }
  }
  return parseFloat(stripped_value);
}

// call once for the UI to adjust
cj("#payment_option").trigger('change');
cj("div.payment-create").change(updatePaymentSummaryText);
</script>
{/literal}
