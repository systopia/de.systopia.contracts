<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Action_Resume extends CRM_Contract_Action{

  function getValidStartStatuses(){
    return array('Paused');
  }

  function getEndStatus(){
    return 'Current';
  }

  function getActivityType(){
    return 'Contract_Resumed';
  }

  function getAction(){
    return 'resume';
  }
  function getResult(){
    return 'resumed';
  }

  function validateFieldUpdate($fields){
    return true;
  }

}
