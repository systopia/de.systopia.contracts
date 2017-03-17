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
  var $monitoredFields = [];

  /**
   * The various actions that can happen to contracts
   */
  static $actions = [
    'CRM_Contract_Action_Cancel',
    'CRM_Contract_Action_Pause',
    'CRM_Contract_Action_Resume',
    'CRM_Contract_Action_Revive',
    'CRM_Contract_Action_Sign',
    'CRM_Contract_Action_Update'
  ];

  function __construct(){


    // Get a definitive list of all core fields and all custom fields indexed by
    // the name and that they are referenced by in forms
    foreach(civicrm_api3('CustomGroup', 'get', ['extends' => 'membership'])['values'] as $group){
      foreach(civicrm_api3('CustomField', 'get', ['custom_group_id' => $group['name']])['values'] as $key => $field){
        $id = 'custom_'.$field['id'];
        $membershipCustomFields[$id] = $field;
        $membershipCustomFields[$id]['id'] = $id;
        // Adding the 'full name' makes custom fields easier to work with
        $membershipCustomFields[$id]['full_name'] = $group['name'].'.'.$field['name'];
        $membershipCustomFields[$id]['title'] = $field['label'];
      };
    }
    // This will return custom fields as well but is missing the group name and
    // field name, hence we retrieived the custom fields seperatley.
    foreach(civicrm_api3('Membership', 'getfields', array( 'api_action' => 'get'))['values'] as $key=> $field){
      $membershipCoreFields[$field['name']] = $field;
      $membershipCoreFields[$field['name']]['id'] = $key;
      // We do this so we can use the full name across both core and custom
      // fields.
      $membershipCoreFields[$field['name']]['full_name'] = $field['name'];
    }

    // Order is important. We prefer the data in $membershipCustomFields
    // so add this first.
    $this->membershipFields = $membershipCustomFields + $membershipCoreFields;

    foreach($this->membershipFields as $field){
      $this->membershipFieldsByFullName[$field['full_name']] = $field;
    }

    // Do the same for activities. This is a cut and paste, search and replace
    // job from above at the moment :( Refactor when you have time.
    foreach(civicrm_api3('CustomGroup', 'get', ['extends' => 'activity'])['values'] as $group){
      foreach(civicrm_api3('CustomField', 'get', ['custom_group_id' => $group['name']])['values'] as $key => $field){
        $id = 'custom_'.$field['id'];
        $activityCustomFields[$id] = $field;
        $activityCustomFields[$id]['id'] = $id;
        // Adding the 'full name' makes custom fields easier to work with
        $activityCustomFields[$id]['full_name'] = $group['name'].'.'.$field['name'];
        $activityCustomFields[$id]['title'] = $field['label'];
      };
    }
    // This will return custom fields as well but is missing the group name and
    // field name, hence we retrieived the custom fields seperatley.
    foreach(civicrm_api3('Activity', 'getfields', array( 'api_action' => 'get'))['values'] as $key=> $field){
      $activityCoreFields[$field['name']] = $field;
      $activityCoreFields[$field['name']]['id'] = $key;
      // We do this so we can use the full name across both core and custom
      // fields.
      $activityCoreFields[$field['name']]['full_name'] = $field['name'];
    }

    // Order is important. We prefer the data in $activityCustomFields
    // so add this first.
    $this->activityFields = $activityCustomFields + $activityCoreFields;

    foreach($this->activityFields as $field){
      $this->activityFieldsByFullName[$field['full_name']] = $field;
    }


    // Define monitored (using the above defined 'full_name' for activity and membership)
    $this->monitoredFields=[
      'membership_type_id' => array('activity_field'=>'contract_updates.ch_membership_type'),
      'status_id' => array(),
      'membership_payment.membership_recurring_contribution' => array('activity_field'=>'contract_updates.ch_recurring_contribution'),
      'membership_payment.membership_annual' => array('activity_field'=>'contract_updates.ch_annual'),
      'membership_payment.membership_frequency' => array('activity_field'=>'contract_updates.ch_frequency'),
      // 'membership_payment.membership_customer_id' => array('activity_field'=>'xxx') // TODO sounds like this needs to be added
    ];

    // A shorthand as we reference this a fair amount
    $this->contributionRecurField = $this->membershipFieldsByFullName['membership_payment.membership_recurring_contribution']['id'];
  }

  /**
   * This records the membership status at the START of a change (sequence)
   * and associated objects
   */
  function setStartMembership($id){
    if($id){
      $this->startMembership = civicrm_api3('Membership', 'getsingle', array('id' => $id));

      $this->saneifyCustomFieldIds($this->startMembership);

      // Why TF does the api return contact references as names, not IDs?
      // (i.e. why does it return something that you cannot pass back to create
      // for an update?! As a work around, I am going to overwriting custom_#
      // with the value from custom_#_id.
      $dialoggerCustomField = $this->membershipFieldsByFullName['membership_general.membership_dialoger']['id'];
      if(isset($this->startMembership[$dialoggerCustomField])){
        $this->startMembership[$dialoggerCustomField] = $this->startMembership[$dialoggerCustomField.'_id'];
      }

      if(isset($this->startMembership[$this->contributionRecurField]) && $this->startMembership[$this->contributionRecurField]){
        $this->startContributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $this->startMembership[$this->contributionRecurField]));
      }else{
        $this->startMembership[$this->contributionRecurField] = '';
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

  function sanitizeParams(&$params){
    // var_dump($params['custom'][16]);exit;
    // Deal with the fact that custom data can be presented in different ways depending on the object
    if(isset($params['custom']) && is_array($params['custom'])){
      foreach($params['custom'] as $key => $fieldToAdd){
        if(!isset($params['custom_'.$key])){
          $params['custom_'.$key] = current($fieldToAdd)['value'];
        }
      }
    }
  }

  function insanitizeParams(&$params){
    foreach($params as $k => $v){
        if(preg_match("/custom_(\d+)$/", $k, $matches)){
          foreach($params['custom'][$matches[1]] as &$customField){
            $customField['value']=$v;
          }
        }
      }
    }

  function preProcessParams(&$params){

    // Contract should always have status overrriden
    $params['is_override'] = true;

    // Contracts should not have pending membership statuses - convert to
    // current
    if($params['status_id'] == civicrm_api3('MembershipStatus', 'getsingle', array('name' => "pending"))['id']){
      $params['status_id'] = civicrm_api3('MembershipStatus', 'getsingle', array('name' => "current"))['id'];
    }

    // If a recurring contribution has been passed into these parameters
    if($params[$this->membershipFieldsByFullName['membership_payment.membership_recurring_contribution']['id']]){
      $ContributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $params[$this->membershipFieldsByFullName['membership_payment.membership_recurring_contribution']['id']]));

      // Calculate the membership amount field and the membership frequency
      $params[$this->membershipFieldsByFullName['membership_payment.membership_annual']['id']] = $this->calcAnnualAmount($ContributionRecur);

      // Calculate the frequency
      $params[$this->membershipFieldsByFullName['membership_payment.membership_frequency']['id']] = $this->calcPaymentFrequency($ContributionRecur);
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

    // Massage the proposed recurring contribution field since we need this when
    // working out what has changed and what should be recorded.
    // If it has been set, i.e. proposed
    if(isset($params[$this->contributionRecurField])){
      // and it has been set to a specific ID
      if($params[$this->contributionRecurField]){
        // Then retreive the propsed recurring contribution object
        $this->proposedContributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $params[$this->contributionRecurField]));
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

    $modifiedFields = $this->getModifiedFields();
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

    // set the source contact id
    // TODO check is this is robust enough
    $session = CRM_Core_Session::singleton();
    if(!$this->activityParams['source_contact_id'] = $session->getLoggedInContactID()){
      $this->activityParams['source_contact_id'] = 1;
    }

    // add further fields as required by different actions
    if(in_array($this->action->getAction(), array('resume', 'update', 'revive', 'sign'))){
      $this->generateActivityUpdateParams();
    }elseif($this->action->getAction() == 'cancel'){
      $this->generateActivityCancelParams();
    }

    // add campaign params if they have changed
    $this->activityParams['campaign_id'] =
      isset($this->proposedParams['campaign_id']) ?
      $this->proposedParams['campaign_id'] :
      $this->startMembership['campaign_id'];
  }

  function generateActivityUpdateParams(){

    // A shorthand just to make the code easier to read
    $from = $this->startMembership;
    $to = $this->proposedParams;


    // Go through each monitored field
    foreach (array_keys($this->monitoredFields) as $monitoredField) {
      // If we want to record this field in the activity
      if(isset($this->monitoredFields[$monitoredField]['activity_field'])){

        // Some shorthands for readability
        $activityFieldId = $this->activityFieldsByFullName[$this->monitoredFields[$monitoredField]['activity_field']]['id'];
        $membershipFieldId = $this->membershipFieldsByFullName[$monitoredField]['id'];

        // If modified
        if(isset($to[$membershipFieldId])){
          $this->activityParams[$activityFieldId] = $to[$membershipFieldId];
          // Otherwise record the previous value
        }else{
          $this->activityParams[$activityFieldId] = $from[$membershipFieldId];
        }
      }
    }

    // Set subject based on modified fields
    foreach($this->getModifiedFields() as $modifiedField){
      if($modifiedField['monitored'] and $modifiedField['id'] != 'status_id'){
        $subjectLineMonitoredFields[] = "[{$modifiedField['title']} {$modifiedField['from']} > {$modifiedField['to']}]";
      }
    }
    $this->activityParams['subject'] .= ": ".implode('; ', $subjectLineMonitoredFields).'.';

    // Work out the difference in annual membership amount

    // If membership_payment.membership_annual is in the params, it must have changed.
    if(isset($to[$this->membershipFieldsByFullName['membership_payment.membership_annual']['id']])){
      $this->activityParams[$this->activityFieldsByFullName['contract_updates.ch_annual_diff']['id']] =
        $to[$this->membershipFieldsByFullName['membership_payment.membership_annual']['id']] - $from[$this->membershipFieldsByFullName['membership_payment.membership_annual']['id']];
    }else{
      $this->activityParams[$this->activityFieldsByFullName['contract_updates.ch_annual_diff']['id']] = 0;
    }

    // For some reason, we are not storing the bank account IDs in the
    // membership. Hence we need to do a bit of extra work to work out what they
    // are and store them in the activity.

    // We can look up the new recurring contribution from our activity params
    // (handy!)
    $contributionRecurId = $this->activityParams[$this->activityFieldsByFullName['contract_updates.ch_recurring_contribution']['id']];

    //If this membership has a recurring contribution
    if($contributionRecurId){
      //Check if it is a sepa recurring contribution
      $sepaMandate = civicrm_api3('SepaMandate', 'get', array(
        'entity_table' => "civicrm_contribution_recur",
        'entity_id' => $contributionRecurId
      ));
      if($sepaMandate['count'] == 1){
        // var_dump($sepaMandate);
        // var_dump($this->activityFieldsByFullName['contract_updates.ch_from_ba']['id']);
        $this->activityParams[$this->activityFieldsByFullName['contract_updates.ch_from_ba']['id']] =
          $this->getBankAccountIdFromIban($sepaMandate['values'][$sepaMandate['id']]['iban']);
        $this->activityParams[$this->activityFieldsByFullName['contract_updates.ch_to_ba']['id']] =
          $this->getBankAccountIdFromIban($this->getCreditorIban($sepaMandate['values'][$sepaMandate['id']]['creditor_id']));
      }
    }
  }

  function generateActivityCancelParams(){
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
      isset($this->proposedParams[$this->contributionRecurField]) &&
      $this->proposedParams[$this->contributionRecurField] &&
      ($this->proposedParams[$this->contributionRecurField] != $this->startMembership[$this->contributionRecurField])
    ){
      // Need to work out what transaction id to assign
      $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array(
        'id' => $this->proposedParams[$this->contributionRecurField]
      ));
      // If recuring contribution doesn't have a transaction ID that is suitable for this contract, we need to create one
      if(!isset($contributionRecur['trxn_id']) || strpos($contributionRecur['trxn_id'], "CONTRACT-{$this->startMembership['id']}") !== 0){
        $this->contributionRecurParams['trxn_id'] = $this->assignNextTransactionId($this->startMembership['id']);
        $this->contributionRecurParams['id'] = $this->proposedParams[$this->contributionRecurField];
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
    $frequencyUnitTranslate = array(
      'day' => 365,
      'week' => 52,
      'month' => 12,
      'year' => 1
    );
    return (string) $contributionRecur['amount'] * $frequencyUnitTranslate[$contributionRecur['frequency_unit']] / $contributionRecur['frequency_interval'];
  }

  function calcPaymentFrequency($contributionRecur){
    if($contributionRecur['frequency_unit'] == 'month'){
      return $contributionRecur['frequency_interval'];
    }

    // Don't guess. Return an empty string if we don't know
    // TODO Or maybe report an exception here?
    return '';
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
   *
   * TODO cache the results of this function so we don't call it more than
   * necessary (if it feels worth it)
   */
  function getModifiedFields(){
    // Define this array since we are returning it even if it nothing was
    // modified
    $modifiedFields = [];

    // Shorter names are nicer to work with
    $from = $this->startMembership;
    $to = $this->proposedParams;

    // The parameters might come with badly named keys
    $this->saneifyCustomFieldIds($to);

    foreach(array_keys($this->membershipFields) as $field){

      // Fields can only have been modified if they are defined in the $to array
      if(isset($to[$field])){

        // Dates in CiviCRM are passed in various formats. This is an attempt to
        // convert dates passed as params to the same format that the api produces
        if(
          isset($to[$field]) &&
          in_array($this->membershipFields[$field]['full_name'], [
            'join_date',
            'start_date',
            'end_date',
            'membership_cancellation.membership_cancel_date',
            'membership_cancellation.membership_resume_date'
          ])
        ){
          $to[$field] = date('Y-m-d', strtotime($to[$field]));
          $from[$field] = date('Y-m-d', strtotime($from[$field]));
        }

        // Check if the field has been modified.
        if(
          // EITHER the field was not set in $from but have a value in $to
          (!isset($from[$field]) && $to[$field]) ||
          // OR the field was set in $from and in $to and the values are
          // different
          (isset($from[$field]) && $from[$field] != $to[$field])
        ){
          $modifiedFields[$field]['id'] = $field;
          $modifiedFields[$field]['full_name'] = $this->membershipFields[$field]['full_name'];
          $modifiedFields[$field]['full_name'] = $this->membershipFields[$field]['full_name'];
          $modifiedFields[$field]['title'] = $this->membershipFields[$field]['title'];

          $modifiedFields[$field]['from'] = isset($from[$field]) ? $from[$field] : '';
          $modifiedFields[$field]['to'] = $to[$field];
          if(in_array($this->membershipFields[$field]['full_name'], array_keys($this->monitoredFields))){
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
