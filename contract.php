<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

require_once 'contract.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function contract_civicrm_config(&$config) {
  _contract_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function contract_civicrm_xmlMenu(&$files) {
  _contract_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function contract_civicrm_install() {
  _contract_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function contract_civicrm_postInstall() {
  _contract_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function contract_civicrm_uninstall() {
  _contract_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function contract_civicrm_enable() {
  _contract_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function contract_civicrm_disable() {
  _contract_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function contract_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _contract_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function contract_civicrm_managed(&$entities) {
  _contract_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function contract_civicrm_caseTypes(&$caseTypes) {
  _contract_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function contract_civicrm_angularModules(&$angularModules) {
  _contract_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function contract_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _contract_civix_civicrm_alterSettingsFolders($metaDataFolders);
}


function contract_civicrm_pageRun( &$page ){
  if($page->getVar('_name') == 'CRM_Member_Page_Tab'){
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('cid' => $page->_contactId));
    CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'templates/CRM/Member/Page/Tab.js');
  }
}


function contract_civicrm_buildForm($formName, &$form) {


  switch ($formName) {

    // Membership form in view mode
    case 'CRM_Member_Form_MembershipView':
      $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $form);
      $modifyForm = new CRM_Contract_FormUtils($form, 'Membership');
      $modifyForm->showPaymentContractDetails();
      break;

    // Membership form in add or edit mode
    case 'CRM_Member_Form_Membership':
    if($form->getAction() ===CRM_Core_Action::ADD){
      if($cid = CRM_Utils_Request::retrieve('cid', 'Integer')){
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contract/create', 'cid='.$cid, true));
      }else{
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contract/rapidcreate', true));

      }
    }
      $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $form);
      if(in_array($form->getAction(), array(CRM_Core_Action::UPDATE, CRM_Core_Action::ADD))){

        // Use JS to hide form elements
        CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'templates/CRM/Member/Form/Membership.js');
        CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('filteredMembershipStatuses' => civicrm_api3('MembershipStatus', 'get', ['name' => ['IN' => ['Current', 'Cancelled']]])));
        CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('hiddenCustomFields' => civicrm_api3('CustomField', 'get', ['name' => ['IN' => ['membership_annual', 'membership_frequency']]])));

        if($form->getAction() == CRM_Core_Action::ADD){
          $form->setDefaults(array(
            'is_override' => true,
            'status_id' => civicrm_api3('MembershipStatus', 'getsingle', array('name' => "current"))['id']
          ));
        }

        $formUtils = new CRM_Contract_FormUtils($form, 'Membership');
        if(!isset($form->_groupTree)){
          // NOTE for initial launch: all core membership fields should be editable
          // $formUtils->removeMembershipEditDisallowedCoreFields();
          // NOTE for initial launch: allow editing of payment contracts via the standard form

        // Custom data version
        }else{
          $result = civicrm_api3('CustomField', 'GetSingle', array('custom_group_id' => 'membership_payment', 'name' => 'membership_recurring_contribution'));
          $customGroupTableId = isset($form->_groupTree[$result['custom_group_id']]['table_id']) ? $form->_groupTree[$result['custom_group_id']]['table_id'] : '-1';
          $elementName = "custom_{$result['id']}_{$customGroupTableId}";
          $form->removeElement($elementName);
          $formUtils->addPaymentContractSelect2($elementName, $contactId);
          // NOTE for initial launch: all custom membership fields should be editable
          $formUtils->removeMembershipEditDisallowedCustomFields();
        }
      }
      break;

    //Activity form in view mode
    case 'CRM_Activity_Form_Activity':
      if($form->getAction() == CRM_Core_Action::VIEW){
        $id =  CRM_Utils_Request::retrieve('id', 'Positive', $form);
        $modifyForm = new CRM_Contract_FormUtils($form, 'Activity');
        $modifyForm->showPaymentContractDetails();
      }
      break;

  }
}

function contract_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors){
  switch ($formName) {
    case 'CRM_Member_Form_Membership':
      if(in_array($form->getAction(), array(CRM_Core_Action::UPDATE, CRM_Core_Action::ADD))){
        $id = CRM_Utils_Request::retrieve('id', 'Positive', $form);
        $contractHandler = new CRM_Contract_Handler();
        $contractHandler->setStartMembership($id);
        $fields['membership_type_id'] = $fields['membership_type_id'][1];
        $contractHandler->sanitizeParams($fields);
        $contractHandler->addProposedParams($fields);
        if(!$contractHandler->isValidStatusUpdate()){
          $errors['status_id']=$contractHandler->errors['status_id'];
          return;
        }
        $contractHandler->validateFieldUpdate();
        if(count($contractHandler->errors)){
          $errors = $contractHandler->errors;
          // We have to add the number back onto the custom field id
          foreach($errors as $key => $message){
            if(!isset($form->_elementIndex[$key])){
              // If it isn't in the element index, presume it is a custom field
              // with the end missing and find the appropriate key for it.
              foreach($form->_elementIndex as $element => $discard){
                if(strpos($element, $key) === 0){
                  $errors[$element] = $message;
                  unset($errors[$key]);
                  break;
                }
              }
            }
          }
        }
      }
  }
}

function contract_civicrm_links( $op, $objectName, $objectId, &$links, &$mask, &$values ){
  switch ($objectName) {
    case 'Membership':
      $alter = new CRM_Contract_AlterMembershipLinks($objectId, $links, $mask, $values);
      // NOTE for initial launch: Allow updating of contracts via standard form.
      // $alter->removeActions(array(CRM_Core_Action::RENEW, CRM_Core_Action::DELETE, CRM_Core_Action::UPDATE));
      $alter->addHistoryActions();
      $alter->removeActions(array(CRM_Core_Action::RENEW, CRM_Core_Action::DELETE));
      // NOTE for initial launch: Remove links to new actions for updating contracts
      break;
    }
}

function contract_civicrm_pre($op, $objectName, $id, &$params){
  // if($objectName == 'Membership' && in_array($op, array('create', 'edit'))){
  //   $BAOWrapper = CRM_Contract_Modify_BAOWrapper::singleton($op);
  //   $BAOWrapper->pre($id, $params);
  // }
}

function contract_civicrm_post($op, $objectName, $id, &$objectRef){
  // if($objectName == 'Membership')
  //   if(in_array($op, array('create', 'edit'))){
  //     $BAOWrapper = CRM_Contract_Modify_BAOWrapper::singleton($op);
  //     $BAOWrapper->post($id);
  // }
  if($objectName == 'Activity'){
    if($op == 'create' && in_array($objectRef->activity_type_id, CRM_Contract_Utils::getCoreMembershipHistoryActivityIds())){
      civicrm_api3('Activity', 'delete', array('id' => $id));
    }
  }
}

// In an effort to keep this file small, we only add simple conditionals to API
// wrappers here. Further filtering should happen in the API wrapper class.

function contract_civicrm_apiWrappers(&$wrappers, $apiRequest) {
  if(
    $apiRequest['entity'] == 'Membership' &&
    $apiRequest['action'] == 'create'
  ){
    $wrappers[] = CRM_Contract_Wrapper_ContractAPI::singleton();
  }
  if(
    $apiRequest['entity'] == 'Activity' &&
    $apiRequest['action'] == 'create' &&
    in_array($apiRequest['params']['activity_type_id'], CRM_Contract_Utils::getModificationActivityTypeIds())
  ){
    $wrappers[] = new CRM_Contract_Wrapper_ModificationActivity;
  }
}
