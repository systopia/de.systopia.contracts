<?php
class CRM_Contract_Form_Update extends CRM_Contract_Form_History{

  var $action = 'update';
  var $validStartStatuses = array('New', 'Current', 'Grace');
  var $endStatus = 'Current'; // maybe we don't update the status for an update?
  var $activityType = 'Contract_Updated';
}
