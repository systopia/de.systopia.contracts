<?php

class CRM_Contract_Page_Review extends CRM_Core_Page {

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    if(!$id = CRM_Utils_Request::retrieve('id', 'Positive')){
      Throw new Exception('Missing a valid contract ID');
    }

    $activityStatuses = civicrm_api3('OptionValue', 'get', [ 'option_group_id' => "activity_status"]);

    foreach(civicrm_api3('OptionValue', 'get', [ 'option_group_id' => 'activity_status', 'return' => ['value', 'label'] ])['values'] as $activityStatus){
      $activityStatuses[$activityStatus['value']] = $activityStatus['label'];
    }
    $this->assign('activityStatuses', $activityStatuses);

    $this->assign('activityTypes', CRM_Contract_ModificationActivity::getModificationActivityTypeLabels());
    $this->assign('includeWysiwygEditor', true);

    // Example: Assign a variable for use in a template
    $this->assign('activities',   $activitiesForReview = civicrm_api3('Activity', 'get', [
      'source_record_id' => $id,
      'options' => array('sort' => "activity_date_time DESC"),
      'activity_date_time' => ['>' => date('Y-m-d H:i:s')]
    ])['values']);

    parent::run();
  }

}
