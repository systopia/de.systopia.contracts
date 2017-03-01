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

  }

  /**
   * this records the membership status at the START of a change (sequence)
   *  and associated objects
   */
  function setStartMembership($id){
    $this->startMembership = civicrm_api3('Membership', 'getsingle', array('id' => $id));
    if($this->startMembership[$this->contributionRecurCustomField]){
      $this->startContributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $this->startMembership[$this->contributionRecurCustomField]));
    }
    $this->startStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $this->startMembership['status_id']))['name'];
  }
  /**
   * this records the membership status at the END of a change (sequence)
   *  and associated objects
   */
  function setEndMembership($id){
    $this->endMembership = civicrm_api3('Membership', 'getsingle', array('id' => $id));
    if($this->endMembership[$this->contributionRecurCustomField]){
      $this->endContributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $this->endMembership[$this->contributionRecurCustomField]));
    }
    $this->endStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $this->endMembership['status_id']))['name'];
    $this->setAction(); // At this point, we can set the action as we know what it will be
  }

  function setAction(){
    $class = $this->lookupStatusUpdate($this->startStatus, $this->proposedStatus)['class'];
    $this->action = new $class;
  }

  function addProposedParams($params){

    // If the status hasn't been supplied, then set it to the same as it was before (since we need it to do the look up)
    if(!$params['status_id']){
      $params['status_id'] = $this->startStatus;
    };

    //Do some extra processing of the status_id to make it easy to work with
    if(is_numeric($params['status_id'])){
      $this->proposedStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $params['status_id']))['name'];
    }else{
      $this->proposedStatus = $this->proposedParams['status_id'];
    }
    if($params[$this->contributionRecurCustomField]){
      $this->proposedContributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $params[$this->contributionRecurCustomField]));
    }
    $this->proposedStatus = $params['status_id'];
    $this->proposedParams = $params;
    $this->setAction(); // At this point, we can set the action as we know what it will be
  }

  /**
   * semantic wrapper around lookupStatusChange
   */
  function isValidStatusUpdate(){
    if($this->lookupStatusUpdate($this->startStatus, $this->proposedStatus)){
      return true;
    }else{
      return false;
    }
  }

  function isValidFieldUpdate(){
    $this->startStatus;
    $this->proposedStatus;
    $class = $this->lookupStatusUpdate($this->startStatus, $this->proposedStatus)['class'];
    $this->action = new $class;
    $modifiedFields = $this->getModifiedFieldKeys($this->startMembership, $this->proposedParams);
    $valid = $this->action->isValidFieldUpdate($modifiedFields);
    if(!$valid){
      $this->errorMessage = $this->action->errorMessage;
      return false;
    }else{
      return true;
    }

  }

  private function lookupStatusUpdate($startStatus, $endStatus){
    if(!isset($this->statusChangeIndex)){
      foreach(self::$actions as $name){
        $action = new $name;
        foreach($action->getValidStartStatuses() as $status){
          $this->statusChangeIndex[] = array(
            'class' => $name,
            'startStatus' => $status,
            'endStatus' => $action->getEndStatus()
          );
        }
      }
    }
    foreach($this->statusChangeIndex as $change){
      if($change['startStatus'] == $startStatus && $change['endStatus'] == $endStatus){
        return $change;
      }
    }
  }

  function generateActivityParams(){

    // set basic activity params
    $this->activityParams = array(
      'source_record_id' => $this->startMembership['id'],
      'activity_type_id' => $this->action->getActivityType(),
      'subject' => "Contract [{$this->startMembership['id']}] {$this->action->getName()}", // A bit superfluous with most actions
      'status_id' => 'Completed',
      'medium_id' => $this->getMedium(),
      'target_id'=> $this->startMembership['contact_id'], // TODO might this have changed?
      // 'details' => // TODO Should we record anything else here? Suggest: no, not if we don't need to
      // 'activity_date_time' => // TODO currently allowing this to be assigned automatically - is this OK?
    );

    // set the source contact id //TODO check is this is robust enough
    $session = CRM_Core_Session::singleton();
    if(!$this->activityParams['source_contact_id'] = $session->getLoggedInContactID()){
      $this->activityParams['source_contact_id'] = 1;
    }

    // add further fields as required by different actions
    if(in_array($this->action->getName(), array('resume', 'update', 'revive', 'sign'))){
      $this->setUpdateParams();
    }elseif($this->action->getName() == 'cancel'){
      $this->setCancelParams();
    }

    // add campaign params
    if(0){
      // $activityParams['campaign_id'] => // membership_campaign_id
    }
  }

  function saveActivity(){
    $activity = civicrm_api3('Activity', 'create', $this->activityParams);
  }

  function setMedium($medium){
    return $this->medium;
  }

  function getMedium(){
    return $this->medium = 1;
  }

  function setUpdateParams(){

    $modifiedFields = $this->getModifiedFieldKeys($this->startMembership, $this->proposedParams);
    if(count($modifiedFields)){
      $this->activityParams['subject'] = "Contract update [".implode(', ', $modifiedFields)."]";
    }else{
      //TODO should we abort and not record an activity at this point since nothing has changed?
      $this->activityParams['subject'] = "Contract update";
    }

    $newAnnualMembershipAmount = $this->calcAnnualAmount($this->proposedContributionRecur);
    $oldAnnualMembershipAmount = $this->calcAnnualAmount($this->startContributionRecur);
    $amountDelta = $newAnnualMembershipAmount - $oldAnnualMembershipAmount;

    $contractUpdateCustomFields = $this->translateCustomFields('contract_updates');

    $this->activityParams[$contractUpdateCustomFields['ch_annual']] = $newAnnualMembershipAmount;
    $this->activityParams[$contractUpdateCustomFields['ch_annual_diff']] = $amountDelta;
    $this->activityParams[$contractUpdateCustomFields['ch_recurring_contribution']] = $this->proposedParams[$this->contributionRecurCustomField];
    $this->activityParams[$contractUpdateCustomFields['ch_frequency']] = $this->proposedContributionRecur['frequency_interval'];

    $sepaMandate = civicrm_api3('SepaMandate', 'get', array(
      'entity_table' => "civicrm_contribution_recur",
      'entity_id' => $this->proposedContributionRecur['id'],
    ));
    if($sepaMandate['count'] == 1){

      $this->activityParams[$contractUpdateCustomFields['ch_from_ba']] =
        $this->getBankAccountIdFromIban($sepaMandate['values'][$sepaMandate['id']]['iban']);

      $this->activityParams[$contractUpdateCustomFields['ch_to_ba']] =
        $this->getBankAccountIdFromIban($this->getCreditorIban($sepaMandate['values'][$sepaMandate['id']]['creditor_id']));

    }
  }

  function getBankAccountIdFromIban($iban){
    try{
      $result = civicrm_api3('BankingAccountReference', 'getsingle', array(
        'reference_type_id' => 'iban',
        'reference' => $iban,
      ));
    } catch(Exception $e){
      throw new Exception("Could not find Banking account reference for IBAN {$iban}");
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

  function getModifiedFieldKeys($from, $to){
    $membershipCustomFields =
      $this->translateCustomFields('membership_cancellation', 'label') +
      $this->translateCustomFields('membership_payment', 'label') +
      $this->translateCustomFields('membership_general', 'label');


    $modifiedFields = array();

    foreach($from as $fromField => $fromValue){
      if(isset($to[$fromField]) && $fromField != 'status_id' ){
        if(in_array($fromField, array('join_date', 'start_date', 'end_date'))){
          // Dates in CiviCRM are passed in various formats so try and normalise
          $fromValue = date('Y-m-d', strtotime($fromValue));
          $to[$fromField] = date('Y-m-d', strtotime($to[$fromField]));
        }
        if($fromValue != $to[$fromField]){
          if(in_array($fromField, $membershipCustomFields)){
            $modifiedFields[$fromField] = array_search($fromField, $membershipCustomFields);
          }else{
            $modifiedFields[$fromField] = civicrm_api3('Membership', 'getfield', array('name' => $fromField, 'action' => "get", ))['values']['title'];
          }
        }
      }
    }
    return $modifiedFields;
  }

  function setCancelParams(){
    $this->translateCustomFields('contract_cancellation');
    $this->activityParams[$this->translateActivityField['contact_history_cancel_reason']] = $this->submitted['contract_history_cancel_reason']; //TODO make select

  }

  function translateCustomFields($customGroup, $key = 'name'){
    $result = civicrm_api3('CustomField', 'get', array( 'sequential' => 1, 'custom_group_id' => $customGroup ));
    foreach($result['values'] as $v){
      $translateCustomFields[$v[$key]] = 'custom_'.$v['id'];
    }
    return $translateCustomFields;
  }

  function calcAnnualAmount($contributionRecur){
    $frequencyUnitTranslate = array(
      'day' => 365,
      'week' => 52,
      'month' => 12,
      'year' => 1
    );
    return $contributionRecur['amount'] * $frequencyUnitTranslate[$contributionRecur['frequency_unit']] / $contributionRecur['frequency_interval'];
  }

}
