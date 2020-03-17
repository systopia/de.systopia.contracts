<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * "Resume Membership" change
 */
class CRM_Contract_Change_Resume extends CRM_Contract_Change {

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  public function getRequiredFields() {
    return [];
  }

  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute() {
    $contract = $this->getContract(TRUE);

    // pause the mandate
    $payment_contract_id = CRM_Utils_Array::value('membership_payment.membership_recurring_contribution', $contract);
    if ($payment_contract_id) {
      CRM_Contract_SepaLogic::resumeSepaMandate($payment_contract_id);
      $this->updateContract(['status_id' => 'Current']);
    }

    // update change activity
    $contract_after = $this->getContract(TRUE);
    $this->setParameter('subject', $this->getSubject($contract_after, $contract));
    $this->setStatus('Completed');
    $this->save();
  }

  /**
   * Render the default subject
   *
   * @param $contract_after       array  data of the contract after
   * @param $contract_before      array  data of the contract before
   * @return                      string the subject line
   */
  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    $contract_id = $this->getContractID();
    if ($this->isNew()) {
      // FIXME: replicating weird behaviour by old engine
      return "id{$contract_id}:";
    } else {

      $subject = "id{$contract_id}:";
      if (!empty($this->data['contract_cancellation.contact_history_cancel_reason'])) {
        // FIXME: replicating weird behaviour by old engine
        $subject .= ' cancel reason ' . $this->resolveValue($this->data['contract_cancellation.contact_history_cancel_reason'], 'contract_cancellation.contact_history_cancel_reason');
        //$subject .= ' cancel reason ' . $this->labelValue($this->data['contract_cancellation.contact_history_cancel_reason'], 'contract_cancellation.contact_history_cancel_reason');
      }
      return $subject;
    }
  }
}
