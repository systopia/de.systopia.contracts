<?php

class CRM_Contract_Page_Review extends CRM_Core_Page {

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    if(!$id = CRM_Utils_Request::retrieve('id', 'Positive')){
      Throw new Exception('Missing a valid contract ID');
    }

    // Get activity statuses
    $activityStatuses = civicrm_api3('OptionValue', 'get', [ 'option_group_id' => "activity_status"]);
    foreach(civicrm_api3('OptionValue', 'get', [ 'option_group_id' => 'activity_status', 'return' => ['value', 'label'] ])['values'] as $activityStatus){
      $activityStatuses[$activityStatus['value']] = $activityStatus['label'];
    }
    $this->assign('activityStatuses', $activityStatuses);

    // Get activity types
    $this->assign('activityTypes', CRM_Contract_ModificationActivity::getModificationActivityTypeLabels());
    $this->assign('includeWysiwygEditor', true);

    // Get membership types
    $membershipTypes = civicrm_api3('MembershipType', 'get')['values'];
    $this->assign('membershipTypes', $membershipTypes);

    // Get campaign types
    $campaigns = civicrm_api3('Campaign', 'get')['values'];
    $this->assign('campaigns', $campaigns);

    $activityParams = [
      'source_record_id' => $id,
      'status_id' => ['NOT IN' => ['cancelled']],
      'return' => [
        'activity_date_time',
        'status_id',
        'activity_type_id',
        'target_contact_id',
        'campaign_id'
      ],
    ];

    // Get activities
    foreach(civicrm_api3('CustomField', 'get', [ 'custom_group_id' => ['IN' => ['contract_cancellation', 'contract_updates']]])['values'] as $customField){
      $activityParams['return'][]='custom_'.$customField['id'];
    }

    // Friendlify custom field names
    CRM_Contract_Utils::warmCustomFieldCache();
    $customFieldIndex = array_flip(CRM_Contract_Utils::$customFieldCache);
    $customFieldIndex = str_replace('.', '_', $customFieldIndex);
    $activities = civicrm_api3('Activity', 'get', $activityParams)['values'];
    foreach($activities as $key => $activity){

      // For accurate sorting by time
      $activities[$key]['activity_date_unixtime'] = strtotime($activity['activity_date_time']);
      foreach($activity as $innerKey => $field){
        if(isset($customFieldIndex[$innerKey])){
          unset($activities[$key][$innerKey]);
          $activities[$key][$customFieldIndex[$innerKey]] = $field;
        }
      }
    }

    // Sort activities
    usort($activities, function($a, $b){
      return $b['activity_date_unixtime'] - $a['activity_date_unixtime'];
    });

    // var_dump(CRM_Contract_Utils::$customFieldCache);
    // var_dump($activityParams);

    $this->assign('activities', $activities);

    CRM_Core_Resources::singleton()->addScriptFile('civicrm', 'packages/ckeditor/ckeditor.js');
    foreach(civicrm_api3('CustomField', 'get', [ 'custom_group_id' => ['IN' => ['contract_cancellation', 'contract_updates']]])['values'] as $customField){
      $activityParams['return'][]='custom_'.$customField['id'];
    }

    parent::run();
  }

}
