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
			$( '#connect-to-taxjar' ).on( 'click', open_connect_popup );
			$( '#disconnect-from-taxjar' ).on( 'click', disconnect_taxjar );
			$( 'a#connect-manual-edit' ).on( 'click', connect_manual_edit );
			window.addEventListener( 'message', handle_connect_callback, false );
			hide_save_on_connect();
		};

		var hide_save_on_connect = function() {
			if ( ! $( '#woocommerce_taxjar-integration_settings\\[api_token\\]' ).is( ':visible' ) && $( '#connect-to-taxjar' ).length ) {
				$( 'p.submit' ).hide();
			}
		};

		var disconnect_taxjar = function( e ) {
			$( '#woocommerce_taxjar-integration_settings\\[api_token\\]' ).val( '' )
			$( '#woocommerce_taxjar-integration_settings\\[connected_email\\]' ).val( '' );

			var input = $( "<input>" ).attr( "type", "hidden" ).attr( "name", "save" ).val( "Save changes" );
			$( '#mainform' ).append( input );
			$( '#mainform' ).submit();
		};

		var connect_manual_edit = function( e ) {
			e.preventDefault();
			$( '.tj-api-token-title' ).parent().parent().find( 'label' ).text( 'API Token' );
			$( '.tj-api-token-title' ).removeClass( 'hidden' );
			$( 'label[for="woocommerce_taxjar-integration_settings[api_token]"]' ).show();
			$( '#woocommerce_taxjar-integration_settings\\[api_token\\]' ).removeClass( 'hidden' );
			$( 'p.submit' ).show();
		};

		var handle_connect_callback = function( e ) {
			if ( e.origin !== woocommerce_taxjar_admin.app_url ) {
				return;
			}

			try {
				var data = JSON.parse( e.data );
				if ( data.api_token && data.email ) {
					window.popup.postMessage( 'Data received', woocommerce_taxjar_admin.app_url );
					$( '#woocommerce_taxjar-integration_settings\\[api_token\\]' ).val( data.api_token );
					$( '#woocommerce_taxjar-integration_settings\\[connected_email\\]' ).val( data.email );
					$( '#mainform .woocommerce-save-button' ).click();
				} else {
					throw 'Invalid data';
				}
			} catch( e ) {
			alert( 'Invalid API token or email provided. Please try connecting to TaxJar again or contact support@taxjar.com.' );
		}
	};

		var open_connect_popup = function( e ) {
			e.preventDefault();
			openPopup( woocommerce_taxjar_admin.connect_url, "Connect to TaxJar", 400, 500 );
		};

		var openPopup = function( url, title, w, h ) {
			var dualScreenLeft = window.screenLeft != undefined ? window.screenLeft : screen.left;
			var dualScreenTop = window.screenTop != undefined ? window.screenTop : screen.top;
			var width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
			var height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;
			var left = ( ( width / 2 ) - ( w / 2 ) ) + dualScreenLeft;
			var top = ( ( height / 2 ) - ( h / 2 ) ) + dualScreenTop;

			window.popup = window.open( url, title, 'scrollbars=yes, width=' + w + ', height=' + h + ', top=' + top + ', left=' + left );

			if ( window.focus ) {
				window.popup.focus();
			}
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
					security: woocommerce_taxjar_admin.update_nexus_nonce,
				}
			}).done( function( data ) {
				if ( data ) {
					if ( data.success == 1 ) {
						alert( 'Nexus Addresses Synced' );
					} else {
						alert( 'Error occurred during nexus sync. Please try again.' );
					}
				} else {
					alert( 'Error occurred during nexus sync. Please try again.' );
				}
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
					security: woocommerce_taxjar_admin.transaction_backfill_nonce,
					'start_date': $('input#start_date').val(),
					'end_date': $('input#end_date').val(),
					'force_sync': $('input#force_sync').prop( 'checked' ),
				}
			}).done( function( data ) {
				if ( data ) {
					if ( data.records_updated != null ) {
						if ( data.records_updated == 0 ) {
							alert( 'No records found to add to queue.' );
						} else {
							if ( data.records_updated == 1 ) {
								alert( data.records_updated + ' record added to queue and will sync to TaxJar shortly.' );
							} else {
								alert( data.records_updated + ' records added to queue and will sync to TaxJar shortly.' );
							}
						}
					} else {
						if ( data.error == "transaction sync disabled" ) {
							alert( 'Sales tax reporting must be enabled to perform transaction backfill. Please enable this setting and try again.' );
						} else if ( data.error == "record queue table does not exist" ) {
							alert( 'The TaxJar record queue database table does not exist. Please try to reinstall the TaxJar plugin in order to fix the installation.' );
						} else {
							alert( 'Error adding records to queue.' );
						}
					}
				} else {
					alert( 'Error occurred during transaction backfill.' );
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
