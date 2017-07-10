/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

// this value will be replaced upon injection
var sepa_creditor_parameters = SEPA_CREDITOR_PARAMETERS;

/**
 * Will calculate the date of the next collection of
 * a CiviSEPA RCUR mandate
 */
function nextCollectionDate(cycle_day, start_date, grace_end, creditor_id='default') {
  // 0. sanity check cycle day
  console.log(creditor_id);
  console.log(cycle_day);
  console.log(start_date);
  console.log(grace_end);
      // $this->now    = strtotime("+$rcur_notice days -$grace_period days");

  // 1. earliest contribution date is: max(now+notice, start_date, grace_end)

  // 2. increase date until it hits cycle_day
}
