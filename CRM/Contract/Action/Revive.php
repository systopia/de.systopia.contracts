<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Action_Revive extends CRM_Contract_Action{

  function getValidStartStatuses(){
    return array('Cancelled');
  }

  function getEndStatus(){
    return 'Current';
  }

  function getActivityType(){
    return 'Contract_Revived';
  }

  function getAction(){
    return 'revive';
  }
  function getResult(){
    return 'revived';
  }

  function validateFieldUpdate($fields){
    return true;
  }
}
