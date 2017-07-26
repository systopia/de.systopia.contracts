<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
* This class wraps calls to the activity create API and passes them to the
* ModificationActivity handler unless they have a status of scheduled and a date in
* the future
**/

class CRM_Contract_Wrapper_ModificationActivity{

  private static $recursion = 0;
  private static $_singleton;

  /**
   * this is a singleton, but it will be destroyed when
   * calling the post method
   */
  public static function one_shot_singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Wrapper_ModificationActivity();
    }
    return self::$_singleton;
  }

  public function pre($op, &$params){
    self::$recursion += 1;
    if (self::$recursion > 1) {
      error_log("WARNING: CONTRACT ACTIVITY RECURSION DEPTH: " . self::$recursion);
    }

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
      $this->contractId = isset($this->startState['source_record_id']) ? $this->startState['source_record_id'] : null;
      $this->checkActivityType($this->startState);
    }

    //Create a subject line for scheduled activities
    if($this->op == 'create' && in_array($params['activity_type_id'], CRM_Contract_ModificationActivity::getModificationActivityTypeIds())){
      $params['subject'] = $this->getScheduledSubjectLine($params);
    }
  }



  public function post($id, $objectRef){
    self::$recursion -= 1;

    // destroy singleton after post command, caused problems
    self::$_singleton = NULL;

    // Return early if we can.
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
      $this->contractId = isset($this->endState['source_record_id']) ? $this->endState['source_record_id'] : null;
      $this->checkActivityType($this->endState);
    }

    // If this is not a contract modification activity (checkActivityType), return
    // We can't do this in pre when the op is create.
    if($this->skip){
      $this->reset();
      return;
    }

    // ##### END OF FACT FINDING MISSION ##### //

    // By this point, we know that we are dealing with a modification activity,
    // and we know the type of operation; the start state, the end state and
    // a textual representation of the start and end statuses and the contract id

    // If the status was changed to needs review, presume that this was
    // done intentionally, and do not trigger any further checks for conflicts
    if($this->startStatus == 'Needs Review' && ($this->endStatus == 'Scheduled' || $this->endStatus == 'Cancelled')){
      $this->reset();
      return;
    }

    // If we still here check the contract for possible conflicts.
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

  function getScheduledSubjectLine($params){

    foreach($params as $key => $param){
      if(substr($key, 0,7) == 'custom_'){
        $params[CRM_Contract_Utils::getCustomFieldName($key)] = $param;
        unset($params[$key]);
      }
    }
    $deltas = [];

    $ma = CRM_Contract_ModificationActivity::findById($params['activity_type_id']);

    switch($ma->getAction()){
      case 'update':
      case 'revive':{
        if(isset($params['contract_updates.ch_annual'])){
          $deltas[] = 'amt. to '.$params['contract_updates.ch_annual'];
        }
        if(isset($params['contract_updates.ch_frequency'])){
          $deltas[] = 'freq. to '.civicrm_api3('OptionValue', 'getvalue', ['return' => "label", 'value' => $params['contract_updates.ch_frequency'], 'option_group_id' => "payment_frequency" ]);
        }
        if(isset($params['contract_updates.ch_to_ba'])){
          $deltas[] = 'gp iban to '.CRM_Contract_BankingLogic::getIBANforBankAccount($params['contract_updates.ch_to_ba']);
        }
        if(isset($params['contract_updates.ch_from_ba'])){
          $deltas[] = 'member iban to '.CRM_Contract_BankingLogic::getIBANforBankAccount($params['contract_updates.ch_from_ba']);
        }
        if(isset($params['contract_updates.ch_membership_type'])){
          $deltas[] = 'type to '.civicrm_api3('MembershipType', 'getvalue', [ 'return' => "name", 'id' => $params['contract_updates.ch_membership_type']]);
        }
        if(isset($params['contract_updates.ch_cycle_day'])){
          $deltas[] = 'cyle day to '.$params['contract_updates.ch_cycle_day'];
        }
        if(isset($params['contract_updates.ch_payment_instrument'])){
          $deltas[] = 'payment method to '.$params['contract_updates.ch_payment_instrument'];
        }
        $subject = "id{$params['source_record_id']}: ".implode(' AND ', $deltas);
        break;
      }
      case 'cancel':
        $subject = "id{$params['source_record_id']}: ";
        $cancelText[] = 'cancel reason '.civicrm_api3('OptionValue', 'getvalue', [ 'return' => "label", 'value' => $params['contract_cancellation.contact_history_cancel_reason']]);
        $subject .= implode(' AND ', $cancelText);
        break;
      case 'pause':
        if(isset($params['resume_date'])){
          $resumeDate = DateTime::createFromFormat('Y-m-d', $params['resume_date']);
          $subject = "id{$params['source_record_id']}: resume scheduled {$resumeDate->format('d/m/Y')}";
        }else{
          $subject = "id{$params['source_record_id']}.";
        }
        break;
      case 'resume':
        // var_dump($this->modificationActivity['activity_date_time']);
        $subject = "id{$params['source_record_id']}.";
        break;
    }

    return $subject;
  }

  /**
   * It feels prudent to unset all values of this wrapper once we are finished
   * with it so ensure that if and when it is run multiple times in one
   * execution, it is not polluted with details from previous runs
   * information from previous
   */
  function reset(){
    unset($this->op);
    unset($this->startState);
    unset($this->startStatus);
    unset($this->endState);
    unset($this->endStatus);
    unset($this->contractId);
  }

}
