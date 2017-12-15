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
class CRM_Contract_Monitoring_EntityMonitor {

  /** store an instance per type/ID */
  private static $_monitor_instances = array();

  /** store the pro/post process stack per type */
  private static $_monitor_stack     = array();


  /**
   * Get the monitor instance for this
   */
  public static function getInstance($class, $entity_id, $params) {
    if ($entity_id) {
      $key = "{$class}-{$entity_id}";
      if (!isset(self::$_monitor_instance[$key])) {
        self::$_monitor_instance[$key] = new $class($entity_id, $params);
      }
      return self::$_monitor_instance[$key];

    } else {
      // no ID? so no tracking...
      return new $class($entity_id, $params);
    }
  }

  /**
   * Process PRE hook
   */
  public static function processPreHook($class, $op, $entity_id, &$params) {
    // first: get the instance
    $monitor = self::getInstance($class, $entity_id, $params);

    // then push to stack
    self::$_monitor_stack[$class][] = $monitor;

    // finally, run the handler
    $monitor->handlePre($op, &$params);
  }

  /**
   * Process POST hook
   */
  public static function processPostHook($class, $op, $entity_id, &$objectRef) {
    // first: get the instance
    $monitor = self::getInstance($class, $entity_id);

    // then push to stack
    array_pop(self::$_monitor_stack[$class]);

    // finally, run the handler
    $monitor->handlePost($op, &$objectRef);
  }
}