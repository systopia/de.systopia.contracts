<?php
class CRM_Contract_Action_Cancel{

  function getValidStartStatuses(){
    return array('New', 'Current', 'Grace');
  }

  function getEndStatus(){
    return 'Cancelled';
  }

  function getActivityType(){
    return 'Contract_Resumed';
  }

  function getName(){
    return 'cancel';
  }

  function isValidFieldUpdate($fields){
    if(count($fields)){
      $this->errorMessage = 'You cannot update fields when cancelling a contract';
      return false;
    }else{
      return true;
    }
  }

}
