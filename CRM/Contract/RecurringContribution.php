<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_RecurringContribution {

  /** cached variables */
  protected $paymentInstruments = NULL;
  protected $sepaPaymentInstruments = NULL;
  static protected $cached_results = array();

  /**
   * Return a detailed list of recurring contribution
   * for the given contact
   */
  public static function getAllForContact($cid, $thatAreNotAssignedToOtherContracts = true, $contractId = null){
    $object = new CRM_Contract_RecurringContribution();
    return $object->getAll($cid, $thatAreNotAssignedToOtherContracts, $contractId);
  }

  /**
   * Return a detailed list of recurring contribution
   * for the given contact
   */
  public static function getCurrentContract($contact_id, $recurring_contribution_id) {
    // make sure we have the necessary information
    if (empty($contact_id) || empty($recurring_contribution_id)) {
      return array();
    }

    // load contact
    $contact = civicrm_api3('Contact', 'getsingle', array(
      'id'     => $contact_id,
      'return' => 'display_name'));

    // load contribution
    $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $recurring_contribution_id]);

    // load SEPA creditors
    $sepaCreditors = civicrm_api3('SepaCreditor', 'get')['values'];

    // load mandate
    $sepaMandates = civicrm_api3('SepaMandate', 'get', [
      'contact_id'   => $contact_id,
      'type'         => 'RCUR',
      'entity_table' => 'civicrm_contribution_recur',
      'entity_id'    => $recurring_contribution_id,
      ])['values'];

    $object = new CRM_Contract_RecurringContribution();
    return $object->renderRecurringContribution($contributionRecur, $contact, $sepaMandates, $sepaCreditors);
  }

  /**
   * Render all recurring contributions for that contact
   */
  public function getAll($cid, $thatAreNotAssignedToOtherContracts = true, $contractId = null){
    // see if we have that cached (it's getting called multiple times)
    $cache_key = "{$cid}-{$thatAreNotAssignedToOtherContracts}-{$contractId}";
    if (isset(self::$cached_results[$cache_key])) {
      return self::$cached_results[$cache_key];
    }

    // load contact
    $contact = civicrm_api3('Contact', 'getsingle', array(
      'id'     => $cid,
      'return' => 'display_name'));

    // load contribution
    $contributionRecurs = civicrm_api3('ContributionRecur', 'get', [
      'contact_id'             => $cid,
      'sequential'             => 0,
      'contribution_status_id' => ['IN' => $this->getValidRcurStatusIds()],
      'option.limit'           => 1000
      ])['values'];

    // load attached mandates
    if (!empty($contributionRecurs)) {
      $sepaMandates = civicrm_api3('SepaMandate', 'get', [
        'contact_id'   => $cid,
        'type'         => 'RCUR',
        'entity_table' => 'civicrm_contribution_recur',
        'entity_id'    => ['IN' => array_keys($contributionRecurs)]
        ])['values'];
    } else {
      $sepaMandates = array();
    }

    // load SEPA creditors
    $sepaCreditors = civicrm_api3('SepaCreditor', 'get')['values'];

    // render all recurring contributions
    foreach($contributionRecurs as $cr) {
      $return[$cr['id']] = $this->renderRecurringContribution($cr, $contact, $sepaMandates, $sepaCreditors);
    }

    // We don't want to return recurring contributions for selection if they are
    // already assigned to OTHER contracts
    if ($thatAreNotAssignedToOtherContracts && !empty($return)) {
      // find contracts already using any of our collected recrruing contributions:
      $rcField = CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution');
      $contract_using_rcs = civicrm_api3('Membership', 'get', array(
        $rcField => array('IN' => array_keys($return)),
        'return' => $rcField));

      // remove the ones from the $return list that are being used by other contracts
      foreach ($contract_using_rcs['values'] as $contract) {
        // but leave the current one in
        if ($contract['id'] != $contractId) {
          unset($return[$contract[$rcField]]);
        }
      }
    }

    self::$cached_results[$cache_key] = $return;
    return $return;
  }


  /**
   * Render the given recurring contribution
   */
  protected function renderRecurringContribution($cr, $contact, $sepaMandates, $sepaCreditors) {
    $result = array();

    // get payment instruments
    $paymentInstruments = $this->getPaymentInstruments();

    // render fields
    $result['fields'] = [
      'display_name' => $contact['display_name'],
      'payment_instrument' => $paymentInstruments[$cr['payment_instrument_id']],
      'frequency' => $this->writeFrequency($cr),
      'amount' => CRM_Contract_SepaLogic::formatMoney($cr['amount']),
      'annual_amount' => CRM_Contract_SepaLogic::formatMoney($this->calcAnnualAmount($cr)),
      'next_debit' => '?',
    ];

    // render text

    // override some values for SEPA mandates
    if (in_array($cr['payment_instrument_id'], $this->getSepaPaymentInstruments())) {
      // this is a SEPA DD mandate
      $mandate = $this->getSepaByRecurringContributionId($cr['id'], $sepaMandates);
      $result['fields']['payment_instrument'] = "SEPA Direct Debit";
      $result['fields']['iban'] = $mandate['iban'];
      $result['fields']['org_iban'] = $sepaCreditors[$mandate['creditor_id']]['iban'];
      $result['fields']['creditor_name'] = $sepaCreditors[$mandate['creditor_id']]['name'];
      // $result['fields']['org_iban'] = $sepa;
      // $result['fields']['org_iban'] = $cr['id'];
      $result['fields']['next_debit'] = substr($cr['next_sched_contribution_date'], 0, 10);
      $result['label'] = "SEPA, {$result['fields']['amount']} {$result['fields']['frequency']} ({$mandate['reference']})";

      $result['text_summary'] = "
        Debitor name: {$result['fields']['display_name']}<br />
        Debitor account: {$result['fields']['iban']}<br />
        Creditor name: {$result['fields']['creditor_name']}<br />
        Creditor account: {$result['fields']['org_iban']}<br />
        Payment method: {$result['fields']['payment_instrument']}<br />
        Frequency: {$result['fields']['frequency']}<br />
        Annual amount: {$result['fields']['annual_amount']}&nbsp;EUR<br />
        Installment amount: {$result['fields']['amount']}&nbsp;EUR<br />
        Next debit: {$result['fields']['next_debit']}
      ";

    } else {
      // this is a non-SEPA recurring contribution
      $result['text_summary'] = "
        Debitor name: {$result['fields']['display_name']}<br />
        Payment method: {$result['fields']['payment_instrument']}<br />
        Frequency: {$result['fields']['frequency']}<br />
        Annual amount: {$result['fields']['annual_amount']}&nbsp;EUR<br />
        Installment amount: {$result['fields']['amount']}&nbsp;EUR<br />
      ";
      $result['label'] = "{$result['fields']['payment_instrument']}, {$result['fields']['amount']} {$result['fields']['frequency']}";
    }

    return $result;
  }


  /**
   * Get the status IDs for eligible recurring contributions
   */
  protected function getValidRcurStatusIds() {
    $pending_id = CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');
    $current_id = CRM_Core_OptionGroup::getValue('contribution_status', 'In Progress', 'name');
    return [$pending_id, $current_id];
  }


  /**
   * ??
   * @author Michael
   */
  private function writeFrequency($cr){
    if($cr['frequency_interval']==1){
      $frequency = "Every {$cr['frequency_unit']}";
    }else{
      $frequency = "Every {$cr['frequency_interval']} {$cr['frequency_unit']}s";
    }

    // FIXME: use SepaLogic::getPaymentFrequencies
    $shortHands = [
      'Every 12 months' => 'annually',
      'Every year'      => 'annually',
      'Every month'     => 'monthly'
    ];
    if(array_key_exists($frequency, $shortHands)){
      return $shortHands[$frequency];
    }
    return $frequency;
  }

  /**
   * ??
   * @author Michael
   */
  private function calcAnnualAmount($cr){
    if($cr['frequency_unit']=='month'){
      $multiplier = 12;
    }elseif($cr['frequency_unit']=='year'){
      $multiplier = 1;
    }
    return $cr['amount'] * $multiplier / $cr['frequency_interval'];
  }

  /**
   * ??
   * @author Michael
   */
  public function writePaymentContractLabel($contributionRecur)
  {
      $paymentInstruments = $this->getPaymentInstruments();
      if (in_array($contributionRecur['payment_instrument_id'], $this->getSepaPaymentInstruments())) {
          $sepaMandate = civicrm_api3('SepaMandate', 'getsingle', array(
            'entity_table' => 'civicrm_contribution_recur',
            'entity_id' => $contributionRecur['id'],
          ));

          $plural = $contributionRecur['frequency_interval'] > 1 ? 's' : '';
          return "SEPA: {$sepaMandate['reference']} ({$contributionRecur['amount']} every {$contributionRecur['frequency_interval']} {$contributionRecur['frequency_unit']}{$plural})";
      } else {
          return "{$paymentInstruments[$contributionRecur['payment_instrument_id']]}: ({$contributionRecur['amount']} every {$contributionRecur['frequency_interval']} {$contributionRecur['frequency_unit']})";
      }
  }

  /**
   * get all payment instruments
   */
  protected function getPaymentInstruments() {
    if (!isset($this->paymentInstruments)) {
      // load payment instruments
      $paymentInstrumentOptions = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => "payment_instrument")
        )['values'];
      $this->paymentInstruments = array();
      foreach($paymentInstrumentOptions as $paymentInstrumentOption){
        // $this->paymentInstruments[$paymentInstrumentOption['value']] = $paymentInstrumentOption['name'];
        $this->paymentInstruments[$paymentInstrumentOption['value']] = $paymentInstrumentOption['label'];
      }
    }
    return $this->paymentInstruments;
  }

  /**
   * Get all CiviSEPA payment instruments(?)
   * @author Michael
   */
  public function getSepaPaymentInstruments() {
      if (!isset($this->sepaPaymentInstruments)) {
          $this->sepaPaymentInstruments = array();
          $result = civicrm_api3('OptionValue', 'get', array('option_group_id' => 'payment_instrument', 'name' => array('IN' => array('RCUR', 'OOFF', 'FRST'))));
          foreach ($result['values'] as $paymentInstrument) {
              $this->sepaPaymentInstruments[] = $paymentInstrument['value'];
          }
      }

      return $this->sepaPaymentInstruments;
  }

  /**
   * Get the CiviSEPA mandate id connected to the given recurring contribution,
   * from the given list.
   * @author Michael
   */
  private function getSepaByRecurringContributionId($id, $sepas){
    foreach($sepas as $sepa){
      if($sepa['entity_id'] == $id && $sepa['entity_table'] == 'civicrm_contribution_recur'){
        return $sepa;
      }
    }
  }

}
