<?php
abstract class CRM_Contract_Form_History extends CRM_Core_Form{

  function preProcess(){
    $this->getParams();
    $this->validateStartStatus();
    parent::preProcess();
  }

  function buildQuickForm(){
    CRM_Utils_System::setTitle($this->title);
    $this->addButtons(array(
        array('type' => 'cancel', 'name' => 'Cancel'),
        array('type' => 'submit', 'name' => $this->buttonText, 'isDefault' => true)
    ));
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function getParams(){
    $this->id = CRM_Utils_Request::retrieve('id', 'Integer');
    if(!$this->id){
      CRM_Core_Error::fatal('Missing a membership ID');
    }
    try{
      $this->membership = civicrm_api3('Membership', 'getsingle', array('id' => $this->id));
    }catch(Exception $e){
      CRM_Core_Error::fatal('Not a valid membership ID');
    }
    $this->_contactId = $this->membership['contact_id']; //useful?
  }

  function validateStartStatus(){
    $this->membershipStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $this->membership['status_id']));
    if(!in_array($this->membershipStatus['name'], $this->validStartStatuses)){
      CRM_Core_Error::fatal("You cannot run '{$this->title}' when the membership status is '{$this->membershipStatus['name']}'.");
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
}
