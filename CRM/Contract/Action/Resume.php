<?php
class CRM_Contract_Action_Resume{

  function getValidStartStatuses(){
    return array('Paused');
  }

  function getEndStatus(){
    return 'Current';
  }

  function getActivityType(){
    return 'Contract_Resumed';
  }

  function getName(){
    return 'resume';
  }

  function isValidFieldUpdate($fields){
    return true;
  }

}
