<?php
abstract class CRM_Contract_Form_History extends CRM_Core_Form{

  function preProcess(){
    $this->getParams();
    $this->validateStartStatus();
    parent::preProcess();
  }

  function buildQuickForm(){

    $session = CRM_Core_Session::singleton();
    $urlParams = "action=browse&reset=1&cid=24&selectedChild=grant";
    $urlString = 'civicrm/contact/view';
    $session->pushUserContext(CRM_Utils_System::url($urlString, $urlParams));


    CRM_Utils_System::setTitle(ucfirst($this->action).' contract');
    $this->addButtons(array(
        array('type' => 'cancel', 'name' => 'Cancel'),
        array('type' => 'submit', 'name' => ucfirst($this->action), 'isDefault' => true)
    ));
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
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
    ; //useful?
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
    $this->membership['status_id'] = $this->endStatus;
    $this->membership['is_override'] = $this->membership['status_id'] == 'Current' ? 0 : 1;
    $activity = civicrm_api3('Activity', 'create', array('target_id'=> $this->membership['contact_id'], 'activity_type_id' => CRM_Contract_Utils_ActionProperties::getByClass($this)['activityType']));
    $membership = civicrm_api3('Membership', 'create', $this->membership);


  }
}
