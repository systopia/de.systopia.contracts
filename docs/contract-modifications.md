# Contract modifications

The contract extension limits modifications that can be made to contracts to those and provides a UI and API to facilitate these modifications.

It keeps a history of significant changes (or modifications) made to contracts and allows for modifications to be scheduled into the future.

The extension specifies 6 different types of modification:

* Sign - when a contract is created
* Update - when significant updates (as defined by the contract extension) are made to a contract
* Pause - when a contract status changes from current to paused
* Resume - when a contract status changes from paused to current.
* Cancel - when a contract status changes from current to cancelled
* Revive - when a contract status changes from cancelled to current. This change may be accompanied by the same significant updates that are defined in the update modification

The extension replaces the default create membership form with a Create contract form.

It provides additional Update, Pause, Cancel and Revive forms to that can be used to modify contracts.

The edit membership form is left in place though extra validation occurs on this form to ensure that modifications made using this form fit the contract model.

## Significant changes

Significant changes those in which one of the following contract fields is updated

* status
* membership type
* recurring membership payment

## Modifying contracts with the API

The contract.modify is designed for contract modifications. These modifications can happen immediately or be scheduled into the future.

The contract.modify API is a wrapper around the activity.create API. It creates scheduled contract modification activities which, depending on the activity date will either be executed now or in the future.

Contract.modify takes the following parameters (* = required).

* id* (of the contract)
* action* - the type of action
* date - when you want the action to be carried out
* note - to store notes about the update
* medium_id - the contact medium for this update

Then depending on what action you are calling, you can pass other parameters.

If you are calling update or revive, you can pass

* membership_type_id
* membership_payment.membership_recurring_contribution
* campaign_id
* membership_payment.membership_recurring_contribution'
* membership_cancellation.membership_cancel_reason
* membership_payment.membership_annual
* membership_payment.membership_frequency
* membership_payment.cycle_day
* membership_payment.to_ba


If you are calling cancel, you must pass
* membership_cancellation.membership_cancel_reason (a cancel reason)

If you are calling pause, you must pass
* resume_data - this will schedule a contract resume activity for that date.

A note on passing dates to the contract API

* a call to contract modify without a date is processed immediately
* a call to contract.modify with a date in the future will be scheduled
* a call to contract.modify with a date in the past will raise an exception

## Modifying a contract directly

Contracts can also be modified by updating the contract directly (for example, via the Contract API or via the contract edit form). When an attempt is made to modified a contract directly, the contract extension checks to ensure that the modification is allowed by the contract extension. If it is valid, the update is made and a *completed* contract modification activity is recorded for the contract.

Note that if a contract is updated and no significant changes are made, then no contract modification activity will be recorded.

## Multiple scheduled modifications

Whenever a scheduled modification is created or edited, we check to see how many scheduled modifications are present.

If there is more than one scheduled modification, we set the status of each  activity to needs review.

If a user attempts to modify (or schedule a modification) to a contract via the UI, and one or more modifications are already scheduled, a warning message will appear alerting the user that there are modifications are already scheduled.

If multiple modifications have been created and a user has not had the chance to acknowledge the warning (for example if it is been created via the API), then contract will be marked for review (how we implement the mark this is TBC - could be either with a scheduled review contract modifications activity or with a tag).

## Failed modifications

Note that one cannot create a failed modification via the UI as it will be caught by the form validation.

One can schedule multiple modifications. one of which may fail.

In the event that a scheduled contract update fails (i.e. it does not pass validation) we set the activity status to 'Failed' and alert the user (how?)

## Implementation notes

There are two pathways for updating contracts. 1) modifying contracts based on information in scheduled contract activities and 2) 'listening' for updates on the contract entity recording activities after the update has been carried out that record what the update was.

We need to allow pathway 1) to ignore and not listen any contract updates that originate via pathway 2). If we don't then pathway 1) will create duplicate contract modification activities when pathway 2) updates the contract.

We do this by passing a setting create_modification_activity to false in the contract API.

## Processing scheduled contract updates

A Job.process_contract_updates process all scheduled updates.

## TODO creating a contract

create

# Mandate related changes
