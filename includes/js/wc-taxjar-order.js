jQuery( document ).ready( function() {
	/*
	* JavaScript for WooCommerce new order page
	*/
	var TaxJarOrder = ( function( $, m ) {
		$( document ).ajaxSend( function( event, request, settings ) {
			if ( settings.data ) {
				try {
					var data = JSON.parse( '{"' + decodeURIComponent( settings.data.replace( /&/g, '","' ).replace( /=/g, '":"' ) ) + '"}' );

					if ( 'woocommerce_calc_line_taxes' === data.action ) {
						var street = '';
						var customer_user = '';

						if ( 'shipping' === woocommerce_admin_meta_boxes.tax_based_on ) {
							street = $( '#_shipping_address_1' ).val();
						}

						if ( 'billing' === woocommerce_admin_meta_boxes.tax_based_on ) {
							street = $( '#_billing_address_1' ).val();
						}

						if ( $( '#customer_user' ).val() ) {
							customer_user = $( '#customer_user' ).val();
						}

						data.street = street;
						data.customer_user = customer_user;
						settings.data = $.param( data );
					}
				} catch ( e ) {
					// Ignore invalid JSON
				}
			}
		} );
	}( jQuery, TaxJarOrder || {} ) );

	var taxjar_order_calculation_meta = {
		init: function() {
			jQuery( '#advanced_order_data .request-json .copy-button' )
				.on( 'click', this.copy_request_json )
				.on( 'aftercopy', this.copy_success );

			jQuery( '#advanced_order_data .response-json .copy-button' )
				.on( 'click', this.copy_response_json )
				.on( 'aftercopy', this.copy_success );
		},

		copy_request_json: function( e ) {
			wcClearClipboard();
			wcSetClipboard( jQuery('#advanced_order_data .request-json .accordion-section-content pre').text(), jQuery( this ) );
			e.preventDefault();
			e.stopPropagation();
		},

		copy_response_json: function( e ) {
			wcClearClipboard();
			wcSetClipboard( jQuery('#advanced_order_data .response-json .accordion-section-content pre').text(), jQuery( this ) );
			e.preventDefault();
			e.stopPropagation();
		},

		copy_success: function() {
			alert('Copied to clipboard.');
		}
	};

	taxjar_order_calculation_meta.init();
});
