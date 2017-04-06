<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Form_Mandate extends CRM_Core_Form{

  function preProcess(){
  }

  function buildQuickForm(){
    $this->addButtons([
      array('type' => 'cancel', 'name' => 'Cancel'), // since Cancel looks bad when viewed next to the Cancel action
      array('type' => 'submit', 'name' => 'Create')
    ]);
  }


  function postProcess(){
  }
}
