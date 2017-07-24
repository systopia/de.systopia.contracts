<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Configuration options for Contract extension
 *
 * @todo create settings page
 */
class CRM_Contract_Configuration {

  /**
   * derive gender_id from the given prefix_id
   *
   * @todo: make configurable
   */
  public static function getGenderID($prefix_id) {
    switch ($prefix_id) {
      case 2: // Frau
        return 1; // female

      case 3: // Herr
        return 2; // male

      default:
        return '';
    }
  }
}
