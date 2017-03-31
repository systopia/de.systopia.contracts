<?php
class CRM_Contract_Page_RecurringContributions extends CRM_Core_Page{
  function run(){
    if($contactId = CRM_Utils_Request::retrieve('cid', 'Positive')){
      $f = new CRM_Contract_FormUtils(null, null);
      echo json_encode($f->getPaymentContributionRecurOptions($contactId));
    }
    exit;
  }
}
