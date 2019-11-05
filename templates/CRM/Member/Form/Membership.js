/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

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
  $.hideCustomFields = function(){
    $.each(CRM.vars['de.systopia.contract'].hiddenCustomFields.values, function(key, value) {
      $('[class*=custom_' + value.id + ']').hide();
    });
  };
  $.hideCustomFields();
  $('#membership_type_id_1').change($.hideCustomFields);
});
