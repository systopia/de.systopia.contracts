<?php
class CRM_Contract_Form_Resume extends CRM_Contract_Form_History{

  var $title = 'Resume Contract';
  var $validStartStatuses = array('New', 'Current', 'Grace');
  var $endStatus = 'Current'; // maybe we don't update the status for an update?

  function preProcess() {
    parent::preProcess();
  }
  function buildForm() {
    parent::buildForm();
  }
}
