<?php

class CRM_Contract_AlterForm
{

    function __construct($form, $contractId){
      $this->form = $form;
      $this->contract = civicrm_api3('Membership', 'getsingle', array('id' => $contractId));
    }

    function getSepaPaymentInstruments(){
      if(!isset($this->sepaPaymentInstruments)){
        $result = civicrm_api3('OptionValue', 'get', array('option_group_id' => 'payment_instrument'));
        foreach ($result['values'] as $paymentInstrument) {
          $this->paymentInstruments[$paymentInstrument['value']] = $paymentInstrument['label'];
        }
        $result = civicrm_api3('OptionValue', 'get', array('option_group_id' => 'payment_instrument', 'name' => array('IN' => array('RCUR', 'OOFF', 'FRST'))));
        foreach ($result['values'] as $paymentInstrument) {
          $this->sepaPaymentInstruments[] = $paymentInstrument['value'];
        }
      }
      return $this->sepaPaymentInstruments;
    }


    public function addPaymentContractSelect2($elementName)
    {

        $contributionRecurs = civicrm_api3('ContributionRecur', 'get', array('contact_id' => $this->contract['contact_id']));
        $contributionRecurOptions = array('' => '- none -') + array_map(array($this, 'writePaymentContractLabel'), $contributionRecurs['values']);

        $this->form->add('select', $elementName, ts('Payment Contract'), $contributionRecurOptions, false, array('class' => 'crm-select2'));
    }

    public function showPaymentContractDetails()
    {
        $result = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'membership_payment', 'name' => 'membership_recurring_contribution'));

        // Get the custom data that was sent to the template
        $details = $this->form->get_template_vars('viewCustomData');

        // We need to know the id for the row of the custom group table that
        // this custom data is stored in
        $customGroupTableId = key($details[$result['custom_group_id']]);

        $contributionRecurId = $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'];
        if($contributionRecurId){
          // Write nice text and return this to the template
          $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $contributionRecurId));
          $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'] = $this->writePaymentContractLabel($contributionRecur);
          $this->form->assign('viewCustomData', $details);
        }

    }

    public function writePaymentContractLabel($contributionRecur)
    {
        if (in_array($contributionRecur['payment_instrument_id'], $this->getSepaPaymentInstruments())) {
            $sepaMandate = civicrm_api3('SepaMandate', 'getsingle', array(
            'entity_table' => 'civicrm_contribution_recur',
            'entity_id' => $contributionRecur['id'],
        ));

            return "SEPA: {$sepaMandate['reference']} ({$contributionRecur['amount']} every {$contributionRecur['frequency_interval']} {$contributionRecur['frequency_unit']})";
        } else {
            return "{$this->paymentInstruments[$contributionRecur['payment_instrument_id']]}: ({$contributionRecur['amount']} every {$contributionRecur['frequency_interval']} {$contributionRecur['frequency_unit']})";
        }
    }
}
