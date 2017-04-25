<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * The processor will 'execute' pending changes recorded as activities
 */
class CRM_Contract_Processor {

  // store singleton instance
  static private $singleton = null;

  /**
   * public access to processor instance
   */
  public static function singleton() {
    if (self::$singleton === null) {
      self::$singleton = new CRM_Contract_Processor();
    }
    return self::$singleton;
  }

  /**
   * private init
   */
  protected function __construct() {
    // TODO: load activites from DB
    $this->contract_activities = array(
      54 => 'Contract_Signed',
      55 => 'Contract_Paused',
      56 => 'Contract_Resumed',
      // and so on
      );
    $this->contract_activity_list = implode(',', array_keys($this->contract_activities));
  }

  /**
   * look up the activity type's name
   */
  protected function getActivityTypeName($activity_type_id) {
    return CRM_Utils_Array::value($activity_type_id, $this->contract_activities, 'Irrelevant');
  }

  /**
   * Process this activity right away
   * (shortcut to processActivity method)
   */
  public static function processScheduledActivity($activity_id, $skip_checks = False) {
    $processor = self::singleton();
    return $processor->processActivity($activity_id, $skip_checks);
  }

  /**
   * Process all 'scheduled' activities with a date <= now()
   * Most likely to be called from a cron job
   *
   * @param $limit  maximum amount of activities to process
   */
  public static function processPendingActivities($limit = 0) {
    $processor = self::singleton();

    // calculate limit clause
    if ($limit && is_numeric($limit)) {
      $limit_clause = "LIMIT " . ((int) $limit);
    } else {
      $limit_clause = '';
    }

    // find all
    $eligible_activity = CRM_Core_DAO::executeQuery("
        SELECT
          id               AS activity_id,
          activity_type_id AS activity_type_id
        FROM civicrm_activity
        WHERE status_id = 1
          AND activity_date_time <= NOW()
          AND activity_type_id IN ({$this->contract_activity_list})
        ORDER BY activity_date_time ASC {$limit_clause};");

    while ($eligible_activity->fetch()) {
      $processor->processActivity($eligible_activity->activity_id, TRUE);
    }
  }

  /**
   * Process the given activity. The result is recorded in the
   * activity's status: Completed (2) if successfull,
   *                    Failed (TODO) if unsuccessfull
   *
   * @param $activity_id  the activity to be processed
   * @param $skip_checks  don't check the status / time of the activity
   */
  public function processActivity($activity_id, $skip_checks = False) {
    $activity = civicrm_api3('Activity', 'getsingle', array('id' => $activity_id));

    if (!$skip_checks) {
      // TODO: verify status & time
    }

    $activity_type_name = $this->getActivityTypeName($activity['activity_type_id']);
    switch ($activity_type_name) {
      case 'Contract_Signed':
        $this->failActivity($activity_id, "Contract_Signed cannot be scheduled!");
        break;

      case 'Contract_Paused':
        $this->processPause($activity);
        break;

      case 'Contract_Resumed':
        $this->processResume($activity);
        break;

      case 'Contract_Updated':
        $this->processUpdate($activity);
        break;

      case 'Contract_Cancelled':
        $this->processCancellation($activity);
        break;

      case 'Contract_Revived':
        $this->processRevive($activity);
        break;

      default:
        // none of our contact types
        break;
    }
  }


  /**
   * This should set the activity to 'Completed' indicating
   * that the processing was successfull
   */
  protected function completeActivity($activity_id) {
    civicrm_api3('Activity', 'create', array(
      'id'        => $activity_id,
      'status_id' => 2 // Completed
      // TODO: set processing date?
      ));
  }

  /**
   * This should set the activity to 'Completed' indicating
   * that the processing was successfull
   */
  protected function failActivity($activity_id, $reason) {
    civicrm_api3('Activity', 'create', array(
      'id'        => $activity_id,
      'status_id' => 5 // TODO: new status Failed
      // TODO: set processing date?
      ));
    // TODO: store error message? Assign to a contact?
  }




  /****************************************************
   *               PROCESSING CODE                    *
   ***************************************************/

  /**
   * process PAUSE activity
   */
  protected function processPause($activity) {
    // TODO: implement
    $this->completeActivity($activity['id']);
  }

  /**
   * process RESUME activity
   */
  protected function processResume($activity) {
    // TODO: implement
    $this->completeActivity($activity['id']);
  }

  /**
   * process REVIVE activity
   */
  protected function processRevive($activity) {
    // TODO: implement
    $this->completeActivity($activity['id']);
  }

  /**
   * process CANCEL activity
   */
  protected function processCancellation($activity) {
    // TODO: implement
    $this->completeActivity($activity['id']);
  }

  /**
   * process UPDATE activity
   */
  protected function processUpdate($activity) {
    // TODO: implement
    $this->completeActivity($activity['id']);
  }
}
