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

    if(in_array($this->updateAction->getAction(), array('resume', 'update', 'revive'))){

      // add fields for update (and similar) actions
      $alter = new CRM_Contract_FormUtils($this, 'Membership');
      $alter->addPaymentContractSelect2('contract_history_recurring_contribution', $this->membership['contact_id']);
      $mediums = civicrm_api3('Activity', 'getoptions', array(
        'sequential' => 1,
        'field' => "activity_medium_id",
      ));
      foreach($mediums['values'] as $medium){
        $mediumOptions[$medium['key']] = $medium['value'];
      }
      $this->add('select', 'contract_history_medium', ts('Medium'), $mediumOptions, false, array('class' => 'crm-select2'));
      $this->assign('isUpdate', true);
    }
    elseif($this->updateAction->getAction() == 'cancel'){


      $this->add('select', 'contract_history_cancel_reason', ts('Cancellation reason'), $mediumOptions, false, array('class' => 'crm-select2'));

    }

    $this->addButtons(array(
        array('type' => 'cancel', 'name' => 'Back'), // since Cancel looks bad when viewed next to the Cancel action
        array('type' => 'submit', 'name' => ucfirst($this->updateAction->getAction()), 'isDefault' => true)
    ));

    $defaults['contract_history_recurring_contribution'] = $this->membership[$this->contributionRecurField];
    $this->setDefaults($defaults);

    $this->assign('historyAction', $this->updateAction->getAction());
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function getRenderableElementNames() {
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  function postProcess(){

    $this->submitted = $this->exportValues();

    // copy the original membership before it was updated and call it
    // updatedMembership (even though it hasn't been updated yet) since we are
    // about to update it (and the save it)
    $this->updatedMembership = $this->membership;

    $this->updatedMembership['status_id'] = $this->updateAction->getEndStatus();
    $this->updatedMembership['is_override'] = $this->membership['status_id'] == 'Current' ? 0 : 1;
    if(isset($this->submitted['contract_history_recurring_contribution'])){
      $this->updatedMembership[$this->contributionRecurField] = $this->submitted['contract_history_recurring_contribution'];
    }
    civicrm_api3('Membership', 'create', $this->updatedMembership);
  }
}
