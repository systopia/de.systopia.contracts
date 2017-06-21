{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

<script type="text/javascript">
var busy_icon_url = "{$config->resourceBase}i/loading.gif";
var sepa_hide_bic_enabled = false;
var sepa_lookup_bic_error_message = "Bank unknown, please enter BIC.";
var sepa_lookup_bic_timerID = 0;
var sepa_lookup_bic_timeout = 1000;
{literal}

/**
 * IBAN changed handler
 */
function sepa_process_iban() {
  var reSpaceAndMinus = new RegExp('[\\s-]', 'g');
  var sanitized_iban = cj("#iban").val();
  sanitized_iban = sanitized_iban.replace(reSpaceAndMinus, "");
  sanitized_iban = sanitized_iban.toUpperCase();
  cj("#iban").val(sanitized_iban);
  sepa_lookup_bic();
}

/**
 * Clear bank for lookup
 */
function sepa_clear_bank() {
  cj("#bank_name").html('');
  cj("#bic_busy").hide();
}

/**
 * BIC visibility
 */
function sepa_show_bic(show_bic, message) {
  if (sepa_hide_bic_enabled) {
    if (show_bic) {
      cj("#bic").parent().parent().show();
      cj("#bic").parent().find("span.sepa-warning").remove();
      if (message.length) {
        cj("#bic").parent().append("<span class='sepa-warning'>&nbsp;&nbsp;" + message + "</span>");
      }
    } else {
      // hide only if no error label attached:
      if (!cj("#bic").parent().find("span.crm-error").length) {
        cj("#bic").parent().parent().hide();
      }
    }
  }
}

/**
 * Resolve BIC
 */
function sepa_lookup_bic() {
  if (sepa_lookup_bic_timerID) {
    // clear any existing lookup timers
    clearTimeout(sepa_lookup_bic_timerID);
    sepa_lookup_bic_timerID = 0;
  }

  var iban_partial = cj("#iban").val();
  if (iban_partial == undefined || iban_partial.length == 0) return;
  if (sepa_hide_bic_enabled) {
    // if it's hidden, we should clear it at this point
    cj("#bic").attr('value', '');
  }
  cj("#bic_busy").show();
  cj("div.payment_processor-section").trigger("sdd_biclookup", "started");
  CRM.api('Bic', 'findbyiban', {'q': 'civicrm/ajax/rest', 'iban': iban_partial},
    {success: function(data) {
      if ('bic' in data) {
        cj("#bic").attr('value', data['bic']);
        cj("#bank_name").html(data['title']);
        cj("#bic_busy").hide();
        cj("div.payment_processor-section").trigger("sdd_biclookup", "success");
        sepa_show_bic(false, "");
      } else {
        sepa_clear_bank();
        //sepa_show_bic(true, sepa_lookup_bic_error_message);
        sepa_show_bic(true, "");
        cj("#bic").attr('value', '');
        cj("div.payment_processor-section").trigger("sdd_biclookup", "failed");
      }
    }, error: function(result, settings) {
      // we suppress the message box here
      // and log the error via console
      cj("#bic_busy").hide();
      cj("div.payment_processor-section").trigger("sdd_biclookup", "failed");
      if (result.is_error) {
        console.log(result.error_message);
        sepa_clear_bank();
        sepa_show_bic(true, result.error_message);
      }
      return false;
    }});
}

/**
 * bootstrap stuff
 */
cj(function() {
  cj("#iban").parent().append('&nbsp;<img id="bic_busy" height="12" src="' + busy_icon_url + '"/>&nbsp;<font color="gray"><span id="bank_name"></span></font>');
  cj("#iban").on("input click keydown blur", sepa_process_iban);
  cj("#bic_busy").hide();
  // call it once
  sepa_lookup_bic();
});

</script>
{/literal}