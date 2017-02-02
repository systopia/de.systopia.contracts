<?php
class CRM_Contract_Form_Pause extends CRM_Contract_Form_History{

  var $action = 'pause';
  var $validStartStatuses = array('New', 'Current', 'Grace');
  var $endStatus = 'Paused';
  var $activityType = 'Contract_Paused';

}
