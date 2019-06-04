<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

const CE_ENGINE_PROCESSING_LIMIT = 1000;

/**
 * Process the scheduled contract modifications
 */
function _civicrm_api3_Contract_process_scheduled_modifications_spec(&$params){
  $params['id'] = [
    'name'         => 'id',
    'title'        => 'Contract ID',
    'api.required' => 0,
    'description'  => 'If given, only pending modifications for this contract will be processed',
    ];
  $params['now'] = [
    'name'         => 'now',
    'title'        => 'NOW Time',
    'api.required' => 0,
    'description'  => 'You can provide another datetime for what the algorithm considers to be now',
    ];
  $params['limit'] = [
    'name'         => 'limit',
    'title'        => 'Limit',
    'api.default'  => CE_ENGINE_PROCESSING_LIMIT,
    'description'  => 'Max count of modifications to be processed',
    ];
}


/**
 * Process the scheduled contract modifications
 */
function civicrm_api3_Contract_process_scheduled_modifications($params) {
  // make sure no other task is running
  /** @var $lock Civi\Core\Lock\LockInterface */
  $lock = Civi\Core\Container::singleton()->get('lockManager')->acquire("worker.member.contract_engine");
  if (!$lock->isAcquired()) {
    return civicrm_api3_create_success(array('message' => "Another instance of the Contract.process_scheduled_modifications process is running. Skipped."));
  }

  if (!CRM_Contract_Configuration::useNewEngine()) {
    $legacy_result = civicrm_api3_Contract_process_scheduled_modifications_legacy($params);
    $lock->release();
    return $legacy_result;
  }

  // make sure that the time machine only works with a single contract, see GP-936
  if (isset($params['now']) && empty($params['id'])) {
    return civicrm_api3_create_error("You can only use the time machine for specific contract! set the 'id' parameter.");
  }

  if (empty($params['limit'])) {
    $params['limit'] = CE_ENGINE_PROCESSING_LIMIT;
  }

  // compile query
  $activityParams = [
      'activity_type_id'   => ['IN' => CRM_Contract_Change::getActivityTypeIds()],
      'status_id'          => 'scheduled',
      'activity_date_time' => ['<=' => date('Y-m-d H:i:s', strtotime($params['now']))], // execute everything scheduled in the past
      'option.limit'       => $params['limit'],
      'sequential'         => 1, // in the scheduled order(!)
      'option.sort'        => 'activity_date_time ASC, id ASC',
      'return'             => 'id,activity_type_id,status_id,activity_date_time,subject,source_record_id,' . CRM_Contract_Change::getCustomFieldList(),
  ];
  if (!empty($params['id'])) {
    $activityParams['source_record_id'] = (int) $params['id'];
  }

  // run query
  $result  = [];
  $counter = 0;
  $scheduled_activities = civicrm_api3('Activity', 'get', $activityParams);
  foreach ($scheduled_activities['values'] as $scheduled_activity) {
    $counter++;
    if ($counter > $params['limit']) {
      break;
    }

    // execute the changes
    $change = CRM_Contract_Change::getChangeForData($scheduled_activity);
    $result['order'][] = $change->getID();
    try {
      // verify the data before execution
      $change->populateData();
      $change->verifyData();
    } catch (Exception $ex) {
      // verification failed
      $change->setStatus('Failed');
      $change->setParameter('details', "Error was: " . $ex->getMessage());
      $change->save();
      continue;
    }

    try {
      // execute
      CRM_Contract_Configuration::disableMonitoring();
      $change->execute();
      CRM_Contract_Configuration::enableMonitoring();

      // log as executed
      $result['completed'][] = $change->getID();

    } catch (Exception $ex) {
      // something went wrong...
      $result['failed'][] = $change->getID();
      CRM_Contract_Configuration::enableMonitoring();
    }
  }

  $lock->release();
  return civicrm_api3_create_success($result);
}









/**
 * Legacy engine implementation
 * @author M. McAndrew (michaelmcandrew@thirdsectordesign.org)
 * @deprecated
 */
function civicrm_api3_Contract_process_scheduled_modifications_legacy($params){
  // make sure that the time machine only works with individual contracts
  //  see GP-936
  if (isset($params['now']) && empty($params['id'])) {
    return civicrm_api3_create_error("You can only use the time machine for specific contract! set the 'id' parameter.");
  }

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
    'option.sort'        => 'activity_date_time ASC, id ASC',
  ];
  if(isset($params['id'])){
    $activityParams['source_record_id'] = $params['id'];
  }

  $scheduledActivities = civicrm_api3('activity', 'get', $activityParams);

  $counter = 0;

  // // Going old school and sorting by timestamp //TODO can remove *IF* the above sort by activity date time is actually working
  // foreach($scheduledActivities['values'] as $k => $scheduledActivity){
  //   // TODO: Michael: please check this change
  //   //  also: the "above sort by activity date time" is working in my tests
  //   $scheduledActivities['values'][$k]['activity_date_unixtime'] = strtotime($scheduledActivity['activity_date_time']);
  // }
  // usort($scheduledActivities['values'], function($a, $b){
  //   return $a['activity_date_unixtime'] - $b['activity_date_unixtime'];
  // });

  $result=[];

  foreach($scheduledActivities['values'] as $scheduledActivity){
    try {
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
      if ($handler->isValid(['resume_date'])) {
        try {
          $handler->modify();
          $result['completed'][]=$scheduledActivity['id'];
        } catch (Exception $e) {
          // log problem
          error_log("de.systopia.contract: Failed to execute handler for activity [{$scheduledActivity['id']}]: " . $e->getMessage());

          // set activity to FAILED
          $scheduledActivity['status_id'] = 'Failed';
          $scheduledActivity['details'] .= '<p><b>Errors</b></p>'.implode($handler->getErrors(), ';') . ';' . $e->getMessage();
          civicrm_api3('Activity', 'create', $scheduledActivity);
          $result['failed'][]=$scheduledActivity['id'];
        }
      } else {
        $scheduledActivity['status_id'] = 'Failed';
        $scheduledActivity['details'] .= '<p><b>Errors</b></p>'.implode($handler->getErrors(), ';');
        civicrm_api3('Activity', 'create', $scheduledActivity);
        $result['failed'][]=$scheduledActivity['id'];
      }
    } catch (Exception $e) {
      error_log("de.systopia.contract: Failed to execute activity [{$scheduledActivity['id']}]: " . $e->getMessage());
    }
  }

//  $lock->release();
  return civicrm_api3_create_success($result);
}
