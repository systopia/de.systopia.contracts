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

    // We set this to let the BAO wrapper know that we've handled it at the API
    // layer and there is no need to handle it again
    $apiRequest['params']['handledByApi'] = true;

    // Set the ID if it has been passed
    if(isset($apiRequest['params']['id'])){
      $id = $apiRequest['params']['id'];
      $this->newMembership = false;
    }else{
      $id = null;

      // Setting this here is convenient as it helps distinguish between new
      // memberships and existing membership updates where we aren't changing
      // the status
      $apiRequest['params']['status_id'] = 'Current';

      // We set this to remind toApiOutput to insert missing params (that we
      // don't know at this point)
      $this->newMembership = true;
    }

    // retreive a snapshot of the membership before any changes are made
    $this->contractHandler->setStartMembership($id);

    // Do various things (like force field values, set calculated fields, etc.)
    // bfore the contract is saved.
    $this->contractHandler->preProcessParams($apiRequest['params']);
    $this->contractHandler->addProposedParams($apiRequest['params']);
    if(!$this->contractHandler->isValidStatusUpdate()){
      throw new \Exception("Cannot update contract status from {$this->contractHandler->startStatus} to {$this->contractHandler->proposedStatus}.");
    }

    $this->contractHandler->generateActivityParams();
    $this->contractHandler->validateFieldUpdate();

    if(count($this->contractHandler->action->errors)){
      throw new \Exception(current($this->contractHandler->action->errors));
    }

    return $apiRequest;
  }

  function toApiOutput($apiRequest, $result){

    if($this->newMembership){
      $this->contractHandler->insertMissingParams($result['id']); //TODO - check this is being set
    }
    $this->contractHandler->saveEntities();
    if(isset($this->contractHandler->activityHistoryId)){
      $result['links']['activity_history_id'] = $this->contractHandler->activityHistoryId;
    };
    return $result;
  }

}
