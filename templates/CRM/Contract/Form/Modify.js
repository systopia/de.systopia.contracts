CRM.$(function($) {

  $('.update-payment-contracts').click($.updateRecurringContributions);
  $('.create-mandate').click(CRM.popup);
  $('.create-mandate' ).on('crmPopupClose', $.updateRecurringContributions);

  $.updateRecurringContributions = function(){
    $.getJSON('/civicrm/contract/recurringContributions?cid=' + CRM.vars['de.systopia.contract'].cid).done(function(data) {
      select = CRM.$('[name=contract_history_recurring_contribution]');
      select.find('option').remove();
      maxIndex = 0;
      CRM.$.each(data, function(index, value){
        select.append('<option value="' + index + '">' + value + '</option>');
        if(index > maxIndex){
          maxIndex=index;
        }
      });
      select.val(maxIndex);
    });
  };

});
