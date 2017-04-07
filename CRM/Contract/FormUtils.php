<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_FormUtils
{
    public function __construct($form, $entity)
    {

        // The form object and type of entity that we are updating with the form
        // is passed via the constructor
        $this->entity = $entity;
        $this->form = $form;
    }

    public function getRecurringContributions($cid){
      $contacts = civicrm_api3('Contact', 'get', array('id' => $cid))['values'];
      $contributionRecurs = civicrm_api3('ContributionRecur', 'get', ['contact_id' => $cid,'option.limit' => 1000])['values'];
      $sepaMandates = civicrm_api3('sepaMandate', 'get', ['contact_id' => $cid,'option.limit' => 1000])['values'];
      $paymentInstrumentOptions = civicrm_api3('OptionValue', 'get', array( 'option_group_id' => "payment_instrument"))['values'];
      $sepaCreditors = civicrm_api3('SepaCreditor', 'get')['values'];
      foreach($paymentInstrumentOptions as $paymentInstrumentOption){
        $paymentInstruments[$paymentInstrumentOption['value']]=$paymentInstrumentOption['name'];
      }
      // var_dump($paymentInstruments);
      foreach($contributionRecurs as $cr){
        $return[$cr['id']]['fields']=[
          'display_name' => $contacts[$cr['contact_id']]['display_name'],
          'payment_instrument' => $paymentInstruments[$cr['payment_instrument_id']],
          'frequency' => $this->writeFrequency($cr),
          'amount' => $cr['amount'],
          'annual_amount' => $this->calcAnnualAmount($cr),
          'next_debit' => '?',
        ];
        $return[$cr['id']]['text_summary'] ="Creditor name: {$return[$cr['id']]['fields']['display_name']}<br />
Payment method: {$return[$cr['id']]['fields']['payment_instrument']}<br />
Frequency: {$return[$cr['id']]['fields']['frequency']}<br />
Annual contract amount: {$return[$cr['id']]['fields']['annual_amount']}<br />
Frequency contract amount: {$return[$cr['id']]['fields']['amount']}<br />
";
        if (in_array($cr['payment_instrument_id'], $this->getSepaPaymentInstruments())) {
          $sepa = $this->getSepaByRecurringContributionId($cr['id'], $sepaMandates);
          $return[$cr['id']]['fields']['payment_instrument'] = "SEPA";
          $return[$cr['id']]['fields']['iban'] = $sepa['iban'];
          $return[$cr['id']]['fields']['org_iban'] = $sepaCreditors[$sepa['creditor_id']]['iban'];
          // $return[$cr['id']]['fields']['org_iban'] = $sepa;
          // $return[$cr['id']]['fields']['org_iban'] = $cr['id'];
          $return[$cr['id']]['fields']['next_debit'] = '?';
          $return[$cr['id']]['label'] = "SEPA, {$return[$cr['id']]['fields']['amount']} {$return[$cr['id']]['fields']['frequency']} ({$sepa['reference']})";
          $return[$cr['id']]['text_summary'] .= "Organisational account: {$return[$cr['id']]['fields']['org_iban']}<br />
Creditor account: {$return[$cr['id']]['fields']['iban']}<br />
Next debit: {$return[$cr['id']]['fields']['next_debit']}";
        }else{
          $return[$cr['id']]['label'] = "{$return[$cr['id']]['fields']['payment_instrument']}, {$return[$cr['id']]['fields']['amount']} {$return[$cr['id']]['fields']['frequency']}";
        }
      }
      return $return;

    }

    private function getSepaByRecurringContributionId($id, $sepas){
      foreach($sepas as $sepa){
        if($sepa['entity_id'] == $id && $sepa['entity_table'] == 'civicrm_contribution_recur'){
          return $sepa;
        }
      }
    }

    private function calcAnnualAmount($cr){
      if($cr['frequency_unit']=='month'){
        $multiplier = 12;
      }elseif($cr['frequency_unit']=='year'){
        $multiplier = 1;
      }
      return $cr['amount'] * $multiplier / $cr['frequency_interval'];
    }

    private function writeFrequency($cr){
      if($cr['frequency_interval']==1){
        $frequency = "Every {$cr['frequency_unit']}";
      }else{
        $frequency = "Every {$cr['frequency_interval']} {$cr['frequency_unit']}s";
      }
      $shortHands = [
        'Every 12 months' => 'Annual',
        'Every year' => 'Annual',
        'Every month' => 'Monthly'
      ];
      if(array_key_exists($frequency, $shortHands)){
        return $shortHands[$frequency];
      }
      return $frequency;
    }

    public function getSepaPaymentInstruments()
    {
        if (!isset($this->sepaPaymentInstruments)) {
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

    public function addPaymentContractSelect2($elementName, $contactId, $required = true)
    {
        $rc[''] = '- none -';
        foreach($this->getRecurringContributions($contactId) as $key => $rc){
          $recurringContributionOptions[$key] = $rc['label'];
        }
        $this->form->add('select', $elementName, ts('Mandate / Recurring Contribution'), $recurringContributionOptions, $required, array('class' => 'crm-select2'));
    }

    //TODO refactor to use something nice like something extracted from $this->getRecurringContributions()
    public function showPaymentContractDetails()
    {
        if($this->entity == 'Membership'){
          $result = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'membership_payment', 'name' => 'membership_recurring_contribution'));
        }elseif($this->entity == 'Activity'){
          $result = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'contract_updates', 'name' => 'ch_recurring_contribution'));
        }
        // Get the custom data that was sent to the template
        $details = $this->form->get_template_vars('viewCustomData');

        // We need to know the id for the row of the custom group table that
        // this custom data is stored in
        $customGroupTableId = key($details[$result['custom_group_id']]);

        $contributionRecurId = $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'];
        if ($contributionRecurId) {
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

    public function removeMembershipEditDisallowedCoreFields()
    {
        foreach ($this->getMembershpEditDisallowedCoreFields() as $element) {
            if ($this->form->elementExists($element)) {
                $this->form->removeElement($element);
            }
        }
    }

    public function getMembershpEditDisallowedCoreFields()
    {
        return array('status_id', 'is_override');
    }

    public function removeMembershipEditDisallowedCustomFields()
    {
        // $customGroupsToRemove = array('membership_cancellation');
        $customGroupsToRemove = array();
        $customFieldsToRemove['membership_payment'] = array();

        foreach ($this->form->_groupTree as $groupKey => $group) {
            if (in_array($group['name'], $customGroupsToRemove)) {
                unset($this->form->_groupTree[$groupKey]);
            } else {
                foreach ($group['fields'] as $fieldKey => $field) {
                    if (isset($customFieldsToRemove[$group['name']]) && in_array($field['column_name'], $customFieldsToRemove[$group['name']])) {
                        unset($this->form->_groupTree[$groupKey]['fields'][$fieldKey]);
                    }
                }
            }
        }
    }

    /**
     * Replaced with js/membership-edit.js for now as filtering was doing weird
     * things to the status_id
     */
}
