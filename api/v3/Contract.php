<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/*************************************
 **    Contract.create              **
 ************************************/

/**
 * A wrapper around Membership.create with appropriate fields passed.
 * You cannot schedule Contract.create for the future.
 */
function civicrm_api3_Contract_create($params){

    // Any parameters with a period in will be converted to the custom_N format
    // Other fields will be passed directly to the membership.create API
    foreach ($params as $key => $value){

      if(strpos($key, '.')){
        unset($params[$key]);
        $params[CRM_Contract_Utils::getCustomFieldId($key)] = $value;
      }
    }
    $membership = civicrm_api3('Membership', 'create', $params);

    $activity = civicrm_api3('Activity', 'getsingle', [
      'source_record_id' => $membership['id'],
      'activity_type_id' => 'Contract_Signed',
    ]);
    $activity = civicrm_api3('Activity', 'create', [
      'id' => $activity['id'],
      'details' => $params['note'],
      'medium_id' => $params['medium_id']
    ]);
    return $membership;
}




/*************************************
 **    Contract.modify              **
 ************************************/

/**
 * Schedule a Contract modification
 */
function _civicrm_api3_Contract_modify_spec(&$params){
  $params['action'] = array(
    'name'         => 'Action',
    'api.required' => 1,
    'title'        => 'Action to be executed',
    );
  $params['id'] = array(
    'name'         => 'Contract ID',
    'api.required' => 1,
    'title'        => 'Contract (Membership) ID of the contract to be modified',
    );
}


/**
 * Schedule a Contract modification
 */
function civicrm_api3_Contract_modify($params){
  //Throw an exception is $params['action'] is not set
  if(!isset($params['action'])){
    throw new Exception('Please include an action parameter with this API call');
  }

  // Throw an exception if the date is < today, i.e. any time yesterday or
  // before as this model requires being able to compare the pre and post state
  // of the contract to create accurate changes. It would require a lot of logic
  // and manipulation of existing data to be able add modifications
  // retrospectivley.


  if(isset($params['date'])){
    $date = DateTime::createFromFormat('Y-m-d H:i:s', $params['date']);
    if(!$date || $date->getLastErrors()['warning_count']){
      throw new Exception("Invalid format for date. Should be in 'Y-m-d H:i:s' format, for example, '".date_format(new DateTime(),'Y-m-d H:i:s')."'");
    }
    if($date < DateTime::createFromFormat('Y-m-d H:i:s', date_format(new DateTime(), 'Y-m-d 00:00:00'))){
      throw new Exception("'date' must either be in the future, or absent if you want to execute the modification immediatley.");
    }
  }else{
    $date = new DateTime;
  }

  // Find the appropriate activity type
  $class = CRM_Contract_ModificationActivity::findByAction($params['action']);
  // Start populating the activity parameters
  $activityParams['status_id'] = 'scheduled';
  $activityParams['activity_type_id'] = $class->getActivityType();
  $activityParams['activity_date_time'] = $date->format('Y-m-d H:i:s');
  $activityParams['source_record_id'] = $params['id'];
  $activityParams['medium_id'] = $params['medium_id'];
  $activityParams['details'] = $params['note'];

  // Get the membership that is associated with the contract so we can
  // associate the activity with the contact.
  $membershipParams = civicrm_api3('membership', 'getsingle', ['id' => $params['id']]);
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
    'membership_payment.to_ba'
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
        'membership_payment.to_ba'
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
        $installment = CRM_Contract_SepaLogic::formatMoney($params['membership_payment.membership_annual'] / $params['membership_payment.membership_frequency']);
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
      'activity_date_time' => $resumeDate->format('Y-m-d H:i:s')
    ]);
  }
  $result['membership'] = civicrm_api3('membership', 'getsingle', ['id' => $params['id']]);
  return civicrm_api3_create_success($result);
}



/************************************************
 **    Contract.get_open_modification_counts   **
 ************************************************/

/**
 * Get the number of scheduled modifications for a contract
 */
function _civicrm_api3_Contract_get_open_modification_counts_spec(&$params){
  $params['id'] = array(
    'name'         => 'Contract ID',
    'api.required' => 1,
    'title'        => 'Contract (Membership) ID of the contract to be modified',
    );
}

/**
 * Get the number of scheduled modifications for a contract
 */
