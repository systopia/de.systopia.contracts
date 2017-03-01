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
    public function __construct($form, $id, $entity = 'Membership')
    {
        $this->entity = $entity;
        //If this is an activity form, then we need to get the contract ID before
        if($entity =='Activity'){
          $activity = civicrm_api3('Activity', 'getsingle', array('id' => $id));
          $id = $activity['source_record_id'];
        }
        $this->form = $form;
        $this->contract = civicrm_api3('Membership', 'getsingle', array('id' => $id));
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

    public function addPaymentContractSelect2($elementName)
    {
        $contributionRecurs = civicrm_api3('ContributionRecur', 'get', array('contact_id' => $this->contract['contact_id']));
        $contributionRecurOptions = array('' => '- none -') + array_map(array($this, 'writePaymentContractLabel'), $contributionRecurs['values']);

        $this->form->add('select', $elementName, ts('Payment Contract'), $contributionRecurOptions, false, array('class' => 'crm-select2'));
    }

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

    public function removeMembershpEditDisallowedCoreFields()
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

    public function removeMembershpEditDisallowedCustomFields()
    {
        $customGroupsToRemove = array('membership_cancellation');
        $customFieldsToRemove['membership_payment'] = array('membership_annual', 'membership_frequency', 'membership_recurring_contribution');

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
}
