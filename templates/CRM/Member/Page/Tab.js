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
    row = $(this).parent().parent().parent().parent().parent().parent().parent().prev();
    console.log(row);
    id = getIdFromRow(row);
    table = $(row).parent();
    reviewPane = table.find(".crm-membership-review_"+ id)[0];
    reviewLink = table.find("#crm-membership_"+ id + ' .review-link')[0];
    $(reviewPane).find('td').load(reviewLink.href);

    CRM.api3('Contract', 'get_open_modification_counts', {
      "id": id
    }).done(function(result) {
      contractStatuses[id] = result;
      table = $(row).parent();
      reviewPane = table.find(".crm-membership-review_"+ id)[0];
      $(reviewPane).find('td').load(reviewLink.href);

    });
  });

  // Clicking on show review opens up a review pane under the contract if not already open
  $(document).on( "click", ".review-link", function(e) {

    e.preventDefault();
    var row = $(this).parent().parent();
    id = getIdFromRow(row);
    table = $(row).parent();

    reviewPane = table.find(".crm-membership-review_"+ id)[0];
    reviewLink = table.find("#crm-membership_"+ id + ' .review-link')[0];

    if($(reviewPane).is(":visible")){
      $(reviewPane).hide();
      $(reviewLink).html(getReviewLinkText(id));
    }else{
      $(reviewPane).find('td').load(reviewLink.href, function(){
        $(reviewPane).show();
        $(reviewLink).html('(close)');
      });
    }

    // Prevent the default link action

    // console.log(reviewPane);
    // reviewPane.each().remove();
  });

  // Clicking on hide review closes the review pane
  $(document).on( "click", ".hide-review", function() {
    var review = $(this).parent().parent();
    $(review).remove();
  });

  function decorateRow(){
    console.log('called decorateRow');
    // get the status
    id = getIdFromRow(this);
    var contractStatus = contractStatuses[id];

    // Add a needs review
    addReviewHtml.call(this);

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

  function addReviewHtml(){

    id = getIdFromRow(this);

    // Add text to the membership status field if it is not there already
    if($( this ).find('td.crm-membership-status .review-link').length == 0){
      console.log('adding the review row');
      $( this ).find('td.crm-membership-status').append(
        " <a href='/civicrm/contract/review?reset=&snippet=1&id=" +
        id + "' class='review-link'>" + getReviewLinkText(id) + "</a>"
      );
    }

    if($( this ).parent().find('tr.crm-membership-review_' + id).length == 0){
      $( this ).after("<tr class= 'crm-membership-review_" + id + "'><td colspan=9></td></tr>");
    }
    $( this ).parent().find('tr.crm-membership-review_' + id).hide();

  }

  function getReviewLinkText(id){
    if(contractStatuses[id].needs_review > 0){
      text = '(needs review)';
    }else if(contractStatuses[id].scheduled > 0) {
      text = '(scheduled modifications)';
    } else {
      text = '(view modifications)';
    }
    return text;
  }

});
