<?php
class CRM_Contract_Form_Pause extends CRM_Contract_Form_History{

  var $title = 'Pause Contract';
  var $validStartStatuses = array('New', 'Current', 'Grace');
  var $endStatus = 'Paused';
  var $buttonText = 'Pause';

  function preProcess() {
    parent::preProcess();
  }

  function buildQuickForm() {
    parent::buildQuickForm();
  }
}
