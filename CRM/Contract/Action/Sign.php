<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Action_Sign{

  function getValidStartStatuses(){
    return array('');
  }

  function getEndStatus(){
    return 'New'; // TODO Will need to change to Current if we remove New
  }

  function getActivityType(){
    return 'Contract_Signed';
  }

  function getName(){
    return 'sign';
  }
}
