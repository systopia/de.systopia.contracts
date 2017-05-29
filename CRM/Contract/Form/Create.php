<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Form_Create extends CRM_Core_Form{

  function buildQuickForm(){


    $this->cid = CRM_Utils_Request::retrieve('cid', 'Integer');
    if($this->cid){
      $this->set('cid', $this->cid);
    }
    $this->controller->_destination = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$this->get('cid')}&selectedChild=member");

    $this->assign('cid', $this->get('cid'));
    $this->assign('contact', civicrm_api3('Contact', 'getsingle', ['id' => $this->get('cid')]));

    $formUtils = new CRM_Contract_FormUtils($this, 'Membership');
    $formUtils->addPaymentContractSelect2('recurring_contribution', $this->get('cid'), true);
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('cid' => $this->get('cid')));
    CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'templates/CRM/Contract/Form/MandateBlock.js');


    // Contract dates
    $this->addDate('join_date', ts('Member since'), TRUE, array('formatType' => 'activityDate'));
    $this->addDate('start_date', ts('Membership start date'), TRUE, array('formatType' => 'activityDate'));
    $this->addDate('end_date', ts('End date'), FALSE, array('formatType' => 'activityDate'));

    $this->addEntityRef('campaign_id', ts('Campaign'), [
      'entity' => 'campaign',
      'placeholder' => ts('- none -')
    ]);

    // Membership type (membership)
    foreach(civicrm_api3('MembershipType', 'get', [])['values'] as $MembershipType){
      $MembershipTypeOptions[$MembershipType['id']] = $MembershipType['name'];
    };
    $this->add('select', 'membership_type_id', ts('Membership type'), array('' => '- none -') + $MembershipTypeOptions, true, array('class' => 'crm-select2'));

    // Source media (activity)
    foreach(civicrm_api3('Activity', 'getoptions', ['field' => "activity_medium_id"])['values'] as $key => $value){
      $mediumOptions[$key] = $value;
    }
    $this->add('select', 'activity_medium', ts('Source media'), array('' => '- none -') + $mediumOptions, false, array('class' => 'crm-select2'));

    // Reference number text
    $this->add('text', 'membership_reference', ts('Reference number'));

    // Contract number text
    $this->add('text', 'membership_contract', ts('Contract number'));

    // DD-Fundraiser
    $this->addEntityRef('membership_dialoger', ts('DD-Fundraiser'), array('api' => array('params' => array('contact_type' => 'Individual'))));

    // Membership channel
    foreach(civicrm_api3('OptionValue', 'get', ['option_group_id' => "campaign_subtype"])['values'] as $optionValue){
      $membershipChannelOptions[$optionValue['value']] = $optionValue['label'];
    };
    $this->add('select', 'membership_channel', ts('Membership channel'), array('' => '- none -') + $membershipChannelOptions, false, array('class' => 'crm-select2'));

    // Notes
    $this->addWysiwyg('activity_details', ts('Notes'), []);


    $this->addButtons([
      array('type' => 'cancel', 'name' => 'Cancel'),
      array('type' => 'submit', 'name' => 'Create')
    ]);

    $this->setDefaults();

  }

  function setDefaults($defaultValues = null, $filter = null){

    list($defaults['join_date'], $null) = CRM_Utils_Date::setDateDefaults(NULL, 'activityDateTime');
    list($defaults['start_date'], $null) = CRM_Utils_Date::setDateDefaults(NULL, 'activityDateTime');

    parent::setDefaults($defaults);
  }

  function postProcess(){
    $submitted = $this->exportValues();

    // Create the contract (the membership)

    // Core fields
    $params['contact_id'] = $this->get('cid');
    $params['membership_type_id'] = $submitted['membership_type_id'];
    $params['start_date'] = $submitted['start_date'];
    $params['join_date'] = $submitted['join_date'];
    if($submitted['end_date']){
      $params['end_date'] = $submitted['end_date'];
    }
    $params['campaign_id'] = $submitted['campaign_id'];

    // 'Custom' fields
    $params['membership_payment.membership_recurring_contribution'] = $submitted['recurring_contribution']; // Recurring contribution
    $params['membership_general.membership_reference'] = $submitted['membership_reference']; // Reference number
    $params['membership_general.membership_contract'] = $submitted['membership_contract']; // Contract number
    $params['membership_general.membership_dialoger'] = $submitted['membership_dialoger']; // DD fundraiser
    $params['membership_general.membership_channel'] = $submitted['membership_channel']; // Membership channel

    $membershipResult = civicrm_api3('Contract', 'create', $params);
  }
}
