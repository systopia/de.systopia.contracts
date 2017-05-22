<?php

/**
* This class wraps calls to the activity create API and passes them to the
* ModificationActivity handler unless they have a status of scheduled and a date in
* the future
**/

class CRM_Contract_Wrapper_ModificationActivity{
  public function fromApiInput($apiRequest){
    return $apiRequest;
  }

  public function toApiOutput($apiRequest, $result){

    // API get the activity again to ensure that we get custom data
    $this->activity = civicrm_api3('Activity', 'getsingle', ['id' => $result['id']]);

    $this->params = $apiRequest['params'];
    if(
      // It is scheduled...
      ($this->activity['status_id'] == civicrm_api3('OptionValue', 'getvalue', ['return' => "value", 'option_group_id' => "activity_status", 'name' => "scheduled"])) &&

      // ..and it is not scheduled for the future
      DateTime::createFromFormat('Y-m-d H:i:s', $this->activity['activity_date_time']) <= new DateTime
    ){
      // Then modify the contract based on the activity

      // Get a handler to do the heavy lifting
      $handler = new CRM_Contract_Handler_Contract;

      // Set the initial state of the handler
      $handler->setStartState($this->activity['source_record_id']);

      $handler->setModificationActivity($this->activity);

      // Pass the parameters of the change
      $handler->setParams($this->getContractParams());
      if($handler->isValid()){
        $handler->modify();
        return $handler->getModificationActivity();
      }else{
        throw new exception(implode($handler->getErrors(), ';'));
      }
    }
    return $result;
  }

  // Extract the parameters from the modification activities and put them into a
  // format that the contract handler can understand
  public function getContractParams(){
    $params['id'] = $this->activity['source_record_id'];
    $modificationClass = CRM_Contract_ModificationActivity::findById($this->activity['activity_type_id']);
    $params['status_id'] = $modificationClass->getEndStatus();
    switch($modificationClass->getAction()){
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
      case 'pause':
      // We need to pass the resume date through
        $params['resume_date'] = $this->params['resume_date'];
    }

    return $params;
  }
}






// Load up extra parameters for the change depending on the change
