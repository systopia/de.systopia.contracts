CRM.$(function($) {
  $('#contri').hide();
  $('select#status_id option').hide();
  $('.crm-membership-form-block-record_contribution').hide();
  $('select#status_id option:contains(- select -)').show();
  $('select#status_id option:contains(Current)').show();
  $('select#status_id option:contains(Cancelled)').show();
});
