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
class CRM_Contract_ModificationActivity_Pause extends CRM_Contract_ModificationActivity{

  protected $whitelist = ['resume_date'];
  protected $required = ['resume_date'];

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

function getGerund(){
  return 'pausing';
}

  function getResult(){
    return 'paused';
  }


}
