<?php

/**
* This wrapper listens for updates to contracts via the membership API and
* These updates may come in via
*
* - a direct call to the the membership.create API
* - via the activity.create API
* - via the contract.modify API that wraps the activity.create API
*
* By default, it checks to see if the changes are significant. If so, it
* 'reverse engineers' a contract modification activity. This behaviour can be
* turned off by passing a 'skip_create_modification_activity' parameter. This
* is necessary when the API is called via the
* CRM_Contract_Handler_Contract::usingActivityData method as otherwise we
* would create duplicate modify contract activities.
*/
class CRM_Contract_Wrapper_MembershipEditForm{

  private static $_singleton;
  private $errors = [];


  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Wrapper_ContractAPI();
    }
    return self::$_singleton;
  }

  public function validate($form, $id, $params){
    $this->form = $form;
    $this->handler = new CRM_Contract_Handler_Contract;
    $this->handler->setStartState($id);

    // Date formats are returned in a WEIRD format...
    $joinDate = CRM_Utils_Date::processDate($params['join_date'], null, null, 'Y-m-d H:i:s');
    $startDate = CRM_Utils_Date::processDate($params['start_date'], null, null, 'Y-m-d H:i:s');
    $endDate = CRM_Utils_Date::processDate($params['end_date'], null, null, 'Y-m-d H:i:s');

    // In theory, the membership form is designed to take more than one
    // membership. We only ever use it with one.
    $params['membership_type_id'] = $params['membership_type_id'][1];
    $this->handler->setParams($params);
    if(!$this->handler->isValid()){
      $this->errors=$this->handler->getErrors();
    }
  }

  public function getErrors(){
    if(count($this->errors)){
      foreach($this->errors as $key => $error){
        if(strpos($key, '.')){
          unset($this->errors[$key]);
          $this->errors[CRM_Contract_Utils::getCustomFieldId($key)] = $error;
        }
      }

      // We have to add the number back onto the custom field id
      foreach($this->errors as $key => $message){
        if(!isset($this->form->_elementIndex[$key])){
          // If it isn't in the element index, presume it is a custom field
          // with the end missing and find the appropriate key for it.
          foreach($this->form->_elementIndex as $element => $discard){
            if(strpos($element, $key) === 0){
              $this->errors[$element] = $message;
              unset($this->errors[$key]);
              break;
            }
          }
        }
      }
      return $this->errors;
    }else{
      return [];
    }
  }
}
