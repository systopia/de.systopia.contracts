<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * This class handles updates to contracts, checking that an update is valid and
 * ensuring that important changes are recorded in activities.
 *
 * There are two layers at which this handler can be called: the API and the
 * BAO. If it is called at the API layer, it sets a parameter handledByApi which
 * will prevent it from being handled again at the BAO.
 *
 * We prefer to handle it at the API as then we can pass back information to the
 * API result, including the ID of the contract that was created as
 * $result['links']['activity_history_id'].
 */
class CRM_Contract_Handler{




  function assignNextTransactionId($contractId){
    $contributionRecurs = civicrm_api3('ContributionRecur', 'get', array(
      'trxn_id' => array('LIKE' => "CONTRACT-{$contractId}%"),
    ));
    if($contributionRecurs['values']){
      foreach($contributionRecurs['values'] as $v){
        $tids[] = $v['trxn_id'];
      }
      $tids = preg_filter('/CONTRACT\-\d+-(\d+)/', '$1', $tids);
      if($tids){
        return "CONTRACT-{$contractId}-".(string)(max($tids) + 1);
      }else{
        return  "CONTRACT-{$contractId}-2";
      }
    }else{
      return  "CONTRACT-{$contractId}";
    }
  }

}
