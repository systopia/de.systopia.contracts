<?php

// This class is called after the creation of contract history activities by the
// API wrapper CRM_Contract_Wrapper_ModificationActivity

class CRM_Contract_Handler_Contract{

  // The start state of the contract
  public $startState = [];
  public $startStatus = '';

  // Parameters that are changed
  public $params = [];

  // The end state (this is calculated by merging the start state and the params so
  // it can be used before the contract is saved.
  public $endState = [];

  public $errors = [];

  public $deltas = [];

  function setStartState($id = null){
    if(isset($id)){
      $this->startState = $this->normalise(civicrm_api3('Membership', 'getsingle', ['id' => $id]));
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
    $params = $this->normalise($params);
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

  function setEndState($id){

    // Unfortunatley the custom data has not been saved by this point, so we
    // update the $endState data with $params to catch any custom data that
    // is scheduled to change
    $this->endState = $this->normalise(civicrm_api3('Membership', 'getsingle', ['id' => $id])) + $this->params;

    // We also set the id in the params array as this is used for calculating
    // field updates. Without this, we would create a new contract instead of
    // updating an existing one when updating the derived fields of a new
    // contract
    $this->params['id'] = $id;
  }

  function setModificationActivity($activity){
    $this->modificationActivity = $activity;
    $this->modificationClass = CRM_Contract_ModificationActivity::findById($activity['activity_type_id']);

  }

  function getModificationActivity(){
    return $this->modificationActivity;
  }

  function isValid($errorsToIgnore = []){

    // If the modification class is set already, i.e. it we set it when we set
    // the modification activity, then check that the status change is valid
    if(isset($this->modificationClass)){
      if(!in_array($this->startStatus, $this->modificationClass->getStartStatuses())){
        $this->errors['status_id'] = "Cannot {$this->modificationClass->getAction()} a contract when its status is {$this->startStatus}";
      }
    }else{
      $this->modificationClass = CRM_Contract_ModificationActivity::findByStatusChange($this->startStatus, $this->proposedStatus);
    }

    // If we have a modification class, then validate the parameters that have
    // been passed and set any errors.
    if($this->modificationClass){
      $this->modificationClass->validateParams($this->params, $this->startState);
      $this->errors += $this->modificationClass->getErrors();
    }else{
      // If by this stage, we have been unable to find a valid modificationClass
      // this status change should not be allowed.
      $this->errors['status_id'] = "You cannot update contract status from '{$this->startStatus}' to '{$this->proposedStatus}'.";
    }

    // Used, for instance, when we want to process a handle a pause without specifying a resume
    foreach($errorsToIgnore aS $e){
      unset($this->errors[$e]);
    }

    return !count($this->errors);
  }

  function getErrors(){
    return $this->errors;
  }

  function modify(){

    // Updates to the mandate should happen here. You have access to
    // $this->startState (the original state of the mandate $this->params (the
    // changes that have been requested (these were taken from
    // $this->modificationActivity. I think you want to make the changes to the
    // mandate before any changes to the contract. If you want to do anything
    // after the contract has been updated, do it after setEndState. Or maybe
    // add it to postModify. Let me know if you want to discuss.

    // adjust mandate
    CRM_Contract_SepaLogic::updateSepaMandate($this->startState['id'], $this->startState, $this->params, $this->modificationActivity);

    // Setting skip_handler to true  avoids us 'handling the already handled' call
    $params = $this->convertCustomIds($this->params);
    $params['skip_handler'] = true;
    civicrm_api3('Membership', 'create', $params);
    $this->setEndState($params['id']);

    // Various tasks need to be carried out once the contract has been modified
    $this->postModify();
  }

  // Called by modify. Can also be called directly for those times when the
  // modification has been done already and you just want to do the things that need
  // to happen afterwards
  function postModify(){
    //update derived fields
    $this->updateDerivedFields();

    if(isset($this->modificationActivity)){
      $this->updateModificationActivity();
    }else{
      $this->createModificationActivity();
    }

    // Check for conflicts in the scheduled contract modifications
    $conflictHandler = new CRM_Contract_Handler_ModificationConflicts;
    $conflictHandler->checkForConflicts($this->endState['id']);
  }

  // Some fields in the contract are derived from other fields. This function
  // calculates those fields and updates the contract
  private function updateDerivedFields(){

    // We use end state rather than parameters, because if the value of
    // membership_payment.membership_recurring_contribution has stayed the same
    // then it won't be available in the params
    $params = $this->endState;
    // If the contract has a contribution,
    if($this->endState['membership_payment.membership_recurring_contribution']){
      $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $this->endState['membership_payment.membership_recurring_contribution']));
      $params['membership_payment.membership_annual']    = $this->calcAnnualAmount($contributionRecur);
      $params['membership_payment.membership_frequency'] = $this->calcPaymentFrequency($contributionRecur);
      $params['membership_payment.cycle_day'] = $contributionRecur['cycle_day'];

      //If this is a sepa payment, get the 'to' and 'from' bank account
      $sepaMandateResult = civicrm_api3('SepaMandate', 'get', array(
        'entity_table' => "civicrm_contribution_recur",
        'entity_id' => $contributionRecur['id']
      ));
      if($sepaMandateResult['count'] == 1){
        $sepaMandate = $sepaMandateResult['values'][$sepaMandateResult['id']];
        $params['membership_payment.from_ba'] = CRM_Contract_BankingLogic::getOrCreateBankAccount($sepaMandate['contact_id'], $sepaMandate['iban'], $sepaMandate['bic']);
        $params['membership_payment.to_ba']   = CRM_Contract_BankingLogic::getCreditorBankAccount();
      }
    }
    $params = $this->convertCustomIds($params);

    $params['skip_handler'] = true;

    $contract = civicrm_api3('Membership', 'create', $params);

    // We reload the contract to update the end state.
    $contract = civicrm_api3('Membership', 'getsingle', ['id' => $params['id']]);
    $this->endState = $this->normalise($contract);
  }

