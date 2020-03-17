<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

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
*
* @deprecated - replaced because it caused recursion errors
*/
class CRM_Contract_Wrapper_Membership{

  private static $recursion = 0;
  private static $_singleton;

  private function __construct(){
    $this->handler = new CRM_Contract_Handler_Contract;
  }

  /**
   * this is a singleton, but it will be destroyed when
   * calling the post method
   */
  public static function one_shot_singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Wrapper_Membership();
    }
    return self::$_singleton;
  }

  public function pre($op, $id, $params){
    self::$recursion += 1;
    if (self::$recursion > 1) {
      error_log("WARNING: CONTRACT MEMBERSHIP RECURSION DEPTH: " . self::$recursion);
    }

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
    self::$recursion -= 1;

    // destroy singleton after post command, caused problems
    self::$_singleton = NULL;

    if($this->skip){
      return;
    }
    $this->handler->setEndState($id);
    // The contract wrapper skips actually making the change (as the membership
    // BAO call handles this). However, it still does the necessary postModify
    // tasks.
    // straight to postModify();
    $this->handler->postModify();
  }
}
