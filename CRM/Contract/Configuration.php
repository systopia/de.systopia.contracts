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

  public static $use_new_engine = TRUE;
  protected static $eligible_campaigns = NULL;

  /**
   * Should the new Engine be used?
   */
  public static function useNewEngine() {
    return self::$use_new_engine;
  }

  /**
   * Get logged in contact ID
   *
   * @todo: make configurable
   */
  public static function getUserID() {
    $session = CRM_Core_Session::singleton();
    $contact_id = $session->getLoggedInContactID();
    if (!$contact_id) {
      // TODO: make default configurable
      $contact_id = 1;
    }
    return $contact_id;
  }

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

  /**
   * get the list of campaigns eligible for creating
   * new contracts
   * @todo configure
   */
  public static function getCampaignList() {
    if (self::$eligible_campaigns === NULL) {
      self::$eligible_campaigns = array(
        '' => ts('- none -'));
      $campaign_query = civicrm_api3('Campaign', 'get', array(
        'sequential'   => 1,
        'is_active'    => 1,
        'option.limit' => 0,
        'return'       => 'id,title'
        ));
      foreach ($campaign_query['values'] as $campaign) {
        self::$eligible_campaigns[$campaign['id']] = $campaign['title'];
      }
    }
    return self::$eligible_campaigns;
  }


  /**
   * Get a list of contract references that are excempt
   * from the UNIQUE contraint.
   */
  public static function getUniqueReferenceExceptions() {
    // TODO: these are GP values,
    //   create a setting to make more flexible
    return array(
      "Einzug durch TAS",
      "Vertrag durch TAS",
      "Allgemeine Daueraufträge",
      "Vertrag durch Directmail",
      "Dauerauftrag neu",
      "Vertrag durch Canvassing",
      "Einzugsermächtigung",
      "Frontline",
      "Online-Spende",
      "Greenpeace in Action",
      "Online Spende",
      "VOR",
      "Internet",
      "Onlinespende",
      "Online-Spenden",
    );
  }
}
