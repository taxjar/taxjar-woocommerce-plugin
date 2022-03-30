<?php
/**
 * TaxJar Settings
 *
 * The TaxJar settings class is responsible for loading all admin functionality of the TaxJar plugin
 * It also allows contains the functionality for saving and accessing the TaxJar settings
 *
 * @package TaxJar/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TaxJar_Settings
 */
class TaxJar_Settings {

	/**
	 * Identifier for the TaxJar integration
	 *
	 * @var string
	 */
	public static $id = 'taxjar-integration';

	/**
	 * Adds settings and admin functions to hooks
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 15 );
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_sections_' . self::$id, array( __CLASS__, 'output_sections' ) );
		add_action( 'woocommerce_settings_' . self::$id, array( __CLASS__, 'output_settings_page' ) );
		add_action( 'woocommerce_settings_save_' . self::$id, array( __CLASS__, 'save' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_woocommerce_taxjar-integration_settings', array( __CLASS__, 'sanitize_settings' ), 10, 2 );

		if ( self::is_tax_calculation_enabled() ) {
			add_action( 'woocommerce_sections_tax', array( __CLASS__, 'output_sections_before' ), 9 );
		}
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
     * Checks if tax calculation through TaxJar has been enabled
     *
	 * @return bool
	 */
	public static function is_tax_calculation_enabled() {
	    return apply_filters( 'taxjar_enabled', self::is_setting_enabled( 'enabled' ) );
    }

