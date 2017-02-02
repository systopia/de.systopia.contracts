<?php
class CRM_Contract_Form_Revive extends CRM_Contract_Form_History{

  var $action = 'revive';
  var $validStartStatuses = array('Cancelled');
  var $endStatus = 'Current';
  var $activityType = 'Contract_Revived';

}
