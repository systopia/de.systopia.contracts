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

    // Back fill parameters with the start state to create an end state, which comes
    // in handy in a few different places
    $this->endState = $this->params + $this->startState;
  }

  function setModificationActivity($activity){
    $this->modificationActivity = $activity;
    $this->modificationClass = CRM_Contract_ModificationActivity::findById($activity['activity_type_id']);

  }

  function isValid(){

    // If the modification class is set already, i.e. it we set it when we set
    // the modification activity, then check that the status change is valid
    if(isset($this->modificationClass)){
      if(!in_array($this->startStatus, $this->modificationClass->getStartStatuses())){
        $this->errors['status_id'] = "Cannot {$this->modificationClass->getAction()} a contract with status '{$this->startStatus}'";
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
      $this->errors['status_id'] = "You cannot update contract status from {$this->startStatus} to {$this->proposedStatus}.";
    }

    return !count($this->errors);
  }

  function getErrors(){
    return $this->errors;
  }

  function modify(){

    // calculate derived fields
    // Setting skip_handler to true  avoids us 'handling the already handled' call
    $params = $this->params;
    $params['skip_handler'] = true;
    civicrm_api3('Membership', 'create', $params);
    // civicrm_api3('Membership', 'create', $this->convertCustomIds($this->params));

    // Various tasks need to be carried out once the contract has been modified
    $this->postModify();
  }

  // Called by modify. Can also be called directly for those times when the
  // modification has been done already and you just want to do the things that need
  // to happen afterwards
  function postModify(){
    //update derived fields
    $this->updateDerivedFields();

    $this->calculateDeltas();

    if(isset($this->modificationActivity)){
      $this->updateModificationActivity();
    }else{
      $this->createModificationActivity();
    }
  }

  // Some fields in the contract are derived from other fields. This function
  // calculates those fields and updates the contract
  private function updateDerivedFields(){

    // We use end state rather than parameters, because if the value of
    // membership_payment.membership_recurring_contribution, then it won't be
    // available in the params

    // If the contract has a contribution,
    if($this->endState['membership_payment.membership_recurring_contribution']){
      $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $this->endState['membership_payment.membership_recurring_contribution']));
      $this->params['membership_payment.membership_annual'] = $this->calcAnnualAmount($contributionRecur);
      $this->params['membership_payment.membership_frequency'] = $this->calcPaymentFrequency($contributionRecur);
      $this->params['membership_payment.cycle_day'] = $contributionRecur['cycle_day'];

      //If this is a sepa payment, get the 'to' and 'from' bank account
      $sepaMandateResult = civicrm_api3('SepaMandate', 'get', array(
        'entity_table' => "civicrm_contribution_recur",
        'entity_id' => $contributionRecur['id']
      ));
      if($sepaMandateResult['count'] == 1){
        $sepaMandate = $sepaMandate['values'][$sepaMandate['id']];
        $this->params['membership_payment.from_ba'] = $this->getBankAccountIdFromIban($sepaMandate['iban']); // TODO I *THINK* we are waiting on Björn for this functionality - need to confirm
        $this->params['membership_payment.to_ba'] = $this->getBankAccountIdFromIban($this->getCreditorIban($sepaMandate['creditor_id']));
      }
    }

    // Since we have updated some parameters, we need to recalculate the end
    // state
    $this->endState = $this->params + $this->startState;

    $params = $this->convertCustomIds($this->params);
    $params['skip_handler'] = true;
    civicrm_api3('Membership', 'create', $params);
  }

  private function calculateDeltas(){
    foreach($this->endState as $key => $param){
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
    return $params;
  }

  private function getSubjectLine(){

    $deltas = array_intersect_key($this->deltas, array_flip($this->getMonitoredFields()));

    // We don't need to mention status_id in the subject line as it is implicit
    // in the activity type
    if(isset($deltas['status_id'])){
      unset($deltas['status_id']);
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
    $abbrevations['membership_payment.membership_recurring_contribution']='rc id';
    $abbrevations['membership_payment.membership_annual']='amt.';
    $abbrevations['membership_payment.membership_freq']='freq.';
    $abbrevations['membership_payment.from_ba']='member iban';
    $abbrevations['membership_payment.cycle_day']='cycle day';
    $abbrevations['membership_payment.payment_instrument']='payment method';

    switch($this->modificationClass->getAction()){
      case 'update':
      case 'revive':{
        $subjectLine = "id{$this->endState['id']}: ";
        foreach($deltas as $key => $delta){
          $changesText[] = "{$abbrevations[$key]} {$delta['old']} to {$delta['new']}";
        }
        $subjectLine .= implode(', ', $changesText);
        break;
      }
      case 'sign':
        $subjectLine = "id{$this->endState['id']}: ";
        foreach($deltas as $key => $delta){
          $additionsText[] = "{$abbrevations[$key]} {$delta['new']}";
        }
        $subjectLine .= implode(', ', $additionsText);
        break;
      case 'cancel':
        $subjectLine = "id{$this->endState['id']}: ";
        $cancelDate = new DateTime($this->modificationActivity['activity_date_time']);
        // $cancelText[] = 'cancel date '.$cancelDate->format('d/m/Y');
        $cancelText[] = 'cancel reason '.$this->modificationActivity[CRM_Contract_Utils::getCustomFieldId('contract_cancellation.contact_history_cancel_reason')];
        $subjectLine .= implode(', ', $cancelText);
        break;
      case 'pause':
        $resumeDate = DateTime::createFromFormat('Y-m-d', $this->params['resume_date']);
        $subjectLine = "id{$this->endState['id']}: resume scheduled {$resumeDate->format('d/m/Y')}";
        break;
      case 'resume':
        // var_dump($this->modificationActivity['activity_date_time']);
        $subjectLine = "id{$this->endState['id']}";
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

  function getModificationActivity(){
    return $this->modificationActivity;
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

    foreach(['join_date', 'start_date', 'end_date'] as $event){
      if(is_numeric($params[$event]) && strlen($params[$event]) == 14){
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

  function calcPaymentFrequency($contributionRecur){
    if($contributionRecur['frequency_unit'] == 'month' && $contributionRecur['frequency_interval'] == 1){
      return 12;
    }
    throw new Exception('Unkown payment frequency');
  }

  private function getBankAccountIds(){
    //Check if it is a sepa recurring contribution
    if($sepaMandate['count'] == 1){
      // var_dump($sepaMandate);
      // var_dump($this->activityFieldsByFullName['contract_updates.ch_from_ba']['id']);
        $this->getBankAccountIdFromIban($sepaMandate['values'][$sepaMandate['id']]['iban']);
      $this->activityParams[$this->activityFieldsByFullName['contract_updates.ch_to_ba']['id']] =
        $this->getBankAccountIdFromIban($this->getCreditorIban($sepaMandate['values'][$sepaMandate['id']]['creditor_id']));
    }
  }

  private function getBankAccountIdFromIban($iban){
    try{
      $result = civicrm_api3('BankingAccountReference', 'getsingle', array(
        'reference_type_id' => 'iban',
        'reference' => $iban,
      ));
    } catch(Exception $e){
      // TODO FOR BJORN
      // At the moment, if we can't find a bank account, we just return ''.
      // Instead, we should try and find an account id and be throwing an
      // exception when we can't
      return '';
      // throw new Exception("Could not find Banking account reference for IBAN {$iban}");
    }
    if(!$result['ba_id']){
      throw new Exception("Bank account ID not defined for Bank account reference with ID {$result['id']}");
    }
    return $result['ba_id'];
  }

  private function getCreditorIban($creditorId){
    try{
      $result = civicrm_api3('SepaCreditor', 'getsingle', array(
        'id' => $creditorId,
      ));
    } catch(Exception $e){
      throw new Exception("Could not find IBAN for SEPA creditor with Id {$creditorId}");
    }
    return $result['iban'];
  }

}
