<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


/**
 * Amendments to the "regular" CiviCRM membership form
 */
class CRM_Contract_Handler_MembershipForm {

  /**
   * Form validation for the CRM_Member_Form_Membership form (actions ADD and UPDATE)
   *
   * @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_validateForm
   */
  public static function validateForm($formName, &$fields, &$files, &$form, &$errors) {
    $membership_id = (int) CRM_Utils_Request::retrieve('id', 'Positive', $form);

    // validate the contract number
    $field_key = CRM_Contract_CustomData::getCustomFieldKey('membership_general', 'membership_contract');
    foreach ($fields as $key => $value) {
      if (preg_match("#^{$field_key}_-?[0-9]+$#", $key)) {
        // this should be the value
        $reference_error = CRM_Contract_Validation_ContractNumber::verifyContractNumber($value, $membership_id);
        if ($reference_error) {
          $errors[$key] = $reference_error;
        }
      }
    }
  }
}
