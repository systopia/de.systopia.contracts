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
class CRM_Contract_EngineStessTest extends CRM_Contract_ContractTestBase {

  public function setUp() {
    parent::setUp();
  }

  /**
   * Example: Test that a version is returned.
   */
  public function testMultiUpgrade() {
    $ITERATION_COUNT = 11;
    foreach ([1,0] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);
      $scheduled_updates = $this->callAPISuccess('Contract', 'get_open_modification_counts', ['id' => $contract['id']])['values'];
      $this->assertEquals(0, $scheduled_updates['scheduled'], "There should not be a scheduled change");

      // schedule a bunch of updates

      for ($i = 1; $i <= $ITERATION_COUNT; $i++) {
        $update = [
            'membership_payment.membership_annual' => (1 * $i)
        ];
        if (!$is_sepa) {
          // FIXME: if this is not a SEPA contract, we need to pass the bank account
          $update['membership_payment.from_ba'] = $this->getBankAccountID($contract['contact_id']);
        }
        $this->modifyContract($contract['id'], 'update', date('YmdHis', strtotime("now + {$i} minutes")), $update);
      }

      // now... these should all be conflicting
      $scheduled_updates = $this->callAPISuccess('Contract', 'get_open_modification_counts', ['id' => $contract['id']])['values'];
      $this->assertEquals($ITERATION_COUNT, $scheduled_updates['needs_review'] + $scheduled_updates['scheduled'], "There should be {$ITERATION_COUNT} update scheduled.");

      // cheekily set all of the 'needs review' ones to 'scheduled'
      CRM_Core_DAO::executeQuery("UPDATE civicrm_activity SET status_id = 1 WHERE source_record_id = {$contract['id']} AND status_id <> 2;");

      // now... these should all be scheduled
      $scheduled_updates = $this->callAPISuccess('Contract', 'get_open_modification_counts', ['id' => $contract['id']])['values'];
      $this->assertEquals($ITERATION_COUNT, $scheduled_updates['scheduled'], "There should be {$ITERATION_COUNT} update scheduled.");

      // run engine again for tomorrow
      $result = $this->runContractEngine($contract['id'], '+20 days');
      $contract_changed2 = $this->getContract($contract['id']);
      $this->assertNotEquals($contract, $contract_changed2, "This should have changed");
    }
  }
}
