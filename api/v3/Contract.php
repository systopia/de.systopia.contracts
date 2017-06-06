<?php


// A wrapper around Membership.create with appropriate fields passed. On cannot
// schedule Contract.create for the future.

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
    return $membership;
}

function _civicrm_api3_Contract_modify_spec(&$params){
  $params['action']['api.required'] = 1; // TODO For some reason, this is not getting picked up - I wonder why
  $params['id']['api.required'] = 1;
}

// A wrapper around Activity.create of an contract modification activity (which
// in turn wraps Membership.create). Contract.modify enables the updating of
// contracts either now or in the future.
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
          throw new Exception("Invalid format for resume date. Should be in 'Y-m-d' format, for example, '2000-12-31'");
        }
        $activityParams['resume_date'] = $params['resume_date'];
        $activityParams['ignored_review_activities'][] = $resumeActivity['id'];
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

function _civicrm_api3_Contract_getstatus_spec(&$params){
  $params['id']['api.required'] = 1;
}

function civicrm_api3_Contract_getstatus($params){
  $activitiesForReview = civicrm_api3('Activity', 'getcount', [
    'source_record_id' => $params['id'],
    'status_id' => 'Needs Review'
  ]);
  return ['needs_review' => $activitiesForReview];
}
