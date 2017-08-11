<?php

use CRM_Contract_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Contract_Form_CreateRecur extends CRM_Core_Form {

  private $_currency = 'EUR';

  public function buildQuickForm() {
    $rcontribution_id = 0;
    $contact_id       = 0;

    if (!empty($_REQUEST['rcid'])) {
      // EDIT existing contribution recur
      $rcontribution_id = (int) $_REQUEST['rcid'];
      $rcontribution = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $rcontribution_id));
      $contact_id = $rcontribution['contact_id'];
    } elseif (!empty($this->_submitValues['rcontribution_id'])) {
      $rcontribution_id = (int) $this->_submitValues['rcontribution_id'];
      $rcontribution = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $rcontribution_id));
      $contact_id = $rcontribution['contact_id'];
    } elseif (!empty($_REQUEST['cid'])) {
      $contact_id = (int) $_REQUEST['cid'];
      $rcontribution = array('contact_id' => $contact_id);
      CRM_Utils_System::setTitle('Create Recurring Contribution');
    } elseif (!empty($this->_submitValues['contact_id'])) {
      $contact_id = (int) $this->_submitValues['contact_id'];
      $rcontribution = array('contact_id' => $contact_id);
      CRM_Utils_System::setTitle('Create Recurring Contribution');
    } else {
      // no rcid or cid: ERROR
      CRM_Core_Session::setStatus('Error. You need provide cid or rcid.', 'Error', 'error');
      $dashboard_url = CRM_Utils_System::url('civicrm/dashboard', 'reset=1');
      CRM_Utils_System::redirect($dashboard_url);
      return;
    }

    // LOAD contact
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));
    $this->assign('contact', $contact);

    // Currency
    $this->assign('currency', $this->_currency);

    $frequencies = array(
      '1-month'   => ts('monthly'),
      '3-month'   => ts('quartely'),
      '6-month'   => ts('semi-anually'),
      '1-year'    => ts('anually'),
    );

    // FORM ELEMENTS
    $this->add(
      'text',
      'amount',
      ts('Amount'),
      array('value' => $this->getCurrentValue('amount', $rcontribution), 'size'=>4),
      true
    );
    $this->addRule('amount', "Please enter a valid amount.", 'money');

    $frequency = $this->add(
      'select',
      'frequency',
      ts('Frequency'),
      $frequencies,
      true,
      array('class' => 'crm-select2')
    );
    $selected_frequency = $this->getCurrentValue('frequency', $rcontribution);
    if ($selected_frequency) {
      $frequency->setSelected($selected_frequency);
    } else {
      $frequency_interval = $this->getCurrentValue('frequency_interval', $rcontribution);
      $frequency_unit     = $this->getCurrentValue('frequency_unit', $rcontribution);
      if ($frequency_interval && $frequency_unit) {
        $frequency->setSelected($frequency_interval . '-' . $frequency_unit);
      }
    }

    $this->addDate(
      'start_date',
      'Begins',
      true,
      array('formatType' => 'searchDate', 'value' => $this->getCurrentDate('start_date', $rcontribution))
    );

    $this->addDate(
      'end_date',
      'Ends',
      false,
      array('formatType' => 'searchDate', 'value' => $this->getCurrentDate('end_date', $rcontribution))
    );

    // DATA
    $this->add(
      'text',
      'invoice_id',
      'Invoice ID',
      array('invoice_id' => $this->getCurrentValue('invoice_id', $rcontribution), 'size'=>30),
      false
    );

    $this->add(
      'text',
      'trxn_id',
      'Transaction ID',
      array('trxn_id' => $this->getCurrentValue('trxn_id', $rcontribution), 'size'=>30),
      false
    );

    // special fields
    $this->add(
      'text',
      'contact_id',
      'Contact',
      array('value' => $contact_id),
      false
    );

    $this->add('text', 'rcontribution_id', '', array('value' => $rcontribution_id, 'hidden'=>1), true);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();

    $financialTypeId = CRM_Utils_Array::key('Member Dues', CRM_Contribute_PseudoConstant::financialType());
    // compile contribution object with required values
    $rcontribution = array(
      'contact_id'             => $values['contact_id'],
      'amount'                 => $values['amount'],
      'currency'               => $this->_currency,
      'contribution_status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending'),
      'financial_type_id'      => $financialTypeId,
      'payment_instrument_id'  => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'EFT'),
    );

    // set ID (causes update instead of create)
    if (!empty($values['rcontribution_id'])) {
      $rcontribution['id'] = (int) $values['rcontribution_id'];
    }

    // add cycle period
    $period = preg_split("/-/", $values['frequency']);
    $rcontribution['frequency_interval'] = $period[0];
    $rcontribution['frequency_unit']     = $period[1];

    // add dates
    $rcontribution['start_date']         = date('Y-m-d', strtotime($values['start_date']));
    if (!empty($rcontribution['end_date'])) {
      $rcontribution['end_date']         = date('Y-m-d', strtotime($values['end_date']));
    }

    // add non-required values
    $rcontribution['trxn_id']            = $values['trxn_id'];
    $rcontribution['invoice_id']         = $values['invoice_id'];

    $result = civicrm_api3('ContributionRecur', 'create', $rcontribution);
    if (empty($rcontribution['id'])) {
      CRM_Core_Session::setStatus(ts('Recurring contribution [%1] created.', array(1 => $result['id'])), ts("Success"), "info");
    } else {
      CRM_Core_Session::setStatus(ts('Recurring contribution [%1] updated.', array(1 => $result['id'])), ts("Success"), "info");
    }

    parent::postProcess();
  }

  /**
   * get the current value of a key either from the values
   * that are about to be submitted (in case of a validation error)
   */
  public function getCurrentValue($key, $rcontribution) {
    if (!empty($this->_submitValues)) {
      return CRM_Utils_array::value($key, $this->_submitValues);
    } elseif (CRM_Utils_Array::value($key, $rcontribution)) {
      return CRM_Utils_Array::value($key, $rcontribution);
    }
  }

  /**
   * same as getCurrentValue but adds date formatting
   */
  public function getCurrentDate($key, $rcontribution) {
    $date = $this->getCurrentValue($key, $rcontribution);
    if (empty($date)) {
      return NULL;
    } else {
      return date('Y-m-d', strtotime($date));
    }
  }

}
