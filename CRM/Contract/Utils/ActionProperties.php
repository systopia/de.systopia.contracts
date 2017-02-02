<?php
class CRM_Contract_Utils_ActionProperties{

  function getAll(){

        $actions = array(
            'CRM_Contract_Form_Pause',
            'CRM_Contract_Form_Resume',
            'CRM_Contract_Form_Cancel',
            'CRM_Contract_Form_Revive',
            'CRM_Contract_Form_Update',
        );

        foreach ($actions as $action) {
            $return[] = get_class_vars($action);
        }

        return $return;
    }

    function getByClass($class){
      return  get_class_vars(get_class($class));
    }

}
