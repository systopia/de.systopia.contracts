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
    // TODO:
  }

  /**
   * Get the (target) bank account of the creditor
   */
  public static function getCreditorBankAccount() {
    if (self::$_creditorBankAccount === NULL) {
      $creditor = CRM_Contract_SepaLogic::getCreditor();

    }
    return self::$_creditorBankAccount;
  }
}
