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

    public function addPaymentContractSelect2($elementName, $contactId, $required = true, $contractId)
    {
        $rc[''] = '- none -';
        foreach($this->recurringContribution->getAll($contactId, true, $contractId) as $key => $rc){
          $recurringContributionOptions[$key] = $rc['label'];
        }
        $this->form->add('select', $elementName, ts('Mandate / Recurring Contribution'), $recurringContributionOptions, $required, array('class' => 'crm-select2 huge'));
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

  /**
   * Add a download link to the custom field membership_contract
   * @param $membershipId
   */
    public function addMembershipContractFileDownloadLink($membershipId) {
      $membershipContractCustomField = civicrm_api3('CustomField', 'getsingle', array('name' => "membership_contract", 'return' => "id"));
      $membership = civicrm_api3('Membership','getsingle', array('id' => $membershipId));
      if (!empty($membership['custom_'.$membershipContractCustomField['id']])) {
        $membershipContract = $membership['custom_'.$membershipContractCustomField['id']];
        $contractFile = CRM_Contract_Utils::contractFileExists($membershipContract);
        if ($contractFile) {
          $script = file_get_contents(CRM_Core_Resources::singleton()->getUrl('de.systopia.contract', 'templates/CRM/Member/Form/MembershipView.js'));
          $url = CRM_Utils_System::url('civicrm/contract/modify', "contract=".urlencode($membershipContract));
          $script = str_replace('CONTRACT_FILE_DOWNLOAD', $url, $script);
          CRM_Core_Region::instance('page-footer')->add(array(
            'script' => $script,
          ));
        }
      }
    }

  /**
   * Called from civicrm/contract/download?contract={contract_id}
   * This function downloads a contract file via the users web browser
   */
    public function downloadMembershipContract() {
      // If we requested a contract file download
      $download = CRM_Utils_Request::retrieve('contract', 'String', CRM_Core_DAO::$_nullObject, FALSE, '', 'GET');
      if (!empty($download)) {
        // FIXME: Could use CRM_Utils_System::download but it still requires you to do all the work (load file to stream etc) before calling.
        if (CRM_Contract_Utils::downloadContractFile($download)) {
          CRM_Utils_System::civiExit();
        }
        // If the file didn't exist
        echo "File does not exist";
        CRM_Utils_System::civiExit();
      }
    }
}
