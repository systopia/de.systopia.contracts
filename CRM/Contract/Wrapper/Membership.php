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
class CRM_Contract_Wrapper_Membership{

  private static $_singleton;

  private function __construct(){
    $this->handler = new CRM_Contract_Handler_Contract;
  }

  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Wrapper_Membership();
    }
    return self::$_singleton;
  }

  public function pre($op, $id, $params){
    $this->op = $op;
    $this->skip = isset($params['skip_handler']) && $params['skip_handler'];
    if($this->skip){
      return;
    }

    // Initialise handler as appropriate
    if($op == 'create'){
      $this->handler->isNewContract();
    }elseif($op == 'edit'){
      $this->handler->setStartState($id);
    }else{
      return;
    }
    $this->handler->setParams($params);

    if(!$this->handler->isValid()){
      throw new Exception('Invalid contract modification: '.implode(', ', $this->handler->getErrors()));
    }
  }

  public function post($id){
    if($this->skip){
      return;
    }

    // When a contract is created, we need to get the membership again in order
    // to get all fields (during an update we backfill the start state with
    // the parameters of the change but since there is no start state for a
    // create, we set it now, once it has been created.
    if($this->op == 'create'){
      $this->handler->endState = $this->handler->normalise(civicrm_api3('Membership', 'getsingle', ['id' => $id]));
    }

    // The contract wrapper skips actually making the change (as the membership
    // BAO call handles this). However, it still does the necessary postModify
    // tasks.
    // straight to postModify();
    $this->handler->postModify();
  }
}
