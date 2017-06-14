<table>
  <tr>
    <th>Date</th>
    <th>Modification</th>
    <th>Campaign</th>
    <th>Type</th>
    <th>Mandate</th>
    <th>Annual amount</th>
    <th>Frequency</th>
    <th>Bank account</th>
    <th>Cycle day</th>
    <th>Cancel reason</th>
    <th>Status</th>
    <th>Edit</th>
{foreach from=$activities item=a}
  <tr class="{if $activityStatuses[$a.status_id] eq 'Needs Review'}needs-review{/if} {if $activityStatuses[$a.status_id] eq 'Scheduled'}scheduled{/if}">
    <td>{$a.activity_date_time|crmDate}</td>
    <td>{$activityTypes[$a.activity_type_id]}</td>
    <td>{$campaigns[$a.campaign_id].name}</td>
    <td>{$membershipTypes[$a.contract_updates_ch_membership_type].name}</td>
    <td>{$a.contract_updates_ch_recurring_contribution}</td>
    <td>{$a.contract_updates_ch_annual}</td>
    <td>{$a.contract_updates_ch_frequency}</td>
    <td>{$a.contract_updates_ch_to_ba}</td>
    <td>{$a.contract_updates_ch_cycle_day}</td>
    <td>{$a.contract_cancellation_contact_history_cancel_reason}</td>
    <td>{$activityStatuses[$a.status_id]}</td>
    <td>
      <a class="edit-activity" href="{crmURL p='civicrm/activity/add' q="&action=update&reset=1&id=`$a.id`&context=activity&searchContext=activity&cid=`$a.target_contact_id.0`"}" class="create-mandate">edit</a>
    </td>
  </tr>
{/foreach}
</table>
