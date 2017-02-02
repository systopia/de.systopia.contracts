<?php


class CRM_Contract_Form_Resume extends CRM_Contract_Form_History{

  var $action = 'resume';
  var $validStartStatuses = array('Paused');
  var $endStatus = 'Current';
  var $activityType = 'Contract_Resumed';

}
