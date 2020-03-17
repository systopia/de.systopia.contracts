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
 * Schedule a Contract modification
 */
function _civicrm_api3_contribution_recur_getdetails_spec(&$params) {
  $params['contact_id'] = array(
      'name'         => 'contact_id',
      'title'        => 'CiviCRM Contact ID',
      'api.required' => 1,
      'type'         => 1,
  );
}

/**
 * Generate some information on recrring contributions
 */
function civicrm_api3_contribution_recur_getdetails($params) {
  $recurringContribution = new CRM_Contract_RecurringContribution();
  $data = $recurringContribution->getAll($params['contact_id']);
  return civicrm_api3_create_success($data);
}

