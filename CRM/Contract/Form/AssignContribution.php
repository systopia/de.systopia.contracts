<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Contract_Form_AssignContribution extends CRM_Core_Form {

  public function buildQuickForm() {
    $contribution_id = CRM_Utils_Request::retrieve('cid', 'Integer');
    if (!is_numeric($contribution_id)) {
      throw new Exception("Invalid contribution id given.", 1);
    }

    // add some hidden attributes
    $this->add('hidden', 'cid', $contribution_id);

    // get the available memberships
    $membership_options = $this->getMemberships($contribution_id);
    if (empty($membership_options)) {
      CRM_Core_Session::setStatus("The contact has no memberships!", "Error", "error");
    }

    // see if it's currently assigned
    $this->assign('assigned_to', $this->getCurrentlyAssignedMembership($contribution_id));

    // add form elements
    $this->add(
      'select',
      'membership_id',
      E::ts('Select Membership'),
      $membership_options,
      TRUE,
      array('class' => 'crm-select2 huge')
    );

    // add adjust option
    $this->add(
        'checkbox',
        'adjust_ft',
        E::ts("Set financial type to 'Membership Dues'."));
    $this->setDefaults(['adjust_ft' => 1]);

    // offer 'set to standing order' option, if not SEPA
    if (class_exists('CRM_Sepa_Logic_PaymentInstruments')) {
      $contribution = civicrm_api3('Contribution', 'getsingle', array(
          'id' => $contribution_id,
          'return' => 'payment_instrument_id'));
      if (!CRM_Sepa_Logic_PaymentInstruments::isSDD($contribution)) {
        $this->add(
            'checkbox',
            'adjust_pi',
            E::ts("Set payment instrument to 'Standing Order'."));
        $this->setDefaults(['adjust_pi' => 1]);
      }
    }
    $paymnet_instrument_name = CRM_Core_DAO::singleValueQuery("SELECT FROM civicrm_contribution ")

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Assign'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    parent::buildQuickForm();
  }



  public function postProcess() {
    $values = $this->exportValues();

    if (!empty($values['cid'])) {
      if (!empty($values['membership_id'])) {
        // ok: first remove any old link
        $old_link = $this->getCurrentlyAssignedMembership($values['cid']);
        if ($old_link) {
          // doesn't exist: civicrm_api3('MembershipPayment', 'delete', array('id' => $old_link));
          CRM_Member_BAO_MembershipPayment::del($old_link);
        }

        // adjust contribution
        if ($values['adjust_ft']) {
          $membership_type_id = civicrm_api3('Membership', 'getvalue', array(
              'id'     => $values['membership_id'],
              'return' => 'membership_type_id'));

          $financial_type_id = civicrm_api3('MembershipType', 'getvalue', array(
              'id'     => $membership_type_id,
              'return' => 'financial_type_id'));

          civicrm_api3('Contribution', 'create', array(
              'id'                => $values['cid'],
              'financial_type_id' => $financial_type_id));

          // TODO: message? check first?
        }

        // the assign the membership
        civicrm_api3('MembershipPayment', 'create', array(
          'contribution_id' => $values['cid'],
          'membership_id'   => $values['membership_id'],
        ));
        CRM_Core_Session::setStatus("Contribution assigned to membership.", "Success", "info");

      } else {
        CRM_Core_Session::setStatus("No membership selected!", "Error", "error");
      }
    } else {
      CRM_Core_Session::setStatus("No contribution ID given!", "Error", "error");
    }


    // CRM_Core_Session::setStatus(E::ts('You picked color "%1"', array(
    //   1 => $options[$values['favorite_color']],
    // )));
    parent::postProcess();
  }

  /**
   * Get the id of the current assignment
   */
  protected function getCurrentlyAssignedMembership($contribution_id) {
    $membership_payment = civicrm_api3('MembershipPayment', 'get', array('contribution_id' => $contribution_id));
    if (!empty($membership_payment['id'])) {
      return $membership_payment['id'];
    } else {
      return NULL;
    }
  }

  /**
   * Get all the contact's memberships
   */
  protected function getMemberships($contribution_id) {
    $membership_options = array();

    $contribution = civicrm_api3('Contribution', 'getsingle', array(
      'id'     => $contribution_id,
      'return' => 'contact_id'));

    $memberships = civicrm_api3('Membership', 'get', array(
      'contact_id'   => $contribution['contact_id'],
      'sequential'   => 1,
      'is_test'      => 0,
      'option.sort'  => 'end_date DESC, start_date DESC, id DESC',
      'option.limit' => 0,
    ));

    $status_list = civicrm_api3('MembershipStatus', 'get', array(
      'sequential'   => 0,
      'option.limit' => 0,
    ))['values'];

    foreach ($memberships['values'] as $membership) {
      $status = $status_list[$membership['status_id']];
      $membership_options[$membership['id']] = "{$membership['membership_name']} [{$membership['id']}] ({$status['label']})";

      // add dates
      if (!empty($membership['start_date'])) {
        $membership_options[$membership['id']] .= " from {$membership['start_date']}";
      }

      if (!empty($membership['end_date'])) {
        $membership_options[$membership['id']] .= " until {$membership['end_date']}";
      }

      if (empty($status['is_active'])) {
        $membership_options[$membership['id']] = '(inactive) ' . $membership_options[$membership['id']];
      }
    }

    return $membership_options;
  }
}
