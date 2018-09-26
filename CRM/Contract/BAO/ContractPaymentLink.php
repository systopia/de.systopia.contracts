<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

class CRM_Contract_BAO_ContractPaymentLink extends CRM_Contract_DAO_ContractPaymentLink {

  /**
   * Create a new payment link
   *
   * @param int $contract_id            the contract to link
   * @param int $contribution_recur_id  the ID of the entity to link to
   * @param bool $is_active             is the link active? default is YES
   * @param string $start_date          start date of the link, default NOW
   * @param string $end_date            end date of the link, default is NONE
   *
   * @return object CRM_Contract_BAO_ContractPaymentLink resulting object
   * @throws Exception if mandatory fields aren't set
   */
  public static function createPaymentLink($contract_id, $contribution_recur_id, $is_active = TRUE, $start_date = 'now', $end_date = NULL) {
    $params = array(
        'contract_id'   => $contract_id,
        'contribution_recur_id'    => $contribution_recur_id,
        '$is_active'   => $is_active ? 1 : 0,
    );

    // set dates
    if ($start_date) {
      $params['start_date'] = date('YmdHis', strtotime($start_date));
    }
    if ($end_date) {
      $params['end_date'] = date('YmdHis', strtotime($end_date));
    }

    return self::add($params);
  }

  /**
   * Get all active payment links with the given parameters,
   *  i.e. link fulfills the following criteria
   *        is_active = 1
   *        start_date NULL or in the past
   *
   *
   * @param int $contract_id            the contract to link
   * @param int $contribution_recur_id  ID of the linked entity
   * @param string $date                what timestamp does the "active" refer to? Default is: now
   *
   * @todo: add limit
   *
   * @return array of link data
   * @throws Exception if contract_id is invalid
   */
  public static function getActiveLinks($contract_id = NULL, $contribution_recur_id = NULL, $date = 'now') {
    // build where clause
    $WHERE_CLAUSES = array();

    // process date
    $now = date('YmdHis', strtotime($date));
    $WHERE_CLAUSES[] = "is_active >= 1";
    $WHERE_CLAUSES[] = "start_date IS NULL OR start_date <= '{$now}'";
    $WHERE_CLAUSES[] = "end_date   IS NULL OR end_date   >  '{$now}'";

    // process contract_id
    if (!empty($contract_id)) {
      $contract_id = (int) $contract_id;
      $WHERE_CLAUSES[] = "contract_id = {$contract_id}";
    }

    // process entity restrictions
    if (!empty($contribution_recur_id)) {
      $contribution_recur_id = (int) $contribution_recur_id;
      $WHERE_CLAUSES[] = "contribution_recur_id = {$contribution_recur_id}";
    }

    // build and run query
    $WHERE_CLAUSE = '(' . implode(') AND (', $WHERE_CLAUSES) . ')';
    $query_sql = "SELECT * FROM civicrm_contract_payment WHERE {$WHERE_CLAUSE}";
    $query = CRM_Core_DAO::executeQuery($query_sql);
    $results = array();
    while ($query->fetch()) {
      $results[] = $query->toArray();
    }
    return $results;
  }

  /**
   * End a payment link
   *
   * @param int $link_id               ID of the link
   * @param string $date               at what timestamp should the link be ended - default is "now"
   *
   * @return object CRM_Contract_BAO_ContractPaymentLink resulting object
   * @throws Exception if mandatory fields aren't set
   */
  public static function endPaymentLink($link_id, $date = 'now') {
    $link_id = (int) $link_id;
    if ($link_id) {
      $link = new CRM_Contract_BAO_ContractPaymentLink();
      $link->id = $link_id;
      $link->is_active = 0;
      $link->end_date = date('YmdHis', strtotime($date));
      $link->save();
    }
  }

  /**
   * Create/edit a ContractPaymentLink entry
   *
   * @param array  $params (reference ) an assoc array of name/value pairs
   * @return object CRM_Contract_BAO_ContractPaymentLink object on success, null otherwise
   * @access public
   * @static
   * @throws Exception if mandatory parameters not set
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    if ($hook == 'create') {
      // check mandatory fields
      if (empty($params['contract_id'])) {
        throw new Exception("Field contract_id is mandatory.");
      }
      if (empty($params['contribution_recur_id'])) {
        throw new Exception("Field contribution_recur_id is mandatory.");
      }

      // set create date
      $params['creation_date'] = date('YmdHis');
    }

    CRM_Utils_Hook::pre($hook, 'ContractPaymentLink', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Contract_BAO_ContractPaymentLink();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'ContractPaymentLink', $dao->id, $dao);
    return $dao;
  }
}
