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
class CRM_Contract_ModificationActivity_NoStatusChange extends CRM_Contract_ModificationActivity{

  public function __contruct($status_id) {
    $this->status_id = $status_id;
  }

  function getStartStatuses(){
    return array($this->status_id);
  }

  function getEndStatus(){
    return $this->status_id;
  }

  function getActivityType(){
    return NULL; // no activity
  }

  function getAction(){
    return 'none';
  }

  function getGerund(){
    return 'idling';
  }

  function getResult(){
    return 'idled';
  }
}