  private function createModificationActivity(){

    // Create the activity
    $activityResult = civicrm_api3('Activity', 'create', $this->getModificationActivityParams());

    // Store the updates so they can be returned )
    $this->modificationActivity = $activityResult['values'][$activityResult['id']];

  }

  private function updateModificationActivity(){

    // Update the activity
    $activityParams = $this->getModificationActivityParams();
    $activityParams['id'] = $this->modificationActivity['id'];
    $activityResult = civicrm_api3('Activity', 'create', $activityParams);

    // And store it for later use (TODO necessary? if so, add note to specify
    // how)
    $this->modificationActivity = $activityResult['values'][$activityResult['id']];
  }

  private function getModificationActivityParams(){

    $params['status_id'] = 'Completed';
    $params['activity_type_id'] = $this->modificationClass->getActivityType();

    $this->calculateDeltas();
    $params['subject'] = $this->getSubjectLine();

    $session = CRM_Core_Session::singleton();
    if(!$sourceContactId = $session->getLoggedInContactID()){
      $sourceContactId = 1;
    }
    $params['source_contact_id'] = $sourceContactId;
    // If the annual amount changed, calculate the difference
    if(isset($this->deltas['membership_payment.membership_annual'])){
      $params[CRM_Contract_Utils::getCustomFieldId('contract_updates.ch_annual_diff')] =
      $this->deltas['membership_payment.membership_annual']['new'] - $this->deltas['membership_payment.membership_annual']['old'];
    }
    // Translate between contract and activity keys
    foreach($this->endState as $key => $value){
      if(isset(CRM_Contract_Utils::$ContractToModificationActivityField[$key])){
        $activityKey = CRM_Contract_Utils::$ContractToModificationActivityField[$key];

        // If it is a custom field, get the custom field id
        if(strpos($activityKey, '.')){
          $activityKey = CRM_Contract_Utils::getCustomFieldId($activityKey);
        }
        $params[$activityKey] = $value;
      }
    }

    // We need to skip the modification activity handler, otherwise, it will
    // create another membership.
    $params['skip_handler'] = true;

    return $params;
  }

