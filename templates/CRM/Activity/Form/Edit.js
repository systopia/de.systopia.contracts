/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

CRM.$(function($) {

  // Note insane selector and chaining necessary to find element
  element = $( ".custom-group-contract_updates label:contains('Membership Type')" ).parent().next();
  membershipTypeId = element.find('input').val();

  CRM.api3('MembershipType', 'getsingle', {"id": membershipTypeId}).done(function(result) {
    element.html(result.name);
  });
});
