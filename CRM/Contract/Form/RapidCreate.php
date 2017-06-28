<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Form_RapidCreate extends CRM_Core_Form{
  function buildQuickForm(){

    // ### Contact information ###
    $this->addEntityRef('prefix_id', ts('Prefix'), [
      'entity' => 'option_value',
      'api' => array(
        'params' => array('option_group_id' => 'individual_prefix'),
      ),
      'placeholder' => ts('- Select -')
    ], null, true);

    $this->add('text', 'first_name', 'First name');
    $this->add('text', 'last_name', 'Last name');
    $this->add('text', 'phone', 'Phone');
    $this->add('text', 'email', 'Email');
    $this->add('text', 'street_address', 'Address');
    $this->add('text', 'postal_code', 'Postcode');
    $this->add('text', 'city', 'City');
    $this->addDate('birth_date', 'Date of birth');

    $this->addCheckbox('community_newsletter', 'Add to Community newsletter', ['' => true]);
    $this->addEntityRef('groups', ts('Additional groups'), array(
      'entity' => 'group',
      'multiple' => true,
    ));

    $this->addCheckbox('post_delivery_only_online', 'Post delivery only online', ['' => true]);
    $this->add('text', 'interest', 'Interest');
    $this->add('text', 't_shirt_size', 'T-shirt size');
    $this->add('text', 'talk_topic', 'Talk topic');


    // ### Mandate information ###

    $this->add('select', 'cycle_day', ts('Cycle day'), CRM_Contract_SepaLogic::getCycleDays());
    $this->add('text',   'iban', ts('IBAN'), array('class' => 'huge'));
    $this->add('text',   'bic', ts('BIC'));
    $this->add('text',   'payment_amount', ts('Annual amount'), array('size' => 6));
    $this->add('select', 'payment_frequency', ts('Payment Frequency'), CRM_Contract_SepaLogic::getPaymentFrequencies());
    $this->assign('bic_lookup_accessible', CRM_Contract_SepaLogic::isLittleBicExtensionAccessible());

    // ### Contract information ###
    $this->addDate('join_date', ts('Member since'), TRUE, array('formatType' => 'activityDate'));
    $this->addDate('start_date', ts('Membership start date'), TRUE, array('formatType' => 'activityDate'));
    $this->addEntityRef('campaign_id', ts('Campaign'), [
      'entity' => 'campaign',
      'placeholder' => ts('- none -')
    ]);
    // Membership type (membership)
    foreach(civicrm_api3('MembershipType', 'get', [])['values'] as $MembershipType){
      $MembershipTypeOptions[$MembershipType['id']] = $MembershipType['name'];
    };
    $this->add('select', 'membership_type_id', ts('Membership type'), array('' => '- none -') + $MembershipTypeOptions, true, array('class' => 'crm-select2'));
    // Source media (activity)
    foreach(civicrm_api3('Activity', 'getoptions', ['field' => "activity_medium_id"])['values'] as $key => $value){
      $mediumOptions[$key] = $value;
    }
    $this->add('select', 'activity_medium', ts('Source media'), array('' => '- none -') + $mediumOptions, false, array('class' => 'crm-select2'));
    // Reference number text
    $this->add('text', 'membership_reference', ts('Reference number'));
    // Contract number text
    $this->add('text', 'membership_contract', ts('Contract number'));
    // DD-Fundraiser
    $this->addEntityRef('membership_dialoger', ts('DD-Fundraiser'), array('api' => array('params' => array('contact_type' => 'Individual'))));
    // Membership channel
    foreach(civicrm_api3('OptionValue', 'get', ['option_group_id' => "campaign_subtype"])['values'] as $optionValue){
      $membershipChannelOptions[$optionValue['value']] = $optionValue['label'];
    };
    $this->add('select', 'membership_channel', ts('Membership channel'), array('' => '- none -') + $membershipChannelOptions, false, array('class' => 'crm-select2'));

    // Notes
    $this->addWysiwyg('activity_details', ts('Notes'), []);


    $this->addButtons([
      array('type' => 'cancel', 'name' => 'Cancel'),
      array('type' => 'submit', 'name' => 'Create')
    ]);

    $this->setDefaults();

  }

  /**
   * form validation
   */
  function validate() {
    $submitted = $this->exportValues();

    if($submitted['payment_amount'] && !$submitted['payment_frequency']){
      HTML_QuickForm::setElementError ( 'payment_frequency', 'Please specify a frequency when specifying an amount');
    }
    if($submitted['payment_frequency'] && !$submitted['payment_amount']){
      HTML_QuickForm::setElementError ( 'payment_amount', 'Please specify an amount when specifying a frequency');
    }

    $amount = CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount'] / $submitted['payment_frequency']);
    if ($amount < 0.01) {
      HTML_QuickForm::setElementError ( 'payment_amount', 'Annual amount too small.');
    }

    // SEPA validation
    if (!empty($submitted['iban']) && !CRM_Contract_SepaLogic::validateIBAN($submitted['iban'])) {
      echo 'i am hgere';
      HTML_QuickForm::setElementError ( 'iban', 'Please enter a valid IBAN');
    }
    if (!empty($submitted['bic']) && !CRM_Contract_SepaLogic::validateBIC($submitted['bic'])) {
      HTML_QuickForm::setElementError ( 'bic', 'Please enter a valid BIC');
    }

    return parent::validate();
  }


  function setDefaults($defaultValues = null, $filter = null){

    list($defaults['join_date'], $null) = CRM_Utils_Date::setDateDefaults(NULL, 'activityDateTime');
    list($defaults['start_date'], $null) = CRM_Utils_Date::setDateDefaults(NULL, 'activityDateTime');

    // sepa defaults
    $defaults['payment_frequency'] = '12'; // monthly
    $defaults['payment_option'] = 'create';
    $defaults['cycle_day'] = CRM_Contract_SepaLogic::nextCycleDay();

    parent::setDefaults($defaults);
  }

  function postProcess(){
    $submitted = $this->exportValues();

    // Create contact
    $contactParams['prefix_id'] = $submitted['prefix_id'];
    $contactParams['first_name'] = $submitted['first_name'];
    $contactParams['last_name'] = $submitted['last_name'];
    $contactParams['street_address'] = $submitted['street_address'];
    $contactParams['postal_code'] = $submitted['postal_code'];
    $contactParams['birth_date'] = $submitted['birth_date'];
    $contactParams['contact_type'] = 'Individual';
    $contact = civicrm_api3('Contact', 'create', $contactParams);

    civicrm_api3('Email', 'create', ['contact_id' => $contact['id'], 'email' => $submitted['email']]);
    civicrm_api3('Phone', 'create', [
      'contact_id' => $contact['id'],
      'phone' => $submitted['phone'],
      'phone_type_id' => 'phone',
      'phone_location_id' => 'home'
    ]);

    if($submitted['groups']){
      foreach(explode(',', $submitted['groups']) as $groupId){
        civicrm_api3('GroupContact', 'create', [
          'contact_id' => $contact['id'],
          'group_id' => $groupId]
        );
      }
    }

    if(isset($submitted['community_newsletter'])){
      $newsletter = civicrm_api3('Group', 'get', [ 'name' => ['LIKE' => "community_nl%"]]);
      civicrm_api3('GroupContact', 'create', [
        'contact_id' => $contact['id'],
        'group_id' => $newsletter['id']]
      );
    }

    // Create mandate
    if ($submitted['cycle_day'] < 1 || $submitted['cycle_day'] > 30) {
      // invalid cycle day
      $submitted['cycle_day'] = CRM_Contract_SepaLogic::nextCycleDay();
    }

    // calculate amount
    $annual_amount = CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount']);
    $frequency_interval = 12 / $submitted['payment_frequency'];
    $amount = CRM_Contract_SepaLogic::formatMoney($annual_amount / $submitted['payment_frequency']);

    $new_mandate = CRM_Contract_SepaLogic::createNewMandate(array(
      'type'               => 'RCUR',
      'contact_id'         => $contact['id'],
      'amount'             => $amount,
      'currency'           => 'EUR',
      'start_date'         => CRM_Utils_Date::processDate($submitted['start_date'], null, null, 'Y-m-d H:i:s'),
      'creation_date'      => date('YmdHis'), // NOW
      'date'               => CRM_Utils_Date::processDate($submitted['start_date'], null, null, 'Y-m-d H:i:s'),
      'validation_date'    => date('YmdHis'), // NOW
      'iban'               => $submitted['iban'],
      'bic'                => $submitted['bic'],
      // 'source'             => ??
      'campaign_id'        => $submitted['campaign_id'],
      'financial_type_id'  => 2, // Membership Dues
      'frequency_unit'     => 'month',
      'cycle_day'          => $submitted['cycle_day'],
      'frequency_interval' => $frequency_interval,
    ));
    $contractParams['membership_payment.membership_recurring_contribution'] = $new_mandate['entity_id'];
    $contractParams['membership_general.membership_dialoger'] = $submitted['membership_dialoger']; // DD fundraiser

    $contractParams['contact_id'] = $contact['id'];
    $contractParams['membership_type_id'] = $submitted['membership_type_id'];
    $contractParams['start_date'] = CRM_Utils_Date::processDate($submitted['start_date'], null, null, 'Y-m-d H:i:s');
    $contractParams['join_date'] = CRM_Utils_Date::processDate($submitted['join_date'], null, null, 'Y-m-d H:i:s');

    $contractParams['campaign_id'] = $submitted['campaign_id'];

    // 'Custom' fields
    $contractParams['membership_general.membership_reference'] = $submitted['membership_reference']; // Reference number
    $contractParams['membership_general.membership_contract'] = $submitted['membership_contract']; // Contract number
    $contractParams['membership_general.membership_dialoger'] = $submitted['membership_dialoger']; // DD fundraiser

    $contractParams['note'] = $submitted['activity_details']; // Membership channel
    $contractParams['medium_id'] = $submitted['activity_medium']; // Membership channel

    civicrm_api3('Contract', 'create', $contractParams);

    $this->controller->_destination = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact['id']}");

  }

}
