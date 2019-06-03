<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * New membership signup
 */
class CRM_Contract_Change_Sign extends CRM_Contract_Change {

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  public function getRequiredFields() {
    // TODO:
    return [

    ];
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
    // TODO:
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
