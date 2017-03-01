<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Action_Pause{

  function getValidStartStatuses(){
    return array('New', 'Current', 'Grace');
  }

  function getEndStatus(){
    return 'Paused';
  }

  function getActivityType(){
    return 'Contract_Paused';
  }

  function getName(){
    return 'pause';
  }

  function isValidFieldUpdate($fields){
    if(count($fields)){
      $this->errorMessage = 'You cannot update fields when pausing a contract';
      return false;
    }else{
      return true;
    }
  }

}
