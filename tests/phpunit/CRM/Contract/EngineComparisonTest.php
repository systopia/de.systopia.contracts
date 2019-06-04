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
  public function testCompareCreate() {
    CRM_Contract_Configuration::$use_new_engine = FALSE;

    // run on old engine
    $contract_old = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Signed']);
    $this->assertEquals(1, count($activities_old), "Exactly one signed activity expected!");
    $activity_old = $activities_old[0];
    CRM_Contract_CustomData::labelCustomFields($activity_old);
    CRM_Contract_CustomData::labelCustomFields($contract_old);

    // run the new engine
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
    CRM_Contract_CustomData::labelCustomFields($activity_new);
    CRM_Contract_CustomData::labelCustomFields($contract_new);


    // mend subjects for comparison
    $this->stripActivitySubjectID($activity_new['subject']);
    $this->stripActivitySubjectID($activity_old['subject']);

    // make sure they generate the same data in the fields that are supposed to
    $this->assertArraysEqual($activity_old, $activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba']);
    $this->assertArraysEqual($contract_old, $contract_new, ['membership_type_id','join_date','start_date','end_date','status_id','is_test','is_pay_later','membership_name','membership_general.membership_dialoger','membership_payment.membership_annual','membership_payment.membership_frequency','membership_payment.to_ba','membership_payment.cycle_day','membership_payment.payment_instrument','membership_payment.defer_payment_start']);
  }

  /**
   * Test a simple cancel
   */
  public function testCompareCancel() {
    $cancel_reason = $this->getRandomOptionValue('contract_cancel_reason');

    // run on old engine
    CRM_Contract_Configuration::$use_new_engine = FALSE;
    $contract_old = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $this->modifyContract($contract_old['id'], 'cancel', 'today', [
        'membership_cancellation.membership_cancel_reason' => $cancel_reason,
    ]);
    $activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Cancelled']);
    $this->assertEquals(1, count($activities_old), "Exactly one signed activity expected!");
    $pre_activity_old = $activities_old[0];

    $this->runContractEngine($contract_old['id']);
    $activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Cancelled']);
    $this->assertEquals(1, count($activities_old), "Exactly one signed activity expected!");
    $post_activity_old = $activities_old[0];
    $contract_old_after = $this->getContract($contract_old['id']);
    CRM_Contract_CustomData::labelCustomFields($pre_activity_old);
    CRM_Contract_CustomData::labelCustomFields($post_activity_old);
    CRM_Contract_CustomData::labelCustomFields($contract_old);
    CRM_Contract_CustomData::labelCustomFields($contract_old_after);

    // run the new engine
    CRM_Contract_Configuration::$use_new_engine = TRUE;
    $contract_new = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $this->modifyContract($contract_new['id'], 'cancel', 'today', [
        'membership_cancellation.membership_cancel_reason' => $cancel_reason,
        ]);
    $activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Cancelled']);
    $this->assertEquals(1, count($activities_new), "Exactly one signed activity expected!");
    $pre_activity_new = $activities_new[0];

    $this->runContractEngine($contract_new['id']);
    $activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Cancelled']);
    $this->assertEquals(1, count($activities_new), "Exactly one signed activity expected!");
    $post_activity_new = $activities_new[0];
    $contract_new_after = $this->getContract($contract_new['id']);
    CRM_Contract_CustomData::labelCustomFields($pre_activity_new);
    CRM_Contract_CustomData::labelCustomFields($post_activity_new);
    CRM_Contract_CustomData::labelCustomFields($contract_new);
    CRM_Contract_CustomData::labelCustomFields($contract_new_after);

    // mend subject for comparison
    $this->stripActivitySubjectID($pre_activity_new['subject']);
    $this->stripActivitySubjectID($post_activity_new['subject']);
    $this->stripActivitySubjectID($pre_activity_old['subject']);
    $this->stripActivitySubjectID($post_activity_old['subject']);

    // make sure they generate the same data in the fields that are supposed to
    $this->assertArraysEqual($pre_activity_old, $pre_activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba'], 'Change Activity before execution');
    $this->assertArraysEqual($post_activity_old, $post_activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba'], 'Change Activity after execution');
    $this->assertArraysEqual($contract_old, $contract_new, ['membership_type_id','join_date','start_date','end_date','status_id','is_test','is_pay_later','membership_name','membership_general.membership_dialoger','membership_payment.membership_annual','membership_payment.membership_frequency','membership_payment.to_ba','membership_payment.cycle_day','membership_payment.payment_instrument','membership_payment.defer_payment_start'], [], 'Contract after execution');
    $this->assertArraysEqual($contract_old_after, $contract_new_after, ['membership_type_id','join_date','start_date','end_date','status_id','is_test','is_pay_later','membership_name','membership_general.membership_dialoger','membership_payment.membership_annual','membership_payment.membership_frequency','membership_payment.to_ba','membership_payment.cycle_day','membership_payment.payment_instrument','membership_payment.defer_payment_start'], [], 'Contract after execution');
  }


  /**
   * Test a simple upgrade
   */
  public function testCompareUpgrade() {
    // run on old engine
    CRM_Contract_Configuration::$use_new_engine = FALSE;
    $contract_old = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $this->modifyContract($contract_old['id'], 'update', 'today', [
        'membership_payment.membership_annual'    => '240.00',
        'membership_payment.membership_frequency' => 2
    ]);
    $activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Updated']);
    $this->assertEquals(1, count($activities_old), "Exactly one update activity expected!");
    $pre_activity_old = $activities_old[0];

    $this->runContractEngine($contract_old['id']);
    $activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Updated']);
    $this->assertEquals(1, count($activities_old), "Exactly one update activity expected!");
    $post_activity_old = $activities_old[0];
    $contract_old_after = $this->getContract($contract_old['id']);
    CRM_Contract_CustomData::labelCustomFields($pre_activity_old);
    CRM_Contract_CustomData::labelCustomFields($post_activity_old);
    CRM_Contract_CustomData::labelCustomFields($contract_old);
    CRM_Contract_CustomData::labelCustomFields($contract_old_after);

    // run the new engine
    CRM_Contract_Configuration::$use_new_engine = TRUE;
    $contract_new = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $this->modifyContract($contract_new['id'], 'update', 'today', [
        'membership_payment.membership_annual'    => '240.00',
        'membership_payment.membership_frequency' => 2
    ]);
    $activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Updated']);
    $this->assertEquals(1, count($activities_new), "Exactly one update activity expected!");
    $pre_activity_new = $activities_new[0];

    $this->runContractEngine($contract_new['id']);
    $activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Updated']);
    $this->assertEquals(1, count($activities_new), "Exactly one update activity expected!");
    $post_activity_new = $activities_new[0];
    $contract_new_after = $this->getContract($contract_new['id']);
    CRM_Contract_CustomData::labelCustomFields($pre_activity_new);
    CRM_Contract_CustomData::labelCustomFields($post_activity_new);
    CRM_Contract_CustomData::labelCustomFields($contract_new);
    CRM_Contract_CustomData::labelCustomFields($contract_new_after);

    // mend subjects for comparison
    $this->stripActivitySubjectID($pre_activity_new['subject']);
    $this->stripActivitySubjectID($post_activity_new['subject']);
    $this->stripActivitySubjectID($pre_activity_old['subject']);
    $this->stripActivitySubjectID($post_activity_old['subject']);

    // make sure they generate the same data in the fields that are supposed to
    $this->assertArraysEqual($pre_activity_old, $pre_activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba'], 'Change Activity before execution');
    $this->assertArraysEqual($post_activity_old, $post_activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba'], 'Change Activity after execution');
    $this->assertArraysEqual($contract_old, $contract_new, ['membership_type_id','join_date','start_date','end_date','status_id','is_test','is_pay_later','membership_name','membership_general.membership_dialoger','membership_payment.membership_annual','membership_payment.membership_frequency','membership_payment.to_ba','membership_payment.cycle_day','membership_payment.payment_instrument','membership_payment.defer_payment_start'], [], 'Contract after execution');
    $this->assertArraysEqual($contract_old_after, $contract_new_after, ['membership_type_id','join_date','start_date','end_date','status_id','is_test','is_pay_later','membership_name','membership_general.membership_dialoger','membership_payment.membership_annual','membership_payment.membership_frequency','membership_payment.to_ba','membership_payment.cycle_day','membership_payment.payment_instrument','membership_payment.defer_payment_start'], [], 'Contract after execution');
  }

  /**
   * Test a cancel / revive workflow
   */
  public function testCompareRevive() {
    $cancel_reason = $this->getRandomOptionValue('contract_cancel_reason');

    // run on old engine
    CRM_Contract_Configuration::$use_new_engine = FALSE;
    $contract_old = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $this->modifyContract($contract_old['id'], 'cancel', 'today', [
        'membership_cancellation.membership_cancel_reason' => $cancel_reason,
    ]);
    $this->runContractEngine($contract_old['id']);

    $this->modifyContract($contract_old['id'], 'revive', 'today', [
        'membership_payment.membership_annual'    => '240.00',
        'membership_payment.membership_frequency' => 2
    ]);
    $activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Revived']);
    $this->assertEquals(1, count($activities_old), "Exactly one revive activity expected!");
    $pre_activity_old = $activities_old[0];

    $this->runContractEngine($contract_old['id']);
    $activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Revived']);
    $this->assertEquals(1, count($activities_old), "Exactly one revive activity expected!");
    $post_activity_old = $activities_old[0];
    $contract_old_after = $this->getContract($contract_old['id']);
    CRM_Contract_CustomData::labelCustomFields($pre_activity_old);
    CRM_Contract_CustomData::labelCustomFields($post_activity_old);
    CRM_Contract_CustomData::labelCustomFields($contract_old);
    CRM_Contract_CustomData::labelCustomFields($contract_old_after);

    // run the new engine
    CRM_Contract_Configuration::$use_new_engine = TRUE;
    $contract_new = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $this->modifyContract($contract_new['id'], 'cancel', 'today', [
        'membership_cancellation.membership_cancel_reason' => $cancel_reason,
    ]);
    $this->runContractEngine($contract_new['id']);

    $this->modifyContract($contract_new['id'], 'revive', 'today', [
        'membership_payment.membership_annual'    => '240.00',
        'membership_payment.membership_frequency' => 2
    ]);
    $activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Revived']);
    $this->assertEquals(1, count($activities_new), "Exactly one revive activity expected!");
    $pre_activity_new = $activities_new[0];

    $this->runContractEngine($contract_new['id']);
    $activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Revived']);
    $this->assertEquals(1, count($activities_new), "Exactly one revive activity expected!");
    $post_activity_new = $activities_new[0];
    $contract_new_after = $this->getContract($contract_new['id']);
    CRM_Contract_CustomData::labelCustomFields($pre_activity_new);
    CRM_Contract_CustomData::labelCustomFields($post_activity_new);
    CRM_Contract_CustomData::labelCustomFields($contract_new);
    CRM_Contract_CustomData::labelCustomFields($contract_new_after);

    // mend subjects for comparison
    $this->stripActivitySubjectID($pre_activity_new['subject']);
    $this->stripActivitySubjectID($post_activity_new['subject']);
    $this->stripActivitySubjectID($pre_activity_old['subject']);
    $this->stripActivitySubjectID($post_activity_old['subject']);

    // make sure they generate the same data in the fields that are supposed to
    $this->assertArraysEqual($pre_activity_old, $pre_activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba'], 'Change Activity before execution');
    $this->assertArraysEqual($post_activity_old, $post_activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba'], 'Change Activity after execution');
    $this->assertArraysEqual($contract_old, $contract_new, ['membership_type_id','join_date','start_date','end_date','status_id','is_test','is_pay_later','membership_name','membership_general.membership_dialoger','membership_payment.membership_annual','membership_payment.membership_frequency','membership_payment.to_ba','membership_payment.cycle_day','membership_payment.payment_instrument','membership_payment.defer_payment_start'], [], 'Contract after execution');
    $this->assertArraysEqual($contract_old_after, $contract_new_after, ['membership_type_id','join_date','start_date','end_date','status_id','is_test','is_pay_later','membership_name','membership_general.membership_dialoger','membership_payment.membership_annual','membership_payment.membership_frequency','membership_payment.to_ba','membership_payment.cycle_day','membership_payment.payment_instrument','membership_payment.defer_payment_start'], [], 'Contract after execution');
  }



  /**
   * Test the pause / resume workflow
   */
  public function testComparePause() {
    // run on old engine
    CRM_Contract_Configuration::$use_new_engine = FALSE;
    $contract_old = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $this->modifyContract($contract_old['id'], 'pause', 'today', [
        'resume_date' => date('Y-m-d', strtotime('tomorrow')),
    ]);
    $pause_activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Paused']);
    $this->assertEquals(1, count($pause_activities_old), "Exactly one pause activity expected!");
    $pre_pause_activity_old = $pause_activities_old[0];
    $resume_activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Resumed']);
    $this->assertEquals(1, count($resume_activities_old), "Exactly one resume activity expected!");
    $pre_resume_activity_old = $resume_activities_old[0];

    // execute the pause change
    $this->runContractEngine($contract_old['id']);
    $pause_activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Paused']);
    $this->assertEquals(1, count($pause_activities_old), "Exactly one pause activity expected!");
    $post_pause_activity_old = $pause_activities_old[0];
    $paused_contract_old = $this->assertAPI3('Contract', 'getsingle', ['id' => $contract_old['id']]);

    // execute the resume change
    $this->runContractEngine($contract_old['id'], '+2 day');
    $pause_activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Resumed']);
    $this->assertEquals(1, count($pause_activities_old), "Exactly one resume activity expected!");
    $post_resume_activity_old = $pause_activities_old[0];
    $resumed_contract_old = $this->assertAPI3('Contract', 'getsingle', ['id' => $contract_old['id']]);

    CRM_Contract_CustomData::labelCustomFields($pre_pause_activity_old);
    CRM_Contract_CustomData::labelCustomFields($post_pause_activity_old);
    CRM_Contract_CustomData::labelCustomFields($pre_resume_activity_old);
    CRM_Contract_CustomData::labelCustomFields($post_resume_activity_old);
    CRM_Contract_CustomData::labelCustomFields($contract_old);
    CRM_Contract_CustomData::labelCustomFields($paused_contract_old);
    CRM_Contract_CustomData::labelCustomFields($resumed_contract_old);


    // run the new engine
    CRM_Contract_Configuration::$use_new_engine = TRUE;
    $contract_new = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $this->modifyContract($contract_new['id'], 'pause', 'today', [
        'resume_date' => date('Y-m-d', strtotime('tomorrow')),
    ]);
    $pause_activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Paused']);
    $this->assertEquals(1, count($pause_activities_new), "Exactly one pause activity expected!");
    $pre_pause_activity_new = $pause_activities_new[0];
    $resume_activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Resumed']);
    $this->assertEquals(1, count($resume_activities_new), "Exactly one resume activity expected!");
    $pre_resume_activity_new = $resume_activities_new[0];


    // execute the pause change
    $this->runContractEngine($contract_new['id']);
    $pause_activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Paused']);
    $this->assertEquals(1, count($pause_activities_new), "Exactly one pause activity expected!");
    $post_pause_activity_new = $pause_activities_new[0];
    $paused_contract_new = $this->assertAPI3('Contract', 'getsingle', ['id' => $contract_new['id']]);

    // execute the resume change
    $this->runContractEngine($contract_new['id'], '+2 day');
    $pause_activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Resumed']);
    $this->assertEquals(1, count($pause_activities_new), "Exactly one resume activity expected!");
    $post_resume_activity_new = $pause_activities_new[0];
    $resumed_contract_new = $this->assertAPI3('Contract', 'getsingle', ['id' => $contract_new['id']]);

    CRM_Contract_CustomData::labelCustomFields($pre_pause_activity_new);
    CRM_Contract_CustomData::labelCustomFields($post_pause_activity_new);
    CRM_Contract_CustomData::labelCustomFields($pre_resume_activity_new);
    CRM_Contract_CustomData::labelCustomFields($post_resume_activity_new);
    CRM_Contract_CustomData::labelCustomFields($contract_new);
    CRM_Contract_CustomData::labelCustomFields($paused_contract_new);
    CRM_Contract_CustomData::labelCustomFields($resumed_contract_new);


    // mend subjects for comparison
    $this->stripActivitySubjectID($pre_pause_activity_old['subject']);
    $this->stripActivitySubjectID($post_pause_activity_old['subject']);
    $this->stripActivitySubjectID($pre_resume_activity_old['subject']);
    $this->stripActivitySubjectID($post_resume_activity_old['subject']);
    $this->stripActivitySubjectID($pre_pause_activity_new['subject']);
    $this->stripActivitySubjectID($post_pause_activity_new['subject']);
    $this->stripActivitySubjectID($pre_resume_activity_new['subject']);
    $this->stripActivitySubjectID($post_resume_activity_new['subject']);

    // make sure they generate the same data in the fields that are supposed to
    $this->assertArraysEqual($pre_pause_activity_old, $pre_pause_activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba'], 'Pause Activity before execution');
    $this->assertArraysEqual($post_pause_activity_old, $post_pause_activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba'], 'Pause Activity after execution');
    $this->assertArraysEqual($pre_resume_activity_old, $pre_resume_activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba'], 'Resume Activity before execution');
    $this->assertArraysEqual($post_resume_activity_old, $post_resume_activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba'], 'Resume Activity after execution');
    $this->assertArraysEqual($contract_old, $contract_new, ['membership_type_id','join_date','start_date','end_date','status_id','is_test','is_pay_later','membership_name','membership_general.membership_dialoger','membership_payment.membership_annual','membership_payment.membership_frequency','membership_payment.to_ba','membership_payment.cycle_day','membership_payment.payment_instrument','membership_payment.defer_payment_start'], [], 'Contract after execution');
    $this->assertArraysEqual($paused_contract_old, $paused_contract_new, ['membership_type_id','join_date','start_date','end_date','status_id','is_test','is_pay_later','membership_name','membership_general.membership_dialoger','membership_payment.membership_annual','membership_payment.membership_frequency','membership_payment.to_ba','membership_payment.cycle_day','membership_payment.payment_instrument','membership_payment.defer_payment_start'], [], 'Contract after pausing');
    $this->assertArraysEqual($resumed_contract_old, $resumed_contract_new, ['membership_type_id','join_date','start_date','end_date','status_id','is_test','is_pay_later','membership_name','membership_general.membership_dialoger','membership_payment.membership_annual','membership_payment.membership_frequency','membership_payment.to_ba','membership_payment.cycle_day','membership_payment.payment_instrument','membership_payment.defer_payment_start'], [], 'Contract after resume');
  }



  /**
   * Test a pause / resume workflow
   */
  public function _testCompareResume() {
    $cancel_reason = $this->getRandomOptionValue('contract_cancel_reason');

    // run on old engine
    CRM_Contract_Configuration::$use_new_engine = FALSE;
    $contract_old = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $this->modifyContract($contract_old['id'], 'cancel', 'today', [
        'membership_cancellation.membership_cancel_reason' => $cancel_reason,
    ]);
    $this->runContractEngine($contract_old['id']);

    $this->modifyContract($contract_old['id'], 'revive', 'today', [
        'membership_payment.membership_annual'    => '240.00',
        'membership_payment.membership_frequency' => 2
    ]);
    $activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Revived']);
    $this->assertEquals(1, count($activities_old), "Exactly one revive activity expected!");
    $pre_activity_old = $activities_old[0];

    $this->runContractEngine($contract_old['id']);
    $activities_old = $this->getChangeActivities($contract_old['id'], ['Contract_Revived']);
    $this->assertEquals(1, count($activities_old), "Exactly one revive activity expected!");
    $post_activity_old = $activities_old[0];
    CRM_Contract_CustomData::labelCustomFields($pre_activity_old);
    CRM_Contract_CustomData::labelCustomFields($post_activity_old);
    CRM_Contract_CustomData::labelCustomFields($contract_old);

    // run the new engine
    CRM_Contract_Configuration::$use_new_engine = TRUE;
    $contract_new = $this->createNewContract([
        'is_sepa'            => 1,
        'amount'             => '10.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
    ]);
    $this->modifyContract($contract_new['id'], 'cancel', 'today', [
        'membership_cancellation.membership_cancel_reason' => $cancel_reason,
    ]);
    $this->runContractEngine($contract_new['id']);

    $this->modifyContract($contract_new['id'], 'revive', 'today', [
        'membership_payment.membership_annual'    => '240.00',
        'membership_payment.membership_frequency' => 2
    ]);
    $activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Revived']);
    $this->assertEquals(1, count($activities_new), "Exactly one revive activity expected!");
    $pre_activity_new = $activities_new[0];

    $this->runContractEngine($contract_new['id']);
    $activities_new = $this->getChangeActivities($contract_new['id'], ['Contract_Revived']);
    $this->assertEquals(1, count($activities_new), "Exactly one revive activity expected!");
    $post_activity_new = $activities_new[0];

    CRM_Contract_CustomData::labelCustomFields($pre_activity_new);
    CRM_Contract_CustomData::labelCustomFields($post_activity_new);
    CRM_Contract_CustomData::labelCustomFields($contract_new);

    // mend subjects for comparison
    $this->stripActivitySubjectID($pre_activity_new['subject']);
    $this->stripActivitySubjectID($post_activity_new['subject']);
    $this->stripActivitySubjectID($pre_activity_old['subject']);
    $this->stripActivitySubjectID($post_activity_old['subject']);

    // make sure they generate the same data in the fields that are supposed to
    $this->assertArraysEqual($pre_activity_old, $pre_activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba'], 'Change Activity before execution');
    $this->assertArraysEqual($post_activity_old, $post_activity_new, NULL, ['id', 'source_record_id', 'activity_date_time', 'details', 'created_date', 'contract_updates.ch_recurring_contribution', 'modified_date', 'contract_updates.ch_from_ba'], 'Change Activity after execution');
    $this->assertArraysEqual($contract_old, $contract_new, ['membership_type_id','join_date','start_date','end_date','status_id','is_test','is_pay_later','membership_name','membership_general.membership_dialoger','membership_payment.membership_annual','membership_payment.membership_frequency','membership_payment.to_ba','membership_payment.cycle_day','membership_payment.payment_instrument','membership_payment.defer_payment_start'], [], 'Contract after execution');
  }

}
