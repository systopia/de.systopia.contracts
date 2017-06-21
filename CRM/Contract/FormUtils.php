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
        $this->recurringContribution = new CRM_Contract_RecurringContribution;

    }

    public function addPaymentContractSelect2($elementName, $contactId, $required = true)
    {
        $rc[''] = '- none -';
        foreach($this->recurringContribution->getAll($contactId) as $key => $rc){
          $recurringContributionOptions[$key] = $rc['label'];
        }
        $this->form->add('select', $elementName, ts('Mandate / Recurring Contribution'), $recurringContributionOptions, $required, array('class' => 'crm-select2 huge'));
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
            $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'] = $this->recurringContribution->writePaymentContractLabel($contributionRecur);
            $this->form->assign('viewCustomData', $details);
        }
    }
    public function showMembershipTypeLabel()
    {
        $result = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => 'contract_updates', 'name' => 'ch_membership_type'));
        // Get the custom data that was sent to the template
        $details = $this->form->get_template_vars('viewCustomData');

        // We need to know the id for the row of the custom group table that
        // this custom data is stored in
        $customGroupTableId = key($details[$result['custom_group_id']]);

        $membershipTypeId = $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'];
        if($membershipTypeId){
            $membershipType = civicrm_api3('MembershipType', 'getsingle', ['id' => $membershipTypeId]);

            // Write nice text and return this to the template
            $details[$result['custom_group_id']][$customGroupTableId]['fields'][$result['id']]['field_value'] = $membershipType['name'];
            $this->form->assign('viewCustomData', $details);
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
}
