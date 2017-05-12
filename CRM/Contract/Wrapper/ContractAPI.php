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

  public function fromApiInput($apiRequest){

    // Set the start state
    if(isset($apiRequest['params']['id'])){
      $this->handler->setStartState($apiRequest['params']['id']);
    }

    // // If this is contract update originates from a contract history activity
    // // then we don't need to do anything else before the contract is updated,
    // // so we return.
    // if(isset($apiRequest['params']['contract_history_activity_id'])){
    //   var_dump('via contract history activity');
    //   return $apiRequest;
    // }

    // If we are still here, we assume that the contract has been edited
    // directly. Hence we need to check that this is a valid update and abort if
    // not.
    $this->handler->setParams($apiRequest['params']);

    // Validate the modification
    $this->handler->validateModification();


    return $apiRequest;
  }

  public function toApiOutput($apiRequest, $result){
    return $result;
  }
}
