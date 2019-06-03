<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * New membership sign-up
 */
class CRM_Contract_Change_Sign extends CRM_Contract_Change {

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  public function getRequiredFields() {
    return []; // none required because change is documentary
  }

  /**
   * Render the default subject
   *
   * @param $contract_after       array  data of the contract after
   * @param $contract_before      array  data of the contract before
   * @return                      string the subject line
   */
  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    $attributes = [];
    $contract = $contract_after;

    // collect values
    if (isset($contract['membership_type_id'])) {
      try {
        $attributes[] = 'type '.civicrm_api3('MembershipType', 'getvalue', [ 'return' => "name", 'id' =>  $contract['membership_type_id']]);
      } catch(Exception $ex) {
        CRM_Core_Error::debug_log_message("Couldn't load membership type {$contract['membership_type_id']}");
      }
    }
    if (isset($contract['membership_payment.membership_annual'])) {
      $attributes[] = 'amt. '. $contract['membership_payment.membership_annual'];
    }
    if (isset($contract['membership_payment.membership_frequency'])) {
      // TODO: the old engine didn't do this -> use?
      // $attributes[] = 'freq. ' . $this->getOptionValueLabel($contract['membership_payment.membership_frequency'], 'payment_frequency');
      $attributes[] = 'freq. ' . $contract['membership_payment.membership_frequency'];
    }
    if (isset($contract['membership_payment.to_ba'])) {
      $attributes[] = 'gp iban '.CRM_Contract_BankingLogic::getIBANforBankAccount($contract['membership_payment.to_ba']);
    }
    if (isset($contract['membership_payment.from_ba'])) {
      $attributes[] = 'member iban '.CRM_Contract_BankingLogic::getIBANforBankAccount($contract['membership_payment.from_ba']);
    }
    if (isset($contract['membership_payment.cycle_day'])) {
      $attributes[] = 'cycle day '. $contract['membership_payment.cycle_day'];
    }
    if (isset($contract['membership_payment.payment_instrument'])) {
      $attributes[] = 'payment method ' . $this->getOptionValueLabel($contract['membership_payment.payment_instrument'], 'payment_instrument');
      //$attributes[] = 'payment method ' . civicrm_api3('OptionValue', 'getvalue', ['return' => "label", 'value' =>  $contract['membership_payment.payment_instrument'], 'option_group_id' => "payment_instrument" ]);
    }
    if (isset($contract['membership_payment.defer_payment_start'])) {
      $attributes[] = 'defer '. $contract['membership_payment.defer_payment_start'];
    }

    return "id{$contract['id']}: ".implode(' AND ', $attributes);
  }

  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute() {
    throw new Exception("New membership sign-ups are documentary, they cannot be scheduled into the future, and therefore not executed.");
  }

  /**
   * Derive/populate additional data
   */
  public function populateData() {
    // TODO: populate data for sign-ups
    if (empty($this->data['source_record_id'])) {
      if (!empty($this->data['membership']['id'])) {
        $this->data['source_record_id'] = $this->data['membership']['id'];
      }
    }

    // populate
    $contract = $this->getContract(TRUE);
    foreach (CRM_Contract_Change::$field_mapping_change_contract as $contract_attribute => $change_attribute) {
      $this->data[$change_attribute] = CRM_Utils_Array::value($contract_attribute, $contract, '');
    }
    $this->data['contract_updates.ch_annual_diff'] = CRM_Utils_Array::value('membership_payment.membership_annual', $contract, '');

    // finally, set subject
    if (empty($this->data['subject'])) {
      $this->data['subject'] = $this->getSubject($contract);
    }
  }


  /**
   * Check whether this change activity should actually be created
   *
   * CANCEL activities should not be created, if there is another one already there
   *
   * @throws Exception if the creation should be disallowed
   */
  public function shouldBeAccepted() {
    parent::shouldBeAccepted();

    // TODO: check if the parameters are good
  }

}