function civicrm_api3_Contract_get_open_modification_counts($params){
  $activitiesForReview = civicrm_api3('Activity', 'getcount', [
    'source_record_id' => $params['id'],
    'status_id' => 'Needs Review'
  ]);
  $activitiesScheduled = civicrm_api3('Activity', 'getcount', [
    'source_record_id' => $params['id'],
    'status_id' => ['IN' => ['Scheduled']]
  ]);
  // TODO (Michael): return proper API results (civicrm_api3_create_success)
  return [
    'needs_review' => $activitiesForReview,
    'scheduled' => $activitiesScheduled
  ];
}



/***************************************************
 **    Contract.process_scheduled_modifications   **
 ***************************************************/

/**
 * Process the scheduled contract modifications
 */
  function _civicrm_api3_Contract_process_scheduled_modifications_spec(&$params){
  $params['id'] = array(
    'name'         => 'Contract ID',
    'api.required' => 0,
    'title'        => 'If given, only pending modifications for this contract will be processed',
    );
  $params['now'] = array(
    'name'         => 'NOW Time',
    'api.required' => 0,
    'title'        => 'You can provide another datetime for what the algorithm considers to be now',
    );
  $params['limit'] = array(
    'name'         => 'Limit',
    'api.required' => 0,
    'title'        => 'Max count of modifications to be processed',
    );
}


/**
 * Process the scheduled contract modifications
 */
function civicrm_api3_Contract_process_scheduled_modifications($params){

  // Passing the now param is useful for testing
  $now = new DateTime(isset($params['now']) ? $params['now'] : '');

  // Get the limit (defaults to 1000)
  $limit = isset($params['limit']) ? $params['limit'] : 1000;

  $activityParams = [
    'activity_type_id'   => ['IN' => CRM_Contract_ModificationActivity::getModificationActivityTypeIds()],
    'status_id'          => 'scheduled',
    'activity_date_time' => ['<=' => $now->format('Y-m-d H:i:s')], // execute everything scheduled in the past
    'option.limit'       => $limit,
    'sequential'         => 1, // in the scheduled order(!)
    'option.sort'        => 'activity_date_time ASC',
  ];
  if(isset($params['id'])){
    $activityParams['source_record_id'] = $params['id'];
  }

  $scheduledActivities = civicrm_api3('activity', 'get', $activityParams);

  $counter = 0;

  // Going old school and sorting by timestamp //TODO can remove *IF* the above sort by activity date time is actually working
  foreach($scheduledActivities['values'] as $k => $scheduledActivity){
    $scheduledActivities['values'][$k]['activity_date_unixtime'] = strtotime($scheduledModification['activity_date_time']);
  }
  usort($scheduledActivities['values'], function($a, $b){
    return $a['activity_date_unixtime'] - $b['activity_date_unixtime'];
  });

  $result=[];

  foreach($scheduledActivities['values'] as $scheduledActivity){
    $result['order'][]=$scheduledActivity['id'];
    // If the limit parameter has been passed, only process $params['limit']
    $counter++;
    if($counter > $limit){
      break;
    }

    $handler = new CRM_Contract_Handler_Contract;

    // Set the initial state of the handler
    $handler->setStartState($scheduledActivity['source_record_id']);
    $handler->setModificationActivity($scheduledActivity);

    // Pass the parameters of the change
    $handler->setParams(CRM_Contract_Handler_ModificationActivityHelper::getContractParams($scheduledActivity));

    // We ignore the lack of resume_date when processing alredy scheduled pauses
    // as we assume that the resume has already been created when the pause wraps
    // originally scheduled and hence we wouldn't want to create it again
    // TODO I don't think the above is true any more. Should find out for sure
    // and remove if so.
    if($handler->isValid(['resume_date'])){
      //TODO Might need/want to catch more exceptions here
      $handler->modify();
      $result['completed'][]=$scheduledActivity['id'];
    }else{
      $scheduledActivity['status_id'] = 'Failed';
      $scheduledActivity['details'] .= '<p><b>Errors</b></p>'.implode($handler->getErrors(), ';');
      civicrm_api3('activity', 'create', $scheduledActivity);
      $result['failed'][]=$scheduledActivity['id'];
    }
  }
  return civicrm_api3_create_success($result);
}
