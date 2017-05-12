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

  function setParams($params){
    $this->params = $params;

    // Set proposed status
    if(isset($this->params['status_id'])){
      if(is_numeric($this->params['status_id'])){
        $this->proposedStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $this->params['status_id']))['name'];
      }else{
        $this->proposedStatus = $this->params['status_id'];
      }
    }else{
      $this->proposedStatus =  $this->startStatus;
    }

  }

  function validateModification(){

    // First, establish whether the status change is valid by checking whether
    // the start status and proposed end status matches those that are defined
    // in the CRM_Contract_ModificationActivity_* classes

    // If the start status is set then get the status name

    // If the a status_id param has been passed, then ensure it is the string,
    // not the id.


    if(!CRM_Contract_Utils::isValidStatusChange($this->startStatus, $this->proposedStatus)){
    }else{
      $this->errors[] = "You cannot update contract status from {$this->startStatus} to {$this->proposedStatus}.";
    }

  }
}
