<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_ModificationActivity_Cancel extends CRM_Contract_ModificationActivity{

  function getStartStatuses(){
    return array('New', 'Current', 'Grace');
  }

  function getEndStatus(){
    return 'Cancelled';
  }

  function getActivityType(){
    return 'Contract_Cancelled';
  }

  function getAction(){
    return 'cancel';
  }
  function getResult(){
    return 'canceled';
  }

  function validateFieldUpdate($fields){
    // The only fields that should be allowed to be updated when cancelling a
    // contract are the status (obviously) and the cancellation reason.
    //
    // We should also check that the cancellation date is set.

    foreach ($fields as $id => $field){
      if(in_array($field['full_name'], array( 'status_id', 'is_override', 'end_date', 'membership_cancellation.membership_cancel_reason', 'membership_cancellation.membership_cancel_date', 'membership_cancellation.membership_resume_date'))){
        unset($fields[$id]);
      }else{
        $this->errors[$id] = "Cannot update {$field['title']} with membership status set to cancelled.";
      }
    }
  }
}
