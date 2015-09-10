jQuery(document).ready(function(){

	/*
	* Javascript module for handling WooCommerce 2.4+ API key generation
	*/
 	var TaxJarGenerateAPIKeys = (function($, m) {
 		var performingRequest = false;

	  var init = function() {
	  	// Bind generate API button
	  	$('.js-taxjar-generate-api-key').on('click', generateAPIKeys);
	  };

		var generateAPIKeys = function(e) {
			// Prevent button from submitting
			e.preventDefault();

			// Prevent people from spamming the button
			if(performingRequest) { return; }
			performingRequest = true;

			$.ajax({
				method:   'POST',
				dataType: 'json',
				url:      woocommerce_taxjar_admin_api_keys.ajax_url,
				data:     {
					action:      'woocommerce_update_api_key',
					security:    woocommerce_taxjar_admin_api_keys.update_api_nonce,
					key_id:      0,
					description: 'TaxJar',
					user:        woocommerce_taxjar_admin_api_keys.current_user,
					permissions: 'read'
				},
				success: function( response ) {
					var data = response.data;

					if ( response.success ) {	
						var $container = $('.taxjar-generate-api-content');

						// Build success responce
						$container.empty();
						$container.append(data.message);
						$container.append('<br />');
						$container.append('<br />');
						$container.append('Consumer Key: <code>'+data.consumer_key+'</code>');
						$container.append('<br />');
						$container.append('Consumer Secret: <code>'+data.consumer_secret+'</code>');
						$container.append('<br />');
						$container.append('<br />');
						$container.append('<strong>Visit our <a href="'+woocommerce_taxjar_admin_api_keys.integration_uri+'" target="_blank">WooCommerce Integration</a> page to complete our easy setup!</strong>');
						$container.append('<br />');
						$container.append('<br />');
						$container.append(data.revoke_url);

						$('.generate-new-api-key').remove();
					} else {
						var $container = $('.taxjar-generate-api-content');

						// Build error response.
						$container.empty();
						$container.append('<strong>An error occured: </strong><br />');
						$container.append(data.message);
					}
				}
			}).done(function(){
				//Always revert early escape flag
				performingRequest = false;
			});
		}

	  return {
	    init: init
	  };

	}(jQuery, TaxJarGenerateAPIKeys || {}));

	TaxJarGenerateAPIKeys.init();
});