	/**
     * Checks if a certain TaxJar setting is enabled.
     *
	 * @param string $setting_name Name of setting.
	 *
	 * @return bool
	 */
    public static function is_setting_enabled( $setting_name ) {
	    $settings = self::get_taxjar_settings();

	    if ( isset( $settings[ $setting_name ] ) && 'yes' === $settings[ $setting_name ] ) {
		    return true;
	    }
	    return false;
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
	 * @param array $settings_tabs - array of settings tabs.
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

		if ( empty( $sections ) || 1 === count( $sections ) ) {
			return;
		}

		echo '<ul class="subsubsub">';

		$array_keys = array_keys( $sections );

		foreach ( $sections as $id => $label ) {
			$section_relative_url = 'admin.php?page=wc-settings&tab=' . self::$id . '&section=' . sanitize_title( $id );
			$section_html_string  = '<li><a href="';
			$section_html_string .= admin_url( $section_relative_url );
			$section_html_string .= '" class="' . ( $current_section === $id ? 'current' : '' ) . '">';
			$section_html_string .= $label . '</a> ' . ( end( $array_keys ) === $id ? '' : '|' ) . ' </li>';
			echo wp_kses_post( $section_html_string );
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
	 * Outputs the TaxJar settings page content
	 */
	public static function output_settings_page() {
		global $current_section;

		if ( '' === $current_section ) {
			$settings = self::get_settings();
			WC_Admin_Settings::output_fields( $settings );
			wp_nonce_field( 'taxjar_settings' );
		} elseif ( 'transaction_backfill' === $current_section ) {
			self::output_transaction_backfill();
		} elseif ( 'sync_queue' === $current_section ) {
			self::output_sync_queue();
		}
	}

	/**
	 * Gets the array of settings fields for the TaxJar settings page
	 *
	 * @param string $current_section - identifier of current TaxJar settings section.
	 *
	 * @return mixed|void
	 */
	public static function get_settings( $current_section = '' ) {
		$settings = array();

		if ( '' === $current_section ) {
			$store_settings = self::get_store_settings();
			$tj_connection  = new WC_TaxJar_Connection();

			if ( empty( $store_settings['state'] ) ) {
				$store_settings['state'] = 'N/A';
			}

			$settings = self::get_plugin_title_settings();

			if ( $tj_connection->is_api_token_valid() ) {
				$settings = array_merge( $settings, self::get_connected_display_settings() );
			} else {
				array_push( $settings, self::get_connect_to_taxjar_setting() );
			}

			$settings = array_merge( $settings, self::get_hidden_settings() );

			if ( isset( $store_settings['api_token'] ) && ( ! $tj_connection->can_connect_to_api() || ! $tj_connection->is_api_token_valid() ) ) {
				array_push( $settings, $tj_connection->get_form_settings_field() );
				array_push( $settings, self::get_section_end_setting() );
			}

			if ( $tj_connection->is_api_token_valid() ) {
				$settings = array_merge( $settings, self::get_configuration_settings() );

				if ( self::post_or_setting( 'enabled' ) ) {
					$settings = array_merge( $settings, self::get_nexus_settings_display() );
				}

				if ( get_option( 'woocommerce_store_address' ) || get_option( 'woocommerce_store_city' ) || get_option( 'woocommerce_store_postcode' ) ) {
					$settings = array_merge( $settings, self::get_detected_address_settings_fields() );
				} else {
					$settings = array_merge( $settings, self::get_configurable_address_settings_fields() );
				}

				$settings = array_merge( $settings, self::get_debug_settings_fields() );
			}
		}

		return apply_filters( 'woocommerce_get_settings_' . self::$id, $settings );
	}

	/**
	 * Gets the fields for the TaxJar debug settings
	 *
	 * @return array
	 */
	public static function get_debug_settings_fields() {
		return array(
			array(
				'type' => 'title',
			),
			TaxJar()->download_orders->get_form_settings_field(),
			array(
				'title'   => __( 'Debug Log', 'wc-taxjar' ),
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => __( 'Log events such as API requests.', 'wc-taxjar' ),
				'id'      => 'woocommerce_taxjar-integration_settings[debug]',
			),
			self::get_section_end_setting(),
			array(
				'type' => 'title',
				'desc' => __( 'If you find TaxJar for WooCommerce useful, please rate us <a href="https://wordpress.org/support/plugin/taxjar-simplified-taxes-for-woocommerce/reviews/#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>. Thank you!', 'wc-taxjar' ),
			),
			self::get_section_end_setting(),
		);
	}

	/**
	 * Gets the configurable address fields on the TaxJar settings page
	 * Displays when address is not configured in WooCommerce store settings
	 *
	 * @return array
	 */
	public static function get_configurable_address_settings_fields() {
		$store_settings = self::get_store_settings();
		return array(
			array(
				'type' => 'title',
			),
			array(
				'title'    => __( 'Ship From Address', 'wc-taxjar' ),
				'type'     => 'text',
				'desc'     => __( 'Enter the street address where your store ships from.', 'wc-taxjar' ),
				'desc_tip' => true,
				'default'  => '',
				'id'       => 'woocommerce_taxjar-integration_settings[store_street]',
			),
			array(
				'title'       => __( 'Ship From City', 'wc-taxjar' ),
				'type'        => 'text',
				'description' => __( 'Enter the city where your store ships from.', 'wc-taxjar' ),
				'desc_tip'    => true,
				'default'     => '',
				'id'          => 'woocommerce_taxjar-integration_settings[store_city]',
			),
			array(
				'title'       => __( 'Ship From State', 'wc-taxjar' ),
				'type'        => 'title',
				'description' => __( 'We have automatically detected your ship from state as being ', 'wc-taxjar' ) . $store_settings['state'] . '.<br>' . __( 'You can change this setting at', 'wc-taxjar' ) . ' <a href="' . get_admin_url( null, 'admin.php?page=wc-settings' ) . '">WooCommerce -> Settings -> General -> Base Location</a>',
				'class'       => 'input-text disabled regular-input',
				'id'          => 'woocommerce_taxjar-integration_settings[store_state]',
			),
			array(
				'title'       => __( 'Ship From Postcode / ZIP', 'wc-taxjar' ),
				'type'        => 'text',
				'description' => __( 'Enter the zip code from which your store ships products.', 'wc-taxjar' ),
				'desc_tip'    => true,
				'default'     => '',
				'id'          => 'woocommerce_taxjar-integration_settings[store_postcode]',
			),
			array(
				'title'       => __( 'Ship From Country', 'wc-taxjar' ),
				'type'        => 'hidden',
				'description' => __( 'We have automatically detected your ship from country as being ', 'wc-taxjar' ) . $store_settings['country'] . '.<br>' . __( 'You can change this setting at', 'wc-taxjar' ) . ' <a href="' . get_admin_url( null, 'admin.php?page=wc-settings' ) . '">WooCommerce -> Settings -> General -> Base Location</a>',
				'class'       => 'input-text disabled regular-input',
				'id'          => 'woocommerce_taxjar-integration_settings[store_country]',
			),
			self::get_section_end_setting(),
		);
	}

	/**
	 * Gets the detected address field from the WooCommerce store settings
	 *
	 * @return array
	 */
	public static function get_detected_address_settings_fields() {
		$store_settings = self::get_store_settings();
		return array(
			array(
				'title' => __( 'Ship From Address', 'wc-taxjar' ),
				'type'  => 'title',
				'desc'  => __( 'We have automatically detected your ship from address:', 'wc-taxjar' ) . '<br><br>' . $store_settings['street'] . '<br>' . $store_settings['city'] . ', ' . $store_settings['state'] . ' ' . $store_settings['postcode'] . '<br>' . WC()->countries->countries[ $store_settings['country'] ] . '<br><br>' . __( 'You can change this setting at:', 'wc-taxjar' ) . '<br><a href="' . get_admin_url( null, 'admin.php?page=wc-settings' ) . '">WooCommerce -> Settings -> General -> Store Address</a>',
			),
			self::get_section_end_setting(),
		);
	}

	/**
	 * Gets the nexus settings field
	 *
	 * @return array
	 */
	public static function get_nexus_settings_display() {
		$tj_nexus = new WC_Taxjar_Nexus();
		return array(
			$tj_nexus->get_form_settings_field(),
			self::get_section_end_setting(),
		);
	}

	/**
	 * Gets the configuration settings fields
	 *
	 * @return array
	 */
	public static function get_configuration_settings() {
		return array(
			array(
				'title' => __( 'Step 2: Configure your sales tax settings', 'wc-taxjar' ),
				'type'  => 'title',
				'desc'  => '',
			),
			array(
				'title'   => __( 'Sales Tax Calculation', 'wc-taxjar' ),
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => __( 'If enabled, TaxJar will calculate sales tax for your store.', 'wc-taxjar' ),
				'id'      => 'woocommerce_taxjar-integration_settings[enabled]',
			),
			array(
				'title'   => __( 'Tax Calculation on API Orders', 'wc-taxjar' ),
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => __( 'If enabled, TaxJar will calculate sales tax for orders created through the WooCommerce REST API.', 'wc-taxjar' ),
				'id'      => 'woocommerce_taxjar-integration_settings[api_calcs_enabled]',
			),
			array(
				'title'   => __( 'Save Tax Rates', 'wc-taxjar' ),
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => __( 'TaxJar calculates tax in realtime through the TaxJar API. While not necessary for tax calculation, enabling this setting will store the tax rate in the WooCommerce tax table during the calculation process.', 'wc-taxjar' ),
				'id'      => 'woocommerce_taxjar-integration_settings[save_rates]',
			),
			self::get_section_end_setting(),
		);
	}

	/**
	 * Gets the hidden settings fields
	 *
	 * @return array
	 */
	public static function get_hidden_settings() {
		return array(
			array(
				'title'   => 'TaxJar API Token',
				'type'    => 'text',
				'desc'    => '<p class="hidden tj-api-token-title"><a href="' . WC_Taxjar_Integration::$app_uri . 'account#api-access" target="_blank">' . __( 'Get API token', 'wc-taxjar' ) . '</a></p>',
				'default' => '',
				'class'   => 'hidden',
				'id'      => 'woocommerce_taxjar-integration_settings[api_token]',
			),
			self::get_section_end_setting(),
			array(
				'title' => '',
				'type'  => 'email',
				'desc'  => '',
				'class' => 'hidden',
				'id'    => 'woocommerce_taxjar-integration_settings[connected_email]',
			),
			self::get_section_end_setting(),
		);
	}

	/**
	 * Gets the connect to TaxJar field
	 * Displays when no API token is stored
	 *
	 * @return array
	 */
	public static function get_connect_to_taxjar_setting() {
		return array(
			'title' => '',
			'type'  => 'title',
			'desc'  => '<button id="connect-to-taxjar" name="connect-to-taxjar" class="button-primary" type="submit" value="Connect">' . __( 'Connect To TaxJar', 'wc-taxjar' ) . '</button><p>' . __( 'Already have an API Token?', 'wc-taxjar' ) . ' <a href="#" id="connect-manual-edit">' . __( 'Edit API Token.', 'wc-taxjar' ) . '</a></p>',
		);
	}

	/**
	 * Gets the connected to TaxJar settings field
	 * Displays when a valid API token has been entered
	 *
	 * @return array
	 */
	public static function get_connected_display_settings() {
		$settings        = array();
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
		return $settings;
	}

	/**
	 * Gets title settings fields
	 *
	 * @return array
	 */
	public static function get_plugin_title_settings() {
		return array(
			array(
				'title' => __( 'TaxJar', 'wc-taxjar' ),
				'type'  => 'title',
				'desc'  => __( 'TaxJar is the easiest to use sales tax calculation and reporting engine for WooCommerce. Connect your TaxJar account and enter the city and zip code from which your store ships. Enable TaxJar calculations to automatically collect sales tax at checkout. You may also enable sales tax reporting to begin importing transactions from this store into your TaxJar account, all in one click!', 'wc-taxjar' ),
			),
			self::get_section_end_setting(),
			array(
				'type' => 'title',
				'desc' => '<strong>' . __( 'For the fastest help, please email', 'wc-taxjar' ) . ' <a href="mailto:support@taxjar.com">support@taxjar.com</a>.',
			),
			self::get_section_end_setting(),
			array(
				'title' => __( 'Step 1: Activate your TaxJar WooCommerce Plugin', 'wc-taxjar' ),
				'type'  => 'title',
				'desc'  => '',
			),
		);
	}

	/**
	 * Gets section end field - used for formatting purposes
	 *
	 * @return array
	 */
	public static function get_section_end_setting() {
		return array(
			'type' => 'sectionend',
		);
	}

	/**
	 * Gets the store address settings
	 *
	 * @return array
	 */
	public static function get_store_settings() {
		$store_address   = self::get_store_setting('woocommerce_store_address', 'store_street' );
		$store_city      = self::get_store_setting('woocommerce_store_city', 'store_city' );
		$store_postcode  = self::get_store_setting('woocommerce_store_postcode', 'store_postcode' );
		$store_country   = explode( ':', get_option( 'woocommerce_default_country' ) );


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

		return apply_filters( 'taxjar_store_settings', $store_settings, self::get_taxjar_settings() );
	}

	/**
	 * Gets the woocommerce store setting if present, otherwise gets the TaxJar store setting.
	 *
	 * @param string $woocommerce_option_name WooCommerce option name
	 * @param string $taxjar_setting_key TaxJar setting key
	 *
	 * @return mixed
	 */
	public static function get_store_setting( string $woocommerce_option_name, string $taxjar_setting_key ) {
		if ( $woo_setting = get_option( $woocommerce_option_name ) ) {
			return $woo_setting;
		}

		$taxjar_settings = self::get_taxjar_settings();
		if ( isset( $taxjar_settings[ $taxjar_setting_key ] ) ) {
			return $taxjar_settings[ $taxjar_setting_key ];
		}

		return null;
	}

	/**
	 * Return either the post value or settings value of a key
	 *
	 * @param string $key - key for TaxJar setting.
	 *
	 * @return int|mixed|null
	 */
	public static function post_or_setting( $key ) {
		$val            = null;
		$saved_settings = self::get_taxjar_settings();

		if ( isset( $_POST['woocommerce_taxjar-integration_settings'][ $key ] ) ) {
			check_admin_referer( 'woocommerce-settings' );
			$val = sanitize_text_field( wp_unslash( $_POST['woocommerce_taxjar-integration_settings'][ $key ] ) );
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
		$taxjar_settings  = self::get_taxjar_settings();

		if ( isset( $taxjar_settings['taxjar_download'] ) && 'yes' === $taxjar_settings['taxjar_download'] ) {
			$current_date = current_time( 'Y-m-d' );
			?>
			<table class="form-table">
				<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="start_date">Backfill Start Date</label>
					</th>
					<td class="start_date_field">
						<input type="text" class="taxjar-datepicker" style="" name="start_date" id="start_date" value="<?php echo esc_html( $current_date ); ?>" placeholder="YYYY-MM-DD" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="end_date">Backfill End Date</label>
					</th>
					<td class="end_date_field">
						<input type="text" class="taxjar-datepicker" style="" name="end_date" id="end_date" value="<?php echo esc_html( $current_date ); ?>" placeholder="YYYY-MM-DD" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
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
		$taxjar_settings  = self::get_taxjar_settings();

		if ( isset( $taxjar_settings['taxjar_download'] ) && 'yes' === $taxjar_settings['taxjar_download'] ) {
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

	/**
	 * Sanitize TaxJar settings before saving
	 *
	 * @param mixed $value - Value of setting.
	 * @param $option - Setting option.
	 *
	 * @return array|string
	 */
	public static function sanitize_settings( $value, $option ) {
		parse_str( $option['id'], $option_name_array );
		$option_name  = current( array_keys( $option_name_array ) );
		$setting_name = key( $option_name_array[ $option_name ] );

		if ( in_array( $setting_name, array( 'store_postcode', 'store_city', 'store_street' ), true ) ) {
			return wc_clean( $value );
		}

		if ( 'api_token' === $setting_name ) {
			return strtolower( wc_clean( $value ) );
		}

		if ( 'taxjar_download' === $setting_name ) {
			return TaxJar()->download_orders->validate_taxjar_download_field( $setting_name );
		}

		return $value;
	}

	/**
	 * Output TaxJar message above tax configuration screen
	 */
	public static function output_sections_before() {
		echo '<div class="updated taxjar-notice"><p><b>Powered by <a href="https://www.taxjar.com" target="_blank">TaxJar</a></b> ― Your tax rates and settings are automatically configured below.</p><p><a href="admin.php?page=wc-settings&tab=taxjar-integration" class="button-primary">Configure TaxJar</a> &nbsp; <a href="https://www.taxjar.com/contact/" class="button" target="_blank">Help &amp; Support</a></p></div>';
	}

	/**
	 * Checks if TaxJar API Tax Calculation setting is enabled.
	 *
	 * @return bool
	 */
	public static function is_save_rates_enabled(): bool {
		$settings = self::get_taxjar_settings();
		return isset( $settings['save_rates'] ) && 'yes' === $settings['save_rates'];
	}

}
