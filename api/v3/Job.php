<?php


// A wrapper around Membership.create with appropriate fields passed. On cannot
// schedule Contract.create for the future.

function civicrm_api3_Job_executeScheduledContractModifications($params){

  // Passing the now param is useful for testing
  $now = new DateTime(isset($params['now']) ? $params['now'] : '');

  // Get the limit (defaults to 1000)
  $limit = isset($params['limit']) ? $params['limit'] : 1000;

  $activityParams = [
    'activity_type_id' => ['IN' => CRM_Contract_ModificationActivity::getModificationActivityTypeIds()],
    'status_id' => 'scheduled',
    'activity_date_time' => ['<=' => $now->format('Y-m-d 00:00')],
    'option.limit' => $limit
  ];

  $scheduledActivities = civicrm_api3('activity', 'get', $activityParams);

  $counter = 0;

  foreach($scheduledActivities['values'] as $scheduledActivity){

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
    if($handler->isValid()){
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
