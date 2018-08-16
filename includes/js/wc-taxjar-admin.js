jQuery( document ).ready( function() {
	/*
	* JavaScript for TaxJar admin settings page
	*/
	var TaxJarAdmin = ( function( $, m ) {
		var init = function() {
			$( '[name="woocommerce_taxjar-integration_api_token"]' ).on( 'blur', clean_api_key );
			$( '.js-wc-taxjar-sync-nexus-addresses' ).on( 'click', sync_nexus_addresses );
		};

		var clean_api_key = function() {
			$( this ).attr( 'value', $(this).attr( 'value' ).replace( / /g, '' ) );
		};

		var sync_nexus_addresses = function( e ) {
			e.preventDefault();

			$.ajax({
				method: 'POST',
				dataType: 'json',
				url: woocommerce_taxjar_admin.ajax_url,
				data: {
					action: 'wc_taxjar_update_nexus_cache',
					security: woocommerce_taxjar_admin.update_api_nonce,
					'woocommerce_taxjar-integration_api_token': woocommerce_taxjar_admin.api_token
				}
			}).done(function() {
				alert( 'Nexus Addresses Synced' );
				location.reload();
			});
		};

		return {
			init: init
		};
	}( jQuery, TaxJarAdmin || {} ) );

	TaxJarAdmin.init();
});
