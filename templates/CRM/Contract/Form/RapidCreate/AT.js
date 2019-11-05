/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


CRM.$(function($) {

  $('.tshirt_order_fields').hide();
  $('#activity_details').val('');

  function toggleTshirtOrderFields(){
    if($('#tshirt_order_1:checked').length){
      $('.tshirt_order_fields').show();
    }else{
      $('.tshirt_order_fields').hide();
    }
  }

  $('#tshirt_order_1').on('click', toggleTshirtOrderFields);

});
