<?php
class CRM_Contract_Action_Revive{

  function getValidStartStatuses(){
    return array('Cancelled');
  }

  function getEndStatus(){
    return 'Current';
  }

  function getActivityType(){
    return 'Contract_Revived';
  }

  function getName(){
    return 'revive';
  }

  function isValidFieldUpdate($fields){
    return true;
  }
}
