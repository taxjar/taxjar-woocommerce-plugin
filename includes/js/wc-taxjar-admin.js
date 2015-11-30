jQuery(document).ready(function(){

	/*
	* Javascript module for TaxJar admin settings page
	*/
 	var TaxJarAdmin = (function($, m) {
 		var performingRequest = false;

	  var init = function() {
	  	// Bind generate API button
	  	$('.js-taxjar-generate-api-key').on('click', generateAPIKeysClicked);
      $('.js-taxjar-regenerate-api-key').on('click', regenerateAPIKeysClicked);
      $('[name="woocommerce_taxjar-integration_api_token"]').on('blur', clean_api_key)
	  };

    var clean_api_key = function() {
      $(this).attr('value', $(this).attr('value').replace(/ /g,''))
    };

    var generateAPIKeysClicked = function(e) {      
      e.preventDefault();
      if(performingRequest) { return; }

      generateAPIKeys();
    };

    var regenerateAPIKeysClicked = function(e) {
      e.preventDefault();
      if(performingRequest) { return; }
      
      var confirmed = confirm("By regenerating the WooCommerce API keys for TaxJar you will need to go through the steps to connect TaxJar to your website again.");
      if (confirmed == true) {
        regenerateAPIKeys();
      }
    };

		var generateAPIKeys = function() {
      console.log(generateAPIKeys);
      performingRequest = true;

			$.ajax({
				method:   'POST',
				dataType: 'json',
				url:      woocommerce_taxjar_admin.ajax_url,
				data:     {
					action:      'woocommerce_update_api_key',
					security:    woocommerce_taxjar_admin.update_api_nonce,
					key_id:      0,
					description: 'TaxJar',
					user:        woocommerce_taxjar_admin.current_user,
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
						$container.append('<strong>Visit our <a href="'+woocommerce_taxjar_admin.integration_uri+'" target="_blank">WooCommerce Integration</a> page to complete our easy setup!</strong>');
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

    var regenerateAPIKeys = function() {
      console.log('destroyAPIKeys');
      performingRequest = true;

      $.ajax({
        method:   'POST',
        dataType: 'json',
        url:      woocommerce_taxjar_admin.ajax_url,
        data:     {
          action: 'wc_taxjar_delete_wc_taxjar_keys',
        },
        success: function( response ) {
          generateAPIKeys();
        }
      }).done(function(){
        performingRequest = false;
      });
    }

	  return {
	    init: init
	  };

	}(jQuery, TaxJarAdmin || {}));

	TaxJarAdmin.init();
});