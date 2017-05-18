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
class CRM_Contract_Wrapper_ContractAPI{

  private static $_singleton;

  private function __construct(){
    $this->handler = new CRM_Contract_Handler_Contract();
  }

  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Wrapper_ContractAPI();
    }
    return self::$_singleton;
  }

  public function pre($op, $id, $params){

    // Initialise handler as appropriate
    if($op == 'create'){
      $this->handler->isNewContract();
    }elseif($op == 'edit'){
      $this->handler->setStartState($id);
    }else{
      return;
    }
    $this->handler->setParams($this->normaliseParams($params));

    if(!$this->handler->isValid){
      throw new Exception('Invalid contract modification: '.implode(';', $this->handler->getErrors()));
    }
  }

  public function post(){

    // At this point the change has happened, so we skip the modify and go
    // straight to postModify();
    $this->handler->postModify();
  }

  // Custom data can be passed in different ways. This function tries to
  // normalise the structure.
  private function normaliseParams($params){
    if(isset($params['custom'])){
      foreach($params['custom'] as $key => $fieldToAdd){
        if(!isset($params['custom_'.$key])){
          $params['custom_'.$key] = current($fieldToAdd)['value'];
        }
      }
    }
    return $params;
  }
}
