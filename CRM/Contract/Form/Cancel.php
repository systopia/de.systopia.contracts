<?php

class CRM_Contract_Form_Cancel extends CRM_Contract_Form_History{

  var $action = 'cancel';
  var $validStartStatuses = array('New', 'Current', 'Grace');
  var $endStatus = 'Cancelled';
  var $activityType = 'Contract_Cancelled';

}
