<?php

use CRM_Contract_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Contract_ContractTestBase extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface
{

  protected static $counter = 0;

  public function setUpHeadless()
  {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
        ->installMe(__DIR__)
        ->apply();
  }

  public function setUp()
  {
    parent::setUp();
  }

  public function tearDown()
  {
    parent::tearDown();
  }


  /**
   * Execute the API call and assert that it is successfull
   *
   * @param $entity string entity
   * @param $action string action
   * @param $params array parameters
   * @return array result
   */
  public function assertAPI3($entity, $action, $params)
  {
    try {
      return civicrm_api3($entity, $action, $params);
    } catch (CiviCRM_API3_Exception $ex) {
      $this->assertFalse(TRUE, "API Exception: " . $ex->getMessage());
      return NULL;
    }
  }

  /**
   * Run the contract engine, and make sure it works
   *
   * @param $contract_id
   */
  public function runContractEngine($contract_id, $now = 'now')
  {
    $this->assertNotEmpty($contract_id, "You can only run the contract engine on a specific contract ID.");
    $result = $this->assertAPI3('Contract', 'process_scheduled_modifications', [
        'now' => $now,
        'id'  => $contract_id]);
    $this->assertTrue(empty($result['values']['failed']), "Contract Engine reports failure");
    return $result;
  }


  /**
   * Create a new contact with a random email address. Good for simple
   *  tests via the 'CRM_Xcm_Matcher_EmailOnlyMatcher'
   *
   * @param array $contact_data
   */
  public function createContactWithRandomEmail($contact_data = [])
  {
    if (empty($contact_data['contact_type'])) {
      $contact_data['contact_type'] = 'Individual';
    }
    if (empty($contact_data['first_name'])) {
      $contact_data['first_name'] = 'Random';
    }
    if (empty($contact_data['last_name'])) {
      $contact_data['last_name'] = 'Bloke';
    }

    // add random email
    self::$counter++;
    $contact_data['email'] = sha1(microtime() . self::$counter) . '@nowhere.nil';

    $contact     = $this->assertAPI3('Contact', 'create', $contact_data);
    $new_contact = $this->assertAPI3('Contact', 'getsingle', ['id' => $contact['id']]);
    return $new_contact;
  }

  /**
   * Get a random payment instrument ID
   *
   * @return integer payment instrument ID
   */
  public function getRandomPaymentInstrumentID() {
    $pis = $this->assertAPI3('OptionValue', 'get', [
        'is_active'       => 1,
        'option_group_id' => 'payment_instrument'
    ]);
    $this->assertNotEmpty($pis['count'], 'No PaymentInstruments configured');
    $instrument = $pis['values'][array_rand($pis['values'])];
    return $instrument['value'];
  }

  /**
   * Get a random membership type ID
   *
   * @return integer membership type ID
   */
  public function getRandomMembershipTypeID()
  {
    $types = $this->assertAPI3('MembershipType', 'get', ['is_active' => 1]);
    if ($types['count'] > 0) {
      $type = $types['values'][array_rand($types['values'])];
      return $type['id'];
    } else {
      // create a new one
      $contact  = $this->createContactWithRandomEmail();
      $new_type = $this->assertAPI3('MembershipType', 'create', [
          'member_of_contact_id' => $contact['id'],
          'financial_type_id'    => "1",
          'duration_unit'        => "year",
          'duration_interval'    => "1",
          'period_type'          => "rolling",
          'name'                 => "Test Fallback",
          'is_active'            => "1",
      ]);
      return $new_type['id'];
    }
  }

  /**
   * Simply load the contract by ID
   * @param $contract_id integer contract ID
   * @return array contract data
   */
  public function getContract($contract_id) {
    $contract = $this->assertAPI3('Membership', 'getsingle', ['id' => $contract_id]);
    CRM_Contract_CustomData::labelCustomFields($contract);
    return $contract;
  }

  /**
   * Create a new payment contract and return the recurring contribution
   *
   * @param $params  array specs
   * @param $is_sepa bool  if true, a SEPA mandate will be generated
   */
  public function createPayment($params, $is_sepa) {
    // fill common parameters
    if (empty($params['contact_id'])) {
      $contact = $this->createContactWithRandomEmail();
      $params['contact_id'] = $contact['id'];
    }
    if (empty($params['frequency_interval'])) {
      $params['frequency_interval'] = 12;
    }
    if (empty($params['frequency_unit'])) {
      $params['frequency_unit'] = 'month';
    }
    if (empty($params['amount'])) {
      $params['amount'] = '120.00';
    }
    if (empty($params['financial_type_id'])) {
      $params['financial_type_id'] = '1';
    }
    if (empty($params['currency'])) {
      $params['currency'] = 'EUR';
    }

    if ($is_sepa) {
      // SEPA Mandate:
      if (empty($params['iban'])) {
        $params['iban'] = 'DE89370400440532013000';
      }
      if (empty($params['bic'])) {
        $params['bic'] = 'GENODEM1GLS';
      }
      if (empty($params['type'])) {
        $params['type'] = 'RCUR';
      }

      $mandate = $this->assertAPI3('SepaMandate', 'createfull', $params);
      $mandate = $this->assertAPI3('SepaMandate', 'getsingle', ['id' => $mandate['id']]);
      return $this->assertAPI3('ContributionRecur', 'getsingle', ['id' => $mandate['entity_id']]);

    } else {
      // Standing Order (recurring contribution)
      if (empty($params['payment_instrument_id'])) {
        $params['payment_instrument_id'] = $this->getRandomPaymentInstrumentID();
      }
      if (empty($params['status_id'])) {
        $params['status_id'] = 'Current';
      }

      $payment = $this->assertAPI3('ContributionRecur', 'create', $params);
      return $this->assertAPI3('ContributionRecur', 'getsingle', ['id' => $payment['id']]);
    }
  }

  /**
   * Create a new contract
   *
   * @param $params
   */
  public function createNewContract($params = []) {
    $contact_id = (!empty($params['contact_id'])) ? $params['contact_id'] : $this->createContactWithRandomEmail()['id'];

    // first: make sure we have a contract payment
    if (empty($params['membership_payment.membership_recurring_contribution'])) {
      $payment = $this->createPayment($params, !empty($params['is_sepa']));
      $params['membership_payment.membership_recurring_contribution'] = $payment['id'];
    }


    //membership_payment.membership_recurring_contribution
    $contract = $this->assertAPI3('Contract', 'create', [
        'contact_id'         => $contact_id,
        'membership_type_id' => (!empty($params['membership_type_id'])) ? $params['membership_type_id'] : $this->getRandomMembershipTypeID(),
        'join_date'          => (!empty($params['join_date'])) ? $params['join_date'] : date('YmdHis'),
        'start_date'         => (!empty($params['start_date'])) ? $params['start_date'] : date('YmdHis'),
        'end_date'           => (!empty($params['end_date'])) ? $params['end_date'] : NULL,
        'campaign_id'        => (!empty($params['campaign_id'])) ? $params['campaign_id'] : NULL,
        'note'               => (!empty($params['note'])) ? $params['note'] : 'Test',
        'medium_id'          => (!empty($params['medium_id'])) ? $params['medium_id'] : '1',
        // custom stuff:
        'membership_payment.membership_recurring_contribution' => $params['membership_payment.membership_recurring_contribution'],
        'membership_general.membership_dialoger'               => $contact_id,
        // membership_general.membership_contract   // Contract number
        // membership_general.membership_reference  // Reference number
        // membership_general.membership_contract   // Contract number
        // membership_general.membership_channel    // Membership Channel
    ]);
    $this->assertNotEmpty($contract['id'], "Contract couldn't be created");

    // load the contract
    return $this->getContract($contract['id']);
  }

  /**
   * Modify a contract
   *
   * @param $contract_id integer contract ID
   * @param $action      string  one of 'cancel', 'revive', 'update', ...
   * @param $date        string  date string
   * @param $params      array   update parameters, incl 'date'
   *
   * @return array API result
   */
  public function modifyContract($contract_id, $action, $date = 'now', $params = []) {
    $params['id'] = $contract_id;
    $params['modify_action'] = $action;
    $params['date'] = date('Y-m-d H:i:s', strtotime($date));
    if (empty($params['medium_id'])) {
      $params['medium_id'] = 1;
    }
    return $this->assertAPI3('Contract', 'modify', $params);
  }

  /**
   * Get a random value from the given option group
   */
  public function getRandomOptionValue($option_group_id, $label = TRUE) {
    $all_option_values = $this->assertAPI3('OptionValue', 'get', [
        'option.limit'    => 0,
        'return'          => 'value,label',
        'option_group_id' => $option_group_id]);
    $value = $all_option_values['values'][array_rand($all_option_values['values'])];
    if ($label) {
      return $value['label'];
    } else {
      return $value['value'];
    }
  }
}
