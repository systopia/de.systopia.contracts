<?php

/**
* This wrapper listens for updates to contracts via the membership API and
* These updates may come in via
*
* - a direct call to the the membership.create API
* - via the activity.create API
* - via the contract.modify API that wraps the activity.create API
*
* By default, it checks to see if the changes are significant. If so, it
* 'reverse engineers' a contract modification activity. This behaviour can be
* turned off by passing a 'skip_create_modification_activity' parameter. This
* is necessary when the API is called via the
* CRM_Contract_Handler_Contract::usingActivityData method as otherwise we
* would create duplicate modify contract activities.
*/
class CRM_Contract_Wrapper_MembershipBAO{

  private static $_singleton;

  private function __construct(){
    $this->handler = new CRM_Contract_Handler_Contract;
  }

  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Wrapper_MembershipBAO();
    }
    return self::$_singleton;
  }

  public function pre($op, $id, $params){
    $this->skip = $params['skip_handler'];
    if($this->skip){
      return;
    }

    // Initialise handler as appropriate
    if($op == 'create'){
      $this->handler->isNewContract();
    }elseif($op == 'edit'){
      $this->handler->setStartState($id);
    }else{
      return;
    }
    $this->handler->setParams($this->normaliseParams($params));


    if(!$this->handler->isValid()){
      throw new Exception('Invalid contract modification: '.implode(', ', $this->handler->getErrors()));
    }
  }

  public function post(){
    if($this->skip){
      return;
    }

    // At this point the change has happened, so we skip the modify and go
    // straight to postModify();
    $this->handler->postModify();
  }

  // Custom data can be passed in different ways. This function tries to
  // normalise the structure.
  private function normaliseParams($params){

    // If a custom data field has been passed in the $params['custom'] element
    // which is not also in $params move it to params
    if(isset($params['custom'])){
      foreach($params['custom'] as $key => $custom){
        if(!isset($params['custom_'.$key])){
          $params['custom_'.$key] = current($custom)['value'];
        }
      }
    }

    // Get a definitive list of core and custom fields
    foreach(civicrm_api3('membership', 'getfields')['values'] as $mf){
      if(isset($mf['where']) || isset($mf['extends'])){
        $coreAndCustomFields[] = $mf['name'];
      }
    }
    // Allow people to pass a resume date as this is required when pausing a contract
    $coreAndCustomFields[] = 'resume_date';


    // Remove any params that are not core and custom fields
    foreach($params as $key => $param){
      if(!in_array($key, $coreAndCustomFields)){
        unset($params[$key]);
      }
    }

    // Convert from custom_N format to custom_group.custom_field format
    foreach($params as $key => $param){
      if(substr($key, 0,7) == 'custom_'){
        $params[CRM_Contract_Utils::getCustomFieldName($key)] = $param;
        unset($params[$key]);
      }
    }

    return $params;
  }
}
