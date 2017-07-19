/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

cj(document).ready(function() {
  // quick-n-dirty: disable protected fields in membership edit form
  cj(".custom-group-membership_payment").addClass("collapsed");
  cj(".custom-group-membership_payment").find("select").prop('disabled', true);
  cj(".custom-group-membership_payment").find("input").prop('disabled', true);
  cj("select[name^=membership_type_id]").prop('disabled', true);
  cj("input[id=is_override]").parent().parent().hide();
});