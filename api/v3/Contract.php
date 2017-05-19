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

    // Add a signed activity once this contract has been created
    $membershipParams = $membership['values'][$membership['id']];
    $sign = new CRM_Contract_ModificationActivity_Sign;
    $activityParams['status_id'] = 'completed';
    $activityParams['activity_type_id'] = $sign->getActivityType();
    $activityParams['activity_date_time'] = $membershipParams['join_date'];
    $activityParams['source_record_id'] = $membershipParams['id'];
    $activityParams['medium_id'] = $params['medium_id'];
    $activityParams['description'] = $params['note'];
    $activityParams['source_contact_id'] = $membershipParams['contact_id'];

    $activity = civicrm_api3('Activity', 'create', $activityParams);

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

  // Throw an exception if the date is in the past. Rational:
  // This model requires being able to compare the pre and post state of the
  // contract to create accurate changes. It would require a lot of logic and
  // manipulation of existing data to be able add modifications retrospectivley.
  $date = new DateTime($params['date']);
  if($date < new DateTime()){
    throw new Exception("'date' must either be in the future, or absent if you want to execute the modification immediatley.");
  }

  // Find the appropriate activity type
  $class = CRM_Contract_Utils::getModificationActivityFromAction($params['action']);

  // Start populating the activity parameters
  $activityParams['status_id'] = 'scheduled';
  $activityParams['activity_type_id'] = $class->getActivityType();
  $activityParams['activity_date_time'] = (new DateTime($params['date']))->format('Y-m-d H:i:s');
  $activityParams['source_record_id'] = $params['id'];
  $activityParams['medium_id'] = $params['medium_id'];
  $activityParams['details'] = $params['note'];

  // Find the contact that this membership is associated with so we can
  // associate the activity
  $membershipParams = civicrm_api3('membership', 'getsingle', ['id' => $params['id']]);
  $activityParams['source_contact_id'] = $membershipParams['contact_id'];

  // Depending on the activity type, populate more parameters / do extra
  // processing

  switch($class->getAction()){
    case 'update':
    case 'revive':
      if(isset($params['membership_type_id'])){
        $activityParams[CRM_Contract_Utils::contractToActivityCustomFieldId('membership_type_id')] = $params['membership_type_id'];
      }
      if(isset($params['campaign_id'])){
        $activityParams['campaign_id'] = $params['campaign_id'];
      }
      if(isset($params['membership_payment.membership_recurring_contribution'])){
        $activityParams[CRM_Contract_Utils::contractToActivityCustomFieldId('membership_payment.membership_recurring_contribution')] = $params['membership_payment.membership_recurring_contribution'];
      }
      break;
    case 'cancel':
      if(isset($params['membership_cancellation.membership_cancel_reason'])){
        $activityParams[CRM_Contract_Utils::contractToActivityCustomFieldId('membership_cancellation.membership_cancel_reason')] = $params['membership_cancellation.membership_cancel_reason'];
      }
      break;
    case 'pause':
      if(isset($params['resume_date'])){
        civicrm_api3('Activity', 'create', [
          'status_id' => 'scheduled',
          'source_record_id' => $params['id'],
          'activity_type_id' => 'Contract_Resumed',
          'source_contact_id' => $membershipParams['contact_id'],
          'activity_date_time' => (new DateTime($params['resume_date']))->format('Y-m-d H:i:s'),
        ]);
      }else{
        throw new Exception('You must supply a resume_date when pausing a contract.');
      }
  }
  return civicrm_api3('Activity', 'create', $activityParams);
}
