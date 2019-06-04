<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


/**
 * Contract.get -> redirected to Membership.get
 */
function _civicrm_api3_Contract_get_spec(&$params){
  _civicrm_api3_membership_get_spec($params);
}

/**
 * Contract.get -> redirected to Membership.get
 */
function civicrm_api3_Contract_get($params) {
  return civicrm_api3('Membership', 'get', $params);
}