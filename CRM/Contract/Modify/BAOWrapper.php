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

  function pre($id, &$params){

    // retreive a snapshot of the membership before any changes are made
    $this->contractHandler->setStartMembership($id);

    // Params come in in weird formats - we need to santize them before handling
    // them
    $this->contractHandler->sanitizeParams($params);

    // Do various things (like force field values, set calculated fields, etc.)
    // bfore the contract is saved.
    $this->contractHandler->preProcessParams($params);
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

    // We also need to insanitize the params before we save the object
    // (yeah, I know!)
    $this->contractHandler->insanitizeParams($params);
  }

  function post($id){
    if($this->op == 'create'){
      $this->contractHandler->insertMissingParams($id);
    }
    $this->contractHandler->saveEntities();
  }
}
