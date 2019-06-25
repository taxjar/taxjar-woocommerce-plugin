jQuery( document ).ready( function() {
	/*
	* JavaScript for TaxJar admin settings page
	*/
	var TaxJarAdmin = ( function( $, m ) {
		var init = function() {
			$( '[name="woocommerce_taxjar-integration_api_token"]' ).on( 'blur', clean_api_key );
			$( '.js-wc-taxjar-sync-nexus-addresses' ).on( 'click', sync_nexus_addresses );
			$( '.taxjar-datepicker' ).datepicker({ dateFormat: 'yy-mm-dd' });
			$( '.js-wc-taxjar-transaction-backfill' ).on( 'click', trigger_backfill );
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

		var trigger_backfill = function( e ) {
			e.preventDefault();

			$.ajax({
				method: 'POST',
				dataType: 'json',
				url: woocommerce_taxjar_admin.ajax_url,
				data: {
					action: 'wc_taxjar_run_transaction_backfill',
					security: woocommerce_taxjar_admin.update_api_nonce,
					'woocommerce_taxjar-integration_api_token': woocommerce_taxjar_admin.api_token,
					'start_date': $('input#start_date').val(),
					'end_date': $('input#end_date').val(),
					'force_sync': $('input#force_sync').prop( 'checked' ),
				}
			}).done( function( data ) {
				console.log( data );
				alert( 'Transactions in date range added to queue and will sync to TaxJar shortly.' );
				//location.reload();
			});
		}

		return {
			init: init
		};
	}( jQuery, TaxJarAdmin || {} ) );

	TaxJarAdmin.init();
});
