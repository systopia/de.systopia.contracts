<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Contract_Upgrader extends CRM_Contract_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  public function install() {
    $this->executeCustomDataFile('xml/customdata.xml');
  }

  public function postInstall() {

    // For some reason, the XML dump does not currently support specifying the entity type that the group should extend
    $optionValue = civicrm_api3('OptionValue', 'getsingle', array('option_group_id' => 'activity_type', 'name' => 'Contract history'));
    $customGroup = civicrm_api3('CustomGroup', 'getsingle', array('name' => 'Contract_history'));
    $customGroup['extends_entity_column_value'] = $optionValue['value'];
    civicrm_api3('CustomGroup', 'create', $customGroup);
  }

  public function uninstall() {
    $customFields = civicrm_api3('CustomField', 'get', array('custom_group_id' => 'Contract_history'));
    foreach($customFields['values'] as $CustomField){
      civicrm_api3('CustomField', 'delete', array('id' => $CustomField['id']));
    }
    $customGroup = civicrm_api3('CustomGroup', 'getsingle', array('name' => 'Contract_history'));
    civicrm_api3('CustomGroup', 'delete', $customGroup);
  }
}
