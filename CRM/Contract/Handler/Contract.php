<?php

// This class is called after the creation of contract history activities by the
// API wrapper CRM_Contract_Wrapper_ModificationActivity

class CRM_Contract_Handler_Contract{

  public $startState = [];

  public $params = [];

  public $errors = [];


  function setStartState($id = null){
    if(isset($id)){
      $this->startState = civicrm_api3('Membership', 'getsingle', ['id' => $id]);
    }else{
      $this->startState = [];
    }

    // Set start status
    if(isset($this->startState['status_id'])){
      $this->startStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $this->startState['status_id']))['name'];
    }else{
      $this->startStatus = '';
    }
  }

  function isNewContract(){
    $this->isNewContract = true;
  }

  function setParams($params){

    // Set proposed status
    if(isset($params['status_id'])){
      if(is_numeric($params['status_id'])){
        $this->proposedStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $params['status_id']))['name'];
      }else{
        $this->proposedStatus = $params['status_id'];
      }
    }else{
      $this->proposedStatus =  $this->startStatus;
    }

    $this->params = $params;


  }

  function setModificationActivity($activity){
    $this->modificationActivity = $activity;
  }


  function isValid(){


    // First, find a modification class to handle this update based on the
    // status change.
    $this->modificationClass = CRM_Contract_ModificationActivity::getModificationClassFromStatusChange($this->startStatus, $this->proposedStatus);

    // If you can't find a modification class, then the status change must be
    // invalid. Report this as an error.
    if($this->modificationClass){
      $this->modificationClass->validateParams($this->params);

      $this->errors += $this->modificationClass->getErrors();
    }else{
      $this->errors['status_id'] = "You cannot update contract status from {$this->startStatus} to {$this->proposedStatus}.";
    }

    return !count($this->errors);
  }

  function getErrors(){
    return $this->errors;
  }

  function modify(){

    // Call the API to modify contract
    // Passing skip_handler avoids us handling this 'already handled' call to the
    // membership API
    $this->params['skip_handler'] = true;
    civicrm_api3('Membership', 'create', $this->params);

    // Various tasks need to be carried out once the contract has been modified
    $this->postModify();
  }

  // Called by modify
  // Can be called directly for those times when the modification has been done
  // and you just want to create / update the activity.
  function postModify(){
    var_dump($this->source.' is calling post modify');
    if(!$this->modificationActivity){

      var_dump($this->source.' needs to create a modification activity');
      // reverse engineer modification activity if none is present
      $this->modificationActivity = '???';
    }

    $this->calculateDeltas();
    $this->populateDerivedFields();
    $this->updateSubjectLine();

  }

  private function calculateDeltas(){

  }
  private function populateDerivedFields(){

  }
  private function updateSubjectLine(){

  }

  function getModificationActivity(){
    return $this->modificationActivity;
  }

}
