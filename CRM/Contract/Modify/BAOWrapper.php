<?php

class CRM_Contract_Modify_BAOWrapper{

  private static $_singleton;

  private function __construct(){
    $this->contractHandler = new CRM_Contract_Handler();
  }

  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Modify_BAOWrapper();
    }
    return self::$_singleton;
  }

  function pre($params){

    // We only need to take action here if
    if(isset($apiRequest['params']['id'])){
      $this->contractHandler->setStartMembership($apiRequest['params']['id']);
      $this->contractHandler->addProposedParams($apiRequest['params']);
      if(!$this->contractHandler->isValidStatusUpdate()){
        throw new \CiviCRM_API3_Exception("Cannot update contract status from {$this->contractHandler->startStatus} to {$this->contractHandler->desiredEndStatus}.");
      }
      if(!$this->contractHandler->isValidFieldUpdate()){
        //TODO Better way to use exceptions here?
        throw new \CiviCRM_API3_Exception($this->contractHandler->errorMessage);
      }
      // TODO some membership status changes (e.g. to Cancelled or Paused) do not allow us to change membership fields. check to see that we are not trying to change membership fields if changing to these particular statuses
    }
  }

  function post($id){

    $this->contractHandler->setEndMembership($id);
    $this->contractHandler->recordActivity();

  }
}
