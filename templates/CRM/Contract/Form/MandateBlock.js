/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

CRM.$(function($) {

  // Register listeners
  $('[name=recurring_contribution]').change(updatePaymentSummaryText);
  $('.create-mandate').click(CRM.popup);
  $('.create-mandate' ).on('crmPopupFormSuccess', updateRecurringContributions);

  // Get getRecurringContributions data for the first time
  getRecurringContributions();

  function getRecurringContributions(){
    $.getJSON('/civicrm/contract/recurringContributions?cid=' + CRM.vars['de.systopia.contract'].cid).done(function(data) {
      $.recurringContributions = data;
      updatePaymentSummaryText();
    });
  };


  function updatePaymentSummaryText(){
    key = $('[name=recurring_contribution]').val();
    $('.recurring-contribution-summary-text').html($.recurringContributions[key].text_summary);
  };

  function updateRecurringContributions(){
    $.getJSON('/civicrm/contract/recurringContributions?cid=' + CRM.vars['de.systopia.contract'].cid).done(function(data) {
      $.recurringContributions = data;
      select = CRM.$('[name=recurring_contribution]');
      select.find('option').remove();
      maxIndex = 0;
      $.each($.recurringContributions, function(index, value){
        select.append('<option value="' + index + '">' + value.label + '</option>');
        if(index > maxIndex){
          maxIndex=index;
        }
      });
      select.val(maxIndex).trigger('change');
      // Are these next two lines necessary?
      // $.recurringContributions = data;
      // updatePaymentSummaryText();
    });
  };


  // CRM.$('[name=recurring_contribution]').change(
  //   function(){
  //     CRM.$('.recurring-contribution-summary-text').html('Michael');
  //   }
  // );



});
