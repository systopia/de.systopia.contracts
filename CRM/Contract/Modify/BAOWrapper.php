<?php

class CRM_Contract_Modify_BAOWrapper{

  private static $_singleton;

  private $skip = false;

  private function __construct($op){
    $this->contractHandler = new CRM_Contract_Handler();
  }

  public static function singleton($op) {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Modify_BAOWrapper();
      self::$_singleton->op = $op;
    }
    return self::$_singleton;
  }

  function pre($id, $params){
    if(isset($params['skip_wrapper']) && $params['skip_wrapper']){
      $this->skip = true;
      return;
    }
    $this->contractHandler->setStartMembership($id);
    $this->contractHandler->addProposedParams($params);
    if(!$this->contractHandler->isValidStatusUpdate()){
      throw new \Exception("Cannot update contract status from {$this->contractHandler->startStatus} to {$this->contractHandler->proposedStatus}.");
    }

    $this->contractHandler->generateActivityParams();

    if(!$this->contractHandler->isValidFieldUpdate()){
      throw new \Exception($this->contractHandler->errorMessage);
    }
    // TODO some membership status changes (e.g. to Cancelled or Paused) do not allow us to change membership fields. check to see that we are not trying to change membership fields if changing to these particular statuses
  }

  function post($id){
    if($this->skip == true){
      return;
    }
    // var_dump($this->contractHandler->membershipParams);
    if($this->op == 'create'){
      $this->contractHandler->insertMissingParams($id);
      // var_dump($this->contractHandler->membershipParams);
    }
    $this->contractHandler->saveEntities();
    civicrm_api3('Membership', 'create', array('id'=>'134', 'custom_18'=>'98', 'skip_wrapper' => true));
    // var_dump($this->contractHandler->membershipParams);exit;
  }
}
