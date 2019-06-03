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
class CRM_Contract_ModificationActivity_Sign extends CRM_Contract_ModificationActivity{

  function getStartStatuses(){
    return array('');
  }

  function getEndStatus(){
    return 'Current'; // TODO Will need to change to Current if we remove New
  }

  function getActivityType(){
    return 'Contract_Signed';
  }

  function getAction(){
    return 'sign';
  }

  function getGerund(){
    return 'siging';
  }

  function getResult(){
    return 'signed';
  }

  function validateExtra(){
    $this->checkPaymentNotAssociatedWithAnotherContract();
  }

}
