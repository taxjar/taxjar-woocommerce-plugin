<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Settings {

    public static $id = 'taxjar-integration';

    public static function init() {
	    add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 15 );
	    add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_settings_tab' ), 50 );
	    add_action( 'woocommerce_sections_' . self::$id, array( __CLASS__, 'output_sections' ) );
	    add_action( 'woocommerce_settings_' . self::$id, array( __CLASS__, 'output_settings_page' ) );
	    add_action( 'woocommerce_settings_save_' . self::$id, array( __CLASS__, 'save' ) );
    }

	/**
     * Gets the option name used to store TaxJar settings in the database
     *
	 * @return string - option name
	 */
    public static function get_option_name() {
        return 'woocommerce_' . self::$id . '_settings';
    }

	/**
     * Gets the TaxJar settings
     *
	 * @return mixed - TaxJar settings
	 */
	public static function get_taxjar_settings() {
		return WC_Admin_Settings::get_option( self::get_option_name() );
	}

	/**
	 * Adds a submenu page under the WooCommerce navigation.
	 *
	 * @return void
	 */
	public static function add_menu_page() {
		add_submenu_page(
            'woocommerce',
            __( 'TaxJar Settings', 'woocommerce' ),
            __( 'TaxJar', 'woocommerce' ),
            'manage_woocommerce',
            'admin.php?page=wc-settings&tab=taxjar-integration'
        );
	}

	/**
     * Adds TaxJar tab to WooCommerce settings page.
     *
	 * @param $settings_tabs - array of settings tabs
	 * @return mixed - array of settings tabs
	 */
	public static function add_settings_tab( $settings_tabs ) {
		$settings_tabs[ self::$id ] = __( 'TaxJar', 'taxjar' );
		return $settings_tabs;
	}

	/**
	 * Output sections on TaxJar settings page.
	 */
	public static function output_sections() {
		global $current_section;

		$sections = self::get_sections();

		if ( empty( $sections ) || 1 === sizeof( $sections ) ) {
			return;
		}

		echo '<ul class="subsubsub">';

		$array_keys = array_keys( $sections );

		foreach ( $sections as $id => $label ) {
		    $section_relative_url = 'admin.php?page=wc-settings&tab=' . self::$id . '&section=' . sanitize_title( $id );
		    $section_html_string = '<li><a href="';
			$section_html_string .= admin_url( $section_relative_url );
            $section_html_string .= '" class="' . ( $current_section === $id ? 'current' : '' ) . '">';
            $section_html_string .= $label . '</a> ' . ( end( $array_keys ) === $id ? '' : '|' ) . ' </li>';
			echo $section_html_string;
		}

		echo '</ul><br class="clear" />';
	}

	/**
	 * Creates array of sections on TaxJar settings page.
	 *
	 * @return array
	 */
	public static function get_sections() {
		$sections = array(
			''                     => __( 'Settings', 'woocommerce' ),
			'transaction_backfill' => __( 'Transaction Backfill', 'wc-taxjar' ),
			'sync_queue'           => __( 'Sync Queue', 'wc-taxjar' ),
		);
		return apply_filters( 'woocommerce_get_sections_' . self::$id, $sections );
	}

	/**
	 * Output the settings.
	 */
	public static function output_settings_page() {
		global $current_section;

		if ( '' === $current_section ) {
			$settings = self::get_settings();
			WC_Admin_Settings::output_fields( $settings );
		} elseif ( 'transaction_backfill' === $current_section ) {
			self::output_transaction_backfill();
		} elseif ( 'sync_queue' === $current_section ) {
			self::output_sync_queue();
		}
	}

	/**
	 * Get settings array.
	 *
	 * @return array
	 */
	public static function get_settings( $current_section = '' ) {
		$settings = array();

		if ( '' === $current_section ) {
			$store_settings = self::get_store_settings();
			$tj_connection  = new WC_TaxJar_Connection();

			if ( empty( $store_settings['state'] ) ) {
				$store_settings['state'] = 'N/A';
			}

			$settings = array(
				array(
					'title' => __( 'TaxJar', 'wc-taxjar' ),
					'type'  => 'title',
					'desc'  => __( 'TaxJar is the easiest to use sales tax calculation and reporting engine for WooCommerce. Connect your TaxJar account and enter the city and zip code from which your store ships. Enable TaxJar calculations to automatically collect sales tax at checkout. You may also enable sales tax reporting to begin importing transactions from this store into your TaxJar account, all in one click!', 'wc-taxjar' ),
				),
				array(
					'type' => 'sectionend',
				),
				array(
					'type' => 'title',
					'desc' => '<strong>' . __( 'For the fastest help, please email', 'wc-taxjar' ) . ' <a href="mailto:support@taxjar.com">support@taxjar.com</a>. ' . __( "We'll get back to you within hours.", 'wc-taxjar' ),
				),
				array(
					'type' => 'sectionend',
				),
				array(
					'title' => __( 'Step 1: Activate your TaxJar WooCommerce Plugin', 'wc-taxjar' ),
					'type'  => 'title',
					'desc'  => '',
				),

			);

			if ( $tj_connection->is_api_token_valid() ) {
				$connected_email = self::post_or_setting( 'connected_email' );

				if ( $connected_email ) {
					array_push(
						$settings,
						array(
							'title' => '',
							'type'  => 'title',
							'desc'  => '<div class="taxjar-connected"><span class="dashicons dashicons-yes-alt"></span><p>' . $connected_email . '</p></div>',
							'id'    => 'connected-to-taxjar',
						)
					);
				} else {
					array_push(
						$settings,
						array(
							'title' => '',
							'type'  => 'title',
							'desc'  => '<div class="taxjar-connected"><span class="dashicons dashicons-yes-alt"></span><p>Connected To TaxJar</p></div>',
							'id'    => 'connected-to-taxjar',
						)
					);
				}

				array_push(
					$settings,
					array(
						'title' => '',
						'type'  => 'title',
						'desc'  => '<button id="disconnect-from-taxjar" name="disconnect-from-taxjar" class="button-primary" type="submit" value="Disconnect">' . __( 'Disconnect From TaxJar', 'wc-taxjar' ) . '</button><p><a href="#" id="connect-manual-edit">' . __( 'Edit API Token', 'wc-taxjar' ) . '</a></p>',
					)
				);
			} else {
				array_push(
					$settings,
					array(
						'title' => '',
						'type'  => 'title',
						'desc'  => '<button id="connect-to-taxjar" name="connect-to-taxjar" class="button-primary" type="submit" value="Connect">' . __( 'Connect To TaxJar', 'wc-taxjar' ) . '</button><p>' . __( 'Already have an API Token?', 'wc-taxjar' ) . ' <a href="#" id="connect-manual-edit">' . __( 'Edit API Token.', 'wc-taxjar' ) . '</a></p>',
					)
				);
			}

			array_push(
				$settings,
				array(
					'title'   => 'TaxJar API Token',
					'type'    => 'text',
					'desc'    => '<p class="hidden tj-api-token-title"><a href="' . WC_Taxjar_Integration::$app_uri . 'account#api-access" target="_blank">' . __( 'Get API token', 'wc-taxjar' ) . '</a></p>',
					'default' => '',
					'class'   => 'hidden',
					'id'      => 'woocommerce_taxjar-integration_settings[api_token]',
				)
			);
			array_push(
				$settings,
				array(
					'type' => 'sectionend',
				)
			);
			array_push(
				$settings,
				array(
					'title' => '',
					'type'  => 'email',
					'desc'  => '',
					'class' => 'hidden',
					'id'    => 'woocommerce_taxjar-integration_settings[connected_email]',
				)
			);
			array_push(
				$settings,
				array(
					'type' => 'sectionend',
				)
			);

			if ( isset( $store_settings[ 'api_token' ] )  && ( ! $tj_connection->can_connect_to_api() || ! $tj_connection->is_api_token_valid() ) ) {
				array_push( $settings, $tj_connection->get_form_settings_field() );
				array_push(
					$settings,
					array(
						'type' => 'sectionend',
					)
				);
			}

			if ( $tj_connection->is_api_token_valid() ) {
				array_push(
					$settings,
					array(
						'title' => __( 'Step 2: Configure your sales tax settings', 'wc-taxjar' ),
						'type'  => 'title',
						'desc'  => '',
					)
				);
				array_push(
					$settings,
					array(
						'title'   => __( 'Sales Tax Calculation', 'wc-taxjar' ),
						'type'    => 'checkbox',
						'default' => 'no',
						'desc'    => __( 'If enabled, TaxJar will calculate sales tax for your store.', 'wc-taxjar' ),
						'id'      => 'woocommerce_taxjar-integration_settings[enabled]',
					)
				);
				array_push(
					$settings,
					array(
						'title'   => __( 'Tax Calculation on API Orders', 'wc-taxjar' ),
						'type'    => 'checkbox',
						'default' => 'no',
						'desc'    => __( 'If enabled, TaxJar will calculate sales tax for orders created through the WooCommerce REST API.', 'wc-taxjar' ),
						'id'      => 'woocommerce_taxjar-integration_settings[api_calcs_enabled]',
					)
				);
				array_push(
					$settings,
					array(
						'type' => 'sectionend',
					)
				);

				if ( self::post_or_setting( 'enabled' ) ) {
					$tj_nexus   = new WC_Taxjar_Nexus();
					$settings[] = $tj_nexus->get_form_settings_field();
					$settings[] = array(
						'type' => 'sectionend',
					);
				}

				if ( get_option( 'woocommerce_store_address' ) || get_option( 'woocommerce_store_city' ) || get_option( 'woocommerce_store_postcode' ) ) {
					$settings[] = array(
						'title' => __( 'Ship From Address', 'wc-taxjar' ),
						'type'  => 'title',
						'desc'  => __( 'We have automatically detected your ship from address:', 'wc-taxjar' ) . '<br><br>' . $store_settings['street'] . '<br>' . $store_settings['city'] . ', ' . $store_settings['state'] . ' ' . $store_settings['postcode'] . '<br>' . WC()->countries->countries[ $store_settings['country'] ] . '<br><br>' . __( 'You can change this setting at:', 'wc-taxjar' ) . '<br><a href="' . get_admin_url( null, 'admin.php?page=wc-settings' ) . '">WooCommerce -> Settings -> General -> Store Address</a>',
					);
					$settings[] = array(
						'type' => 'sectionend',
					);
				} else {
					$settings[] = array(
						'type' => 'title',
					);
					$settings[] = array(
						'title'    => __( 'Ship From Address', 'wc-taxjar' ),
						'type'     => 'text',
						'desc'     => __( 'Enter the street address where your store ships from.', 'wc-taxjar' ),
						'desc_tip' => true,
						'default'  => '',
						'id'       => 'woocommerce_taxjar-integration_settings[store_street]',
					);
					$settings[] = array(
						'title'       => __( 'Ship From City', 'wc-taxjar' ),
						'type'        => 'text',
						'description' => __( 'Enter the city where your store ships from.', 'wc-taxjar' ),
						'desc_tip'    => true,
						'default'     => '',
						'id'          => 'woocommerce_taxjar-integration_settings[store_city]',
					);
					$settings[] = array(
						'title'       => __( 'Ship From State', 'wc-taxjar' ),
						'type'        => 'title',
						'description' => __( 'We have automatically detected your ship from state as being ', 'wc-taxjar' ) . $store_settings['state'] . '.<br>' . __( 'You can change this setting at', 'wc-taxjar' ) . ' <a href="' . get_admin_url( null, 'admin.php?page=wc-settings' ) . '">WooCommerce -> Settings -> General -> Base Location</a>',
						'class'       => 'input-text disabled regular-input',
						'id'          => 'woocommerce_taxjar-integration_settings[store_state]',
					);
					$settings[] = array(
						'title'       => __( 'Ship From Postcode / ZIP', 'wc-taxjar' ),
						'type'        => 'text',
						'description' => __( 'Enter the zip code from which your store ships products.', 'wc-taxjar' ),
						'desc_tip'    => true,
						'default'     => '',
						'id'          => 'woocommerce_taxjar-integration_settings[store_postcode]',
					);
					$settings[] = array(
						'title'       => __( 'Ship From Country', 'wc-taxjar' ),
						'type'        => 'hidden',
						'description' => __( 'We have automatically detected your ship from country as being ', 'wc-taxjar' ) . $store_settings['country'] . '.<br>' . __( 'You can change this setting at', 'wc-taxjar' ) . ' <a href="' . get_admin_url( null, 'admin.php?page=wc-settings' ) . '">WooCommerce -> Settings -> General -> Base Location</a>',
						'class'       => 'input-text disabled regular-input',
						'id'          => 'woocommerce_taxjar-integration_settings[store_country]',
					);
					$settings[] = array(
						'type' => 'sectionend',
					);
				}

				$settings[] = array(
					'type' => 'title',
				);
				$settings[] = TaxJar()->download_orders->get_form_settings_field();
				$settings[] = array(
					'title'   => __( 'Debug Log', 'wc-taxjar' ),
					'type'    => 'checkbox',
					'default' => 'no',
					'desc'    => __( 'Log events such as API requests.', 'wc-taxjar' ),
					'id'      => 'woocommerce_taxjar-integration_settings[debug]',
				);
				$settings[] = array(
					'type' => 'sectionend',
				);
				$settings[] = array(
					'type' => 'title',
					'desc' => __( 'If you find TaxJar for WooCommerce useful, please rate us <a href="https://wordpress.org/support/plugin/taxjar-simplified-taxes-for-woocommerce/reviews/#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>. Thank you!', 'wc-taxjar' ),
				);
				$settings[] = array(
					'type' => 'sectionend',
				);
			}
		}

		return apply_filters( 'woocommerce_get_settings_' . self::$id, $settings );
	}

	/**
	 * Gets the store address settings
	 *
	 * @return array
	 */
	public static function get_store_settings() {
	    $taxjar_settings = self::get_taxjar_settings();
		$store_address  = get_option( 'woocommerce_store_address' ) ? get_option( 'woocommerce_store_address' ) : $taxjar_settings['store_street'];
		$store_city     = get_option( 'woocommerce_store_city' ) ? get_option( 'woocommerce_store_city' ) : $taxjar_settings['store_city'];
		$store_country  = explode( ':', get_option( 'woocommerce_default_country' ) );
		$store_postcode = get_option( 'woocommerce_store_postcode' ) ? get_option( 'woocommerce_store_postcode' ) : $taxjar_settings['store_postcode'];

		$store_settings = array(
			'street'   => $store_address,
			'city'     => $store_city,
			'state'    => null,
			'country'  => $store_country[0],
			'postcode' => $store_postcode,
		);

		if ( isset( $store_country[1] ) ) {
			$store_settings['state'] = $store_country[1];
		}

		return apply_filters( 'taxjar_store_settings', $store_settings, $taxjar_settings );
	}

	/**
	 * Return either the post value or settings value of a key
	 * @param $key
	 *
	 * @return int|mixed|null
	 */
	public static function post_or_setting( $key ) {
		$val = null;
		$saved_settings = self::get_taxjar_settings();

		if ( isset( $_POST['woocommerce_taxjar-integration_settings'][ $key ] ) ) {
			$val = $_POST['woocommerce_taxjar-integration_settings'][ $key ];
		} elseif ( isset( $saved_settings[ $key ] ) ) {
			$val = $saved_settings[ $key ];
		}

		if ( 'yes' === $val ) {
			$val = 1;
		}

		if ( 'no' === $val ) {
			$val = 0;
		}

		return $val;
	}

	/**
	 * Output the transaction backfill settings page.
	 */
	public static function output_transaction_backfill() {
		global $hide_save_button;
		$hide_save_button = true;
		$taxjar_settings = self::get_taxjar_settings();

		if ( isset( $taxjar_settings[ 'taxjar_download' ] ) && 'yes' === $taxjar_settings[ 'taxjar_download' ] ) {
			$current_date = current_time( 'Y-m-d' );
			?>
			<table class="form-table">
				<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="start_date">Backfill Start Date</label>
					</th>
					<td class="start_date_field">
						<input type="text" class="taxjar-datepicker" style="" name="start_date" id="start_date" value="<?php echo $current_date; ?>" placeholder="YYYY-MM-DD" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="end_date">Backfill End Date</label>
					</th>
					<td class="end_date_field">
						<input type="text" class="taxjar-datepicker" style="" name="end_date" id="end_date" value="<?php echo $current_date; ?>" placeholder="YYYY-MM-DD" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
					</td>
				</tr>
				<tr valign="top" class="">
					<th scope="row" class="titledesc">Force Sync</th>
					<td class="forminp forminp-checkbox">
						<fieldset>
							<legend class="screen-reader-text"><span>Force Sync</span></legend>
							<label for="force_sync">
								<input name="force_sync" id="force_sync" type="checkbox" class="" value="1"> If enabled, all orders and refunds will be added to the queue to sync to TaxJar, regardless of if they have been updated since they were lasted synced.
							</label>
						</fieldset>
					</td>
				</tr>
				</tbody>
			</table>
			<p>
				<button class='button js-wc-taxjar-transaction-backfill'>Run Backfill</button>
			</p>
			<?php
		} else {
			?>
			<p>Sales Tax Reporting must be enabled in order to use transaction back fill.</p>
			<?php
		}
	}

	/**
	 * Output the sync queue settings page.
	 */
	public static function output_sync_queue() {
		global $hide_save_button;
		$hide_save_button = true;
		$taxjar_settings = self::get_taxjar_settings();

		if ( isset( $taxjar_settings[ 'taxjar_download' ] ) && 'yes' === $taxjar_settings[ 'taxjar_download' ] ) {
			$report = new WC_Taxjar_Queue_List();
			$report->output_report();
		} else {
			?>
			<p>Enable Sales Tax Reporting in order to view the transaction queue.</p>
			<?php
		}
	}

	/**
	 * Save settings.
	 */
	public static function save() {
		$settings = self::get_settings();
		WC_Admin_Settings::save_fields( $settings );
	}

}