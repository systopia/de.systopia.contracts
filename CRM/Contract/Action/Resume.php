<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Action_Resume{

  function getValidStartStatuses(){
    return array('Paused');
  }

  function getEndStatus(){
    return 'Current';
  }

  function getActivityType(){
    return 'Contract_Resumed';
  }

  function getName(){
    return 'resume';
  }

  function isValidFieldUpdate($fields){
    return true;
  }

}
