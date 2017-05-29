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
    $params = $apiRequest['params'];

    // Get the status to use in conditionals
    $status = civicrm_api3('OptionValue', 'getvalue', [ 'return' => "name", 'option_group_id' => "activity_status", 'value' => $this->activity['status_id']]);

    // Check to see whether the modification should be executed now (scheduled
    // and not in the future), and if so, execute it now
    if($status == 'Scheduled' && DateTime::createFromFormat('Y-m-d H:i:s', $this->activity['activity_date_time']) <= new DateTime('+5 seconds')){
      // Get a handler to do the heavy lifting
      $handler = new CRM_Contract_Handler_Contract;

      // Set the initial state of the handler
      $handler->setStartState($this->activity['source_record_id']);
      $handler->setModificationActivity($this->activity);

      // Get the parameters of the change
      $contractParams = CRM_Contract_Handler_ModificationActivityHelper::getContractParams($this->activity);
      // If we are creating pause, we need pass the resume date through to
      // ensure that the resume activity is created as well
      if($handler->modificationClass->getAction() == 'pause'){
        $contractParams['resume_date'] = $params['resume_date'];
      }
      $handler->setParams($contractParams);
      if($handler->isValid()){
        $handler->modify();
        return $handler->getModificationActivity();
      }else{
        throw new exception(implode($handler->getErrors(), ';'));
      }
    // Else if it is scheduled and (given the last if statement) in the future
    }elseif($status == 'Scheduled'){

      // Check how many scheduled activities there are in the future for this
      // contract
      var_dump();
      $handler = new CRM_Contract_Handler_ModificationConflicts;
      $handler->setContract($this->activity['source_record_id']);
      $handler->checkForConflicts($params['ignored_review_activities']);
    }

    return $result;
  }
}
