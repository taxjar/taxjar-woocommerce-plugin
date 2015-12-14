jQuery(document).ready(function(){

  /*
  * Javascript module for TaxJar admin settings page
  */
	var TaxJarAdmin = (function($, m) {
		var performingRequest = false;

  var init = function() {
    $('[name="woocommerce_taxjar-integration_api_token"]').on('blur', clean_api_key)
  };

  var clean_api_key = function() {
    $(this).attr('value', $(this).attr('value').replace(/ /g,''))
  };

  return {
    init: init
  };

}(jQuery, TaxJarAdmin || {}));

TaxJarAdmin.init();
});