<?php
class CRM_Contract_Form_Resume extends CRM_Contract_Form_History{

  var $title = 'Revive Contract';
  var $validStartStatuses = array('Cancelled');
  var $endStatus = 'Current';

  function preProcess() {
    parent::preProcess();
  }
  function buildForm() {
    parent::buildForm();
  }
}
