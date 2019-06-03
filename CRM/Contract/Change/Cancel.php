<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

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
   * Derive/populate additional data
   */
  public function populateData() {
    if (empty($this->data['contract_cancellation.contact_history_cancel_reason'])) {
      $this->data['contract_cancellation.contact_history_cancel_reason'] = CRM_Utils_Array::value('membership_cancellation.membership_cancel_reason', $this->data, '');
    } else {
      $this->data['membership_cancellation.membership_cancel_reason'] = CRM_Utils_Array::value('contract_cancellation.contact_history_cancel_reason', $this->data, '');
    }

    parent::populateData();
  }


  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute() {
    $contract = $this->getContract();

    // cancel the contract by setting the end date
    $contract_update = [
        'end_date'                => date('YmdHis'),
        'membership_cancellation' => $this->data['membership_cancellation.membership_cancel_reason']
    ];

    // perform the update
    $this->updateContract($contract_update);

    // also: cancel the mandate/recurring contribution
    CRM_Contract_SepaLogic::terminateSepaMandate(
        $contract['membership_payment.membership_recurring_contribution'],
        $this->data['membership_cancellation.membership_cancel_reason']);

    // update change activity
    $contract_after = $this->getContract();
    $this->setParameter('subject', $this->getSubject($contract_after, $contract));
    $this->setStatus('Completed');
    $this->save();
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

    // check for OTHER CANCELLATION REQUEST for the same day
    //  @see https://redmine.greenpeace.at/issues/1190
    $requested_day = date('Y-m-d', strtotime($this->data['activity_date_time']));
    $scheduled_activities = civicrm_api3('Activity', 'get', array(
        'source_record_id' => $this->getContractID(),
        'activity_type_id' => $this->getActvityTypeID(),
        'status_id'        => 'Scheduled',
        'option.limit'     => 0,
        'sequential'       => 1,
        'return'           => 'id,activity_date_time'));
    foreach ($scheduled_activities['values'] as $scheduled_activity) {
      $scheduled_for_day = date('Y-m-d', strtotime($scheduled_activity['activity_date_time']));
      if ($scheduled_for_day == $requested_day) {
        // there's already a scheduled 'cancel' activity for the same day
        throw new Exception("Scheduling an (additional) cancellation request in not desired in this context.");
      }
    }

    // IF CONTRACT ALREADY CANCELLED, create another cancel activity only
    //  when there are other scheduled (or 'needs review') changes
    //  @see https://redmine.greenpeace.at/issues/1190
    $contract = $this->getContract();

    $contract_cancelled_status = civicrm_api3('MembershipStatus', 'get', array(
        'name'   => 'Cancelled',
        'return' => 'id'));
    if ($contract['status_id'] == $contract_cancelled_status['id']) {
      // contract is cancelled
      $pending_activity_count = civicrm_api3('Activity', 'getcount', array(
          'source_record_id' => $params['id'],
          'activity_type_id' => ['IN' => CRM_Contract_ModificationActivity::getModificationActivityTypeIds()],
          'status_id'        => ['IN' => ['Scheduled', 'Needs Review']],
      ));
      if ($pending_activity_count == 0) {
        throw new Exception("Scheduling an (additional) cancellation request in not desired in this context.");
      }
    }
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
    $subject = "id{$contract_id}:";
    if (!empty($this->data['contract_cancellation.contact_history_cancel_reason'])) {
      $subject .= ' cancel reason ' . $this->getOptionValue($this->data['contract_cancellation.contact_history_cancel_reason'], 'contract_cancel_reason');

      // FIXME: I would prefer this:
      //$subject .= ' cancel reason ' . $this->data['contract_cancellation.contact_history_cancel_reason'];
    }
    return $subject;
  }
}
