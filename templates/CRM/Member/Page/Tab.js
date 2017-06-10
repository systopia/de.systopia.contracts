CRM.$(function($) {


  // If we don't rewrite the link, then the pop up doesn't close properly - not sure why
  $('a[href="/civicrm/contact/view/membership?reset=1&action=add&cid=' + CRM.vars['de.systopia.contract'].cid + '&context=membership"]')
  .attr("href", "/civicrm/contract/create?cid=" + CRM.vars['de.systopia.contract'].cid);


  $(document).on( "click", '.edit-activity', CRM.popup);
  $(document).on( "crmPopupFormSuccess", '.edit-activity', updateActivities);

  function updateActivities(){
    var link = getHistoryLink(getIdFromRow($(this).parents('tr').parents('tr').prev()));
    $(this).parents('tr').parents('tr div.scheduled-modifications').load(link);

  }

  function getIdFromRow(row){
    var elementId = $(row).attr('id');
    return elementId.substr(elementId.indexOf("_") + 1);
  }

  function getHistoryLink(id){
    return "/civicrm/contract/review?reset=&snippet=1&id=" + id;
  }

  $( "#memberships td.crm-membership-status" ).each(createNeedsReview);

  function createNeedsReview(){
    contractId = getIdFromRow($(this).parent());
    if(CRM.vars['de.systopia.contract'].contractStatuses[contractId].needs_review > 0){
      var link = getHistoryLink(contractId);
      $( this ).append(" <a href='" + link + "' class='show-scheduled-modifications'>needs review</a>");
    }
  }

  $(document).on( "click", ".show-scheduled-modifications", function(e) {
    e.preventDefault();
    var link = getHistoryLink(getIdFromRow($(this).parent().parent()));
    $(this).parent().parent().after("<tr><td colspan=9><div><div class='scheduled-modifications'></div><a class='hide-scheduled-modifications'>hide</a></div></td></tr>");
    $(this).parent().parent().next().find('div.scheduled-modifications').load(link);
    $(this).remove();
  });
  $(document).on( "click", ".hide-scheduled-modifications", function() {
    $(this).parent().parent().parent().prev().find('td.crm-membership-status').each(createNeedsReview);
    $(this).parent().parent().remove();
  });

});
