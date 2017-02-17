<?php
class CRM_Contract_Form_Modify extends CRM_Core_Form{

  function preProcess(){
    $this->getParams();
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
    }catch(Exception $e){
      CRM_Core_Error::fatal('Not a valid membership ID');
    }

    try{
      $CustomField = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'membership_payment', 'name' => 'membership_recurring_contribution'));
      $this->contributionRecurCustomField = 'custom_'.$CustomField['id'];
      if($this->membership[$this->contributionRecurCustomField]){
        $this->contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $this->membership[$this->contributionRecurCustomField]));
      }
    }catch(Exception $e){
      CRM_Core_Error::fatal('Could not find recurring contribution for this membership');
    }
  }

  function validateStartStatus(){
    $this->membershipStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $this->membership['status_id']));
    if(!in_array($this->membershipStatus['name'], $this->updateAction->getValidStartStatuses())){
      CRM_Core_Error::fatal("You cannot {$this->updateAction->getName()} a membership when its status is '{$this->membershipStatus['name']}'.");
    }
  }

  function buildQuickForm(){

    CRM_Utils_System::setTitle(ucfirst($this->updateAction->getName()).' contract');

    if(in_array($this->updateAction->getName(), array('resume', 'update', 'revive'))){

      // add fields for update (and similar) actions
      $alter = new CRM_Contract_FormUtils($this, $this->get('id'));
      $alter->addPaymentContractSelect2('contract_history_recurring_contribution');
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
    elseif($this->updateAction->getName() == 'cancel'){


      $this->add('select', 'contract_history_cancel_reason', ts('Cancellation reason'), $mediumOptions, false, array('class' => 'crm-select2'));

    }

    $this->addButtons(array(
        array('type' => 'cancel', 'name' => 'Back'), // since Cancel looks bad when viewed next to the Cancel action
        array('type' => 'submit', 'name' => ucfirst($this->updateAction->getName()), 'isDefault' => true)
    ));

    $defaults['contract_history_recurring_contribution'] = $this->membership[$this->contributionRecurCustomField];
    $this->setDefaults($defaults);

    $this->assign('historyAction', $this->updateAction->getName());
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
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
      $this->updatedMembership[$this->contributionRecurCustomField] = $this->submitted['contract_history_recurring_contribution'];
    }

    // The good thing about calling the API here is that
    // CRM_Contract_HistoryApiWrapper will take care of creating activities for
    // us.
    civicrm_api3('Membership', 'create', $this->updatedMembership);
  }


  /**
   * When a contracted is updated, record more detail on the changes
   */
  function getUpdateParams(){

    $membershipCustomFields =
      $this->getTranslateCustomFields('membership_cancellation', 'label') +
      $this->getTranslateCustomFields('membership_payment', 'label') +
      $this->getTranslateCustomFields('membership_general', 'label');


    // See what fields have changed between updatedMembership and membership
    $updatedKeys = array();
    foreach($this->updatedMembership as $k => $v){
      if($v != $this->membership[$k]){
        if(in_array($k, $membershipCustomFields)){
          $updatedKeys[] = array_search($k, $membershipCustomFields);
        }elseif($k != 'status_id'){
          $updatedKeys[] = civicrm_api3('Membership', 'getfield', array('name' => $k, 'action' => "get", ))['values']['title'];
        }
      }
    }
    if(count($updatedKeys)){
      $this->activityParams['subject'] = "Contract update [".implode(', ', $updatedKeys)."]";
    }else{
      //TODO should we abort and not record an activity at this point since nothing has changed?
      $this->activityParams['subject'] = "Contract update";
    }

    $oldAnnualMembershipAmount = $this->calcAnnualAmount($this->contributionRecur);
    $newContributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $this->updatedMembership[$this->contributionRecurCustomField]));
    $newAnnualMembershipAmount = $this->calcAnnualAmount($newContributionRecur);
    $amountDelta = $newAnnualMembershipAmount - $oldAnnualMembershipAmount;

    $contractUpdateCustomFields = $this->getTranslateCustomFields('contract_updates');

    $this->activityParams[$contractUpdateCustomFields['ch_annual']] = $newAnnualMembershipAmount;
    $this->activityParams[$contractUpdateCustomFields['ch_annual_diff']] = $amountDelta;
    $this->activityParams[$contractUpdateCustomFields['ch_recurring_contribution']] = $this->updatedMembership[$this->contributionRecurCustomField];
    $this->activityParams[$contractUpdateCustomFields['ch_frequency']] = 1; //TODO where should this come from? The SEPA mandate?
    $this->activityParams[$contractUpdateCustomFields['ch_from_ba']] = 1; //TODO where should this come from? The SEPA mandate?
    $this->activityParams[$contractUpdateCustomFields['ch_to_ba']] = 1; //TODO where should this come from? The SEPA mandate?
  }

  function getCancelParams(){
    $this->getTranslateCustomFields('contract_cancellation');
    $this->activityParams[$this->translateActivityField['contact_history_cancel_reason']] = $this->submitted['contract_history_cancel_reason']; //TODO make select
  }


  function getTemplateFileName(){
    return 'CRM/Contract/Form/History.tpl';
  }


}
