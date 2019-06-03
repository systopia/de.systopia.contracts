<?php

use CRM_Contract_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

include_once 'ContractTestBase.php';

/**
 * Basic Contract Engine Tests
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
class CRM_Contract_EngineComparisonTest extends CRM_Contract_ContractTestBase {
  public function setUp() {
    CRM_Contract_Configuration::$use_new_engine = TRUE;
  }

  /**
   * Test a simple create
   */
  public function testSimpleCreate() {
    CRM_Contract_Configuration::$use_new_engine = TRUE;
    $contract_new = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Signed']);
    $this->assertEquals(1, count($activities_new), "Exactly one signed activity expected!");
    $activity_new = $activities_new[0];

    CRM_Contract_Configuration::$use_new_engine = FALSE;
    $contract_old = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Signed']);
    $this->assertEquals(1, count($activities_old), "Exactly one signed activity expected!");
    $activity_old = $activities_old[0];

    // make sure they generate the same data
    $this->assertArraysEqual($activity_old, $activity_new, NULL, ['id', 'source_record_id', 'activity_date_time']);
  }

  /**
   * Example: Test that a version is returned.
   */
  public function testSimpleUpgrade() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'cancel', 'tomorrow', [
          'membership_payment.membership_annual'             => '240.00',
          'membership_cancellation.membership_cancel_reason' => $this->getRandomOptionValue('contract_cancel_reason')]);

      // run engine see if anything changed
      $this->runContractEngine($contract['id']);

      // things should not have changed
      $contract_changed1 = $this->getContract($contract['id']);
      $this->assertEquals($contract, $contract_changed1, "This shouldn't have changed");

      // run engine again for tomorrow
      $this->runContractEngine($contract['id'], '+2 days');
      $contract_changed2 = $this->getContract($contract['id']);
      $this->assertNotEquals($contract, $contract_changed2, "This should have changed");
    }
  }

  // update, revive, cancel, pause

}
