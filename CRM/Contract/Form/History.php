<?php
abstract class CRM_Contract_Form_History extends CRM_Core_Form{

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
    if(!$this->get('id')){
      CRM_Core_Error::fatal('Missing a membership ID');
    }
    try{
      $this->membership = civicrm_api3('Membership', 'getsingle', array('id' => $this->get('id')));
    }catch(Exception $e){
      CRM_Core_Error::fatal('Not a valid membership ID');
    }
  }

  function buildQuickForm(){

    CRM_Utils_System::setTitle(ucfirst($this->action).' contract');

    if(in_array($this->action, array('resume', 'update', 'revive'))){

      // add fields for update (and similar) actions
      $alter = new CRM_Contract_AlterForm($this, $this->id);
      $contributionRecurs = civicrm_api3('ContributionRecur', 'get', array('contact_id' => $this->membership['contact_id']));
      $contributionRecurOptions = array('' => '- none -') + array_map(array($alter, 'writePaymentContractLabel'), $contributionRecurs['values']);
      $this->add('select', 'contract_recurring_contribution', ts('Payment Contract'), $contributionRecurOptions, false, array('class' => 'crm-select2'));

      //add fields to record membership info
      //
    }
    elseif($this->action == 'cancel'){
      //
      $this->add('text', 'contact_history_cancel_reason', ts('Cancellation reason'));
    }else{
    }

    $this->addButtons(array(
        array('type' => 'cancel', 'name' => 'Cancel'),
        array('type' => 'submit', 'name' => ucfirst($this->action), 'isDefault' => true)
    ));
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function validateStartStatus(){
    $this->membershipStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $this->membership['status_id']));
    if(!in_array($this->membershipStatus['name'], $this->validStartStatuses)){
      CRM_Core_Error::fatal("You cannot run '{$this->action}' when the membership status is '{$this->membershipStatus['name']}'.");
    }
  }

  function getTemplateFileName(){
    return 'CRM/Contract/Form/History.tpl';
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

    $session = CRM_Core_Session::singleton();

    $activityParams = array(
      'source_record_id' => $this->id,
      'activity_type_id' => CRM_Contract_Utils_ActionProperties::getByClass($this)['activityType'],
      // 'activity_date_time' => // currently allowing this to be assigned automatically
      //TODO 'subject' =>
      //TODO 'details' =>
      'status_id' => 'Completed',
      //TODO (see issue 410 for details) 'medium_id' => //
      'source_contact_id'=> $session->getLoggedInContactID(),
      'target_id'=> $this->membership['contact_id'],
    );

    // optional activity params

    if(0){
      // $activityParams['campaign_id'] => // membership_campaign_id
    }

    $activity = civicrm_api3('Activity', 'create', $activityParams);

    $this->membership['status_id'] = $this->endStatus;
    $this->membership['is_override'] = $this->membership['status_id'] == 'Current' ? 0 : 1;
    $membership = civicrm_api3('Membership', 'create', $this->membership);

  }

}
