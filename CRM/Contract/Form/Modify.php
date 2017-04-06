<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Form_Modify extends CRM_Core_Form{

  function preProcess(){
    $this->getParams();
    $this->controller->_destination = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$this->membership['contact_id']}&selectedChild=member");
    $this->validateStartStatus();
    parent::preProcess();
  }

  function getParams(){
    $this->id = CRM_Utils_Request::retrieve('id', 'Integer');
    if($this->id){
      $this->set('id', $this->id);
    }
    $this->update_action = CRM_Utils_Request::retrieve('update_action', 'String');
    if($this->update_action){
      $this->set('update_action', $this->update_action);
    }
    $updateActionClass = 'CRM_Contract_Action_'.ucfirst($this->get('update_action'));
    $this->updateAction = new $updateActionClass;
    if(!$this->get('id')){
      CRM_Core_Error::fatal('Missing a membership ID');
    }
    try{
      $this->membership = civicrm_api3('Membership', 'getsingle', array('id' => $this->get('id')));

      $dialoggerCustomField = 'custom_'.civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'membership_general', 'name' => 'membership_dialoger'))['id'];
      if(isset($this->membership[$dialoggerCustomField])){
        $this->membership[$dialoggerCustomField] = $this->membership[$dialoggerCustomField.'_id'];
      }

      foreach($this->membership as $k => $v){
        if(preg_match("/custom_\d+_\d+/", $k)){
          unset($this->membership[$k]);
        }
      }
    }catch(Exception $e){
      CRM_Core_Error::fatal('Not a valid membership ID');
    }

    try{
      $CustomField = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'membership_payment', 'name' => 'membership_recurring_contribution'));
      $this->contributionRecurField = 'custom_'.$CustomField['id'];
      if(isset($this->membership[$this->contributionRecurField]) && $this->membership[$this->contributionRecurField]){
        $this->contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $this->membership[$this->contributionRecurField]));
      }else{
        // Ensure that this is set to squish warnings later in buildQuickForm
        $this->membership[$this->contributionRecurField]='';
      }
    }catch(Exception $e){
      CRM_Core_Error::fatal('Could not find recurring contribution for this membership');
    }
  }

  function validateStartStatus(){
    $this->membershipStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $this->membership['status_id']));
    if(!in_array($this->membershipStatus['name'], $this->updateAction->getValidStartStatuses())){
      CRM_Core_Error::fatal("You cannot {$this->updateAction->getAction()} a membership when its status is '{$this->membershipStatus['name']}'.");
    }
  }

  function buildQuickForm(){

    CRM_Utils_System::setTitle(ucfirst($this->updateAction->getAction()).' contract');
    CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'templates/CRM/Contract/Form/Modify.js');
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('cid' => $this->membership['contact_id']));

    // Add fields that are present on all contact history forms

    // * Activity date (activity)
    $this->addDateTime('activity_date', ts('Activity Date'), TRUE, array('formatType' => 'activityDateTime'));

    // * Source media (activity)
    foreach(civicrm_api3('Activity', 'getoptions', ['field' => "activity_medium_id"])['values'] as $key => $value){
      $mediumOptions[$key] = $value;
    }
    $this->add('select', 'activity_medium', ts('Source media'), array('' => '- none -') + $mediumOptions, false, array('class' => 'crm-select2'));

    // * Note (activity)
    $this->addWysiwyg('activity_details', ts('Notes'), []);

    // Add appropriate fields
    if(in_array($this->updateAction->getAction(), array('update', 'revive'))){
      $this->assign('isUpdate', true);
      $this->addUpdateFields();
    }elseif($this->updateAction->getAction() == 'cancel'){
      $this->addCancelFields();
    }

    $this->addButtons(array(
        array('type' => 'cancel', 'name' => 'Back'), // since Cancel looks bad when viewed next to the Cancel action
        array('type' => 'submit', 'name' => ucfirst($this->updateAction->getAction()), 'isDefault' => true)
    ));

    $this->setDefaults();

    $this->assign('mid', $this->membership['id']);
    $this->assign('cid', $this->membership['contact_id']);
    $this->assign('historyAction', $this->updateAction->getAction());

    parent::buildQuickForm();
  }

  function setDefaults($defaultValues = null, $filter = null){

    $defaults['contract_history_recurring_contribution'] = $this->membership[$this->contributionRecurField];
    list($defaults['activity_date'], $defaults['activity_date_time']) = CRM_Utils_Date::setDateDefaults(NULL, 'activityDateTime');
    list($defaults['cancel_date'], $defaults['cancel_date_time']) = CRM_Utils_Date::setDateDefaults(NULL, 'activityDateTime');
    $defaults['membership_type_id'] = $this->membership['membership_type_id'];
    if(isset($this->membership['campaign_id'])){
      $defaults['campaign_id'] = $this->membership['campaign_id'];
    }

    parent::setDefaults($defaults);
  }

  /**
   *  Add fields for update (and similar) actions
   */
  function addUpdateFields(){

    // Recurring contribution / Mandate
    $formUtils = new CRM_Contract_FormUtils($this, 'Membership');
    $formUtils->addPaymentContractSelect2('contract_history_recurring_contribution', $this->membership['contact_id'], true);


    // Campaign (membership)
    foreach(civicrm_api3('Campaign', 'get', [])['values'] as $campaign){
      $campaignOptions[$campaign['id']] = $campaign['name'];
    };
    $this->add('select', 'campaign_id', ts('Campaign'), array('' => '- none -') + $campaignOptions, false, array('class' => 'crm-select2'));


    // Membership type (membership)
    foreach(civicrm_api3('MembershipType', 'get', [])['values'] as $MembershipType){
      $MembershipTypeOptions[$MembershipType['id']] = $MembershipType['name'];
    };
    $this->add('select', 'membership_type_id', ts('Membership type'), array('' => '- none -') + $MembershipTypeOptions, true, array('class' => 'crm-select2'));

  }

  /**
   *  Add fields for cancel (and similar) actions
   */
  function addCancelFields(){

    // Cancel date
    $this->addDateTime('cancel_date', ts('Cancel Date'), TRUE, array('formatType' => 'activityDateTime'));

    // Cancel reason
    foreach(civicrm_api3('OptionValue', 'get', ['option_group_id' => "contract_cancel_reason"])['values'] as $cancelReason){
      $cancelOptions[$cancelReason['value']] = $cancelReason['label'];
    };
    $this->add('select', 'cancel_reason', ts('Cancellation reason'), array('' => '- none -') + $cancelOptions, true, array('class' => 'crm-select2'));


  }

  function getFieldId($group, $field){
    $id = civicrm_api3('CustomField', 'getvalue', ['return' => "id", 'custom_group_id' => $group, 'name' => $field]);
    return 'custom_'.$id;
  }


  function postProcess(){

    $this->submitted = $this->exportValues();

    // copy the original membership before it was updated and call it
    // updatedMembership (even though it hasn't been updated yet) since we are
    // about to update it (and the save it)
    $membershipParams = $this->membership;

    $membershipParams['status_id'] = $this->updateAction->getEndStatus();
    $membershipParams['membership_type_id'] = $this->submitted['membership_type_id'];
    $membershipParams['campaign_id'] = $this->submitted['campaign_id'];
    if(in_array($this->updateAction->getAction(), ['cancel'])){
      $membershipParams[$this->getFieldId('membership_cancellation', 'membership_cancel_date')] = "{$this->submitted['cancel_date']} {$this->submitted['cancel_date_time']}";
      $membershipParams[$this->getFieldId('membership_cancellation', 'membership_cancel_reason')] = $this->submitted['cancel_reason'];
    }

    $membershipParams[$this->contributionRecurField] = $this->submitted['contract_history_recurring_contribution'];

    $updatedMembership = civicrm_api3('Membership', 'create', $membershipParams);

    // If we created a contract history activity
    if(isset($updatedMembership['links']['activity_history_id'])){

      // Global fields
      $activityParams['id'] = $updatedMembership['links']['activity_history_id'];
      $activityParams['details'] = $this->submitted['activity_details'];
      $activityParams['activity_date_time'] = "{$this->submitted['activity_date']} {$this->submitted['activity_date_time']}";
      $activityParams['medium'] = $this->submitted['activity_medium'];

      // Update and revive fields
      if(in_array($this->updateAction->getAction(), ['update', 'revive'])){
        $activityParams['campaign_id'] = $this->submitted['campaign_id'];
      }

      // Pause fields
      if(in_array($this->updateAction->getAction(), ['pause'])){

      }
      // Cancel fields
      if(in_array($this->updateAction->getAction(), ['cancel'])){
        $activityParams[$this->getFieldId('contract_cancellation', 'contact_history_cancel_reason')] = $this->submitted['cancel_reason'];
      }

      civicrm_api3('Activity', 'create', $activityParams);
    }

    // Find the most recent contract activity for this contact of the appropriate type
  }
}
