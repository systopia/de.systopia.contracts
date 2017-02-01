<?php

class CRM_Contract_AlterContractForm
{
    public function __construct()
    {
        $result = civicrm_api3('OptionValue', 'get', array('option_group_id' => 'payment_instrument'));
        foreach ($result['values'] as $paymentInstrument) {
            $this->paymentInstruments[$paymentInstrument['value']] = $paymentInstrument['label'];
        }
        $result = civicrm_api3('OptionValue', 'get', array('option_group_id' => 'payment_instrument', 'name' => array('IN' => array('RCUR', 'OOFF', 'FRST'))));
        foreach ($result['values'] as $paymentInstrument) {
            $this->sepaPaymentInstruments[] = $paymentInstrument['value'];
        }
    }

    public function makePaymentContractSelect2($form)
    {

        // We are actually dealing with two forms that have the same name.
        // We are only interested in the one that generates the custom data fields
        // Use the presence of $form->_groupTree as a test to see if this is the
        // custom data form
        //
        if(!isset($form->_groupTree)){
          return;
        }

        // Find the field we want to replace
        $result = civicrm_api3('CustomField', 'GetSingle', array('custom_group_id' => 'membership_payment', 'name' => 'membership_recurring_contribution'));

        $customGroupTableId = $form->getAction() == CRM_Core_Action::ADD ? '-1' : $form->_groupTree[$result['custom_group_id']]['table_id'];

        $paymentContractElementName = "custom_{$result['id']}_{$customGroupTableId}";
        $contributionRecurs = civicrm_api3('ContributionRecur', 'get', array('contact_id' => $form->getContactId()));
        $contributionRecurOptions = array('' => '- none -') + array_map(array($this, 'writePaymentContractLabel'), $contributionRecurs['values']);

        $form->removeElement($paymentContractElementName);
        $form->add('select', $paymentContractElementName, ts('Payment Contract'), $contributionRecurOptions, false, array('class' => 'crm-select2'));
    }

    public function showPaymentContractDetails($form)
    {
        $result = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'membership_payment', 'name' => 'membership_recurring_contribution'));

        // Get the custom data that was sent to the template
        $details = $form->get_template_vars('viewCustomData');

        // We need to know the id for the row of the custom group table that
        // this custom data is stored in
        $customGroupTableId = key($details[$result['custom_group_id']]);

        $contributionRecurId = $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'];
        if($contributionRecurId){
          // Write nice text and return this to the template
          $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $contributionRecurId));
          $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'] = $this->writePaymentContractLabel($contributionRecur);
          $form->assign('viewCustomData', $details);
        }

    }

    public function writePaymentContractLabel($contributionRecur)
    {
        if (in_array($contributionRecur['payment_instrument_id'], $this->sepaPaymentInstruments)) {
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
