<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Form_Mandate extends CRM_Core_Form{

  function buildQuickForm(){

    $this->cid = CRM_Utils_Request::retrieve('cid', 'Integer');
    if($this->cid){
      $this->set('cid', $this->cid);
    }

    $this->add('text', 'iban', ts('IBAN'), array('class' => 'huge'), true);
    $this->add('text', 'bic', ts('BIC'), null, true);
    $this->add('text', 'amount', ts('Amount'), null, true);
    $this->add('select', 'frequency_interval', ts('Payment Frequency'), CRM_Contract_SepaLogic::getPaymentFrequencies());
    $this->add('select', 'cycle_day', ts('Cycle day'), CRM_Contract_SepaLogic::getCycleDays(), true);
    $this->addDate('start_date', ts('Start date'), true, array('formatType' => 'activityDate'));

    // check if BIC lookup is possible
    $this->assign('bic_lookup_accessible', CRM_Contract_SepaLogic::isLittleBicExtensionAccessible());

    $this->addButtons([
      array('type' => 'cancel', 'name' => 'Cancel'), // since Cancel looks bad when viewed next to the Cancel action
      array('type' => 'submit', 'name' => 'Create')
    ]);
  }

  function postProcess(){

    $submitted = $this->exportValues();

    $mandateParams = [
      'contact_id' => $this->get('cid'),
      'type' => 'RCUR',
      'iban' => $submitted['iban'],
      'bic' => $submitted['bic'],
      'amount' => $submitted['amount'],
      'date' => date('YmdHis'),
      'financial_type_id' => civicrm_api3('FinancialType', 'getvalue', ['return' => "id", 'name' => "Member dues"]),
      'cycle_day' => $submitted['cycle_day'],
      'start_date' => $submitted['start_date'],
      'frequency_unit' => 'month',
      'creditor_id' => 1,
      // caution: frequency_interval in SEPA/RecurringContribution terms is the inverse to the contract logic
      'frequency_interval' => 12 / $submitted['frequency_interval'],
    ];

    $sepaResult = civicrm_api3('SepaMandate', 'createfull', $mandateParams);
  }
}
