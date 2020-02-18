<?php

use CRM_Contract_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

include_once 'ContractTestBase.php';

/**
 * Compatibility Tests: Make sure the the engine refactoring
 *  is still compatible with the old behaviour where wanted
 *
 * @group headless
 */
class CRM_Contract_CompatibilityTest extends CRM_Contract_ContractTestBase {

  public function setUp() {
    parent::setUp();
    $this->setActivityFlavour('GP');
  }

  /**
   * Check if the subject of the change activity is as requested
   *
   * @see https://redmine.greenpeace.at/issues/1276#note-74
   */
  public function testUpdateActivitySubject() {
    // create a new contract
    $contract = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '12.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
        'cycle_day'          => 25,
        'iban'               => 'DE89370400440532013000',
        'bic'                => 'GENODEM1GLS',
    ]);

    // modify contract
    $this->modifyContract($contract['id'], 'update', 'tomorrow', [
        'membership_payment.membership_annual' => '168.00',
        'membership_payment.cycle_day'         => '3',
    ]);

    // get the resulting change activity
    $this->runContractEngine($contract['id'], '+2 days');
    $change_activity = $this->getLastChangeActivity($contract['id']);
    $this->assertNotEmpty($change_activity, "There should be a change activity after the upgrade");
    $this->assertContains("cycle day 25 to 3", $change_activity['subject'], "Activity subject should contain the changed cycle day");
    $this->assertContains("amt. 144.00 to 168.00", $change_activity['subject'], "Activity subject should contain the changed amount");
    $this->assertNotContains("DE89370400440532013000", $change_activity['subject'], "Activity subject should NOT contain the unchanged IBAN");
    $this->assertNotContains("freq. 12 to 12", $change_activity['subject'], "Activity subject should NOT contain the unchanged frequency");
  }

}
