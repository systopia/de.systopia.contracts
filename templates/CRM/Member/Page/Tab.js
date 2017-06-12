CRM.$(function($) {

  var contractStatuses = CRM.vars['de.systopia.contract'].contractStatuses;

  // # Link to membership create should be replaced with contract create #
  $('a[href="/civicrm/contact/view/membership?reset=1&action=add&cid=' + CRM.vars['de.systopia.contract'].cid + '&context=membership"]')
  .attr("href", "/civicrm/contract/create?cid=" + CRM.vars['de.systopia.contract'].cid);

  // # Membership rows should be decorated with more information about the contract #

  // Decorate each row on initial load
  $( "#memberships tr.crm-membership" ).each(decorateRow);

  // Set modification edit activities to open in a pop up
  $(document).on( 'click', '.edit-activity', CRM.popup);

  // When a pop up is closed, update the contract status and decorate the row
  $(document).on( "crmPopupFormSuccess", '.edit-activity', function(){
    row = ($(this).parent().parent().parent().parent().parent().parent().parent().parent().prev());
    id = getIdFromRow(row);
    CRM.api3('Contract', 'get_open_modifications', {
      "id": id
    }).done(function(result) {
      contractStatuses[id] = result;
      decorateRow.call(row);
      // reload review pane
      $(row).next().find('.review-pane').load("/civicrm/contract/review?reset=&snippet=1&id=" + id);
    });
  });

  function decorateRow(){

    // get the status
    id = getIdFromRow(this);
    var contractStatus = contractStatuses[id];

    // Add link to view contract history
    contractHistory.call(this);

    // Add a needs review if necessary
    if(contractStatus.needs_review > 0){
      addReviewLink.call(this);
    }else if (contractStatus.scheduled > 0){
      addReviewLink.call(this);
    }

    if(contractStatus.needs_review > 0){
      $(this).addClass('needs-review');
    }else{
      $(this).removeClass('needs-review');
    }

    if(contractStatus.scheduled > 0){
      $(this).addClass('scheduled');
    }else{
      $(this).removeClass('scheduled');
    }
  }

  function getIdFromRow(row){
    var elementId = $(row).attr('id');
    return elementId.substr(elementId.indexOf("_") + 1);
  }

  function contractHistory(){
    // $(this).find('td.crm-membership-status').append(' history');
  }

  function addReviewLink(){
    if($( this ).find('td.crm-membership-status .show-review').length == 0){
      $( this ).find('td.crm-membership-status').append(
        " <a href='/civicrm/contract/review?reset=&snippet=1&id=" +
        id + "' class='show-review'>scheduled modifications</a>"
      );
    }
    if(contractStatuses[id].needs_review > 0){
      $( this ).find('td.crm-membership-status .show-review').html('needs review');
    }else{
      $( this ).find('td.crm-membership-status .show-review').html('scheduled modifications');
    }
  }

  function getHistoryLink(id){
    return "/civicrm/contract/review?reset=&snippet=1&id=" + id;
  }

  function updateActivities(){
    var link = getHistoryLink(getIdFromRow($(this).parents('tr').parents('tr').prev()));
    $(this).parents('tr').parents('tr div.scheduled-modifications').load(link);
  }


  // Clicking on show review opens up a review pane under the contract
  $(document).on( "click", ".show-review", function(e) {
    var row = $(this).parent().parent();
    // Prevent the default link action
    e.preventDefault();
    $.get(e.target.attributes.href.value, function(data){
      row.after("<tr><td colspan=9><div class='review-pane'>" + data + "</div><a class='hide-review'>hide</a></div></td></tr>");
    });
    $(this).remove();
    id = getIdFromRow(row);
    contractStatuses[id].reviewPane = true;
  });

  // Clicking on hide review closes the review pane
  $(document).on( "click", ".hide-review", function() {
    var review = $(this).parent().parent();
    var row = review.prev();
    id = getIdFromRow(row);
    $(review).remove();
    addReviewLink.call(row);
    contractStatuses[id].reviewPane = false;
  });

  // Clicking on show history opens up a history pane under the contract
  $(document).on( "click", ".show-history", function(e) {
    e.preventDefault();
    var link = getHistoryLink(getIdFromRow($(this).parent().parent()));
    $(this).parent().parent().after("<tr><td colspan=9><div><div class='history'></div><a class='hide-history'>hide</a></div></td></tr>");
    $(this).parent().parent().next().find('div.scheduled-modifications').load(link);
    $(this).remove();
  });

  // Clicking on hide history closes the history pane
  $(document).on( "click", ".hide-history", function() {
    $(this).parent().parent().parent().prev().find('td.crm-membership-status').each(createNeedsReview);
    $(this).parent().parent().remove();
  });

});
