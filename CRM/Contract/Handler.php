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
    $class = $this->lookupStatusUpdate($this->startStatus, $this->endStatus)['class'];
    $this->action = new $class;
  }

  /**
   * Takes a set of API parameters that cover the proposed changes to the membership
   */
  function addProposedParams($params){

    $this->proposedParams = $params;
    //Do some extra processing of the status_id to make it easy to work with
    if(is_numeric($params['status_id'])){
      $this->desiredEndStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $this->proposedParams['status_id']))['name'];
    }else{
      $this->desiredEndStatus = $this->proposedParams['status_id'];
    }
  }

  /**
   * semantic wrapper around lookupStatusChange
   */
  function isValidStatusUpdate(){
    if($this->lookupStatusUpdate($this->startStatus, $this->desiredEndStatus)){
      return true;
    }else{
      return false;
    }
  }

  function isValidFieldUpdate(){
    $class = $this->lookupStatusUpdate($this->startStatus, $this->desiredEndStatus)['class'];
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

  function recordActivity(){

    // set basic activity params
    $activityParams = array(
      'source_record_id' => $this->endMembership['id'],
      'activity_type_id' => $this->action->getActivityType(),
      'subject' => "Contract [{$this->endMembership['id']}] {$this->action->getName()}", // A bit superfluous with most actions
      'status_id' => 'Completed',
      'medium_id' => $this->getMedium(),
      'target_id'=> $this->endMembership['contact_id'],
      // 'details' => // TODO Should we record anything else here? Suggest: no, not if we don't need to
      // 'activity_date_time' => // TODO currently allowing this to be assigned automatically - is this OK?
    );

    // set the source contact id //TODO check is this is robust enough
    $session = CRM_Core_Session::singleton();
    if(!$activityParams['source_contact_id'] = $session->getLoggedInContactID()){
      $activityParams['source_contact_id'] = 1;
    }

    // add further fields as required by different actions
    if(in_array($this->action->getName(), array('resume', 'update', 'revive', 'sign'))){
      $activityParams += $this->getUpdateParams();
    }elseif($this->action->getName() == 'cancel'){
      $activityParams += $this->getCancelParams();
    }

    // add campaign params
    if(0){
      // $activityParams['campaign_id'] => // membership_campaign_id
    }
    $activityParams['options']['reload'] = 1;
    $activity = civicrm_api3('Activity', 'create', $activityParams);
  }

  function setMedium($medium){
    return $this->medium;
  }

  function getMedium(){
    return $this->medium = 1;
  }

  function getUpdateParams(){

    // See what fields have changed between startMembership and endMembership
    $modifiedFieldKeys = $this->getModifiedFieldKeys($this->startMembership, $this->endMembership);

    if(count($modifiedFieldKeys)){
      $activityParams['subject'] = "Contract update [".implode(', ', $modifiedFieldKeys)."]";
    }else{
      //TODO should we abort and not record an activity at this point since nothing has changed?
      $activityParams['subject'] = "Contract update";
    }

    $newAnnualMembershipAmount = $this->calcAnnualAmount($this->endContributionRecur);
    $oldAnnualMembershipAmount = $this->calcAnnualAmount($this->startContributionRecur);
    $amountDelta = $newAnnualMembershipAmount - $oldAnnualMembershipAmount;

    $contractUpdateCustomFields = $this->translateCustomFields('contract_updates');

    $activityParams[$contractUpdateCustomFields['ch_annual']] = $newAnnualMembershipAmount;
    $activityParams[$contractUpdateCustomFields['ch_annual_diff']] = $amountDelta;
    $activityParams[$contractUpdateCustomFields['ch_recurring_contribution']] = $this->endMembership[$this->contributionRecurCustomField];
    $activityParams[$contractUpdateCustomFields['ch_frequency']] = 1; //TODO where should this come from? The SEPA mandate?
    $activityParams[$contractUpdateCustomFields['ch_from_ba']] = 1; //TODO where should this come from? The SEPA mandate?
    $activityParams[$contractUpdateCustomFields['ch_to_ba']] = 1; //TODO where should this come from? The SEPA mandate?

    return $activityParams;
  }

  function getModifiedFieldKeys($from, $to){
    $membershipCustomFields =
      $this->translateCustomFields('membership_cancellation', 'label') +
      $this->translateCustomFields('membership_payment', 'label') +
      $this->translateCustomFields('membership_general', 'label');


    foreach($to as $k => $v){
      // there are some fields that we know we don't want to check
      // I'm not sure why the membership API create returns two fields from the
      // MembershipType API when we are creating a new membership, but it does,
      // so we exclude that too
      if(!in_array($k, array('version', 'options', 'status_id', 'id', 'membership_name', 'relationship_name'))){
        if($v != $from[$k]){
          if(in_array($k, $membershipCustomFields)){
            $modifiedFields[$k] = array_search($k, $membershipCustomFields);
          }else{
            $modifiedFields[$k] = civicrm_api3('Membership', 'getfield', array('name' => $k, 'action' => "get", ))['values']['title'];
          }
        }
      }
    }
    return $modifiedFields;
  }

  function getCancelParams(){
    $this->translateCustomFields('contract_cancellation');
    $activityParams[$this->translateActivityField['contact_history_cancel_reason']] = $this->submitted['contract_history_cancel_reason']; //TODO make select

    return $activityParams;

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
