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
});
