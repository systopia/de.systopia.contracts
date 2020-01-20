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
class CRM_Contract_BasicEngineTest extends CRM_Contract_ContractTestBase {

  public function setUp() {
    parent::setUp();
  }

  /**
   * Test a simple create
   */
  public function testSimpleCreate() {
    foreach ([0,1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract([
          'is_sepa'            => $is_sepa,
          'amount'             => '10.00',
          'frequency_unit'     => 'month',
          'frequency_interval' => '1',
      ]);

      // annual amount
      $this->assertEquals('120.00', $contract['membership_payment.membership_annual']);
      $this->assertEquals('2', $contract['status_id']);
      $this->assertNotEmpty($contract['membership_payment.membership_recurring_contribution']);
      $this->assertNotEmpty($contract['membership_payment.cycle_day']);
    }
  }

  /**
   * Test a simple cancellation
   */
  public function testSimpleCancel() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'cancel', 'tomorrow', [
          'membership_cancellation.membership_cancel_reason' => 'Unknown'
      ]);

      // run engine see if anything changed
      $this->runContractEngine($contract['id']);

      // things should not have changed
      $contract_changed1 = $this->getContract($contract['id']);
      $this->assertEquals($contract, $contract_changed1, "This shouldn't have changed");

      // run engine again for tomorrow
      $this->runContractEngine($contract['id'], '+2 days');
      $contract_changed2 = $this->getContract($contract['id']);
      $this->assertNotEquals($contract, $contract_changed2, "This should have changed");

      // make sure status is cancelled
      $this->assertEquals($this->getMembershipStatusID('Cancelled'), $contract_changed2['status_id'], "The contract wasn't cancelled");
    }
  }

  /**
   * Test a simple upgrade
   */
  public function testSimpleUpgrade() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'update', 'tomorrow', [
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

      // make sure status is current
      $this->assertEquals($this->getMembershipStatusID('Current'), $contract_changed2['status_id'], "The contract isn't active");
      $this->assertEquals(240.00, $contract_changed2['membership_payment.membership_annual'], "The contract has the wrong amount");
    }
  }

  /**
   * Test a simple pause/resume
   */
  public function testSimplePauseResume() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'pause', 'tomorrow');
      $changes = $this->callAPISuccess('Activity', 'get', ['source_record_id' => $contract['id']]);


      // run engine see if anything changed
      $this->runContractEngine($contract['id']);

      // things should not have changed
      $contract_changed1 = $this->getContract($contract['id']);
      $this->assertEquals($contract, $contract_changed1, "This shouldn't have changed");

      // run engine again for tomorrow
      $this->runContractEngine($contract['id'], '+1 day');
      $contract_changed2 = $this->getContract($contract['id']);
      $this->assertEquals($this->getMembershipStatusID('Paused'), $contract_changed2['status_id'], "The contract isn't paused");
    }
  }

  /**
   * Test a simple revive
   */
  public function testSimpleRevive() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'cancel', 'tomorrow', [
          'membership_cancellation.membership_cancel_reason' => 'Unknown'
      ]);

      // run engine again for tomorrow
      $this->runContractEngine($contract['id'], '+1 days');
      $contract_cancelled = $this->getContract($contract['id']);
      $this->assertNotEquals($contract, $contract_cancelled, "This should have changed");

      // make sure status is cancelled
      $this->assertEquals($this->getMembershipStatusID('Cancelled'), $contract_cancelled['status_id'], "The contract wasn't cancelled");

      // now: revive contract
      $this->modifyContract($contract['id'], 'revive', '+2 days', [
          'membership_payment.membership_annual'             => '240.00',
          'membership_cancellation.membership_cancel_reason' => $this->getRandomOptionValue('contract_cancel_reason')]);

      $this->runContractEngine($contract['id'], '+2 days');
      $contract_revived = $this->getContract($contract['id']);
      $this->assertNotEquals($contract_cancelled, $contract_revived, "This should have changed");

      // make sure status is cancelled
      $this->assertEquals($this->getMembershipStatusID('Current'), $contract_revived['status_id'], "The contract wasn't revived");
      $this->assertEquals(240.00, $contract_revived['membership_payment.membership_annual'], "The contract has the wrong amount");
    }
  }

}
