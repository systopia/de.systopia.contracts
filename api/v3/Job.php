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

  // Ensure that you execute the scheduled updates in order
  usort($scheduledActivities['values'], function($a, $b) {
      return new DateTime($b['activity_date_time']) - new DateTime($a['activity_date_time']);
  });

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

    // We ignore the lack of resume_date when processing alredy scheduled pauses
    // as we assume that the resume has already been created when the pause wraps
    // originally scheduled and hence we wouldn't want to create it again
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

function civicrm_api3_Job_delacs(){
  $activities = civicrm_api3('activity', 'get', ['option.limit' => 2000]);
  foreach($activities['values'] as $v){
    civicrm_api3('activity', 'delete', ['id' => $v['id']]);
  }
}
