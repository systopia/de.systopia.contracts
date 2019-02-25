<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

abstract class CRM_Contract_ModificationActivity{

  var $errors = array();

  static $modificationActivityClasses = [
    'CRM_Contract_ModificationActivity_Cancel',
    'CRM_Contract_ModificationActivity_Pause',
    'CRM_Contract_ModificationActivity_Resume',
    'CRM_Contract_ModificationActivity_Revive',
    'CRM_Contract_ModificationActivity_Sign',
    'CRM_Contract_ModificationActivity_Update',
  ];


  function getActivityTypeId(){
    $result = civicrm_api3('OptionValue', 'getvalue', [
      'return' => "value",
      'option_group_id' => "activity_type",
      'name' => $this->getActivityType()
    ]);
    return $result;
  }

  static function findByAction($action){
    foreach (self::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      if($action == $activityClass->getAction()){
        return $activityClass;
      }
    }
    throw new Exception("Could not find a valid modification activity for $action");

  }

  /**
   * finds an appropriate child of the CRM_Contract_ModificationActivity when
   * given the activityTypeId of the corresponding activity type
   * @param int $id the activityTypeId of the corresponding activity type
   * @return CRM_Contract_ModificationActivity
   */
  static function findById($id){
    $name = civicrm_api3('OptionValue', 'getsingle', ['option_group_id' => 'activity_type', 'value' => $id, 'return' => 'name'])['name'];
    foreach (self::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      if($name == $activityClass->getActivityType()){
        return $activityClass;
      }
    }
  }

  static function findByStatusChange($startStatus, $endStatus){
    if ($startStatus == $endStatus) {
      // introducing NoStatuChange class, see GP-1207
      return new CRM_Contract_ModificationActivity_NoStatusChange($startStatus);
    }

    foreach (self::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      if(
        in_array($startStatus, $activityClass->getStartStatuses()) &&
        $endStatus == $activityClass->getEndStatus()
      ){
        return $activityClass;
      }
    }
    return false;
  }

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

  static function getModificationActivityTypeLabels(){
    foreach (self::$modificationActivityClasses as $class) {
      $activityClass = new $class;
      $activityTypes[] = $activityClass->getActivityType();
    }
    foreach(civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'activity_type',
      'name' => ['IN' => $activityTypes],
      'return' => ['value', 'label']
    ])['values'] as $activityType){
      $activityTypeIds[$activityType['value']] = $activityType['label'];
    }
    return $activityTypeIds;
  }


  function validateParams($params, $start){
    $this->params = $params;
    $this->start = $start;
    unset($this->params['status_id']);
    unset($this->params['id']);
    if(isset($this->allowed)){
      $this->checkAllowed();
    }
    $this->checkRequired();
    return !count($this->errors);
  }


  // This class should be overridden by child classes when they want to do extra
  // validation
  function validateExtra(){
  }

  function checkAllowed(){
    foreach($this->params as $key => $param){
      if(!in_array($key, $this->allowed)){
        if(isset($this->start[$key]) && $this->params[$key] != $this->start[$key]){
          $this->errors[$key] = "Cannot update '{$key}' when {$this->getGerund()} a contract";
        }
      }
    }
  }
  function checkRequired(){
    if(isset($this->required)){
      foreach($this->required as $required){
        if(!isset($this->params[$required]) || !$this->params[$required]){
          $this->errors[$required] = "'{$required}' is required when {$this->getGerund()} a contract";
        }
      }
    }
  }

  // For when a modification wants to check that the recurring payment is not
  // already associated with another contract.
  function checkPaymentNotAssociatedWithAnotherContract(){

    // If we have been asked to update the associated recurring contribution
    if(isset($this->params['membership_payment.membership_recurring_contribution'])){

      // Get all contracts already associated with this contribution (hopefully
      // only one)
      $associatedContracts = civicrm_api3('Membership', 'get', [
        CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution') => $this->params['membership_payment.membership_recurring_contribution']
      ]);

      // If this is a modification, then we need to exlude the current contract
      // from the list of contracts to check that the payment is associated with
      if(isset($this->start['id'])){
        unset($associatedContracts['values'][$this->start['id']]);
      }

      if(count($associatedContracts['values'])){
        $this->errors['membership_payment.membership_recurring_contribution'] = "Recurring payment '$this->params['membership_payment.membership_recurring_contribution']' is already associated with contract '".implode(',', array_keys($associatedContracts['values']))."'";
      }
    }
  }

  function getErrors(){
    return $this->errors;
  }

  abstract function getAction();



  /**
   * Check if scheduling a new modification activity
   * might be undesirable given the circumstances.
   *
   * @return TRUE if the activity should NOT be scheduled in this case
   *
   * @see GP-1190
   */
  public static function omitCreatingActivity($params, $date) {
    $cancelActivity = new CRM_Contract_ModificationActivity_Cancel();

    if ($params['action'] == $cancelActivity->getAction()) {
      // IF another cancel activity already scheduled for the same date
      $requested_day = date('Y-m-d', strtotime($date));
      $scheduled_activities = civicrm_api3('Activity', 'get', array(
        'source_record_id' => $params['id'],
        'activity_type_id' => $cancelActivity->getActivityType(),
        'status_id'        => 'Scheduled',
        'option.limit'     => 0,
        'sequential'       => 1,
        'return'           => 'id,activity_date_time'));
      foreach ($scheduled_activities['values'] as $scheduled_activity) {
        $scheduled_for_day = date('Y-m-d', strtotime($scheduled_activity['activity_date_time']));
        if ($scheduled_for_day == $requested_day) {
          // there's already a scheduled 'cancel' activity for the same day
          return TRUE;
        }
      }

      // IF CONTRACT ALREADY CANCELLED, create another cancel activity only
      //  when there are other schedueld (or 'needs review') changes
      //  see https://redmine.greenpeace.at/issues/1190
      $contract = civicrm_api3('Membership', 'getsingle', array(
        'id'     => $params['id'],
        'return' => 'id,status_id'));
      $contract_cancelled_status = civicrm_api3('MembershipStatus', 'get', array(
        'name'   => 'Cancelled',
        'return' => 'id'));
      if ($contract['status_id'] == $contract_cancelled_status['id']) {
        // contract is cancelled
        $pending_activity_count = civicrm_api3('Activity', 'getcount', array(
          'source_record_id' => $params['id'],
          'activity_type_id' => ['IN' => CRM_Contract_ModificationActivity::getModificationActivityTypeIds()],
          'status_id'        => ['IN' => ['Scheduled', 'Needs Review']],
        ));
        if ($pending_activity_count == 0) {
          return TRUE;
        }
      }

      // TODO: other unwanted scenarios?
    }


    // no matching scenario...? I guess than it's o.k. to create this
    return FALSE;
  }
}
