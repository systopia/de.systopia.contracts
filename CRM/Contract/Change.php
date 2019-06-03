<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * Base class for contract changes. These are tracked changes to
 *  a contract, represented by an activity
 *
 * This new 'Change' concept is the replacement for the CRM_Contract_ModificationActivity
 *  and the CRM_Contract_Handlers
 */
abstract class CRM_Contract_Change implements  CRM_Contract_Change_SubjectRendererInterface {

  /**
   * Data representing the data. Will mostly be the activity data
   */
  protected $data = NULL;

  /**
   * Contract data (cached)
   */
  protected $contract = NULL;

  /**
   * List of known changes,
   *  activity_type_name => change class
   */
  protected static $type2class = [
    'Contract_Cancelled' => 'CRM_Contract_Change_Cancel',
    'Contract_Signed'    => 'CRM_Contract_Change_Sign',
  ];

  /**
   * List of known actions,
   *  activity_type_name => change class
   */
  protected static $action2class = [
      'cancel' => 'CRM_Contract_Change_Cancel',
      'sign'   => 'CRM_Contract_Change_Sign',
  ];

  /**
   * List of activity_type_id => change class
   * Will be be populated on demand
   */
  protected static $_type_id2class = NULL;

  /**
   * @var array Maps the contract fields to the change activity fields
   */
  protected static $field_mapping_change_contract = [
      'id'                                                   => 'source_record_id',
      'contact_id'                                           => 'target_contact_id',
      'campaign_id'                                          => 'campaign_id',
      'membership_type_id'                                   => 'contract_updates.ch_membership_type',
      'membership_payment.membership_recurring_contribution' => 'contract_updates.ch_recurring_contribution',
      'membership_payment.payment_instrument'                => 'contract_updates.ch_payment_instrument',
      'membership_payment.membership_annual'                 => 'contract_updates.ch_annual',
      'membership_payment.membership_frequency'              => 'contract_updates.ch_frequency',
      'membership_payment.from_ba'                           => 'contract_updates.ch_from_ba',
      'membership_payment.to_ba'                             => 'contract_updates.ch_to_ba',
      'membership_payment.cycle_day'                         => 'contract_updates.ch_cycle_day',
      'membership_payment.defer_payment_start'               => 'contract_updates.ch_defer_payment_start',
      'membership_cancellation.membership_cancel_reason'     => 'contract_cancellation.contact_history_cancel_reason',
  ];


  /**
   * CRM_Contract_Change constructor.
   * @param $data
   */
  protected function __construct($data) {
    $this->data = $data;
    // make sure activity_type_id is numeric
    $this->data['activity_type_id'] = $this->getActvityTypeID();
  }

  ################################################################################
  ##                          ABSTRACT FUNCTIONS                                ##
  ################################################################################

  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  abstract public function execute();

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  abstract public function getRequiredFields();

  /**
   * Render the default subject
   *
   * @param $contract_after       array  data of the contract after
   * @param $contract_before      array  data of the contract before
   * @return                      string the subject line
   */
  abstract public function renderDefaultSubject($contract_after, $contract_before = NULL);


  ################################################################################
  ##                           COMMON FUNCTIONS                                 ##
  ################################################################################

  /**
   * Derive/populate additional data
   */
  public function populateData() {}

  /**
   * Check whether this change activity should actually be created
   *
   * @throws Exception if the creation should be disallowed
   */
  public function shouldBeAccepted() {}

  /**
   * Make sure that the data for this change is valid
   *
   * @throws Exception if the data is not valid
   */
  public function verifyData() {
    // simply check if all required fields are there
    // ...anything else needs to be checked in the specific class...
    $required_fields = $this->getRequiredFields();
    foreach ($required_fields as $required_field) {
      if (!isset($this->data[$required_field])) {
        throw new Exception("Parameter '{$required_field}' missing.");
      }
    }
  }

