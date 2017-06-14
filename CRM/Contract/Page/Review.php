<?php

class CRM_Contract_Page_Review extends CRM_Core_Page {

  public function run() {
    if(!$id = CRM_Utils_Request::retrieve('id', 'Positive')){
      Throw new Exception('Missing a valid contract ID');
    }

    // Set activity params
    $activityParams = [
      'source_record_id' => $id,
      'status_id' => ['NOT IN' => ['cancelled']],
      'return' => [
        'activity_date_time',
        'status_id',
        'activity_type_id',
        'target_contact_id',
        'source_contact_id',
        'details',
        'campaign_id',
        'medium_id'
      ],
    ];
    foreach(civicrm_api3('CustomField', 'get', [ 'custom_group_id' => ['IN' => ['contract_cancellation', 'contract_updates']]])['values'] as $customField){
      $activityParams['return'][]='custom_'.$customField['id'];
    }
    $activities = civicrm_api3('Activity', 'get', $activityParams)['values'];


    // Friendlify custom field names
    CRM_Contract_Utils::warmCustomFieldCache();
    $customFieldIndex = array_flip(CRM_Contract_Utils::$customFieldCache);
    $customFieldIndex = str_replace('.', '_', $customFieldIndex);

    // To collect the campaign ids that we need to get the names of
    $campaigns=[];
    $contacts=[];



    foreach($activities as $key => $activity){

      // For accurate sorting by time
      $activities[$key]['activity_date_unixtime'] = strtotime($activity['activity_date_time']);

      foreach($activity as $innerKey => $field){
        if(isset($customFieldIndex[$innerKey])){
          unset($activities[$key][$innerKey]);
          $activities[$key][$customFieldIndex[$innerKey]] = $field;
        }
      }
      if(isset($activities[$key]['contract_updates_ch_recurring_contribution']) && $activities[$key]['contract_updates_ch_recurring_contribution']){
        civicrm_api3('ContributionRecur', 'getsingle', ['id' => $activities[$key]['contract_updates_ch_recurring_contribution']]);
        $c = new CRM_Contract_RecurringContribution;
        $activities[$key]['contract_updates_ch_recurring_contribution_text'] = $c->writePaymentContractLabel( civicrm_api3('ContributionRecur', 'getsingle', ['id' => $activities[$key]['contract_updates_ch_recurring_contribution']]));
      }
      if(isset($activities[$key]['contract_updates_ch_annual']) && isset($activities[$key]['contract_updates_ch_frequency']) && $activities[$key]['contract_updates_ch_annual'] && $activities[$key]['contract_updates_ch_frequency']){
        $activities[$key]['contract_updates_ch_amount'] = $activities[$key]['contract_updates_ch_annual'] / $activities[$key]['contract_updates_ch_frequency'];
      }
      if(isset($activities[$key]['campaign_id'])){
        $campaigns[] = $activities[$key]['campaign_id'];
      }
      if(isset($activities[$key]['source_contact_id'])){
        $contacts[] = $activities[$key]['source_contact_id'];
      }
    }

    // Sort activities
    usort($activities, function($a, $b){
      return $b['activity_date_unixtime'] - $a['activity_date_unixtime'];
    });

    $this->assign('activities', $activities);

    // Get campaigns
    if($campaigns){
      foreach(civicrm_api3('Campaign', 'get', ['id' => ['IN' => array_unique($campaigns)]])['values'] as $campaign){
        $campaigns[$campaign['id']] = $campaign['name'];
      }
    }
    $this->assign('campaigns', $campaigns);

    foreach(civicrm_api3('Contact', 'get', ['id' => ['IN' => array_unique($contacts)]])['values'] as $contact){
      $contacts[$contact['id']] = $contact['display_name'];
    }
    $this->assign('contacts', $contacts);

    foreach(civicrm_api3('OptionValue', 'get', [ 'option_group_id' => 'encounter_medium', 'return' => ['value', 'label'] ])['values'] as $medium){
      $mediums[$medium['value']] = $medium['label'];
    }
    $this->assign('mediums', $mediums);


    // Get activity statuses
    foreach(civicrm_api3('OptionValue', 'get', [ 'option_group_id' => 'activity_status', 'return' => ['value', 'label'] ])['values'] as $activityStatus){
      $activityStatuses[$activityStatus['value']] = $activityStatus['label'];
    }
    $this->assign('activityStatuses', $activityStatuses);

    foreach(civicrm_api3('OptionValue', 'get', [ 'option_group_id' => 'payment_frequency', 'return' => ['value', 'label'] ])['values'] as $paymentFrequency){
      $paymentFrequencies[$paymentFrequency['value']] = $paymentFrequency['label'];
    }
    $this->assign('paymentFrequencies', $paymentFrequencies);

    // Get activity types
    $this->assign('activityTypes', CRM_Contract_ModificationActivity::getModificationActivityTypeLabels());
    $this->assign('includeWysiwygEditor', true);

    // Get membership types
    foreach(civicrm_api3('MembershipType', 'get', [])['values'] as $membershipType){
      $membershipTypes[$membershipType['id']] = $membershipType['name'];
    }
    $this->assign('membershipTypes', $membershipTypes);

    $this->assign('membershipTypes', $membershipTypes);




    CRM_Core_Resources::singleton()->addScriptFile('civicrm', 'packages/ckeditor/ckeditor.js');
    foreach(civicrm_api3('CustomField', 'get', [ 'custom_group_id' => ['IN' => ['contract_cancellation', 'contract_updates']]])['values'] as $customField){
      $activityParams['return'][]='custom_'.$customField['id'];
    }

    parent::run();
  }

}
