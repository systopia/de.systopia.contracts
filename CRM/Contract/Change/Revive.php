<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * "Revive Membership" change
 */
class CRM_Contract_Change_Revive extends CRM_Contract_Change_Upgrade
{
  /**
   * Update the contract with the given data
   *
   * @param $updates array changes: attribute->value
   * @throws Exception
   */
  public function updateContract($updates) {
    // Revive does all the same things as Upgrade, except it also removes end_date and sets status
    $updates['end_date'] = '';
    $updates['status_id'] = 'Current';
    parent::updateContract($updates);
  }
}