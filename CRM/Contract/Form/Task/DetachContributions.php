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
class CRM_Contract_Form_Task_DetachContributions extends CRM_Contribute_Form_Task {

  function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Detach Contributions from Membership'));

    // compile an info text
    $infotext = E::ts("%1 of the %2 contributions are currently attached to a membership.", array(
      1 => $this->getAssignedCount(),
      2 => count($this->_contributionIds)));
    $this->assign('infotext', $infotext);


    // additional options
    $this->addCheckbox(
        'detach_recur',
        E::ts('Detach %1 connected recurring contributions', [1 => $this->getRecurringCount()]),
        ['' => true]);

    $this->addElement('select',
        'change_financial_type',
        E::ts('Update Financial Type'),
        $this->getFinancialTypesList(),
        array('class' => 'crm-select2'));

    $this->addCheckbox(
        'change_recur_financial_type',
        E::ts('Update recurring contributions\' financial type, too.'),
        ['' => true]);

    // call the (overwritten) Form's method, so the continue button is on the right...
    CRM_Core_Form::addDefaultButtons(ts('Detach'));
  }

  function postProcess() {
    // get the count
    $count = $this->getAssignedCount();

    // simply do this by SQL
    $id_list = implode(',', $this->_contributionIds);
    if (!empty($id_list)) {
      CRM_Core_DAO::executeQuery("DELETE FROM civicrm_membership_payment WHERE contribution_id IN ({$id_list})");
    }

    CRM_Core_Session::setStatus(E::ts("%1 contribution(s) have been detached from their memberships.", array(1 => $count)), ts('Success'), 'info');

    // detach the recurring contributions
    $values = $this->exportValues();
    $recur_ids = $this->getRecurringIDs();
    if (!empty($values['detach_recur'])) {
      foreach ($recur_ids as $recur_id) {
        // TODO: remove from contract
      }
    }

    // update financial types
    if (!empty($values['change_financial_type'])) {
      // update all contributions
      $ccounter = 0;
      foreach ($this->_contributionIds as $contribution_id) {
        try {
          civicrm_api3('Contribution', 'create', [
              'id'                => $contribution_id,
              'financial_type_id' => $values['change_financial_type']]);
          $ccounter += 1;
        } catch (API_Exception $ex) {
          CRM_Core_Session::setStatus(E::ts("Financial type for contribution [%1] couldn't be changed: %2", [1 => $contribution_id, 2 => $ex->getMessage()]), ts('Error'), 'error');
        }
      }
      CRM_Core_Session::setStatus(E::ts("Financial type for %1 contribution(s) has been updated.", [1 => $ccounter]), ts('Success'), 'info');

      // update all recurring contributions
      if (!empty($values['change_recur_financial_type'])) {
        $rcounter = 0;
        foreach ($recur_ids as $recur_id) {
          try {
            civicrm_api3('ContributionRecur', 'create', [
                'id'                => $recur_id,
                'financial_type_id' => $values['change_financial_type']]);
            $rcounter += 1;
          } catch (API_Exception $ex) {
            CRM_Core_Session::setStatus(E::ts("Financial type for recurring contribution [%1] couldn't be changed: %2", [1 => $recur_id, 2 => $ex->getMessage()]), ts('Error'), 'error');
          }
        }
        CRM_Core_Session::setStatus(E::ts("Financial type for %1 recurring contribution(s) has been updated.", [1 => $rcounter]), ts('Success'), 'info');
      }
    }
  }



  /**
   * get the number of assigned contributions
   */
  protected function getAssignedCount() {
    $id_list = implode(',', $this->_contributionIds);
    if (empty($id_list)) {
      return 0;
    } else {
      return CRM_Core_DAO::singleValueQuery("SELECT COUNT(id) FROM civicrm_membership_payment WHERE contribution_id IN ({$id_list})");
    }
  }

  /**
   * get the number of distinct recurring contributions connected to the contributions
   */
  protected function getRecurringCount() {
    $id_list = implode(',', $this->_contributionIds);
    if (empty($id_list)) {
      return 0;
    } else {
      return CRM_Core_DAO::singleValueQuery("SELECT COUNT(DISTINCT(contribution_recur_id)) FROM civicrm_contribution WHERE id IN ({$id_list})");
    }
  }

  /**
   * get the number of distinct recurring contributions connected to the contributions
   */
  protected function getRecurringIDs() {
    $id_list = implode(',', $this->_contributionIds);
    $rcur_ids = [];
    if (!empty($id_list)) {
      $query = CRM_Core_DAO::executeQuery("SELECT DISTINCT(contribution_recur_id) AS rid FROM civicrm_contribution WHERE id IN ({$id_list})");
      while ($query->fetch()) {
        $rcur_ids[] = $query->rid;
      }
    }
    return $rcur_ids;
  }

  /**
   * Get a list of financial types
   */
  protected function getFinancialTypesList() {
    $list =  ['' => E::ts("don't change")];
    $financial_types = civicrm_api3('FinancialType', 'get', [
        'is_active'    => 1,
        'option.limit' => 0,
        'sequential'   => 1,
        'return'       => 'id,name']);
    foreach ($financial_types['values'] as $financial_type) {
      $list[$financial_type['id']] = $financial_type['name'];
    }
    return $list;
  }
}
