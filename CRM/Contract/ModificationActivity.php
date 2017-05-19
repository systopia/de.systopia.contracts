<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_ModificationActivity{

  var $errors = array();

  function getActivityTypeId(){
    return $result = civicrm_api3('OptionValue', 'getvalue', [
      'return' => "value",
      'option_group_id' => "activity_type",
      'name' => $this->getActivityType()
    ]);
  }

  function getModificationClassFromStatusChange($startStatus, $endStatus){
    foreach (CRM_Contract_Utils::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      if(
        in_array($startStatus, $activityClass->getStartStatuses()) &&
        $endStatus == $activityClass->getEndStatus()
      ){
        return $activityClass;
      }
    }
    return false;
  }

  function validateParams($params){
    $this->params = $params;
    unset($this->params['status_id']);
    unset($this->params['id']);
    if($this->allowed){
      $this->checkAllowed();
    }
    $this->checkRequired();
    // The only fields that should be allowed to be updated when cancelling a
    // contract are
    return !count($this->errors);

  }

  function checkAllowed(){

    foreach($this->params as $key => $param){
      if(!in_array($key, $this->allowed)){
        $this->errors[$key] = "Cannot update '{$key}' when a contract is {$this->getResult()}";
      }
    }
  }
  function checkRequired(){
    foreach($this->required as $required){
      if(!isset($this->params[$required])){
        $this->errors[$required] = "'{$required}' is required when a contract is {$this->getResult()}";
      }
    }
  }

  function getErrors(){
    return $this->errors;
  }

}
