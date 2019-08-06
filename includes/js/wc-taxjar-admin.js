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

			$( "body" ).css( "cursor", "wait" );

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
				if ( data.records_updated != null ) {
					if ( data.records_updated == 0 ) {
						alert( 'No records found to add to queue.' );
					} else {
						if ( data.records_updated == 1 ) {
							alert(data.records_updated + ' record added to queue and will sync to TaxJar shortly.');
						} else {
							alert(data.records_updated + ' records added to queue and will sync to TaxJar shortly.');
						}
					}
				} else {
					if ( data.error == "transaction sync disabled" ) {
						alert( 'Sales tax reporting must be enabled to perform transaction backfill. Please enable this setting and try again.' );
					} else {
						alert('Error adding records to queue.');
					}
				}
				$("body").css("cursor", "default");
			});
		}

		return {
			init: init
		};
	}( jQuery, TaxJarAdmin || {} ) );

	TaxJarAdmin.init();
});
