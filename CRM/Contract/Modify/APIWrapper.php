<?php

class CRM_Contract_Modify_APIWrapper{

  private static $_singleton;

  private function __construct(){
    $this->contractHandler = new CRM_Contract_Handler();
  }

  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Modify_APIWrapper();
    }
    return self::$_singleton;
  }

  function fromApiInput($apiRequest){
    return $apiRequest;
  }

  function toApiOutput($apiRequest, $result){
    return $result;
  }

}
