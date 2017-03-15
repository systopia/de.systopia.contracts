<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * This class handles updates to contracts, checking that an update is valid and
 * ensuring that important changes are recorded.
 */
class CRM_Contract_Handler{

  /**
   * Whether this contract has had any significant changes, i.e. whether any
   * $monitoredFields have changed.
   * @var Boolean
   */
  var $significantChanges = 0;

  /**
   * When one or more of these fields have changed, we should record a
   * Contract_Update activity (set when constructed)
   */
  var $monitoredFields = array();

  /**
   * The various actions that can happen to contracts
   */
  static $actions = array(
    'CRM_Contract_Action_Cancel',
    'CRM_Contract_Action_Pause',
    'CRM_Contract_Action_Resume',
    'CRM_Contract_Action_Revive',
    'CRM_Contract_Action_Sign',
    'CRM_Contract_Action_Update'
  );

  function __construct(){
    $CustomField = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'membership_payment', 'name' => 'membership_recurring_contribution'));
    $this->contributionRecurCustomField = 'custom_'.$CustomField['id'];

    $this->monitoredFields=array(
      'membership_type_id',
      'status_id',
      'membership_payment.membership_recurring_contribution',
      'membership_payment.membership_frequency',
      'membership_payment.membership_customer_id',
    );
  }

  /**
   * This records the membership status at the START of a change (sequence)
   * and associated objects
   */
  function setStartMembership($id){
    if($id){
      $this->startMembership = civicrm_api3('Membership', 'getsingle', array('id' => $id));

      // Why TF does the api return contact references as names, not IDs?
      // (i.e. why does it return something that you cannot pass back to create
      // for an update?! As a work around, I am going to overwriting custom_#
      // with the value from custom_#_id.
      //
      // I am also unsetting the custom_#_# fields as they are also causing
      // issues when combined with the contact reference fields

      $dialoggerCustomField = 'custom_'.civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'membership_general', 'name' => 'membership_dialoger'))['id'];
      if(isset($this->startMembership[$dialoggerCustomField])){
        $this->startMembership[$dialoggerCustomField] = $this->startMembership[$dialoggerCustomField.'_id'];
      }

      $this->saneifyCustomFieldIds($this->startMembership);


      if(isset($this->startMembership[$this->contributionRecurCustomField]) && $this->startMembership[$this->contributionRecurCustomField]){
        $this->startContributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $this->startMembership[$this->contributionRecurCustomField]));
      }else{
        $this->startMembership[$this->contributionRecurCustomField] = '';
        $this->startContributionRecur = null;
      }
      $this->startStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $this->startMembership['status_id']))['name'];

      // Ensure that the start membership status is refered to by the name, not the id.
      $this->startMembership['status_id'] = $this->startStatus;
    }else{
      $this->startMembership = null;
      $this->startStatus = null;
      $this->startContributionRecur = null;
    }
  }

  function addProposedParams($params){

    // TODO ensure that we skip the new status FIXME
    if($params['status_id'] == 1 || $params['status_id'] == 'New'){
      $params['status_id'] = 'Current';
    };

    // If a proposed status hasn't been supplied, then presume it will stay the
    // same as it was before. We need it to be set when working out if this is a
    // valid status change.
    if(!$params['status_id']){
      $params['status_id'] = $this->startStatus;
    };

    //Do some extra processing of the status_id to make it easy to work with
    if(is_numeric($params['status_id'])){
      $params['status_id'] = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $params['status_id']))['name'];
    }else{
      $params['status_id'] = $params['status_id'];
    }

    // Deal with the fact that custom data can be presented in different ways depending on the object
    if(isset($params['custom']) && is_array($params['custom'])){
      foreach($params['custom'] as $key => $fieldToAdd){
        if(!isset($params['custom_'.$key])){
          $params['custom_'.$key] = current($fieldToAdd)['value'];
        }
      }
    }

    // Massage the proposed recurring contribution field since we need this when
    // working out what has changed and what should be recorded.
    // If it has been set, i.e. proposed
    if(isset($params[$this->contributionRecurCustomField])){
      // and it has been set to a specific ID
      if($params[$this->contributionRecurCustomField]){
        // Then retreive the propsed recurring contribution object
        $this->proposedContributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $params[$this->contributionRecurCustomField]));
      }else{
        // Else set it to none.
        $this->proposedContributionRecur = null;
      }
    }else{
      // If it hasn't been pased, then presume that is the same as it was at the
      // beginning.
      $this->proposedContributionRecur = $this->startContributionRecur;
    }

    $this->proposedStatus = $params['status_id'];
    $this->proposedParams = $params;
    $this->setAction(); // At this point, we can set the action as we know what it will be
  }

  function setAction(){
    if($class = $this->lookupStatusUpdate($this->startStatus, $this->proposedStatus)['class']){
      $this->action = new $class;

      // We should always treat the signing action as significant as we want to
      // record an activity. NOTE: This may now not be necessary as we treat
      // status changes as significant.
      if($this->action->getAction() == 'sign'){
        $this->significantChanges = 1;
      }
    //If we can't find an action, report an error
    }else{
      if($this->startStatus == 'Cancelled' && $this->proposedStatus == 'Cancelled'){
        $this->errors['status_id'] = "Cannot update a contract if the status is $this->startStatus.";
      }else{
        $this->errors['status_id'] = "Cannot update contract status from '$this->startStatus' to '$this->proposedStatus'";
      }
    }
  }

  function lookupStatusUpdate($startStatus, $endStatus){
    if(!$startStatus){
      $startStatus='';
    }
    // NOTE for initial launch: limit transitions to starting and ending with $validStatuses
    $validStatuses = array('', 'Current', 'Cancelled');

    if(!isset($this->statusChangeIndex)){
      foreach(self::$actions as $name){
        $action = new $name;
        foreach($action->getValidStartStatuses() as $status){
          if(method_exists($action, 'getValidEndStatuses')){
            foreach($action->getValidEndStatuses() as $validEndStatus){
              // NOTE for initial launch: limit transitions to starting and ending with $validStatuses
              if(in_array($status, $validStatuses) && in_array($validEndStatus, $validStatuses)){
                $this->statusChangeIndex[] = array(
                  'class' => $name,
                  'startStatus' => $status,
                  'endStatus' => $validEndStatus
                );
              }
            }
          }else{
            // NOTE for initial launch: limit transitions to starting and ending with $validStatuses
            if(in_array($status, $validStatuses) && in_array($action->getEndStatus(), $validStatuses))
            $this->statusChangeIndex[] = array(
              'class' => $name,
              'startStatus' => $status,
              'endStatus' => $action->getEndStatus()
            );
          }
        }
      }
    }
    // NOTE for initial launch: only a couple of membership status changes are allowed
    foreach($this->statusChangeIndex as $change){
      if($change['startStatus'] == $startStatus && $change['endStatus'] == $endStatus){
        return $change;
      }
    }
  }

  function isValidStatusUpdate(){
    $this->setAction();
    if(isset($this->errors['status_id'])){
      return false;
    }else{
      return true;
    }
  }

  function validateFieldUpdate(){
    if(!$this->action){
      throw new Exception('No action defined for this contract update.');
    }

    $modifiedFields = $this->getModifiedFields($this->startMembership, $this->proposedParams);
    $this->action->validateFieldUpdate($modifiedFields);
    $this->errors = $this->action->errors;
  }

  /**
   * This is where we set activity parameters so that entities can be saved.
   * @return [type] [description]
   */
  function generateActivityParams(){

    // set basic activity params
    $this->activityParams = array(
      'source_record_id' => $this->startMembership['id'],
      'activity_type_id' => $this->action->getActivityType(),
      'subject' => "Contract {$this->startMembership['id']} {$this->action->getResult()}", // A bit superfluous with most actions
      'status_id' => 'Completed',
      'medium_id' => $this->getMedium(),
      'target_id'=> $this->startMembership['contact_id'], // TODO might this have changed?
    );

    // set the source contact id //TODO check is this is robust enough
    $session = CRM_Core_Session::singleton();
    if(!$this->activityParams['source_contact_id'] = $session->getLoggedInContactID()){
      $this->activityParams['source_contact_id'] = 1;
    }

    // add further fields as required by different actions
    if(in_array($this->action->getAction(), array('resume', 'update', 'revive', 'sign'))){
      $this->setUpdateParams();
    }elseif($this->action->getAction() == 'cancel'){
      $this->setCancelParams();
    }

    // add campaign params if they have changed
    $this->activityParams['campaign_id'] =
      isset($this->proposedParams['campaign_id']) ?
      $this->proposedParams['campaign_id'] :
      $this->startMembership['campaign_id'];
  }

  function setUpdateParams(){

    // Set activity params

    $modifiedFields = $this->getModifiedFields($this->startMembership, $this->proposedParams);
    if($this->action->getAction() == 'update'){
      $this->activityParams['subject'] .= ": ".implode(', ', $modifiedFields);
    }
    $contractUpdateCustomFields = $this->translateCustomFields('contract_updates');

    // If a contributionRecurCustomField has been passed in the parameters
    if(isset($this->proposedParams[$this->contributionRecurCustomField])){
      $newAnnualMembershipAmount = $this->calcAnnualAmount($this->proposedContributionRecur);
      $oldAnnualMembershipAmount = $this->calcAnnualAmount($this->startContributionRecur);
      $this->activityParams[$contractUpdateCustomFields['ch_annual_diff']] = $newAnnualMembershipAmount - $oldAnnualMembershipAmount;
      $this->activityParams[$contractUpdateCustomFields['ch_annual']] = $newAnnualMembershipAmount;
      $finalContributionRecur = $this->proposedContributionRecur;
    }else{
      $this->activityParams[$contractUpdateCustomFields['ch_annual_diff']] = 0;
      $this->activityParams[$contractUpdateCustomFields['ch_annual']] = $this->calcAnnualAmount($this->startContributionRecur);
      $finalContributionRecur = $this->startContributionRecur;
    }

    $this->activityParams[$contractUpdateCustomFields['ch_recurring_contribution']] = $finalContributionRecur['id'];
    $translateFromContributionRecurFreqToContractFreq = array(
      'month' => 1,
      'year' => 12
    );

    $this->activityParams[$contractUpdateCustomFields['ch_frequency']] =
      isset($finalContributionRecur['frequency_unit']) ?
      $translateFromContributionRecurFreqToContractFreq[$finalContributionRecur['frequency_unit']] : '';

    $sepaMandate = civicrm_api3('SepaMandate', 'get', array(
      'entity_table' => "civicrm_contribution_recur",
      'entity_id' => $finalContributionRecur['id'],
    ));

    if($sepaMandate['count'] == 1){
      $this->activityParams[$contractUpdateCustomFields['ch_from_ba']] =
        $this->getBankAccountIdFromIban($sepaMandate['values'][$sepaMandate['id']]['iban']);
      $this->activityParams[$contractUpdateCustomFields['ch_to_ba']] =
        $this->getBankAccountIdFromIban($this->getCreditorIban($sepaMandate['values'][$sepaMandate['id']]['creditor_id']));
    }

    // set membership params
    $membershipPaymentCustomFields = $this->translateCustomFields('membership_payment');

    $this->membershipParams[$membershipPaymentCustomFields['membership_frequency']] =
      $this->activityParams[$contractUpdateCustomFields['ch_frequency']];

    $this->membershipParams['id'] = $this->startMembership['id'];
  }

  function setCancelParams(){
    $cancelCustomFields = $this->translateCustomFields('contract_cancellation');
    $this->activityParams[$cancelCustomFields['contact_history_cancel_reason']] = $this->submitted['contract_history_cancel_reason'];
  }

  /**
   * Called after the contract has been saved to save other entities if
   * necessary. This presumes (but doesn't check) that
   * $this->generateActivityParams() has been run.
   */
  function saveEntities(){
    // This should only be called if significant changes have been made
    if($this->significantChanges){
      $activity = civicrm_api3('Activity', 'create', $this->activityParams);
    }

    //if we are changing the recurring contribution associated with this
    //contract a new ContributionRecur, then we'll need to update the
    // recurring contribution with a reference to the new membership
    if(
      isset($this->proposedParams[$this->contributionRecurCustomField]) &&
      $this->proposedParams[$this->contributionRecurCustomField] &&
      ($this->proposedParams[$this->contributionRecurCustomField] != $this->startMembership[$this->contributionRecurCustomField])
    ){
      // Need to work out what transaction id to assign
      $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array(
        'id' => $this->proposedParams[$this->contributionRecurCustomField]
      ));
      // If recuring contribution doesn't have a transaction ID that is suitable for this contract, we need to create one
      if(!isset($contributionRecur['trxn_id']) || strpos($contributionRecur['trxn_id'], "CONTRACT-{$this->startMembership['id']}") !== 0){
        $this->contributionRecurParams['trxn_id'] = $this->assignNextTransactionId($this->startMembership['id']);
        $this->contributionRecurParams['id'] = $this->proposedParams[$this->contributionRecurCustomField];
        $contributionRecur = civicrm_api3('ContributionRecur', 'create', $this->contributionRecurParams);
      }
    }
  }

  /**
   * This is used when we a creating a new membership since we couldn't set
   * these values until we knew the ID of the membership we created.in_array
   */
  function insertMissingParams($id){
    $this->setStartMembership($id);
    $this->startMembership['id'] = $id;
    $this->generateActivityParams();
    $this->membershipParams['id'] = $id;
    $this->membershipParams['status_id'] = 2; // set to current (skip new) TODO Delete new
  }

  function assignNextTransactionId($contractId){
    $contributionRecurs = civicrm_api3('ContributionRecur', 'get', array(
      'trxn_id' => array('LIKE' => "CONTRACT-{$contractId}%"),
    ));
    if($contributionRecurs['values']){
      foreach($contributionRecurs['values'] as $v){
        $tids[] = $v['trxn_id'];
      }
      $tids = preg_filter('/CONTRACT\-\d+-(\d+)/', '$1', $tids);
      if($tids){
        return "CONTRACT-{$contractId}-".(string)(max($tids) + 1);
      }else{
        return  "CONTRACT-{$contractId}-2";
      }
    }else{
      return  "CONTRACT-{$contractId}";
    }
  }

  function setMedium($medium){
    return $this->medium;
  }

  function getMedium(){
    return $this->medium = 1;
  }

  function calcAnnualAmount($contributionRecur){
    if(!$contributionRecur){
      return 0;
    }
    $frequencyUnitTranslate = array(
      'day' => 365,
      'week' => 52,
      'month' => 12,
      'year' => 1
    );
    return $contributionRecur['amount'] * $frequencyUnitTranslate[$contributionRecur['frequency_unit']] / $contributionRecur['frequency_interval'];
  }

  function getBankAccountIdFromIban($iban){
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

  function getCreditorIban($creditorId){
    try{
      $result = civicrm_api3('SepaCreditor', 'getsingle', array(
        'id' => $creditorId,
      ));
    } catch(Exception $e){
      throw new Exception("Could not find IBAN for SEPA creditor with Id {$creditorId}");
    }
    return $result['iban'];
  }

  /**
   * getModifiedFields compares submitted fields to orginal fields and looks at
   * which ones have changed.
   *
   * If significant fields have changed, we record the changes in a contract
   * history activity.
   *
   * This is much more convoluted that I'd like it to be because we are using
   * the parameters submitted with the API or the form, not an API call.
   */
  function getModifiedFields($from, $to){

    // Retreive metadata on relevant core and custom fields so we can work out
    // what has changed
    $customGroupNames = array('membership_cancellation', 'membership_payment','membership_general');
    $customGroups = civicrm_api3('CustomGroup', 'get', array( 'name' => array('IN' => $customGroupNames)))['values'];
    $membershipCustomFields = array();
    foreach($customGroupNames as $customGroup){
      foreach(civicrm_api3('CustomField', 'get', array( 'custom_group_id' => $customGroup))['values'] as $field){
        $membershipCustomFields['custom_'.$field['id']] = $field;
      };
    }
    foreach(civicrm_api3('Membership', 'getfields', array( 'api_action' => 'get'))['values'] as $field){
      $membershipCoreFields[$field['name']] = $field;
    }

    $this->saneifyCustomFieldIds($to);
    $modifiedFields = array();
    // Start off with a definitive list of all membership core and custom fields
    // (whether they have been defined or not). Relying on membership.get (which
    // is what we have stored in $from) isn't robust enough as some fields are
    // only returned when they are set.

    foreach(array_keys($membershipCoreFields) + array_keys($membershipCustomFields) as $field){

      // We can't be thinking about modifying it unless it is defined in $to
      if(isset($to[$field])){
        // Dates in CiviCRM are passed in various formats. This is an attempt to
        // convert dates passed as params to the same format that the api produces
        if(isset($to[$field]) && in_array($field, array('join_date', 'start_date', 'end_date'))){
          $to[$field] = date('Y-m-d', strtotime($to[$field]));
        }

        // If the field has been modified means either...
        if(
          // ...the field was not set in from but is set in to and has a value
          (!isset($from[$field]) && $to[$field]) ||
          // ... OR the field was set in from and to and is different
          (isset($from[$field]) && $from[$field] != $to[$field])
        ){
          $modifiedFields[$field]['id'] = $field;
          if(isset($membershipCustomFields[$field])){
            $modifiedFields[$field]['name'] = $customGroups[$membershipCustomFields[$field]['custom_group_id']]['name'].'.'.$membershipCustomFields[$field]['name'];
            $modifiedFields[$field]['title'] = $membershipCustomFields[$field]['label'];
          }else{
            $modifiedFields[$field]['name'] = $field;
            $modifiedFields[$field]['title'] = $membershipCoreFields[$field]['title'];
          }
          $modifiedFields[$field]['from'] = isset($from[$field]) ? $from[$field] : '';
          $modifiedFields[$field]['to'] = $to[$field];
          if(array_search($modifiedFields[$field]['name'], $this->monitoredFields) !== false){
            $this->significantChanges = true;
            $modifiedFields[$field]['monitored'] = true;
          }else{
            $modifiedFields[$field]['monitored'] = false;
          }
        }
      }
    }
    return $modifiedFields;
  }

  function translateCustomFields($customGroup, $key = 'name'){
    $result = civicrm_api3('CustomField', 'get', array( 'sequential' => 1, 'custom_group_id' => $customGroup ));
    foreach($result['values'] as $v){
      $translateCustomFields[$v[$key]] = 'custom_'.$v['id'];
    }
    return $translateCustomFields;
  }
  /**
   * Sometimes you get custom_20_7876 but you want custom_20.
   * This function does that for you
   */
  function saneifyCustomFieldIds(&$array){
    foreach($array as $k => $v){
      if(preg_match("/(custom_\d+)_\-?\d+$/", $k, $matches)){
        unset($array[$matches[0]]);
        $array[$matches[1]] = $v;
      }
    }
  }
}
