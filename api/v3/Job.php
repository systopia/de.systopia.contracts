<?php


// A wrapper around Membership.create with appropriate fields passed. On cannot
// schedule Contract.create for the future.

function civicrm_api3_Job_process_open_contract_modifications($params){
  civicrm_api3_Contract_process_open_modifications($params);
}
