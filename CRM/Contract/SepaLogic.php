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
  public static function updateSepaMandate($contribution_recur_id, $current_state, $desired_state) {

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
