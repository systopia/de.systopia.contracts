<?php


class CRM_Contract_Form_Resume extends CRM_Contract_Form_History{

  var $title = 'Resume Contract';
  var $validStartStatuses = array('Paused');
  var $endStatus = 'Current';

  function preProcess() {
    echo 'a';
    parent::preProcess();
  }
  function buildQuickForm() {
    echo 'b';
    parent::buildForm();
  }
}
