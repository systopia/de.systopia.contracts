<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Cancel membership change
 */
class CRM_Contract_Change_Cancel extends CRM_Contract_Change {

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  public function getRequiredFields() {
    return [
        'membership_cancellation.membership_cancel_reason'
    ];
  }

  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute() {
    $contract = $this->getContract();

    // cancel the contract by setting the end date
    $this->updateContract([
        'end_date'                => date('YmdHis'),
        'membership_cancellation' => $this->data['membership_cancellation.membership_cancel_reason']
    ]);

    // also: cancel the mandate/recurring contribution
    CRM_Contract_SepaLogic::terminateSepaMandate(
        $contract['membership_payment.membership_recurring_contribution'],
        $this->data['membership_cancellation.membership_cancel_reason']);

    $new_contract = $this->getContract();
    // TODO: update the change itself (subject, etc.)
  }
}
