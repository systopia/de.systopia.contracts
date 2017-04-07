CRM.$(function($) {

  // Register listeners
  $('[name=contract_history_recurring_contribution]').change(updatePaymentSummaryText);
  $('.create-mandate').click(CRM.popup);
  $('.test').click(updateRecurringContributions);
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
    key = $('[name=contract_history_recurring_contribution]').val();
    $('.recurring-contribution-summary-text').html($.recurringContributions[key].text_summary);
  };

  function updateRecurringContributions(){
    $.getJSON('/civicrm/contract/recurringContributions?cid=' + CRM.vars['de.systopia.contract'].cid).done(function(data) {
      $.recurringContributions = data;
      select = CRM.$('[name=contract_history_recurring_contribution]');
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


  // CRM.$('[name=contract_history_recurring_contribution]').change(
  //   function(){
  //     CRM.$('.recurring-contribution-summary-text').html('Michael');
  //   }
  // );



});
