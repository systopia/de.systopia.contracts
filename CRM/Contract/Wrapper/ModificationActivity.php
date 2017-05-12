<?php

/**
* This class wraps calls to the activity create API and passes them to the
* ModifyContract handler unless they have a status of scheduled and a date in
* the future
**/

class CRM_Contract_Wrapper_ModificationActivity{
  public function fromApiInput($apiRequest){


    return $apiRequest;
  }

  public function toApiOutput($apiRequest, $result){

    // Examine the activity
    $activity = $result['values'][$result['id']];
    if(
      // It is scheduled...
      ($activity['status_id'] == civicrm_api3('OptionValue', 'getvalue', ['return' => "value", 'option_group_id' => "activity_status", 'name' => "scheduled"])['result']) &&

      // ..and it is not scheduled for the future
      DateTime::createFromFormat('Ymdhis', $activity['activity_date_time']) <= new DateTime
    ){

      // Then attempt to modify the contract based on the activity now
      $handler = new CRM_Contract_Handler_ModificationActivity;
      $handler->initialize($activity['id']);
      // check that the contract modification this activity would produce is
      // valid.
      $handler->validateModification();

      // If there are no errors, make the change
      if($handler->isValidModification()){
        $handler->modify();
        return $handler->getCompletedActivity();
      }else{
        throw new exception(implode($handler->errors, ';'));
      }
    }

    // The API wrapper expects us to return the result (even if we haven't done
    // anything to it).
    return $result;
  }
}
