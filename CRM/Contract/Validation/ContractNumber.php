<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Contract validation functions
 *
 * @todo resolve hard dependecy to CiviBanking module
 */
class CRM_Contract_Validation_ContractNumber implements API_Wrapper {

  /**
   * Verifies that a membership_general.membership_contract number is UNIQUE
   * (unless its part of the exceptions, or already set for this ID)
   *
   * @param $reference   string  proposed reference
   * @param $contract_id int     updated contract, or empty if NEW
   *
   * @return NULL if given reference is valid, error message otherwise
   */
  public static function verifyContractNumber($reference, $contract_id = NULL) {
    error_log("CHECKING $reference");
    // empty references are acceptable
    if (empty($reference)) {
      return NULL;
    }

    // check if part of the exceptions
    $exceptions = CRM_Contract_Configuration::getUniqueReferenceExceptions();
    if (in_array($reference, $exceptions)) {
      return NULL;
    }

    // Validate requested reference:
    // prepare query
    $query = array(
      'membership_general.membership_contract' => $reference,
      'return'                                 => 'id',
      'option.limit'                           => 1
    );
    CRM_Contract_CustomData::resolveCustomFields($query);

    if (empty($contract_id)) {
      // NEW CONTRACT is to be created:
      $usage = civicrm_api3('Membership', 'get', $query);
      if ($usage['count'] > 0) {
        return ts("Reference '%1' is already in use!", array(1 => $reference));
      } else {
        return NULL;
      }

    } else {
      // // EXISTING CONTRACT is being updated
      $query['id'] = $contract_id;
      $unchanged = civicrm_api3('Membership', 'getcount', $query);
      if ($unchanged) {
        // this means the reference is already used by this contract
        return NULL;
      }

      // see if the reference is used elsewhere
      $query['id'] = array('<>' => $contract_id);
      $is_used = civicrm_api3('Membership', 'getcount', $query);
      if ($is_used) {
        // this means the reference is already used
        return ts("Reference '%1' is already in use!", array(1 => $reference));
      } else {
        return NULL;
      }
    }
  }


  /**
   * Membership API Wrapper: check API command
   */
  public function fromApiInput($apiRequest) {
    if ($apiRequest['action'] == 'create' || $apiRequest['action'] == 'edit') {
      $params = $apiRequest['params'];
      CRM_Contract_CustomData::labelCustomFields($params);
      if (!empty($params['membership_general.membership_contract'])) {
        $validation_error = self::verifyContractNumber($params['membership_general.membership_contract'], CRM_Utils_Array::value('id', $params));
        if ($validation_error) {
          throw new Exception($validation_error, 1);
        }
      }
    }
    return $apiRequest;
  }

  /**
   * Membership API Wrapper
   */
  public function toApiOutput($apiRequest, $result) {
    // nothing to do here
    return $result;
  }
}
