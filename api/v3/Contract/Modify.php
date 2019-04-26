<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


/**
 * Schedule a Contract modification
 */
function _civicrm_api3_Contract_modify_spec(&$params){
  $params['modify_action'] = array(
    'name'         => 'modify_action',
    'title'        => 'Action',
    'api.required' => 1,
    'api.alias'    => 'action',
    'description'  => 'Action to be executed (same as "action")',
    );
  $params['id'] = array(
    'name'         => 'id',
    'title'        => 'Contract ID',
    'api.required' => 1,
    'description'  => 'Contract (Membership) ID of the contract to be modified',
    );
  $params['medium_id'] = array(
      'name'         => 'medium_id',
      'title'        => 'Medium ID',
      'api.required' => 1,
      'description'  => 'How was the modification received',
  );
  if (CRM_Contract_Configuration::useNewEngine()) {
    $params['date'] = array(
        'name'         => 'date',
        'title'        => 'Date',
        'api.default'  => 'now',
        'description'  => 'Scheduled execution date (not in the past, and in format Y-m-d H:i:s)',
    );
  } else {
    $params['date'] = array(
        'name'         => 'date',
        'title'        => 'Date',
        'api.required' => 0,
        'description'  => 'Scheduled execution date (not in the past, and in format Y-m-d H:i:s)',
    );
  }
}


/**
 * Schedule a new Contract modification
 */
function civicrm_api3_Contract_modify($params) {
  // use activity_type_id instead of modify_action
  $params['action'] = $params['modify_action'];

  // also: revert REST-like '.' -> '_' conversion
  foreach (array_keys($params) as $key) {
    $new_key = preg_replace('#^membership_payment_#', 'membership_payment.', $key);
    $new_key = preg_replace('#^membership_cancellation_#', 'membership_cancellation.', $new_key);
    $params[$new_key] = $params[$key];
  }

  if (!CRM_Contract_Configuration::useNewEngine()) {
    return civicrm_api3_Contract_modify_legacy($params);
  }

  // check the requested execution time
  $requested_execution_time = strtotime($params['date']);
  if ($requested_execution_time < strtotime('today')) {
    throw new Exception("Parameter 'date' must either be in the future, or absent if you want to execute the modification immediately.");
  }

  // modify data to match internal structure
  $params['activity_type_id']   = $params['action'];
  $params['activity_date_time'] = date('Y-m-d H:i:s', $requested_execution_time);
  $params['source_record_id']   = $params['contract_id'];
  if (!empty($params['note'])) {
    $params['details'] = $params['note'];
  }

  // TODO: set contacts

  // generate change (activity)
  $change = CRM_Contract_Change::getChangeForData($params);
  $change->setStatus('scheduled');
  $change->populateData();
  $change->verifyData();
  $change->save();
}


/**
 * Legacy engine implementation
 * @author M. McAndrew (michaelmcandrew@thirdsectordesign.org)
 * @deprecated
 */
