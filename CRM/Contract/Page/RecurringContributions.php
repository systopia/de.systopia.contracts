<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Page_RecurringContributions extends CRM_Core_Page{
  function run(){
    if($contactId = CRM_Utils_Request::retrieve('cid', 'Positive')){
      $recurringContribution = new CRM_Contract_RecurringContribution();
      echo json_encode($recurringContribution->getAll($contactId), JSON_PRETTY_PRINT);
    }
    exit;
  }
}