  /**
   * Get the change ID
   */
  public function getID() {
    return $this->data['id'];
  }

  /**
   * Get the contract ID
   */
  public function getContractID() {
    return $this->data['source_record_id'];
  }

  /**
   * Get the contract data
   *
   * @param boolean $with_payment_data
   */
  public function getContract($with_payment_data = FALSE) {
    $contract_id = $this->getContractID();
    if ($this->contract === NULL || $this->contract['id'] != $contract_id) {
      // (re)load contract
      try {
        $this->contract = civicrm_api3('Membership', 'getsingle', ['id' => $contract_id]);
      } catch (Exception $ex) {
        throw new Exception("Contract [{$contract_id}] not found!");
      }
      CRM_Contract_CustomData::labelCustomFields($this->contract);
    }

    // add the payment data, if requested
    if ($with_payment_data) {
      if (empty($this->contract['membership_payment.membership_frequency'])) {
        $this->derivePaymentData($this->contract);
      }
    }

    return $this->contract;
  }

  /**
   * Enrich the given contact with payment data
   *
   * @param $contract array contract data
   */
  public function derivePaymentData(&$contract) {
    if (!empty($contract['membership_payment.membership_recurring_contribution'])) {
      // we have a recurring contribution!
      try {
        $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $contract['membership_payment.membership_recurring_contribution']]);
        $contract['membership_payment.membership_annual']    = $this->calcAnnualAmount($contributionRecur);
        $contract['membership_payment.membership_frequency'] = $this->calcPaymentFrequency($contributionRecur);
        $contract['membership_payment.cycle_day']            = $contributionRecur['cycle_day'];
        $contract['membership_payment.payment_instrument']   = $contributionRecur['payment_instrument_id'];

        // if this is a sepa payment, get the 'to' and 'from' bank account
        $sepaMandateResult = civicrm_api3('SepaMandate', 'get', array(
            'entity_table' => "civicrm_contribution_recur",
            'entity_id'    => $contributionRecur['id']
        ));
        if($sepaMandateResult['count'] == 1) {
          $sepaMandate = $sepaMandateResult['values'][$sepaMandateResult['id']];
          $contract['membership_payment.from_ba'] = CRM_Contract_BankingLogic::getOrCreateBankAccount($sepaMandate['contact_id'], $sepaMandate['iban'], $sepaMandate['bic']);
          $contract['membership_payment.to_ba']   = CRM_Contract_BankingLogic::getCreditorBankAccount();

        } elseif ($sepaMandateResult['count'] == 0) {
          // this should be a recurring contribution -> get from the latest contribution
          list($from_ba, $to_ba) = CRM_Contract_BankingLogic::getAccountsFromRecurringContribution($contributionRecur['id']);
          $contract['membership_payment.from_ba'] = $from_ba;
          $contract['membership_payment.to_ba']   = $to_ba;

        } else {
          // this is an error:
          $contract['membership_payment.from_ba'] = '';
          $contract['membership_payment.to_ba']   = '';

        }
      } catch(Exception $ex) {
        CRM_Core_Error::debug_log_message("Couldn't load recurring contribution [{$contract['membership_payment.membership_recurring_contribution']}]");
      }
    }
  }

  /**
   * Get the (numeric) activity type ID
   *
   * @return int activity type ID
   */
  public function getActvityTypeID() {
    if (is_numeric($this->data['activity_type_id'])) {
      return $this->data['activity_type_id'];
    }

    // otherwise translate class to ID
    $id2class = self::getActivityTypeId2Class();
    $class2id = array_flip($id2class);
    $activity_type_id = $class2id[get_class($this)];
    return $activity_type_id;
  }

  /**
   * Update the contract with the given data
   *
   * @param $updates array changes: attribute->value
   * @throws Exception
   */
  public function updateContract($updates) {
    // make sure the ID is there
    $updates['id'] = $this->getContractID();

    // derive fields if possible
    $this->derivePaymentData($updates);

    // make sure all fields are resolved
    CRM_Contract_CustomData::resolveCustomFields($updates);

    // finally: write through
    civicrm_api3('Membership', 'create', $updates);

    // and delete the cached contract data (if any)
    $this->contract = NULL;
  }

  /**
   * Calculate the subject line for this activity
   *
   * @param $contract_before array contract before update
   * @param $contract_after  array contract after update
   *
   * @return string subject line
   */
  public function getSubject($contract_after, $contract_before = NULL) {
    $subject_renderer = CRM_Contract_Configuration::getSubjectRender();
    if (!$subject_renderer) {
      $subject_renderer = $this; // use default renderer
    }
    return $subject_renderer->renderChangeSubject($this, $contract_after, $contract_before);
  }

  /**
   * Calculate the activities subject
   *
   * @param $change               CRM_Contract_Change the change object
   * @param $contract_before      array  data of the contract before
   * @param null $contract_after  array  data of the contract after
   * @return                      string the subject line
   */
  public function renderChangeSubject($change, $contract_after, $contract_before = NULL) {
    return $change->renderDefaultSubject($contract_after, $contract_before);
  }


  /**
   * Calculate annual amount
   *
   * @param $contributionRecur array recurring contribution data
   * @return string properly formatted annual amount
   */
  protected function calcAnnualAmount($contributionRecur){
    // only 'month' and 'year' should be in use
    $frequencyUnitTranslate = ['month' => 12, 'year'  => 1];
    return CRM_Contract_SepaLogic::formatMoney(CRM_Contract_SepaLogic::formatMoney($contributionRecur['amount']) * $frequencyUnitTranslate[$contributionRecur['frequency_unit']] / $contributionRecur['frequency_interval']);
  }


  /**
   * Calculate the frequency from the unit/interval set in the recurring contribution data
   * @param $contributionRecur array recurring contribution data
   * @return int payment frequency (in months)
   * @throws Exception if the unit is not recognised ('month' or 'year')
   */
  protected function calcPaymentFrequency($contributionRecur) {
    if (empty($contributionRecur['frequency_interval'])) {
      // unable to calculate
      return 0;
    }

    if ($contributionRecur['frequency_unit'] == 'year') {
      return 1 / $contributionRecur['frequency_interval'];
    } else if ($contributionRecur['frequency_unit'] == 'month') {
      return 12 / $contributionRecur['frequency_interval'];
    } else {
      throw new Exception("Frequency unit '{$contributionRecur['frequency_unit']}' not allowed.");
    }
  }

  /**
   * Set a parameter with the activity
   *
   * @param $key   string property name
   * @param $value string value to set
   */
  public function setParameter($key, $value) {
    $this->data[$key] = $value;
    // TODO: mark as dirty?
  }

  /**
   * Save data to the DB (activity)
   */
  public function save() {
    // make sure all custom fields are transformed into the 'custom_[id]' notation
    CRM_Contract_CustomData::resolveCustomFields($this->data);

    // store via API
    $result = civicrm_api3('Activity', 'create', $this->data);

    // make sure we store the activity ID (if this is the first time)
    if (empty($this->data['id'])) {
      $this->data['id'] = $result['id'];
    }
  }

  /**
   * Check if this change is new, i.e. has not yet been saved to the DB
   */
  public function isNew() {
    return empty($this->data['id']);
  }

  /**
   * Set change status
   *
   * @param $status string valid activity status
   */
  public function setStatus($status) {
    $this->data['status_id'] = $status;
  }

  public function checkForConflicts() {
    // TODO: refactor CRM_Contract_Handler_ModificationConflicts
    $conflictHandler = new CRM_Contract_Handler_ModificationConflicts();
    $conflictHandler->checkForConflicts($this->data['id']);
  }

  public function getOptionValueLabel($value, $option_group_id) {
    try {
      // TODO: optimise/cache
      return civicrm_api3('OptionValue', 'getvalue', [
          'return'          => "label",
          'value'           => $value,
          'option_group_id' => $option_group_id]);
    } catch (Exception $ex) {
      CRM_Core_Error::debug_log_message("Error looking up option value '{$value}' in {$option_group_id}");
    }
    return 'ERROR';
  }


  ################################################################################
  ##                           STATIC FUNCTIONS                                 ##
  ################################################################################

  /**
   * Get the class for the given activity type
   *
   * @param $activity_type int|string acitivity type ID or name
   * @return string class name
   */
  public static function getClassByActivityType($activity_type_id) {
    // check name -> class mapping first
    if (isset(self::$type2class[$activity_type_id])) {
      return self::$type2class[$activity_type_id];
    }

    // check action -> class mapping second
    if (isset(self::$action2class[$activity_type_id])) {
      return self::$action2class[$activity_type_id];
    }

    // then try ID -> class
    $type_id2class = self::getActivityTypeId2Class();
    if (isset($type_id2class[$activity_type_id])) {
      return $type_id2class[$activity_type_id];
    }

    // not found? not one of ours!
    return NULL;
  }

  /**
   * Get the list of activity type ID to class
   *
   * @return array activity_type_id => class name
   */
  public static function getActivityTypeId2Class() {
    if (self::$_type_id2class === NULL) {
      // populate on demand:
      self::$_type_id2class = [];
      $query = civicrm_api3('OptionValue', 'get', [
          'option_group_id' => 'activity_type',
          'name'            => ['IN' => array_keys(self::$type2class)],
          'return'          => 'value,name',
          'option.limit'    => 0,
          'sequential'      => 1]);
      foreach ($query['values'] as $entry) {
        if (isset(self::$type2class[$entry['name']])) {
          self::$_type_id2class[$entry['value']] = self::$type2class[$entry['name']];
        }
      }
    }
    return self::$_type_id2class;
  }

  /**
   * Get the list of valid activity type IDs representing changes
   */
  public static function getActivityTypeIds() {
    $id2class = self::getActivityTypeId2Class();
    return array_keys($id2class);
  }

  /**
   * Get a change with data
   *
   * @param $data array data
   * @return CRM_Contract_Change change entity
   * @throws Exception if the change type couldn't be detected from the activity_type_id
   */
  public static function getChangeForData($data) {
    if (empty($data['activity_type_id'])) {
      throw new Exception("No activity_type_id given.");
    }

    $change_class = self::getClassByActivityType($data['activity_type_id']);
    if (empty($change_class)) {
      throw new Exception("Activity type ID '{$data['activity_type_id']}' is not a valid contract change type.");
    }

    // make sure we're using the descriptive indices, not the custom_[id] ones
    CRM_Contract_CustomData::labelCustomFields($data);

    // finally: create a change object on the data
    return new $change_class($data);
  }

  /**
   * Get a comma separated list of all change activity custom fields
   *
   * @return string list of field names
   */
  public static function getCustomFieldList() {
    $field_names = [];
    $fields = CRM_Contract_CustomData::getCustomFieldsForGroups(['contract_cancellation','contract_updates']);
    foreach ($fields as $field) {
      $field_names[] = "custom_{$field['id']}";
    }
    return implode(',', $field_names);
  }

  /**
   * Convert the given contract data and convert it to change activity data
   *
   * @param $data array       the data
   * @param $reverse boolean  reverse the transition
   */
  public static function convertContract2ChangeData($data, $reverse = FALSE) {
    $mapping = self::$field_mapping_change_contract;
    if ($reverse) {
      $mapping = array_flip($mapping);
    }

    foreach ($mapping as $old_attribute => $new_attribute) {
      if (isset($data[$old_attribute])) {
        $data[$new_attribute] = $data[$old_attribute];
        unset($data[$old_attribute]);
      }
    }
    return $data;
  }
}
