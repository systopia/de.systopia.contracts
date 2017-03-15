<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Action_Update extends CRM_Contract_Action{

  function getValidStartStatuses(){
    return array('New', 'Current', 'Grace');
  }

  function getValidEndStatuses(){
    return array('New', 'Current', 'Grace');
  }

  function getEndStatus(){
    return 'Current'; // TODO maybe we don't update the status for an update?
  }

  function getActivityType(){
    return 'Contract_Updated';
  }

  function getAction(){
    return 'update';
  }
  function getResult(){
    return 'updated';
  }

  function validateFieldUpdate($fields){
    return true;
  }
}
