<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Action_Cancel{

  function getValidStartStatuses(){
    return array('New', 'Current', 'Grace');
  }

  function getEndStatus(){
    return 'Cancelled';
  }

  function getActivityType(){
    return 'Contract_Cancelled';
  }

  function getAction(){
    return 'cancel';
  }
  function getResult(){
    return 'canceled';
  }

  function isValidFieldUpdate($fields){
    if(count($fields)){
      $this->errorMessage = 'You cannot update fields when cancelling a contract';
      return false;
    }else{
      return true;
    }
  }

}
