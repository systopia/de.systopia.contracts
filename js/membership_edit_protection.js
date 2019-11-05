/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * removes a select element's not-selected options.
 * therfore rendering it virtually "read-only"
 */
function removeOtherOptions(index, select_element) {
  var select = cj(select_element);
  var current_value = select.val();
  select.find('option')
        .filter(function() {
                  return cj(this).val() != current_value;
                })
        .remove();
}

/**
 * This will try and protect all monitored fields from being edited
 */
function protectMonitoredFields() {
  // quick-n-dirty: disable protected fields in membership edit form
  var payment_group = cj(".custom-group-membership_payment");
  var edit_popup = payment_group.closest("div.ui-dialog");

  // make the memership ID selecter "read only"
  edit_popup.find("select[name^=membership_type_id]").each(removeOtherOptions);

  // remove the status override
  edit_popup.find("input[id=is_override]").parent().parent().hide();


  // do the same for the whole payment_group
  payment_group.addClass("collapsed");
  payment_group.find("select").each(removeOtherOptions);
  payment_group.find("input").prop('readonly', true);
}

// trigger
cj(document).ready(protectMonitoredFields);
cj(document).bind("ajaxComplete", protectMonitoredFields);
