<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Action_Update{

  function getValidStartStatuses(){
    return array('New', 'Current', 'Grace');
  }

  function getEndStatus(){
    return 'Current'; // TODO maybe we don't update the status for an update?
  }

  function getActivityType(){
    return 'Contract_Updated';
  }

  function getName(){
    return 'update';
  }

  function isValidFieldUpdate($fields){
    return true;
  }
}
