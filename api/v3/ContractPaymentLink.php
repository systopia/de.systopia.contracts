<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * ContractPaymentLink.create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_contract_payment_link_create_spec(&$spec) {
  $spec['id'] = array(
      'name'         => 'id',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'ContractPaymentLink ID',
      'description'  => 'ID of existing ContractPaymentLink entity',
  );
  $spec['contract_id'] = array(
      'name'         => 'contract_id',
      'api.required' => 1,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'Contract ID',
      'description'  => 'Contract this link relates to',
  );
  $spec['contribution_recur_id'] = array(
      'name'         => 'contribution_recur_id',
      'api.required' => 1,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'ContributionRecur ID',
      'description'  => 'Linked payment ID',
  );
  $spec['is_active'] = array(
      'name'         => 'is_active',
      'api.default'  => 1,
      'type'         => CRM_Utils_Type::T_BOOLEAN,
      'title'        => 'Is Active?',
      'description'  => 'Is the link currently active?',
  );
  $spec['start_date'] = array(
      'name'         => 'start_date',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_DATE,
      'title'        => '(Start) Date',
      'description'  => 'When did this link relationship happen or start?',
  );
  $spec['end_date'] = array(
      'name'         => 'end_date',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_DATE,
      'title'        => 'End Date',
      'description'  => 'When did this link relationship end?',
  );
}

/**
 * ContractPaymentLink.create API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_contract_payment_link_create($params) {
  return _civicrm_api3_basic_create(CRM_Contract_BAO_ContractPaymentLink, $params);
}

/**
 * ContractPaymentLink.delete API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_contract_payment_link_delete($params) {
  return _civicrm_api3_basic_delete(CRM_Contract_BAO_ContractPaymentLink, $params);
}

/**
 * ContractPaymentLink.get API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_contract_payment_link_get($params) {
  return _civicrm_api3_basic_get(CRM_Contract_BAO_ContractPaymentLink, $params);
}


/**
 * ContractPaymentLink.getactive API specification
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_contract_payment_link_getactive_spec(&$spec) {
  $spec['contract_id'] = array(
      'name'         => 'contract_id',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'Contract ID',
      'description'  => 'Contract/Membership this link relates to',
  );
  $spec['contribution_recur_id'] = array(
      'name'         => 'contribution_recur_id',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'Payment ID',
      'description'  => 'ContributionRcur this link relates to',
  );
  $spec['date'] = array(
      'name'         => 'date',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_DATE,
      'title'        => 'Date',
      'description'  => 'What point in time are we looking at? Default: now',
  );
}


/**
 * ContractPaymentLink.getactive API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_contract_payment_link_getactive($params) {
  try {
    $result = CRM_Contract_BAO_ContractPaymentLink::getActiveLinks(
        CRM_Utils_Array::value('contract_id', $params, NULL),
        CRM_Utils_Array::value('contribution_recur_id', $params, NULL),
        CRM_Utils_Array::value('date', $params, 'now'));
    return civicrm_api3_create_success($result);
  } catch (Exception $ex) {
    throw new API_Exception($ex->getMessage());
  }
}
