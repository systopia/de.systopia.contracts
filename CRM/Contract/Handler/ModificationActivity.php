<?php

// This class is called after the creation of contract history activities by the
// API wrapper CRM_Contract_Wrapper_ModificationActivity

class CRM_Contract_Handler_ModificationActivity{

  public $errors = [];

  function __construct(){

    // The modification activity handler is a wrapper around the ModifyContract handler
    $this->contractHandler = new CRM_Contract_Handler_Contract();
  }

  function initialize($id){

    // Load the activity
    $this->activity = civicrm_api3('Activity', 'getsingle', ['id' => $id]);

    // Set the start state (this loads the contract's initial state into the the
    // handler)
    $this->contractHandler->setStartState($this->activity['source_record_id']);

    // Load the appropriate modification class for this activity so we can set
    // the desired end status.
    $this->modificationActivity = CRM_Contract_Utils::getModificationActivityFromId($this->activity['activity_type_id']);
    $params['id'] = $this->activity['source_record_id'];
    $params['status_id'] = $this->modificationActivity->getEndStatus();

    // Load up extra parameters for the change depending on the change
    switch($this->modificationActivity->getAction()){
      case 'update':
      case 'revive':
        if(isset($this->activity[CRM_Contract_Utils::contractToActivityCustomFieldId('membership_type_id')])){
          $params['membership_type_id'] = $this->activity[CRM_Contract_Utils::contractToActivityCustomFieldId('membership_type_id')];
        }
        if(isset($this->activity['campaign_id'])){
          $params['campaign_id'] = $this->activity['campaign_id'];
        }
        if(isset($this->activity[CRM_Contract_Utils::contractToActivityCustomFieldId('membership_payment.membership_recurring_contribution')])){
          $params[CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution')] = $this->activity[CRM_Contract_Utils::contractToActivityCustomFieldId('membership_payment.membership_recurring_contribution')];
        }
        break;
      case 'cancel':
        if(isset($this->activity[CRM_Contract_Utils::contractToActivityCustomFieldId('membership_cancellation.membership_cancel_reason')])){
          $params[CRM_Contract_Utils::getCustomFieldId('membership_cancellation.membership_cancel_reason')] = $this->activity[CRM_Contract_Utils::contractToActivityCustomFieldId('membership_cancellation.membership_cancel_reason')];
        }
        break;
    }
    $this->contractHandler->setParams($params);
  }

  function validateModification(){
    if(!in_array($this->contractHandler->startStatus, $this->modificationActivity->getStartStatuses())){
      $this->errors['invalid_start_state'] = "An '{$this->modificationActivity->getAction()}' cannot be carried out on a contract with a status of '{$this->contractHandler->startStatus}'";
    }
    $this->contractHandler->validateModification();
  }

  function isValidModification(){
    return !count($this->errors);
  }

  function getCompletedActivity(){
    return $this->completedActivity;
  }

  function modify(){
    $this->modifiedContract = civicrm_api3('Membership', 'create', $this->contractHandler->params);
    $this->completedActivity = civicrm_api3('Activity', 'create', ['id' => $this->activity['id'], 'status_id' => 'Completed']);
  }
}
