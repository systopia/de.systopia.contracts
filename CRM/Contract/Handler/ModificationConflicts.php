<?php
class CRM_Contract_Handler_ModificationConflicts{

  function __construct(){
    $this->activityReviewerContactId = 2;
  }


  function setContract($id){
    $this->contract = civicrm_api3('membership', 'getsingle', ['id' => $id]);
  }

  function checkForConflicts(){
    $this->scheduledModifications = civicrm_api3('activity', 'get', [
      'source_record_id' => $this->contract['id'],
      'status_id' => ['IN' => ['scheduled', 'needs review']]
    ]);
    if($this->scheduledModifications['count'] > 1){
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
