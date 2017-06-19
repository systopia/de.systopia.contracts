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



  static function contractToActivityCustomFieldId($contractField){
    $translation = self::$ContractToModificationActivityField;
    $activityField = $translation[$contractField];
    return self::getCustomFieldId($activityField);
  }


  static function getCustomFieldId($customField){

    self::warmCustomFieldCache();

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

  private static function warmCustomFieldCache(){
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

  /**
   * Download contract file
   * @param $file
   *
   * @return bool
   */
  static function downloadContractFile($file)
  {
    if (!CRM_Contract_Utils::contractFileExists($file)) {
      return false;
    }
    $fullPath = CRM_Contract_Utils::contractFilePath($file);

    ignore_user_abort(true);
    set_time_limit(0); // disable the time limit for this script

    if ($fd = fopen($fullPath, "r")) {
      $fsize = filesize($fullPath);
      $path_parts = pathinfo($fullPath);
      $ext = strtolower($path_parts["extension"]);
      header("Content-type: application/octet-stream");
      header("Content-Disposition: filename=\"" . $path_parts["basename"] . "\"");
      header("Content-length: $fsize");
      header("Cache-control: private"); //use this to open files directly
      while (!feof($fd)) {
        $buffer = fread($fd, 2048);
        echo $buffer;
      }
    }
    fclose($fd);
    exit;
  }

  /**
   * Check if contract file exists, return false if not
   * @param $logFile
   * @return boolean
   */
  static function contractFileExists($file) {
    $fullPath = CRM_Contract_Utils::contractFilePath($file);
    if ($fullPath) {
      if (file_exists($fullPath)) {
        return $fullPath;
      }
    }
    return false;
  }

  /**
   * Simple function to get real file name from contract number
   * @param $file
   *
   * @return string
   */
  static function contractFileName($file) {
    return $file.'.tif';
  }

  /**
   * This is hardcoded so contract files must be stored in customFileUploadDir/contracts/
   * Extension hardcoded to .tif
   * FIXME: This could be improved to use a setting to configure this.
   *
   * @param $file
   *
   * @return bool|string
   */
  static function contractFilePath($file) {
    // We need a valid filename
    if (empty($file)) {
      return FALSE;
    }

    // Use the custom file upload dir as it's protected by a Deny from All in htaccess
    $config = CRM_Core_Config::singleton();
    if (!empty($config->customFileUploadDir)) {
      $fullPath = $config->customFileUploadDir . "/contracts/" . self::contractFileName($file);
      return $fullPath;
    }
    else {
      CRM_Core_Error::debug_log_message('Warning: Contract file path undefined! Did you set customFileUploadDir?');
      return FALSE;
    }
  }
}
