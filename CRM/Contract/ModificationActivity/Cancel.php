<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_ModificationActivity_Cancel extends CRM_Contract_ModificationActivity{

  protected $allowed = ['membership_cancellation.membership_cancel_reason'];

  protected $required = ['membership_cancellation.membership_cancel_reason'];

  function getStartStatuses(){
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

  function getGerund(){
    return 'cancelling';
  }

  function getResult(){
    return 'canceled';
  }
}
