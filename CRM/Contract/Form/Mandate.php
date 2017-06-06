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

    $this->add('text', 'iban', ts('IBAN'), null, true);
    $this->add('text', 'bic', ts('BIC'), null, true);
    $this->add('text', 'amount', ts('Amount'), null, true);
    $this->add('select', 'frequency_interval', ts('Frequency'), [1 => 'Monthly', 3 => 'Quarterly', 6 => 'Semi-annually', 12 => 'Annually', ], true);
    $this->add('select', 'cycle_day', ts('Cycle day'), [3 => 3, 9 => 9 ,17 => 17, 25 => 25], true);
    $this->addDate('start_date', ts('Start date'), true, array('formatType' => 'activityDate'));


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
      'frequency_interval' => $submitted['frequency_interval'],
    ];

    $sepaResult = civicrm_api3('SepaMandate', 'createfull', $mandateParams);
  }
}
