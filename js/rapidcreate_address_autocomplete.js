/*-------------------------------------------------------+
 | SYSTOPIA - Postcode Lookup for Austria                 |
 | Copyright (C) 2017 SYSTOPIA                            |
 | Author: M. Wire (mjw@mjwconsult.co.uk)                 |
 |         B. Endres (endres@systopia.de)                 |
 | http://www.systopia.de/                                |
 +--------------------------------------------------------+
 | This program is released as free software under the    |
 | Affero GPL license. You can redistribute it and/or     |
 | modify it under the terms of this license which you    |
 | can read by viewing the included agpl.txt or online    |
 | at www.gnu.org/licenses/agpl.html. Removal of this     |
 | copyright header is strictly prohibited without        |
 | written permission from the original author(s).        |
 +--------------------------------------------------------*/

function rapidcreate_addressfields() {
  var fields = new Array();
  fields['postcode'] = CRM.$('#postal_code');
  fields['city'] = CRM.$('#city');
  fields['street'] = CRM.$('#street_address');
  fields['country'] = CRM.$('#country_id');
  return fields;
}

/*
 * Function to retrieve the postcode and fill the fields
 */
function rapidcreate_setstateprovince(postcode) {
  //check if country is AT.
  if ((CRM.$('#country_id').val()) != 1014) {
    return;
  }

  var fields = rapidcreate_addressfields();
  CRM.api3('PostcodeAT', 'getatstate', {'plznr': fields['postcode'].val(), 'ortnam': fields['city'].val(), 'stroffi': fields['street'].val()},
    {success: function(data) {
      if (data.is_error == 0 && data.count == 1) {
        var id = data.id;
        var obj = data.values[id];
        var state = data.values[id][0].state;
        CRM.$('#state_province_id').select2('data', {
          id: id,
          text: state
        });
      }
    }
    });
}

function rapidcreate_autofill(currentField) {
  var fields = rapidcreate_addressfields();

  CRM.$.ajax( {
    url: CRM.url('civicrm/ajax/postcodeat/autocomplete'),
    dataType: "json",
    data: {
      mode: 1,
      plznr: fields['postcode'].val(),
      ortnam: fields['city'].val(),
      stroffi: fields['street'].val(),
    },
    success: function( data ) {
      var plznr = data[0].plznr;
      var ortnam = data[0].ortnam;
      var stroffi = data[0].stroffi;

      if (currentField != 0) fields['postcode'].val(plznr);
      if (currentField != 1) fields['city'].val(ortnam);
      if (currentField != 2) fields['street'].val(stroffi);
    }
  });
}

function rapidcreate_init_addressBlock() {
  var fields = rapidcreate_addressfields();

  fields['postcode'].focusout(function(e) {
    rapidcreate_autofill(0);
    rapidcreate_setstateprovince(fields['postcode'].val());
  });

  fields['city'].focusout(function(e) {
    rapidcreate_autofill(1);
    rapidcreate_setstateprovince(fields['postcode'].val());
  });

  fields['street'].focusout(function(e) {
    rapidcreate_autofill(2);
    rapidcreate_setstateprovince(fields['postcode'].val());
  });

  fields['country'].change(function(e) {
    autocomplete();
  });
}

// Add autocomplete functions to postcode, street, city fields
function autocomplete() {
  var fields = rapidcreate_addressfields();

  // Init autocomplete
  fields['postcode'].autocomplete();
  fields['street'].autocomplete();
  fields['city'].autocomplete();

  if ((fields['country'].val()) == 1014) {
    fields['postcode'].autocomplete({
      source: function( request, response ) {
        CRM.$.ajax( {
          url: CRM.url('civicrm/ajax/postcodeat/autocomplete'),
          dataType: "json",
          data: {
            mode: 0,
            term : request.term,
            plznr: fields['postcode'].val(),
            ortnam: fields['city'].val(),
            stroffi: fields['street'].val(),
            return: 'plznr'
          },
          success: function( data ) {
            response( data );
          }
        });
      },
      width: 280,
      selectFirst: true,
      matchContains: true,
      minLength: 0
    })
      .focus(function() {
        CRM.$(this).autocomplete("search", "");
      });

    fields['city'].autocomplete({
      source: function( request, response ) {
        CRM.$.ajax( {
          url: CRM.url('civicrm/ajax/postcodeat/autocomplete'),
          dataType: "json",
          data: {
            mode: 0,
            term : request.term,
            plznr: fields['postcode'].val(),
            ortnam: fields['city'].val(),
            stroffi: fields['street'].val(),
            return: 'ortnam'
          },
          success: function( data ) {
            response( data );
          }
        });
      },
      width: 280,
      selectFirst: true,
      matchContains: true,
      minLength: 0
    })
      .focus(function() {
        CRM.$(this).autocomplete("search", "");
      });

    fields['street'].autocomplete({
      source: function( request, response ) {
        CRM.$.ajax( {
          url: CRM.url('civicrm/ajax/postcodeat/autocomplete'),
          dataType: "json",
          data: {
            mode: 0,
            term : request.term,
            plznr: fields['postcode'].val(),
            ortnam: fields['city'].val(),
            stroffi: fields['street'].val(),
            return: 'stroffi'
          },
          success: function( data ) {
            response( data );
          }
        });
      },
      width: 280,
      selectFirst: true,
      matchContains: true,
      minLength: 0
    })
      .focus(function() {
        CRM.$(this).autocomplete("search", "");
      });

    fields['postcode'].autocomplete("enable");
    fields['street'].autocomplete("enable");
    fields['city'].autocomplete("enable");

  }
  else {
    // Disable autocomplete
    fields['postcode'].autocomplete("disable");
    fields['street'].autocomplete("disable");
    fields['city'].autocomplete("disable");
  }
}

CRM.$(function($) {
  rapidcreate_init_addressBlock();
  autocomplete();
});
