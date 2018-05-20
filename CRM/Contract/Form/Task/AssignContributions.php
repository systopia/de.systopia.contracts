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

  protected $_eligibleContracts = NULL;

  /**
   * Compile task form
   */
  function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Assign %1 Contributions to:', array(1 => count($this->_contributionIds))));

    // get all contracts
    $contracts = $this->getEligibleContracts();
    $this->assign('contracts', json_encode($contracts));

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

    // option: re-assign
    $this->addCheckbox(
        'reassign',
        E::ts('Re-Assign'),
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
        E::ts('Adjust Payment Instrument'),
        $this->getEligiblePaymentInstruments(),
        array('class' => 'crm-select2'));

    CRM_Core_Form::addDefaultButtons(E::ts("Assign"));
  }

  /**
   * Execute the user's choice
   */
  function postProcess() {
    $values = $this->exportValues();

    $contracts = $this->getEligibleContracts();
    $contract = $contracts[$values['contract_id']];
    if (empty($contract)) {
      throw new Exception("No contract selected!");
    }


    if (empty($contract['sepa_mandate_id'])) {
      // TODO: process non-sepa options

    }

    // TODO: implement
  }



  /**
   * Get a list of all eligible contracts
   */
  protected function getEligibleContracts()
  {
    if ($this->_eligibleContracts === NULL) {
      $this->_eligibleContracts = array();
      $contribution_id_list = implode(',', $this->_contributionIds);
      $search = CRM_Core_DAO::executeQuery("
      SELECT
       m.id                                AS contract_id,
       m.start_date                        AS start_date,
       m.status_id                         AS status_id,
       m.membership_type_id                AS membership_type_id,
       f.name                              AS financial_type,
       p.membership_recurring_contribution AS contribution_recur_id,
       s.id                                AS sepa_mandate_id
      FROM civicrm_contribution c
      LEFT JOIN civicrm_membership m               ON m.contact_id = c.contact_id
      LEFT JOIN civicrm_value_membership_payment p ON p.entity_id = m.id
      LEFT JOIN civicrm_membership_type t          ON t.id = m.membership_type_id
      LEFT JOIN civicrm_financial_type f           ON f.id = t.financial_type_id
      LEFT JOIN civicrm_sdd_mandate s              ON s.entity_id = p.membership_recurring_contribution 
                                                    AND s.entity_table = 'civicrm_contribution_recur'
      WHERE c.id IN ({$contribution_id_list})
        AND p.membership_recurring_contribution IS NOT NULL
      GROUP BY m.id
      ORDER BY m.status_id ASC, m.start_date DESC;");
      while ($search->fetch()) {
        $this->_eligibleContracts[$search->contract_id] = array(
            'id' => $search->contract_id,
            'start_date' => $search->start_date,
            'status_id' => $search->status_id,
            'membership_type_id' => $search->membership_type_id,
            'contribution_recur_id' => $search->contribution_recur_id,
            'sepa_mandate_id' => $search->sepa_mandate_id,
            'financial_type' => $search->financial_type,
        );
      }
    }

    return $this->_eligibleContracts;
  }

  /**
   * Get a list of all eligible contracts
   */
  protected function getContractList($contracts) {
    $contract_list = array();

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
