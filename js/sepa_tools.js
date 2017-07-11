/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

// this value will be replaced upon injection
var sepa_creditor_parameters = SEPA_CREDITOR_PARAMETERS;

/**
 * Will calculate the date of the next collection of
 * a CiviSEPA RCUR mandate
 */
function nextCollectionDate(cycle_day, start_date, grace_end, creditor_id='default') {
  cycle_day = parseInt(cycle_day);
  if (cycle_day < 1 || cycle_day > 30) {
    alert("Illegal cycle day detected: " + cycle_day);
    return "Error";
  }

  // earliest contribution date is: max(now+notice, start_date, grace_end)

  // first: calculate the earliest possible collection date
  var notice = parseInt(sepa_creditor_parameters[creditor_id]['notice']);
  var grace  = parseInt(sepa_creditor_parameters[creditor_id]['grace']);
  var earliest_date = new Date();
  // see https://stackoverflow.com/questions/6963311/add-days-to-a-date-object
  earliest_date = new Date(earliest_date.setTime(earliest_date.getTime() + (notice-grace) * 86400000));

  // then: take start date into account
  if (start_date) {
    start_date = new Date(start_date);
    if (start_date.getTime() > earliest_date.getTime()) {
      earliest_date = start_date;
    }
  }

  // then: take grace period into account
  if (grace_end) {
    grace_end = new Date(grace_end);
    if (grace_end.getTime() > earliest_date.getTime()) {
      earliest_date = grace_end;
    }
  }

  // now move to the next cycle day
  var safety_check = 65; // max two months
  while (earliest_date.getDate() != cycle_day && safety_check > 0) {
    // advance one day
    earliest_date = new Date(earliest_date.setTime(earliest_date.getTime() + 86400000));
    safety_check = safety_check - 1;
  }
  if (safety_check == 0) {
    console.log("Error, cannot cycle to day " + cycle_day);
  }

  // format to YYYY-MM-DD. Don't use toISOString() (timezone mess-up)
  var month = earliest_date.getMonth() + 1;
  month = month.toString();
  if (month.length == 1) {
    month = '0' +  month;
  }
  var day = earliest_date.getDate().toString();
  if (day.length == 1) {
    day = '0' + day;
  }

  // console.log(earliest_date.getFullYear() + '-' + month + '-' + day);
  return earliest_date.getFullYear() + '-' + month + '-' + day;
}
