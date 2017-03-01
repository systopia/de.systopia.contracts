<?php
//Word replacements
foreach(array(
  array('find' => 'Membership', 'replace' => 'Contract'),
  array('find' => 'membership', 'replace' => 'contract'),
  array('find' => 'member', 'replace' => 'supporter'),
) as $wordReplacement){
  $mes[] = array(
      'entity' => 'WordReplacement',
      'name' => "WordReplacement.{$wordReplacement['find']}>{$wordReplacement['replace']}",
      'params' => array(
        'find_word' => $wordReplacement['find'],
        'replace_word' => $wordReplacement['replace'],
  ));
}

foreach(array(
  array('name' => 'Contract_Signed', 'label' => 'Contract Signed'),
  array('name' => 'Contract_Paused', 'label' => 'Contract Paused'),
  array('name' => 'Contract_Resumed', 'label' => 'Contract Resumed'),
  array('name' => 'Contract_Cancelled', 'label' => 'Contract Cancelled'),
  array('name' => 'Contract_Revived', 'label' => 'Contract Revived'),
  array('name' => 'Contract_Updated', 'label' => 'Contract Updated'),
) as $activityType){
  $mes[] = array(
    'entity' => 'OptionValue',
    'name' => "OptionValue.activityType.{$activityType['name']}",
    'params' => array(
      'name' => $activityType['name'],
      'label' => $activityType['label'],
      'option_group_id' => 'activity_type'
  ));
}

foreach(array(
  array('name' => 'web', 'label' => 'Via Web'),
  array('name' => 'back_office', 'label' => 'Back Office'),
) as $activityType){
  $mes[] = array(
    'entity' => 'OptionValue',
    'name' => "OptionValue.activityType.{$activityType['name']}",
    'params' => array(
      'name' => $activityType['name'],
      'label' => $activityType['label'],
      'option_group_id' => 'encounter_medium'
  ));
}

$mes[] = array(
  'entity' => 'MembershipStatus',
  'name' => "MembershipStatus.Paused",
  'params' => array(
    'name' => 'Paused',
    'label' => 'Paused',
    'is_active' => true,
));


// return $mes;
