<?php

$my_class = new CRM_Contract_Form_Resume();

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

        foreach (CRM_Contract_Utils_ActionProperties::getAll() as $actionProperties) {
            if(in_array($membershipStatus['name'], $actionProperties['validStartStatuses'])){
                $this->links[] = array(
                    'name' => ucfirst($actionProperties['action']),
                    'title' => ucfirst("{$actionProperties['action']} Contract"),
                    'url' => "civicrm/contract/{$actionProperties['action']}",
                    'bit' => CRM_Core_Action::UPDATE,
                    'qs' => 'id=%%id%%',
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
