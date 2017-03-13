<?php

class CRM_Contract_Modify_APIWrapper{

  private static $_singleton;

  private function __construct(){
    $this->contractHandler = new CRM_Contract_Handler();
  }

  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Modify_APIWrapper();
    }
    return self::$_singleton;
  }

  function fromApiInput($apiRequest){

    // We only need to take action here if this is an edit
    // if(isset($apiRequest['params']['id'])){
    //
    //   $this->contractHandler->setStartMembership($apiRequest['params']['id']);
    //   $this->contractHandler->addProposedParams($apiRequest['params']);
    //
    //   if(!$this->contractHandler->isValidStatusUpdate()){
    //     throw new \CiviCRM_API3_Exception("Cannot update contract status from {$this->contractHandler->startStatus} to {$this->contractHandler->proposedStatus}.");
    //   }
    //
    //   if(!$this->contractHandler->isValidFieldUpdate()){
    //     throw new \CiviCRM_API3_Exception($this->contractHandler->errorMessage);
    //   }
    // }
    // $apiRequest['params']['options']['reload']=1;
    return $apiRequest;
  }

  function toApiOutput($apiRequest, $result){
    return $result;
  }

}
