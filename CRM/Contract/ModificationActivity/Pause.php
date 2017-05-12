<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_ModificationActivity_Pause extends CRM_Contract_ModificationActivity{

  function getStartStatuses(){
    return array('New', 'Current', 'Grace');
  }

  function getEndStatus(){
    return 'Paused';
  }

  function getActivityType(){
    return 'Contract_Paused';
  }

  function getAction(){
    return 'pause';
  }
  function getResult(){
    return 'paused';
  }

  function validateFieldUpdate($fields){
    if(count($fields)){
      $this->errorMessage = 'You cannot update fields when pausing a contract';
      return false;
    }else{
      return true;
    }
  }

}
