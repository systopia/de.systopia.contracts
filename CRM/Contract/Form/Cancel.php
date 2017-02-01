<?php


class CRM_Contract_Form_Cancel extends CRM_Contract_Form_History{

  var $title = 'Cancel Contract';
  var $validStartStatuses = array('New', 'Current', 'Grace');
  var $endStatus = 'Cancelled';

  function preProcess() {
    parent::preProcess();
  }
  function buildForm() {
    parent::buildForm();
  }
}
