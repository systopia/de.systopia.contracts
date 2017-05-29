<?php
class CRM_Contract_Handler_ModificationConflicts{

  function __construct(){
    $this->activityReviewerContactId = 5;
  }


  function setContract($id){
    $this->contract = civicrm_api3('membership', 'getsingle', ['id' => $id]);
  }

  function checkForConflicts($ignored_activities){
    $this->scheduledModifications = civicrm_api3('activity', 'get', [
      'source_record_id' => $this->contract['id'],
      'status_id' => ['IN' => ['scheduled', 'needs review']]
    ]);

    foreach($ignored_activities as $id){
      unset($this->scheduledModifications['values'][$id]);
    }

    var_dump($ignored_activities);
    var_dump($this->scheduledModifications);

    if(count($this->scheduledModifications['values']) > 1){
      var_dump('there is something to review...');
      foreach($this->scheduledModifications['values'] as $scheduledModification){
        civicrm_api3('activity', 'create', [
          'id' => $scheduledModification['id'],
          'status_id' => 'needs review',
          'assignee_id' => $this->activityReviewerContactId
        ]);
      }
    }
  }
}
