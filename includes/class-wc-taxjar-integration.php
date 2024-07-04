<?php
/**
 * Integration TaxJar
 *
 * @package  WC_Taxjar_Integration
 * @category Integration
 * @author   TaxJar
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! class_exists( 'WC_Taxjar_Integration' ) ) :

	class WC_Taxjar_Integration extends WC_Settings_API {

		protected static $_instance = null;

		public static $app_uri = 'https://app.taxjar.com/';

		/**
		 * Main TaxJar Integration Instance.
		 * Ensures only one instance of TaxJar Integration is loaded or can be loaded.
		 *
		 * @return WC_Taxjar_Integration - Main instance.
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Init and hook in the integration.
		 */
		public function __construct() {
			$this->id                 = 'taxjar-integration';
			$this->method_title       = __( 'TaxJar', 'wc-taxjar' );
			$this->method_description = apply_filters( 'taxjar_method_description', __( 'TaxJar is the easiest to use sales tax calculation and reporting engine for WooCommerce. Connect your TaxJar account and enter the city and zip code from which your store ships. Enable TaxJar calculations to automatically collect sales tax at checkout. You may also enable sales tax reporting to begin importing transactions from this store into your TaxJar account, all in one click!<br><br><b>For the fastest help, please email <a href="mailto:support@taxjar.com">support@taxjar.com</a>.</b>', 'wc-taxjar' ) );
			$this->integration_uri    = self::$app_uri . 'account/apps/add/woo';
			$this->debug              = filter_var( $this->get_option( 'debug' ), FILTER_VALIDATE_BOOLEAN );
			$this->download_orders    = new WC_Taxjar_Download_Orders( $this );
			$this->transaction_sync   = new WC_Taxjar_Transaction_Sync( $this );
			$this->customer_sync      = new WC_Taxjar_Customer_Sync( $this );
			$this->module_loader      = new \TaxJar\Module_Loader();

			// Load the settings.
			TaxJar_Settings::init();
			$this->tax_calculations = new TaxJar_Tax_Calculation();

			if ( is_admin() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'load_taxjar_admin_assets' ) );
			}

			if ( TaxJar_Settings::is_tax_calculation_enabled() ) {
				// Scripts / Stylesheets
				add_action( 'admin_enqueue_scripts', array( $this, 'load_taxjar_admin_new_order_assets' ) );
			}

			new \TaxJar\Admin_Meta_Boxes();
		}

		/**
		 * Get the regions API endpoint URI.
		 *
		 * @return string
		 */
		public static function get_regions_uri() {
			return self::$app_uri . 'account#states';
		}

		/**
		 * Prints debug info to wp-content/uploads/wc-logs/taxjar-*.log
		 *
		 * @return void
		 */
		public function _log( $message ) {
			do_action( 'taxjar_log', $message );
			if ( $this->debug ) {
				if ( ! isset( $this->log ) ) {
					$this->log = new WC_Logger();
				}
				if ( is_array( $message ) || is_object( $message ) ) {
					$this->log->add( 'taxjar', print_r( $message, true ) );
				} else {
					$this->log->add( 'taxjar', $message );
				}
			}
		}

		/**
		 * Slightly altered copy and paste of private function in WC_Tax class file
		 */
		public function _get_wildcard_postcodes( $postcode ) {
			$postcodes = array( '*', strtoupper( $postcode ) );

			if ( version_compare( WC()->version, '2.4.0', '>=' ) ) {
				$postcodes = array( '*', strtoupper( $postcode ), strtoupper( $postcode ) . '*' );
			}

			$postcode_length   = strlen( $postcode );
			$wildcard_postcode = strtoupper( $postcode );

			for ( $i = 0; $i < $postcode_length; $i ++ ) {
				$wildcard_postcode = substr( $wildcard_postcode, 0, -1 );
				$postcodes[]       = $wildcard_postcode . '*';
			}
			return $postcodes;
		}

		/**
		 * Generate Button HTML.
		 */
		public function generate_button_html( $key, $data ) {
			$field    = $this->plugin_id . $this->id . '_' . $key;
			$defaults = array(
				'class'             => 'button-secondary',
				'css'               => '',
				'custom_attributes' => array(),
				'desc_tip'          => false,
				'description'       => '',
				'title'             => '',
			);
			$data     = wp_parse_args( $data, $defaults );
			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
					<?php echo $this->get_tooltip_html( $data ); ?>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
						<button class="<?php echo esc_attr( $data['class'] ); ?>" type="button" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>><?php echo wp_kses_post( $data['title'] ); ?></button>
						<?php echo $this->get_description_html( $data ); ?>
					</fieldset>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		/**
		 * Gets the value for a seting from POST given a key or returns false if box not checked
		 *
		 * @param mixed $key
		 * @return mixed $value
		 */
		public function get_value_from_post( $key ) {
			if ( isset( $_POST[ $this->plugin_id . $this->id . '_settings' ][ $key ] ) ) {
				return $_POST[ $this->plugin_id . $this->id . '_settings' ][ $key ];
			} else {
				return false;
			}
		}

		/**
		 * Display errors by overriding the display_errors() method
		 * @see display_errors()
		 */
		public function display_errors() {
			$error_key_values = array(
				'shop_not_linked' => 'There was an error linking this store to your TaxJar account. Please contact support@taxjar.com',
			);

			foreach ( $this->errors as $key => $value ) {
				$message = $error_key_values[ $value ];
				echo "<div class=\"error\"><p>$message</p></div>";
			}
		}

		/**
		 * Checks if currently on the WooCommerce order or subscription page.
		 *
		 * @return boolean
		 */
		public function on_order_page() {
			return $this->on_new_order_page() || $this->on_edit_order_page() || $this->on_hpos_order_page();
		}

		/**
		 * Checks if current page is new order or subscription page.
		 *
		 * @return bool
		 */
		private function on_new_order_page() {
			global $pagenow;
			if ( 'post-new.php' === $pagenow ) {
				if ( isset( $_GET['post_type'] ) && $this->is_order_post_type( $_GET['post_type'] ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Checks if current page is edit order or subscription page.
		 *
		 * @return bool
		 */
		private function on_edit_order_page() {
			global $pagenow;
			if ( 'post.php' === $pagenow ) {
				if ( $this->is_order_post_type( OrderUtil::get_order_type($_GET['post']) ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Checks if post type is one where our order javascript needs loaded.
		 *
		 * @param string $post_type Post type of current page
		 *
		 * @return bool
		 */
		private function is_order_post_type( $post_type ) {
			$allowed_post_types = array( 'shop_order', 'shop_subscription' );
			return in_array( $post_type, $allowed_post_types, true );
		}

		/**
		 * Checks if the current page is the HPOS admin order page.
		 *
		 * @return bool
		 */
		private function on_hpos_order_page() {
			$allowed_order_pages = array( 'new', 'edit' );
			if ( ! isset( $_GET['page'] ) || sanitize_text_field( wp_unslash( $_GET['page'] ) ) !== 'wc-orders' || ! isset( $_GET['action'] ) ) {
				return false;
			}
			return in_array( sanitize_text_field( wp_unslash( $_GET['action'] ) ), $allowed_order_pages, true );
		}

		/**
		 * Admin Assets
		 */
		public function load_taxjar_admin_assets() {
			// Add CSS that hides some elements that are known to cause problems
			wp_enqueue_style( 'taxjar-admin-style', plugin_dir_url( __FILE__ ) . 'css/admin.css' );

			// Load Javascript for TaxJar settings page
			wp_register_script( 'wc-taxjar-admin', plugin_dir_url( __FILE__ ) . '/js/wc-taxjar-admin.js' );

			wp_localize_script(
				'wc-taxjar-admin',
				'woocommerce_taxjar_admin',
				array(
					'ajax_url'                   => admin_url( 'admin-ajax.php' ),
					'transaction_backfill_nonce' => wp_create_nonce( 'taxjar-transaction-backfill' ),
					'update_nexus_nonce'         => wp_create_nonce( 'taxjar-update-nexus' ),
					'current_user'               => get_current_user_id(),
					'integration_uri'            => $this->integration_uri,
					'connect_url'                => $this->get_connect_url(),
					'app_url'                    => untrailingslashit( self::$app_uri ),
				)
			);

			wp_enqueue_script( 'wc-taxjar-admin', array( 'jquery' ) );

			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_style( 'jquery-ui-datepicker' );
		}

		/**
		 * Generates TaxJar connect popup url
		 *
		 * @return string - TaxJar connect popup url
		 */
		public function get_connect_url() {
			$connect_url  = self::$app_uri . 'smartcalcs/connect/woo/?store=' . urlencode( get_bloginfo( 'url' ) );
			$connect_url .= '&plugin=woo&version=' . WC_Taxjar::$version;
			return esc_url( $connect_url );
		}

		/**
		 * Admin New Order Assets
		 */
		public function load_taxjar_admin_new_order_assets() {
			if ( ! $this->on_order_page() ) {
				return;
			}

			// Load Javascript for WooCommerce new order page
			wp_register_script( 'wc-taxjar-order', plugin_dir_url( __FILE__ ) . '/js/wc-taxjar-order.js' );
			wp_enqueue_script( 'wc-taxjar-order', array( 'jquery' ) );
		}

	}

endif;
