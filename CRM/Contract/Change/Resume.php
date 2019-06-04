<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * "Resume Membership" change
 */
class CRM_Contract_Change_Resume extends CRM_Contract_Change_Upgrade {

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  public function getRequiredFields() {
    return [
//        'membership_cancellation.membership_cancel_reason'
    ];
  }


}
