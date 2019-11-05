<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_AlterMembershipLinks
{
    public function __construct($membershipId, &$links, &$mask, &$values)
    {
        $this->links = &$links;
        $this->mask = &$mask;
        $this->values = &$values;
        $this->membershipId = $membershipId;
    }

    public function addHistoryActions()
    {
        $membership = civicrm_api3('Membership', 'getsingle', array('id' => $this->membershipId));
        $membershipStatus = civicrm_api3('MembershipStatus', 'getsingle', array('id' => $membership['status_id']));

        foreach (CRM_Contract_ModificationActivity::$modificationActivityClasses as $class) {
          $action = new $class;
            if(in_array($membershipStatus['name'], $action->getStartStatuses()) && $action->getAction() != 'resume'){
                $this->links[] = array(
                    'name' => ucfirst($action->getAction()),
                    'title' => ucfirst("{$action->getAction()} Contract"),
                    'url' => "civicrm/contract/modify",
                    'bit' => CRM_Core_Action::UPDATE,
                    'qs' => "modify_action={$action->getAction()}&id=%%id%%",
                );
            }
        }
    }

    public function removeActions($actions)
    {
        foreach ($this->links as $key => $link) {
            if (in_array($link['bit'], $actions)) {
                unset($this->links[$key]);
            }
        }
    }
}
