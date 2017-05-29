<?php
class CRM_Contract_Handler_ModificationConflicts{

  function __construct(){
    $this->activityReviewerContactId = 5;
  }


  function setModificationActivity($activity){
    $this->contract = civicrm_api3('membership', 'getsingle', ['id' => $activity['source_record_id']]);
    $this->ModificationActivityId = $activity['id'];
  }

  function checkForConflicts($ignoredActivities){
    $this->ignoredActivities = $ignoredActivities;
    $this->scheduledModifications = civicrm_api3('activity', 'get', [
      'source_record_id' => $this->contract['id'],
      'status_id' => ['IN' => ['scheduled', 'needs review']]
    ]);

    foreach($ignoredActivities as $id){
      unset($this->scheduledModifications['values'][$id]);
    }

    if(count($this->scheduledModifications['values']) > 1){
      $this->activitiesToReview = $this->ignoredActivities;
      foreach($this->scheduledModifications['values'] as $scheduledModification){
        civicrm_api3('activity', 'create', [
          'id' => $scheduledModification['id'],
          'status_id' => 'needs review',
          'assignee_id' => $this->activityReviewerContactId
        ]);
        $this->activitiesToReview[] = $scheduledModification['id'];
      }
    }
  }

  function getActivitiesToReview(){
    return $this->activitiesToReview;
  }
}
