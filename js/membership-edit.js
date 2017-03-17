CRM.$(function($) {


  // Hide record membership payment
  $('#contri').hide();

  // Hide send confirmation and receipt
  $('#send-receipt').hide();

  // Hide membership options apart from the ones we want
  $('select#status_id option').hide();
  $.each(CRM.vars['de.systopia.contract'].filteredMembershipStatuses.values, function(key, value) {
    $('select#status_id option[value=' + value.id + ']').show();
  });

  // Hide custom fields we don't want to be populated
  $.mikey = function(){
    $.each(CRM.vars['de.systopia.contract'].hiddenCustomFields.values, function(key, value) {
      $('[class*=custom_' + value.id + ']').hide();
    });
  };

  $.mikey();
  $('#membership_type_id_1').change($.mikey);

});
