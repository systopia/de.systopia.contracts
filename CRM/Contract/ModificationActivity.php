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

  static $modificationActivityClasses = [
    'CRM_Contract_ModificationActivity_Cancel',
    'CRM_Contract_ModificationActivity_Pause',
    'CRM_Contract_ModificationActivity_Resume',
    'CRM_Contract_ModificationActivity_Revive',
    'CRM_Contract_ModificationActivity_Sign',
    'CRM_Contract_ModificationActivity_Update',
  ];


  function getActivityTypeId(){
    $result = civicrm_api3('OptionValue', 'getvalue', [
      'return' => "value",
      'option_group_id' => "activity_type",
      'name' => $this->getActivityType()
    ]);
    return $result;
  }

  static function findByAction($action){
    foreach (self::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      if($action == $activityClass->getAction()){
        return $activityClass;
      }
    }
    throw new Exception("Could not find a valid modification activity for $action");

  }

  static function findById($id){
    $name = civicrm_api3('OptionValue', 'getsingle', ['option_group_id' => 'activity_type', 'value' => $id, 'return' => 'name'])['name'];
    foreach (self::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      if($name == $activityClass->getActivityType()){
        return $activityClass;
      }
    }
  }

  static function findByStatusChange($startStatus, $endStatus){
    foreach (self::$modificationActivityClasses as $class) {
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

  static function getModificationActivityTypeIds(){
    foreach (self::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      $activityTypes[] = $activityClass->getActivityType();
    }
    foreach(civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'activity_type',
      'name' => ['IN' => $activityTypes],
      'return' => 'value'
    ])['values'] as $activityType){
      $activityTypeIds[] = $activityType['value'];
    }
    return $activityTypeIds;
  }

  static function getModificationActivityTypeLabels(){
    foreach (self::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      $activityTypes[] = $activityClass->getActivityType();
    }
    foreach(civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'activity_type',
      'name' => ['IN' => $activityTypes],
      'return' => ['value', 'label']
    ])['values'] as $activityType){
      $activityTypeIds[$activityType['value']] = $activityType['label'];
    }
    return $activityTypeIds;
  }


  function validateParams($params, $start){
    $this->params = $params;
    $this->start = $start;
    unset($this->params['status_id']);
    unset($this->params['id']);
    if(isset($this->allowed)){
      $this->checkAllowed();
    }
    $this->checkRequired();
    return !count($this->errors);

  }

  function checkAllowed(){
    foreach($this->params as $key => $param){
      if(!in_array($key, $this->allowed)){
        if(isset($this->start[$key]) && $this->params[$key] != $this->start[$key]){
          $this->errors[$key] = "Cannot update '{$key}' when {$this->getGerund()} a contract";
        }
      }
    }
  }
  function checkRequired(){
    if(isset($this->required)){
      foreach($this->required as $required){
        if(!isset($this->params[$required]) || !$this->params[$required]){
          $this->errors[$required] = "'{$required}' is required when {$this->getGerund()} a contract";
        }
      }
    }
  }

  function getErrors(){
    return $this->errors;
  }


}
