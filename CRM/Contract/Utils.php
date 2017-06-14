<?php

class CRM_Contract_Utils{

  private static $_singleton;
  private static $coreMembershipHistoryActivityIds;
  static $customFieldCache;

  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Utils();
    }
    return self::$_singleton;
  }

  static $ContractToModificationActivityField = [
    'id' => 'source_record_id',
    'contact_id' => 'target_contact_id',
    'campaign_id' => 'campaign_id',
    'membership_type_id' => 'contract_updates.ch_membership_type',
    'membership_payment.membership_recurring_contribution' => 'contract_updates.ch_recurring_contribution',
    'membership_cancellation.membership_cancel_reason' => 'contract_cancellation.contact_history_cancel_reason',
    'membership_payment.membership_annual' => 'contract_updates.ch_annual',
    'membership_payment.membership_frequency' => 'contract_updates.ch_frequency',
    'membership_payment.from_ba' => 'contract_updates.ch_to_ba',
    'membership_payment.to_ba' => 'contract_updates.ch_from_ba',
    'membership_payment.cycle_day' => 'contract_updates.ch_cycle_day'
];



  static function contractToActivityFieldId($contractField){
    $translation = self::$ContractToModificationActivityField;
    $activityField = $translation[$contractField];
    if(strpos($activityField, '.')){
      return self::getCustomFieldId($activityField);
    }
    return $activityField;
  }


  static function getCustomFieldId($customField){

    self::warmCustomFieldCache();

    // Look up if not in cache
    if(!isset(self::$customFieldCache[$customField])){
      $parts = explode('.', $customField);
      try{
        self::$customFieldCache[$customField] = 'custom_'.civicrm_api3('CustomField', 'getvalue', [ 'return' => "id", 'custom_group_id' => $parts[0], 'name' => $parts[1]]);
      }catch (Exception $e){
        throw new Exception("Could not find custom field '{$parts[1]}' in custom field set '{$parts[0]}'");
      }
    }

    // Return result or return an error if it does not exist.
    if(isset(self::$customFieldCache[$customField])){
      return self::$customFieldCache[$customField];
    }else{
      throw new Exception('Could not find custom field id for '.$customField);
    }
  }

  static function getCustomFieldName($customFieldId){

    self::warmCustomFieldCache();
    $name = array_search($customFieldId, self::$customFieldCache);
    if(!$name){
      $customField = civicrm_api3('CustomField', 'getsingle', [ 'id' => substr($customFieldId, 7)]);
      $customGroup = civicrm_api3('CustomGroup', 'getsingle', [ 'id' => $customField['custom_group_id']]);
      self::$customFieldCache["{$customGroup['name']}.{$customField['name']}"] = $customFieldId;
    }
    // Return result or return an error if it does not exist.
    if($name = array_search($customFieldId, self::$customFieldCache)){
      return $name;
    }else{
      throw new Exception('Could not find custom field for id'.$customFieldId);
    }
  }

  static function warmCustomFieldCache(){
    if(!self::$customFieldCache){
      $customGroupNames = ['membership_general', 'membership_payment', 'membership_cancellation', 'contract_cancellation', 'contract_updates'];
      $customGroups = civicrm_api3('CustomGroup', 'get', [ 'name' => ['IN' => $customGroupNames], 'return' => 'name'])['values'];
      $customFields = civicrm_api3('CustomField', 'get', [ 'custom_group_id' => ['IN' => $customGroupNames]]);
      foreach($customFields['values'] as $c){
        self::$customFieldCache["{$customGroups[$c['custom_group_id']]['name']}.{$c['name']}"] = "custom_{$c['id']}";
      }
    }
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
