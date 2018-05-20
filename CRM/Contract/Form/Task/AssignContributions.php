<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;
require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Contract_Form_Task_AssignContributions extends CRM_Contribute_Form_Task {

  /**
   * Compile task form
   */
  function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Assign %1 Contributions to:', array(1 => count($this->_contributionIds))));

    // get all contracts
    $contracts = $this->getEligibleContracts();

    // contract selector
    $this->addElement('select',
        'contract_id',
        E::ts('Contract'),
        $this->getContractList($contracts),
        array('class' => 'crm-select2 huge'));

    // option: adjust financial type?
    $this->addCheckbox(
        'adjust_financial_type',
        E::ts('Adjust Financial Type'),
        ['' => true]);

    // option: also assign to recurring contribution [no, yes, yes and adjust start data, only if within start/end date
    $this->addElement('select',
        'assign_mode',
        E::ts('Assign to Recurring'),
        array(
            'no'     => E::ts("no"),
            'yes'    => E::ts("yes"),
            'adjust' => E::ts("adjust start and end date"),
            'in'     => E::ts("only if within start/end date"),
        ),
        array('class' => 'crm-select2'));

    // option: change payment instrument (except SEPA) to ...
    $this->addElement('select',
        'adjust_pi',
        E::ts('Adjust Payment Instrument (only non-SEPA)'),
        $this->getEligiblePaymentInstruments(),
        array('class' => 'crm-select2'));

    CRM_Core_Form::addDefaultButtons(E::ts("Assign"));
  }

  /**
   * Execute the user's choice
   */
  function postProcess() {
    $values = $this->exportValues();

    // TODO: implement
  }



  /**
   * Get a list of all eligible contracts
   */
  protected function getEligibleContracts() {
    $contracts = array();

    // first: get the IDs of eligible contracts
    $contract_ids = array();
    $contribution_id_list = implode(',', $this->_contributionIds);
    $search = CRM_Core_DAO::executeQuery("
    SELECT m.id AS contract_id  
    FROM civicrm_contribution c
    LEFT JOIN civicrm_membership m ON m.contact_id = c.contact_id
    WHERE c.id IN ({$contribution_id_list})
    GROUP BY m.id
    ORDER BY m.status_id DESC, m.start_date DESC;");
    while ($search->fetch()) {
      $contract_ids[] = $search->contract_id;
    }

    if (!empty($contract_ids)) {
      $contract_query = civicrm_api3('Membership', 'get', array(
        'id'         => array('IN' => $this->_contributionIds),
        'return'     => 'start_date,membership_type_id,status_id',
        'sequential' => 1
      ));
      $contracts = $contract_query['values'];
    }

    return $contracts;
  }

  /**
   * Get a list of all eligible contracts
   */
  protected function getContractList($contracts) {
    $contract_list = array();
    error_log(json_encode($contracts));

    // load membership types
    $membership_types = civicrm_api3('MembershipType', 'get', array(
        'option.limit' => 0,
        'sequential'   => 0,
        'return'       => 'name'));
    $membership_status = civicrm_api3('MembershipStatus', 'get', array(
        'option.limit' => 0,
        'sequential'   => 0,
        'return'       => 'label'));

    foreach ($contracts as $contract) {
      $contract_list[$contract['id']] = E::ts("[%4] '%1' since %2 (%3)", array(
          1 => $membership_types['values'][$contract['membership_type_id']]['name'],
          2 => substr($contract['start_date'], 0, 4),
          3 => $membership_status['values'][$contract['status_id']]['label'],
          4 => $contract['id']
          ));
    }

    return $contract_list;
  }

  /**
   * Get a list of all eligible contracts
   */
  protected function getEligiblePaymentInstruments() {
    $eligible_pis = array();
    $sepa_pi_names = array('OOFF', 'RCUR', 'FRST');

    $all_pis = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => 'payment_instrument',
        'is_active'       => 1,
        'return'          => 'value,name,label',
        'option.limit'    => 0));
    foreach ($all_pis['values'] as $pi) {
      if (!in_array($pi['name'], $sepa_pi_names)) {
        $eligible_pis[$pi['value']] = $pi['label'];
      }
    }

    return array('' => E::ts('no')) + $eligible_pis;
  }

}