function civicrm_api3_Contract_modify_legacy($params) {
  // Throw an exception is $params['action'] is not set
  if(!isset($params['action'])){
    throw new Exception('Please include an action/modify_action parameter with this API call');
  }

  if(isset($params['date'])){
    $date = DateTime::createFromFormat('Y-m-d H:i:s', $params['date']);
    if(!$date || $date->getLastErrors()['warning_count']){
      throw new Exception("Invalid format for date. Should be in 'Y-m-d H:i:s' format, for example, '".date_format(new DateTime(),'Y-m-d H:i:s')."'");
    }
    if($date < DateTime::createFromFormat('Y-m-d H:i:s', date_format(new DateTime(), 'Y-m-d 00:00:00'))){
      // Throw an exception if the date is < today, i.e. any time yesterday or
      // before as this model requires being able to compare the pre and post state
      // of the contract to create accurate changes. It would require a lot of logic
      // and manipulation of existing data to be able add modifications
      // retrospectivley.
      throw new Exception("'date' must either be in the future, or absent if you want to execute the modification immediatley.");
    }
  }else{
    $date = new DateTime;
  }

  // check if we actually want to create this activity (see GP-1190)
  if (CRM_Contract_ModificationActivity::omitCreatingActivity($params, $date->format('Y-m-d H:i:00'))) {
    return civicrm_api3_create_success("Scheduling an (additional) modification request in not desired in this context.");
  }

  // Find the appropriate activity type
  $class = CRM_Contract_ModificationActivity::findByAction($params['action']);
  // Start populating the activity parameters
  $activityParams['status_id'] = 'scheduled';
  $activityParams['activity_type_id'] = $class->getActivityType();
  $activityParams['activity_date_time'] = $date->format('Y-m-d H:i:00');
  $activityParams['source_record_id'] = $params['id'];
  $activityParams['medium_id'] = $params['medium_id'];

  if (!empty($params['note'])) {
    $activityParams['details'] = $params['note'];
  }

  // Get the membership that is associated with the contract so we can
  // associate the activity with the contact.
  $membershipParams = civicrm_api3('Membership', 'getsingle', ['id' => $params['id']]);
  $activityParams['target_contact_id'] = $membershipParams['contact_id'];

  // TODO is this the best way to get the authorised user?
  $session = CRM_Core_Session::singleton();
  if(!$sourceContactId = $session->getLoggedInContactID()){
    $sourceContactId = 1;
  }


  // Depending on the activity type, populate more parameters / do extra
  // processing

  // Convert fields that are passed in custom_N format to . format for
  // converting to activity fields

  $expectedCustomFields = [
    'membership_payment.membership_recurring_contribution',
    'membership_cancellation.membership_cancel_reason',
    'membership_payment.membership_annual',
    'membership_payment.membership_frequency',
    'membership_payment.cycle_day',
    'membership_payment.to_ba',
    'membership_payment.from_ba',
    'membership_payment.defer_payment_start',
  ];

  foreach($expectedCustomFields as $expectedCustomField){
    $expectedCustomFieldIds[]=CRM_Contract_Utils::getCustomFieldId($expectedCustomField);
  }

  foreach($params as $key => $value){
    if(in_array($key, $expectedCustomFieldIds)){
      unset($params[$key]);
      $key = CRM_Contract_Utils::getCustomFieldName($key);
      $params[$key]=$value;
    }
  }

  switch($class->getAction()){
    case 'update':
    case 'revive':

      $updateFields = [
        'membership_type_id',
        'campaign_id',
        'membership_payment.membership_recurring_contribution',
        'membership_payment.membership_annual',
        'membership_payment.membership_frequency',
        'membership_payment.cycle_day',
        'membership_payment.to_ba',
        'membership_payment.from_ba',
        'membership_payment.defer_payment_start',
      ];
      foreach($updateFields as $updateField){
        if(isset($params[$updateField])){
          $updateField;
          $activityParams[CRM_Contract_Utils::contractToActivityFieldId($updateField)] = $params[$updateField];
        }
      }

      // check the if the annual amount can be properly divided into installments
      //  see GP-770
      if (!empty($params['membership_payment.membership_annual']) && !empty($params['membership_payment.membership_frequency'])) {
        $annual      = CRM_Contract_SepaLogic::formatMoney($params['membership_payment.membership_annual']);
        $installment = CRM_Contract_SepaLogic::formatMoney($annual / $params['membership_payment.membership_frequency']);
        $real_annual = CRM_Contract_SepaLogic::formatMoney($installment * $params['membership_payment.membership_frequency']);
        if ($annual != $real_annual) {
          throw new Exception("The annual amount of '{$annual}' cannot be distributed over {$params['membership_payment.membership_frequency']} installments.");
        }
      }
      break;
    case 'cancel':
      if(isset($params['membership_cancellation.membership_cancel_reason'])){
        $activityParams[CRM_Contract_Utils::contractToActivityFieldId('membership_cancellation.membership_cancel_reason')] = $params['membership_cancellation.membership_cancel_reason'];
      }
      break;
    case 'pause':
      if(isset($params['resume_date'])){
        $resumeDate = DateTime::createFromFormat('Y-m-d', $params['resume_date']);
        if($resumeDate->getLastErrors()['warning_count']){
          throw new Exception("Invalid format for resume date. Should be in 'Y-m-d' format, for example, '1999-12-31'");
        }
        $activityParams['resume_date'] = $params['resume_date'];
      }else{
        throw new Exception('You must supply a resume_date when pausing a contract.');
      }
      break;
  }
  $activityParams['source_contact_id'] = $sourceContactId;
  $activityResult = civicrm_api3('Activity', 'create', $activityParams);
  if($class->getAction() == 'pause'){
    $resumeActivity = civicrm_api3('Activity', 'create', [
      'status_id' => 'scheduled',
      'source_record_id' => $params['id'],
      'activity_type_id' => 'Contract_Resumed',
      'target_contact_id' => $membershipParams['contact_id'],
      'source_contact_id' => $sourceContactId,
      'activity_date_time' => $resumeDate->format('Y-m-d H:i:00')
    ]);
  }
  $result['membership'] = civicrm_api3('Membership', 'getsingle', ['id' => $params['id']]);
  return civicrm_api3_create_success($result);
}
