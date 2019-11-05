/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

// REGEX to identifiy the edit button
const edit_button_sentinel = /civicrm\/contact\/view\/membership.*action=update/;

/**
 * remove the "EDIT" button
 */
cj(document).bind("ajaxComplete", function() {
  cj("button.ui-button").filter(function() {
    var data_identifier = cj(this).attr('data-identifier');
    return edit_button_sentinel.exec(data_identifier) !== null;
  }).hide();
});
