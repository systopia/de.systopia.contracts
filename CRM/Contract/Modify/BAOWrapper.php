<?php

class CRM_Contract_Modify_BAOWrapper{

  private static $_singleton;

  private function __construct($op){
    $this->op = $op;
    $this->contractHandler = new CRM_Contract_Handler($op);
  }

  public static function singleton($op) {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Modify_BAOWrapper($op);
    }
    return self::$_singleton;
  }

  function pre($id, $params){
    $this->contractHandler->setStartMembership($id);
    $this->contractHandler->addProposedParams($params);
    if(!$this->contractHandler->isValidStatusUpdate()){
      throw new \Exception("Cannot update contract status from {$this->contractHandler->startStatus} to {$this->contractHandler->proposedStatus}.");
    }

    $this->contractHandler->generateActivityParams();

    if(!$this->contractHandler->isValidFieldUpdate()){
      throw new \CiviCRM_API3_Exception($this->contractHandler->errorMessage);
    }
    // TODO some membership status changes (e.g. to Cancelled or Paused) do not allow us to change membership fields. check to see that we are not trying to change membership fields if changing to these particular statuses
  }

  function post($id){
    if($this->op == 'create'){
      $this->contractHandler->insertMissingParams($id);
    }
    // Necessary only in the case of a new contract
    $this->contractHandler->saveEntities();
  }
}
