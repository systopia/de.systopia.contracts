<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * @deprecated
 */
class CRM_Contract_ModificationActivity_Revive extends CRM_Contract_ModificationActivity{

  function getStartStatuses(){
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

  function getGerund(){
    return 'reviving';
  }

  function getResult(){
    return 'revived';
  }

  function validateFieldUpdate($fields){
    return true;
  }
}
