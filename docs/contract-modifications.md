# Contract modifications

The contract extension limits modifications that can be made to contracts to those and provides a UI to facilitate these modifications.

It keeps a history of significant changes (or modifications) made to contracts.

It provides the ability for modifications can be scheduled into the future.

The extension specifies 6 different types of modification:

* Sign - when a contract is created
* Update - when significant updates (as defined by the contract extension) are made to a contract
* Pause - when a contract status changes from current to paused
* Resume - when a contract status changes from paused to current.
* Cancel - when a contract status changes from current to cancelled
* Revive - when a contract status changes from cancelled to current. This change may be accompanied by other significant updates as defined in the update modification

The extension replaces the default create membership form with a Create contract form.

It provides Update, Pause, Cancel and Revive forms to that can be used to modify contracts.

The edit membership form is left in place though extra validation occurs on this form to ensure that modifications made using this form fit the contract model.

## Significant changes

***TODO***

## Modifying contracts with modification activities

Contracts can be modified by creating an appropriate contract modification activity with status set to scheduled.

If the activity date time is set to a date in the past or is not set, the corresponding contract will be modified immediately and the activity date time of the activity will be updated to now (regardless of whether it is set or not).

If the activity date time is set to a date in the future, the modification will be scheduled for that date in the future.

## Modifying a contract directly

Contracts can also be modified by updating the contract directly (for example, via the Contract API or via the contract edit form). When an attempt is made to modified a contract directly, the contract extension checks to ensure that the modification is allowed by the contract extension. If it is valid, the update is made and a *completed* contract modification activity is 'reverse engineered' for the contract.

Note that if a contract is updated and no signifincant changes are made, then no contract modification actitivities will be recorded.

## Multiple scheduled modifications

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

We implement a membership API method, runScheduledModifications, which takes an optional limit parameter and allows processing of scheduld contract updates.
