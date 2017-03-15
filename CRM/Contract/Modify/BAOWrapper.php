<?php

class CRM_Contract_Modify_BAOWrapper{

  private static $_singleton;

  private $skip = false;

  private function __construct($op){
    $this->contractHandler = new CRM_Contract_Handler();
  }

  public static function singleton($op) {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Modify_BAOWrapper($op);
      self::$_singleton->op = $op;
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

    $this->contractHandler->validateFieldUpdate();
    $this->contractHandler->validateFieldUpdate();
    if(count($this->contractHandler->action->errors)){
      throw new \Exception(current($this->contractHandler->action->errors));
    }
  }

  function post($id){
    if($this->op == 'create'){
      $this->contractHandler->insertMissingParams($id);
    }
    $this->contractHandler->saveEntities();
  }
}
