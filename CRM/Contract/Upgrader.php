<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

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
    $this->specifyCustomDataGroupExtends('contract_updates', array("Contract_Revived", "Contract_Updated", "Contract_Resumed"));
    $this->specifyCustomDataGroupExtends('contract_cancellation', array('Contract_Cancelled'));
   }

  public function uninstall() {
    $this->removeCustomDataGroup('contract_updates');
    $this->removeCustomDataGroup('contract_cancellation');
  }

  public function specifyCustomDataGroupExtends($customGroupName, $extends){
    // For some reason, the XML dump does not currently support specifying
    // the entity type that the group should extend. Hence we do this manually
    // via the API
    $customGroup = civicrm_api3('CustomGroup', 'getsingle', array('name' => $customGroupName));


    if($customGroup['extends']=='Activity'){
      $optionValues = civicrm_api3('OptionValue', 'get', array( 'option_group_id' => "activity_type", 'name' => array('IN' => $extends)));
      foreach($optionValues['values'] as $v){
        $customGroup['extends_entity_column_value'][]=$v['value'];
      }
    }else{
      // We only cater for Activity custom data at the moment!
      return;
    }
    civicrm_api3('CustomGroup', 'create', $customGroup);
  }

  public function removeCustomDataGroup($name){
    $customGroup = civicrm_api3('CustomGroup', 'get', array('name' => $name));
    if($customGroup['count'] == 0){
      return;
    }
    $customFields = civicrm_api3('CustomField', 'get', array('custom_group_id' => $name));
    foreach($customFields['values'] as $CustomField){
      civicrm_api3('CustomField', 'delete', array('id' => $CustomField['id']));
    }
    $customGroup = civicrm_api3('CustomGroup', 'getsingle', array('name' => $name));
    civicrm_api3('CustomGroup', 'delete', $customGroup);
  }

}
