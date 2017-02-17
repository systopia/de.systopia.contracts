<?php

class CRM_Contract_HistoryApiWrapper{

  private static $_singleton;

  /**
   * I've made the contsructor private but TBH it might be that sometimes it's
   * useful/necessary to create a new class, esp. if we start updating many
   * contracts in the same script, e.g. through an import. Might be another
   * patter we can use.
   */
  private function __construct(){
    $this->contractHandler = new CRM_Contract_Handler();
  }

  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_HistoryApiWrapper();
    }
    return self::$_singleton;
  }

  function fromApiInput($apiRequest){

    // force a reload to help with debugging
    // TODO decide whether we want to turn this off by default for performance reasons
    if(!isset($apiRequest['params']['options']['reload'])){
      $apiRequest['params']['options']['reload'] = 1;
    }

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
    return $apiRequest;
  }

  function toApiOutput($apiRequest, $result){

    $this->contractHandler->setEndMembership($result['id']);
    $this->contractHandler->recordActivity();

    return $result;
  }

}
