<?php

class CRM_Contract_Utils{


  private static $_singleton;
  private static $coreMembershipHistoryActivityIds;
  private static $customFieldCache;

  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Utils();
    }
    return self::$_singleton;
  }


  static $modificationActivityClasses = [
    'CRM_Contract_ModificationActivity_Cancel',
    'CRM_Contract_ModificationActivity_Pause',
    'CRM_Contract_ModificationActivity_Resume',
    'CRM_Contract_ModificationActivity_Revive',
    'CRM_Contract_ModificationActivity_Sign',
    'CRM_Contract_ModificationActivity_Update',
  ];

  static $ContractToActivityCustomFieldTranslation = [
    'membership_type_id' => 'contract_updates.ch_membership_type',
    'membership_payment.membership_recurring_contribution' => 'contract_updates.ch_recurring_contribution',
    'membership_cancellation.membership_cancel_reason' => 'contract_cancellation.contact_history_cancel_reason',
  ];


  static function getModificationActivityTypeIds(){
    foreach (self::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      $activityTypes[] = $activityClass->getActivityType();
    }
    foreach(civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'activity_type',
      'name' => ['IN' => $activityTypes],
      'return' => 'value'
    ])['values'] as $activityType){
      $activityTypeIds[] = $activityType['value'];
    }
    return $activityTypeIds;
  }

  static function getModificationActivityFromId($id){
    $name = civicrm_api3('OptionValue', 'getsingle', ['option_group_id' => 'activity_type', 'value' => $id, 'return' => 'name'])['name'];
    foreach (self::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      if($name == $activityClass->getActivityType()){
        return $activityClass;
      }
    }
  }

  static function getModificationActivityFromAction($action){
    foreach (self::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      if($action == $activityClass->getAction()){
        return $activityClass;
      }
    }
    throw new Exception("Could not find a valid modification activity for $action");

  }

  static function contractToActivityCustomFieldId($contractField){
    $translation = self::$ContractToActivityCustomFieldTranslation;
    $activityField = $translation[$contractField];
    return self::getCustomFieldId($activityField);
  }


  static function getCustomFieldId($customField){

    // Warm cache on first invocation
    if(!self::$customFieldCache){
      $customGroupNames = ['membership_general', 'membership_payment', 'membership_cancellation', 'contract_cancellation', 'contract_updates'];
      $customGroups = civicrm_api3('CustomGroup', 'get', [ 'name' => ['IN' => $customGroupNames], 'return' => 'name'])['values'];
      $customFields = civicrm_api3('CustomField', 'get', [ 'custom_group_id' => ['IN' => $customGroupNames]]);
      foreach($customFields['values'] as $c){
        self::$customFieldCache["{$customGroups[$c['custom_group_id']]['name']}.{$c['name']}"] = "custom_{$c['id']}";
      }
    }

    // Look up if not in cache
    if(!isset(self::$customFieldCache[$customField])){
      $parts = explode('.', $customField);
      try{
        self::$customFieldCache[$customField] = 'custom_'.civicrm_api3('CustomField', 'getvalue', [ 'return' => "id", 'custom_group_id' => $parts[0], 'name' => $parts[1]]);
      }catch (Exception $e){
        throw new Exception("Could not find custom field '{$parts[0]}' in custom field set '{$parts[1]}'");
      }
    }

    // Return result or return an error if it does not exist.
    if(isset(self::$customFieldCache[$customField])){
      return self::$customFieldCache[$customField];
    }else{

    }
  }

  function isValidStatusChange($startStatus, $endStatus){
    foreach (self::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      if(
        in_array($startStatus, $activityClass->getStartStatuses()) &&
        $endStatus == $activityClass->getEndStatus()
      ){
        return true;
      }
    }
    return false;
  }

  static function getCoreMembershipHistoryActivityIds(){
    if (!self::$coreMembershipHistoryActivityIds) {
      $result = civicrm_api3('OptionValue', 'get', [
        'option_group_id' => 'activity_type',
        'name' => ['IN' => ['Membership Signup', 'Membership Renewal', 'Change Membership Status', 'Change Membership Type', 'Membership Renewal Reminder']]]
      );
      foreach($result['values'] as $v){
        self::$coreMembershipHistoryActivityIds[] = $v['value'];
      }
    }
    return self::$coreMembershipHistoryActivityIds;
  }
}
