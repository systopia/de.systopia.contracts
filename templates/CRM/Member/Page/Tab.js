CRM.$(function($) {
  $('a[href="/civicrm/contact/view/membership?reset=1&action=add&cid=' + CRM.vars['de.systopia.contract'].cid + '&context=membership"]')
  .attr("href", "/civicrm/contract/create?cid=" + CRM.vars['de.systopia.contract'].cid);
});
