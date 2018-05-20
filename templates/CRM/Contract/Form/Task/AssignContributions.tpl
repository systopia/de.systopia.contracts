{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

<div class="crm-section">
  <div class="label">{$form.contract_id.label}</div>
  <div class="content">{$form.contract_id.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.adjust_financial_type.label}</div>
  <div class="content">{$form.adjust_financial_type.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.assign_mode.label}</div>
  <div class="content">{$form.assign_mode.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.adjust_pi.label}</div>
  <div class="content">{$form.adjust_pi.html}</div>
  <div class="clear"></div>
</div>

{include file="CRM/common/formButtons.tpl" location="bottom"}


<script type="text/javascript">
var generic_segments = {$generic_segments};
{literal}

/*******************************
 *   campaign changed handler  *
 ******************************/
cj("#contract_id").change(function() {
  // rebuild segment list
  //
  // // first: remove all
  // cj("#segment_list option").remove();
  // cj("#segment").val('');
  //
  // // then: add default ones
  // for (var i = 0; i < generic_segments.length; i++) {
  //   cj("#segment_list").append('<option value="' + generic_segments[i] + '">' + generic_segments[i] + '(generic) </option>');
  // }
  //
  // // then: look up the specific ones and add
  // CRM.api3('Segmentation', 'segmentlist', {
  //   "campaign_id": cj("#campaign_id").val(),
  // }).done(function(result) {
  //   for (var i = 0; i < result.values.length; i++) {
  //     cj("#segment_list").append('<option value="' + result.values[i] + '">' + result.values[i] + '</option>');
  //   }
  // });
});

/*******************************
 *    segemnt list handler     *
 ******************************/
cj("#segment_list").change(function() {
  cj("#segment").val(cj("#segment_list").val());
});

// fire off event once
cj("#campaign_id").change();
{/literal}
</script>