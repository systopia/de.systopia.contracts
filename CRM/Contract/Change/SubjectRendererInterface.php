<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


/**
 * Cancel membership change
 */
interface CRM_Contract_Change_SubjectRendererInterface {

  /**
   * Calculate the activities subject
   *
   * @param $change               CRM_Contract_Change the change object
   * @param $contract_after       array  data of the contract after
   * @param $contract_before      array  data of the contract before
   * @return                      string the subject line
   */
  public function renderChangeSubject($change, $contract_after, $contract_before = NULL);
}
