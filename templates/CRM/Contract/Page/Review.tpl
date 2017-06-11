<table>
  <tr>
    <th>Date</th>
    <th>Type</th>
    <th>Status</th>
    <th>Subject</th>
    <th>Edit</th>
{foreach from=$activities item=a}
  <tr>
    <td>{$a.activity_date_time|crmDate}</td>
    <td>{$activityTypes[$a.activity_type_id]}</td>
    <td>{$activityStatuses[$a.status_id]}</td>
    <td>{$a.subject}</td>
    <td>
      <a class="edit-activity" href="{crmURL p='civicrm/activity/add' q="&action=update&reset=1&id=`$a.id`&context=activity&searchContext=activity&cid=`$a.target_contact_id.0`"}" class="create-mandate">edit</a>
    </td>
  </tr>
{/foreach}
</table>
