<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Interface to CiviBanking functions
 *
 * @todo resolve hard dependecy to CiviBanking module
 */
class CRM_Contract_BankingLogic {

  /** cached value for self::getCreditorBankAccount() */
  protected static $_creditorBankAccount = NULL;


  /**
   * Get the ID of the BankingAccount entity representating the
   * submitted contact, IBAN, and BIC.
   * The account will be created if it doesn't exist yet
   *
   * @todo cache results?
   * @return int account ID
   */
  public static function getOrCreateBankAccount($contact_id, $iban, $bic) {
    try {
      // look up reference type option value ID(!)
      $reference_type_value = civicrm_api3('OptionValue', 'getsingle', array(
        'value'           => 'IBAN',
        'option_group_id' => 'civicrm_banking.reference_types',
        'is_active'       => 1));

      // find existing references
      $existing_references = civicrm_api3('BankingAccountReference', 'get', array(
        'reference'         => $iban,
        'reference_type_id' => $reference_type_value['id'],
        'option.limit'      => 0));

      // get the accounts for this
      $bank_account_ids = array();
      foreach ($existing_references['values'] as $account_reference) {
        $bank_account_ids[] = $account_reference['ba_id'];
      }
      if (!empty($bank_account_ids)) {
        $contact_bank_accounts = civicrm_api3('BankingAccount', 'get', array(
          'id'           => array('IN' => $bank_account_ids),
          'contact_id'   => $contact_id,
          'option.limit' => 1));
        if ($contact_bank_accounts['count']) {
          // bank account already exists with the contact
          $account = reset($contact_bank_accounts['values']);
          return $account['id'];
        }
      }

      // if we get here, that means that there is no such bank account
      //  => create one
      $data = array('BIC' => $bic, 'country' => substr($iban, 0, 2));
      $bank_account = civicrm_api3('BankingAccount', 'create', array(
        'contact_id'  => $contact_id,
        'description' => "Bulk Importer",
        'data_parsed' => json_encode($data)));

      $bank_account_reference = civicrm_api3('BankingAccountReference', 'create', array(
        'reference'         => $iban,
        'reference_type_id' => $reference_type_value['id'],
        'ba_id'             => $bank_account['id']));
      return $bank_account['id'];
    } catch (Exception $e) {
      error_log("Couldn't add bank account {$reference} [{$reference_type}]");
    }
  }

  /**
   * Get the (target) bank account of the creditor
   */
  public static function getCreditorBankAccount() {
    if (self::$_creditorBankAccount === NULL) {
      $creditor = CRM_Contract_SepaLogic::getCreditor();
      self::$_creditorBankAccount = self::getOrCreateBankAccount($creditor->creditor_id, $creditor->iban, $creditor->bic);
    }
    return self::$_creditorBankAccount;
  }
}
