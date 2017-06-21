<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Interface to CiviSEPA functions
 *
 * @todo resolve hard dependecy to CiviSEPA module
 */
class CRM_Contract_SepaLogic {

  /**
   * Adjust or update the given SEPA mandate according to the
   * requested change
   */
  public static function updateSepaMandate($contribution_recur_id, $current_state, &$desired_state, $activity) {
    // all relevant fields
    $mandate_relevant_fields = array('cycle_day', 'from_ba', 'membership_annual', 'membership_frequency', 'membership_recurring_contribution');

    // calculate changes
    $mandate_relevant_changes = array();
    foreach ($mandate_relevant_fields as $field_raw) {
      $field = "membership_payment.{$field_raw}";
      if (    isset($desired_state[$field])
           && $desired_state[$field] != $current_state[$field]) {
        $mandate_relevant_changes[] = $field;
      }
    }

    if (empty($mandate_relevant_changes)) {
      // nothing to do here
      return;
    }

    // get the right values
    $from_ba       = CRM_Utils_Array::value('membership_payment.from_ba', $desired_state, CRM_Utils_Array::value('membership_payment.from_ba', $current_state));
    $cycle_day     = CRM_Utils_Array::value('membership_payment.cycle_day', $desired_state, CRM_Utils_Array::value('membership_payment.cycle_day', $current_state));
    $annual_amount = CRM_Utils_Array::value('membership_payment.membership_annual', $desired_state, CRM_Utils_Array::value('membership_payment.membership_annual', $current_state));
    $frequency     = CRM_Utils_Array::value('membership_payment.membership_frequency', $desired_state, CRM_Utils_Array::value('membership_payment.membership_frequency', $current_state));
    $campaign_id   = CRM_Utils_Array::value('campaign_id', $activity, CRM_Utils_Array::value('campaign_id', $current_state));

    // calculate some stuff
    if ($cycle_day < 1 || $cycle_day > 30) {
      // invalid cycle day
      $cycle_day = self::nextCycleDay();
    }

    $frequency_interval = 12 / $frequency;
    $amount = number_format($annual_amount / $frequency, 2);

    // get bank account
    $donor_account = CRM_Contract_BankingLogic::getBankAccount($from_ba);
    if (empty($donor_account['bic']) && self::isLittleBicExtensionAccessible()) {
      $bic_search = civicrm_api3('Bic', 'findbyiban', array('iban' => $donor_account['iban']));
      if (!empty($bic_search['bic'])) {
        $donor_account['bic'] = $bic_search['bic'];
      }
    }

    // we need to create a new mandate
    $new_mandate = civicrm_api3('SepaMandate', 'createfull', array(
      'type'               => 'RCUR',
      'contact_id'         => $current_state['contact_id'],
      'amount'             => $amount,
      'currency'           => 'EUR',
      'start_date'         => date('YmdHis'), // NOW
      'creation_date'      => date('YmdHis'), // NOW
      'date'               => date('YmdHis', strtotime($activity['activity_date_time'])),
      'validation_date'    => date('YmdHis'), // NOW
      'iban'               => $donor_account['iban'],
      'bic'                => $donor_account['bic'],
      // 'source'             =>
      'campaign_id'        => $campaign_id,
      'financial_type_id'  => 2, // Membership Dues
      'frequency_unit'     => 'month',
      'cycle_day'          => $cycle_day,
      'frequency_interval' => $frequency_interval,
      ));

    // reload to get all data
    $new_mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $new_mandate['id']));

    // ...and terminate the old one
    if (!empty($current_state['membership_payment.membership_recurring_contribution'])) {
      self::terminateSepaMandate($current_state['membership_payment.membership_recurring_contribution']);
    }

    // and set the new recurring contribution
    $current_state['membership_payment.membership_recurring_contribution'] = $new_mandate['entity_id'];
  }

  /**
   * Terminate the mandate connected ot the recurring contribution
   * (if there is one)
   */
  public static function terminateSepaMandate($recurring_contribution_id) {
    if (empty($recurring_contribution_id)) {
      return;
    }

    // load mandate
    $mandate = civicrm_api3('SepaMandate', 'get', array(
      'entity_id'    => $recurring_contribution_id,
      'entity_table' => 'civicrm_contribution_recur',
      'type'         => 'RCUR'));

    // if found: stop it
    if ($mandate['count'] == 1 && $mandate['id']) {
      CRM_Sepa_BAO_SEPAMandate::terminateMandate($mandate['id'], "now", 'CHNG');
    }
  }

  /**
   * Get a list of (accepted) payment frequencies
   *
   * @return array list of payment frequencies
   */
  public static function getPaymentFrequencies() {
    // this is a hand-picked list of options
    $optionValues = civicrm_api3('OptionValue', 'get', array(
      'value'           => array('IN' => array(1, 3, 6, 12)),
      'return'          => 'label,value',
      'option_group_id' => 'payment_frequency',
    ));

    $options = array();
    foreach ($optionValues['values'] as $value) {
      $options[$value['value']] = $value['label'];
    }
    return $options;
  }


  /**
   * Get the available cycle days
   *
   * @return array list of accepted cycle days
   */
  public static function getCycleDays() {
    $creditor = CRM_Contract_SepaLogic::getCreditor();
    return CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor->id);
  }

  /**
   * Get the creditor to be used for Contracts
   *
   * @return object creditor (BAO)
   */
  public static function getCreditor() {
    // currently we're just using the default creditor
    return CRM_Sepa_Logic_Settings::defaultCreditor();
  }

  /**
   * Calculate the next possible cycle day
   *
   * @return int next valid cycle day
   */
  public static function nextCycleDay() {
    $buffer_days = 2; // TODO: more?
    $cycle_days = self::getCycleDays();

    $safety_counter = 32;
    $start_date = strtotime("+{$buffer_days} day", strtotime('now'));
    while (!in_array(date('d', $start_date), $cycle_days)) {
      $start_date = strtotime('+ 1 day', $start_date);
      $safety_counter -= 1;
      if ($safety_counter == 0) {
        throw new Exception("There's something wrong with the nextCycleDay method.");
      }
    }
    return date('d', $start_date);
  }

  /**
   * Validate the given IBAN
   *
   * @return TRUE if IBAN is valid
   */
  public static function validateIBAN($iban) {
    return NULL == CRM_Sepa_Logic_Verification::verifyIBAN($iban);
  }

  /**
   * Checks whether the "Little BIC Extension" is installed
   *
   * @return TRUE if it is
   */
  public static function isLittleBicExtensionAccessible() {
    return CRM_Sepa_Logic_Settings::isLittleBicExtensionAccessible();
  }

  /**
   * Validate the given BIC
   *
   * @return TRUE if BIC is valid
   */
  public static function validateBIC($bic) {
    return NULL == CRM_Sepa_Logic_Verification::verifyBIC($bic);
  }

}
