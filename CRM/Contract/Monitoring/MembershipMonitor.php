<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * This class will monitor changes to memberships
 * and act appropriately.
 */
class CRM_Contract_Monitoring_MembershipMonitor extends CRM_Contract_Monitoring_EntityMonitor {

  public function handlePre($op, &$params) {
    // Wrap calls to the Membership BAO so we can reverse engineer modification
    // activities if necessary
    // if($objectName == 'Membership' && in_array($op, array('create', 'edit'))){
    //   $wrapperMembership = CRM_Contract_Wrapper_Membership::one_shot_singleton();
    //   $wrapperMembership->pre($op, $id, $params);
    // }
  }

  public function handlePost($op, &$objectRef) {
    // if($objectName == 'Membership' && in_array($op, array('create', 'edit'))){
    //   $wrapperMembership = CRM_Contract_Wrapper_Membership::one_shot_singleton();
    //   $wrapperMembership->post($id);
    // }
  }

  public static function processPreHook($op, $entity_id, &$params) {
    CRM_Contract_Monitoring_EntityMonitor::processPreHook(CRM_Contract_Monitoring_MembershipMonitor, $op, $entity_id, $params);
  }

  public static function processPostHook($op, $entity_id, $objectRef) {
    CRM_Contract_Monitoring_EntityMonitor::processPostHook(CRM_Contract_Monitoring_MembershipMonitor, $op, $entity_id, $objectRef);
  }

}