  private function calculateDeltas(){
    foreach($this->endState as $key => $value){
      if(isset($this->startState[$key])){
        if($this->startState[$key] != $this->endState[$key]){
          $this->deltas[$key]['old']=$this->startState[$key];
          $this->deltas[$key]['new']=$this->endState[$key];
        }
      }else{
        $this->deltas[$key]['old']='';
        $this->deltas[$key]['new']=$this->endState[$key];
      }
    }
  }

  private function getSubjectLine(){

    $deltas = array_intersect_key($this->deltas, array_flip($this->getMonitoredFields()));

    // We don't need to mention status_id in the subject line as it is implicit
    // in the activity type
    if(isset($deltas['status_id'])){
      unset($deltas['status_id']);
    }

    // Not interested in showing the recurring contribution ID
    if(isset($deltas['membership_payment.membership_recurring_contribution'])){
      unset($deltas['membership_payment.membership_recurring_contribution']);
    }
    // Create user friendly delta text
    if(isset($deltas['membership_type_id']['old']) && $deltas['membership_type_id']['old']){
      $deltas['membership_type_id']['old'] = civicrm_api3('MembershipType', 'getvalue', [ 'return' => "name", 'id' => $deltas['membership_type_id']['old']]);
    }
    if(isset($deltas['membership_type_id']['new']) && $deltas['membership_type_id']['new']){
      $deltas['membership_type_id']['new'] = civicrm_api3('MembershipType', 'getvalue', [ 'return' => "name", 'id' => $deltas['membership_type_id']['new']]);
    }
    if(isset($deltas['membership_payment.payment_instrument']['old']) && $deltas['membership_payment.payment_instrument']['old']){
      civicrm_api3('OptionValue', 'getvalue', ['return' => "label", 'value' => $deltas['membership_payment.payment_instrument']['old'], 'option_group_id' => "payment_instrument" ]);
    }
    if(isset($deltas['membership_payment.payment_instrument']['new']) && $deltas['membership_payment.payment_instrument']['new']){
      civicrm_api3('OptionValue', 'getvalue', ['return' => "label", 'value' => $deltas['membership_payment.payment_instrument']['new'], 'option_group_id' => "payment_instrument" ]);
    }

    $abbrevations['membership_type_id']='type';
    $abbrevations['membership_payment.membership_annual']='amt.';
    $abbrevations['membership_payment.membership_frequency']='freq.';
    $abbrevations['membership_payment.to_ba']='gp iban';
    $abbrevations['membership_payment.from_ba']='member iban';
    $abbrevations['membership_payment.cycle_day']='cycle day';
    $abbrevations['membership_payment.payment_instrument']='payment method';

    $changesText = [];

    switch($this->modificationClass->getAction()){
      case 'update':
      case 'revive':{
        $subjectLine = "id{$this->endState['id']}: ";
        foreach($deltas as $key => $delta){
          $changesText[] = "{$abbrevations[$key]} {$delta['old']} to {$delta['new']}";
        }
        $subjectLine .= implode(' AND ', $changesText);
        break;
      }
      case 'sign':
        $subjectLine = "id{$this->endState['id']}: ";
        foreach($deltas as $key => $delta){
          $additionsText[] = "{$abbrevations[$key]} {$delta['new']}";
        }
        $subjectLine .= implode(' AND ', $additionsText);
        break;
      case 'cancel':
        $subjectLine = "id{$this->endState['id']}: ";
        $cancelDate = new DateTime($this->modificationActivity['activity_date_time']);
        $cancelText[] = 'cancel reason '.$this->modificationActivity[CRM_Contract_Utils::getCustomFieldId('contract_cancellation.contact_history_cancel_reason')];
        $subjectLine .= implode(' AND ', $cancelText);
        break;
      case 'pause':
        if(isset($this->params['resume_date'])){
          $resumeDate = DateTime::createFromFormat('Y-m-d', $this->params['resume_date']);
          $subjectLine = "id{$this->endState['id']}: resume scheduled {$resumeDate->format('d/m/Y')}";
        }else{
          $subjectLine = "id{$this->endState['id']}.";
        }
        break;
      case 'resume':
        // var_dump($this->modificationActivity['activity_date_time']);
        $subjectLine = "id{$this->endState['id']}.";
        break;
    }
    return $subjectLine;
  }

