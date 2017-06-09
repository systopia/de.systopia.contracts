<?php

/**
* This class wraps calls to the activity create API and passes them to the
* ModificationActivity handler unless they have a status of scheduled and a date in
* the future
**/

class CRM_Contract_Wrapper_ModificationActivity{

  private static $_singleton;

  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Wrapper_ModificationActivity();
    }
    return self::$_singleton;
  }

  public function pre($op, $params){

    if(isset($params['resume_date'])){
      $this->resumeDate = $params['resume_date'];
    }

    $this->skip = false;
    if(isset($params['skip_handler']) && $params['skip_handler']){
      $this->skip = true;
      $this->reset();
      return;
    }

    // ##### START OF FACT FINDING MISSION ##### //

    // If this is a create, the id will not be passed in params
    // If this is an edit or delete, the id will be passed

    // Set the operation
    $this->op = $op;

    // Get the start state and the contract id
    if($this->op == 'create'){
      $this->startState = [];
      $this->startStatus = '';
    }else{
      $this->startState = civicrm_api3('Activity', 'getsingle', ['id' => $params['id']]);
      $this->startStatus = civicrm_api3('OptionValue', 'getvalue', [ 'return' => "name", 'option_group_id' => "activity_status", 'value' => $this->startState['status_id']]);
      $this->contractId = $this->startState['source_record_id'];
      $this->checkActivityType($this->startState);
    }
  }

  public function post($id, $objectRef){

    if($this->skip){
      $this->reset();
      return;
    }

    // If this is a delete, the id will not be passed in params
    // If this is an edit or create, the id will be passed

    if($this->op == 'delete'){
      $this->endState = [];
      $this->endStatus = '';
    }else{
      $this->endState = civicrm_api3('Activity', 'getsingle', ['id' => $id]);
      $this->endStatus = civicrm_api3('OptionValue', 'getvalue', [ 'return' => "name", 'option_group_id' => "activity_status", 'value' => $this->endState['status_id']]);
      $this->contractId = $this->endState['source_record_id'];
      $this->checkActivityType($this->endState);
    }
    if($this->skip){
      $this->reset();
      return;
    }

    // ##### END OF FACT FINDING MISSION ##### //

    // By this point, we know that we are dealing with a modification activity,
    // and we know the type of operation; the start state, the end state and
    // a textual representation of the start and end statuses and the contract id

    // From here on in we execute certain actions depending data in and
    // transformations that have occured to the modification activity. Each
    // action should be well commented here. Many actions return from the
    // function, indicating that no further checks are necessary.

    // After if we still here after executing all actions that may have returned
    // us from the function, the default behaviour is to check the contract for
    // possible conflicts.

    // ACTION: If this was a create operation that has a status of scheduled and
    // an activity_date_time of 'now + 30 seconds' (we use a generous definition
    // of now in case the script is taking a while to execute) then attempt the
    // update immediatley and return from the script.
    //
    // Note that since we have disabled the out of the box
    // create forms for contract modification activities, the only time this is
    // likely to happen is via the contract.modify API, or the activity.create
    // API.
    //
    // Note also, that the contract update screens use the contract.modify
    // API so form submissions there will pass by this route.
    if(
      $this->op =='create' &&
      $this->endStatus == 'Scheduled' &&
      DateTime::createFromFormat('Y-m-d H:i:s', $this->endState['activity_date_time']) <= new DateTime('+15 seconds')
    ){

      // Get a handler to do the heavy lifting
      $handler = new CRM_Contract_Handler_Contract;

      // Set the initial state of the handler
      $handler->setStartState($this->endState['source_record_id']);
      $handler->setModificationActivity($this->endState);

      // Get the parameters of the change
      $contractParams = CRM_Contract_Handler_ModificationActivityHelper::getContractParams($this->endState);

      // If we are creating pause, we need pass the resume date through to
      // ensure that the resume activity is created as well
      if(isset($this->resumeDate)){
        $contractParams['resume_date'] = $this->resumeDate;
      }

      $handler->setParams($contractParams);
      if($handler->isValid()){
        $handler->modify();
        $this->reset();
        return;
      }else{
        throw new exception(implode($handler->getErrors(), ';'));
      }
    }

    // ACTION: If the status was changed to needs review, presume that this was
    // done intentionally, and do not trigger any further checks for conflicts
    if($this->startStatus == 'Needs Review' && ($this->endStatus == 'Scheduled' || $this->endStatus == 'Cancelled')){
      $this->reset();
      return;
    }

    $conflictHandler = new CRM_Contract_Handler_ModificationConflicts;
    $conflictHandler->checkForConflicts($this->contractId);
  }



  // This function checks to see whether the activity that has been wrapped is
  // relevant, i.e. is a modification activity
  function checkActivityType($activity){
    if(!in_array($activity['activity_type_id'], CRM_Contract_ModificationActivity::getModificationActivityTypeIds())){
      $this->skip = true;
    }
  }
  // It feels prudent to unset all values of this wrapper once we are finished
  // with it so ensure that if and when it is run multiple times in one
  // execution, it is not polluted with details from previous runs
  // information from previous
  function reset(){
    unset($this->op);
    unset($this->startState);
    unset($this->startStatus);
    unset($this->endState);
    unset($this->endStatus);
    unset($this->contractId);
  }

}