  private function getMonitoredFields(){
    return [
      'membership_type_id',
      'status_id',
      'membership_payment.membership_recurring_contribution',
      'membership_payment.membership_annual',
      'membership_payment.membership_frequency',
      'membership_payment.from_ba',
      'membership_payment.to_ba',
      'membership_payment.cycle_day',
      'membership_payment.payment_instrument',
    ];
  }

  private function convertCustomIds($params){

    foreach($params as $key => $param){
      if(strpos($key, '.')){
        unset($params[$key]);
        $params[CRM_Contract_Utils::getCustomFieldId($key)] = $param;
      }
    }
    return $params;
  }

  private function normalise($params){
    // If a custom data field has been passed in the $params['custom'] element
    // which is not also in $params move it to params
    if(isset($params['custom'])){
      foreach($params['custom'] as $key => $custom){
        if(!isset($params['custom_'.$key])){
          $params['custom_'.$key] = current($custom)['value'];
        }
      }
    }

    // If a custom field has two underscores, remove the last underscore
    foreach($params as $key => $param){
      if(preg_match("/(custom_\d+)_\d+/", $key, $matches)){
        unset($params[$key]);
        $params[$matches[1]] = $param;
      }
    }

    // Get a definitive list of core and custom fields
    foreach(civicrm_api3('membership', 'getfields')['values'] as $mf){
      if(isset($mf['where']) || isset($mf['extends'])){
        $coreAndCustomFields[] = $mf['name'];
      }
    }
    // Allow people to pass a resume date as this is required when pausing a contract
    $coreAndCustomFields[] = 'resume_date';


    // Remove any params that are not core and custom fields
    foreach($params as $key => $param){
      if(!in_array($key, $coreAndCustomFields)){
        unset($params[$key]);
      }
    }

    // Convert from custom_N format to custom_group.custom_field format
    foreach($params as $key => $param){
      if(substr($key, 0,7) == 'custom_'){
        $params[CRM_Contract_Utils::getCustomFieldName($key)] = $param;
        unset($params[$key]);
      }
    }

    // For some reason, when the end date is null, it is passed as the string
    // 'null'. TODO: File an issue in core.
    if(isset($params['end_date']) && $params['end_date'] == 'null'){
      $params['end_date'] = null;
    }

    foreach(['join_date', 'start_date', 'end_date'] as $event){
      if(isset($params[$event]) && is_numeric($params[$event]) && strlen($params[$event]) == 14){
        $date = DateTime::createFromFormat('Ymdhis', $params[$event]);
        $params[$event] = $date->format('Y-m-d');
      }
    }
    return $params;
  }

  private function calcAnnualAmount($contributionRecur){
    $frequencyUnitTranslate = array(
      'day' => 365,
      'week' => 52,
      'month' => 12,
      'year' => 1
    );
    return number_format($contributionRecur['amount'] * $frequencyUnitTranslate[$contributionRecur['frequency_unit']] / $contributionRecur['frequency_interval'], 2, '.', '');
  }

  private function calcPaymentFrequency($contributionRecur){
    if($contributionRecur['frequency_unit'] == 'month' && $contributionRecur['frequency_interval'] == 1){
      return 12;
    }
    //TODO
    return 1;
    throw new Exception('Unkown payment frequency');
  }

}
