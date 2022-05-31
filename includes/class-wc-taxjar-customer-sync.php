<?php
/**
 * TaxJar Transaction Sync
 *
 * @package  WC_Taxjar_Transaction_Sync
 * @author   TaxJar
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Taxjar_Customer_Sync {

	public $taxjar_integration;

	/**
	 * Constructor for class
	 */
	public function __construct( $integration ) {
		$this->taxjar_integration = $integration;
		$this->init();
	}

	/**
	 * Add actions and filters
	 */
	public function init() {
		if ( apply_filters( 'taxjar_enabled', isset( $this->taxjar_integration->settings['enabled'] ) && 'yes' === $this->taxjar_integration->settings['enabled'] ) ) {
			add_action( 'show_user_profile', array( $this, 'add_customer_meta_fields' ) );
			add_action( 'edit_user_profile', array( $this, 'add_customer_meta_fields' ) );

			add_action( 'personal_options_update', array( $this, 'save_customer_meta_fields' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_customer_meta_fields' ) );

			add_action( 'taxjar_customer_exemption_settings_updated', array( $this, 'maybe_sync_customer_on_update' ) );

			add_action( 'woocommerce_rest_insert_customer', array( $this, 'api_customer_updated' ) );

			add_action( 'wc_csv_import_suite_create_customer', array( $this, 'csv_customer_import_customer_updated') );
			add_action( 'wc_csv_import_suite_update_customer', array( $this, 'csv_customer_import_customer_updated') );

			add_action( 'delete_user', array( $this, 'maybe_delete_customer' ) );
		}
	}

	/**
	 * Prints debug info to wp-content/uploads/wc-logs/taxjar-transaction-sync-*.log
	 *
	 * @return void
	 */
	public function _log( $message ) {
		do_action( 'taxjar_customer_sync_log', $message );
		if ( $this->taxjar_integration->debug ) {
			if ( ! isset( $this->log ) ) {
				$this->log = new WC_Logger();
			}
			if ( is_array( $message ) || is_object( $message ) ) {
				$this->log->add( 'taxjar-customer-sync', print_r( $message, true ) );
			} else {
				$this->log->add( 'taxjar-customer-sync', $message );
			}
		}
	}

	/**
	 * Get Address Fields for the edit user pages.
	 *
	 * @return array Fields to display which are filtered through woocommerce_customer_meta_fields before being returned
	 */
	public function get_customer_meta_fields() {
		$show_fields = apply_filters(
			'taxjar_customer_meta_fields',
			array(
				'exemptions' => array(
					'title'  => __( 'TaxJar Sales Tax Exemptions', 'wc-taxjar' ),
					'fields' => array(
						'tax_exemption_type' => array(
							'label'       => __( 'Exemption Type', 'wc-taxjar' ),
							'description' => __( 'All customers are presumed non-exempt unless otherwise selected.', 'wc-taxjar' ),
							'class'       => '',
							'type'        => 'select',
							'options'     => array( '' => __( 'Non-Exempt', 'wc-taxjar' ) ) + self::get_customer_exemption_types(),
						),
						'tax_exempt_regions' => array(
							'label'       => __( 'Exempt States', 'wc-taxjar' ),
							'description' => __( 'Hold CTRL to select multiple states. If no states are selected the customer will be considered exempt in all states.', 'wc-taxjar' ),
							'class'       => '',
							'type'        => 'multi-select',
							'options'     => self::get_all_exempt_regions(),
						),
					),
				),
			)
		);
		return $show_fields;
	}

	/**
	 * Adds customer exemption fields to edit user page
	 *
	 * @param $user
	 */
	public function add_customer_meta_fields( $user ) {
		if ( ! apply_filters( 'taxjar_current_user_can_edit_customer_meta_fields', current_user_can( 'manage_woocommerce' ), $user->ID ) ) {
			return;
		}

		$show_fields = $this->get_customer_meta_fields();

		foreach ( $show_fields as $fieldset_key => $fieldset ) :
			?>
			<h2><?php echo $fieldset['title']; ?></h2>
			<table class="form-table" id="<?php echo esc_attr( 'fieldset-' . $fieldset_key ); ?>">
				<?php foreach ( $fieldset['fields'] as $key => $field ) : ?>
					<tr>
						<th>
							<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						</th>
						<td>
							<?php if ( ! empty( $field['type'] ) && 'select' === $field['type'] ) : ?>
								<select name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( $field['class'] ); ?>" style="width: 25em;">
									<?php
									$selected = esc_attr( get_user_meta( $user->ID, $key, true ) );
									foreach ( $field['options'] as $option_key => $option_value ) :
										?>
										<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $selected, $option_key, true ); ?>><?php echo esc_attr( $option_value ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php elseif ( ! empty( $field['type'] ) && 'multi-select' === $field['type'] ) : ?>
							<select multiple name="<?php echo esc_attr( $key ); ?>[]" id="<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( $field['class'] ); ?>" style="width: 25em;">
								<?php
								$saved_value = esc_attr( get_user_meta( $user->ID, $key, true ) );
								if ( ! empty( $saved_value ) ) {
									$saved_value = explode( ',', $saved_value );
								}
								foreach ( $field['options'] as $option_key => $option_value ) :
									if ( ! empty( $saved_value ) && in_array( $option_key, $saved_value, true ) ) {
										$selected = $option_key;
									} else {
										$selected = false;
									}
									?>
									<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $selected, $option_key, true ); ?>><?php echo esc_attr( $option_value ); ?></option>
								<?php endforeach; ?>
								</select>
							<?php elseif ( ! empty( $field['type'] ) && 'button' === $field['type'] ) : ?>
								<button type="button" id="<?php echo esc_attr( $key ); ?>" class="button <?php echo esc_attr( $field['class'] ); ?>"><?php echo esc_html( $field['text'] ); ?></button>
							<?php else : ?>
								<input type="text" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $this->get_user_meta( $user->ID, $key ) ); ?>" class="<?php echo ( ! empty( $field['class'] ) ? esc_attr( $field['class'] ) : 'regular-text' ); ?>" />
							<?php endif; ?>
							<p class="description"><?php echo wp_kses_post( $field['description'] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php
		endforeach;
	}

	/**
	 * Saves tax exemption user meta if necessary
	 *
	 * @param $user_id - Id of user to update
	 */
	public function save_customer_meta_fields( $user_id ) {
		$has_permission = current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_users' );
		if ( ! apply_filters( 'taxjar_current_user_can_edit_customer_meta_fields', $has_permission, $user_id ) ) {
			return;
		}

		update_user_meta( $user_id, 'tax_exemption_type', $this->get_posted_exemption_type() );
		update_user_meta( $user_id, 'tax_exempt_regions', $this->get_posted_exempt_regions() );

		do_action( 'taxjar_customer_exemption_settings_updated', $user_id );
	}

	/**
	 * Gets the submitted tax exemption type during user save
	 *
	 * @return array|string - value to save
	 */
	public function get_posted_exemption_type() {
		return wc_clean( $_POST['tax_exemption_type'] );
	}

	/**
	 * Gets the submitted exempt regions value during user save
	 *
	 * @return string - Concatenated string containing the exempt regions
	 */
	public function get_posted_exempt_regions() {
		if ( empty( $_POST['tax_exempt_regions'] ) ) {
			return '';
		} else {
			return implode( ',', wc_clean( $_POST['tax_exempt_regions'] ) );
		}
	}

	/**
	 * Syncs customer record to TaxJar if all validation passes
	 *
	 * @param $user_id
	 */
	public function maybe_sync_customer_on_update( $user_id ) {
		$record = TaxJar_Customer_Record::find_active_in_queue( $user_id );
		if ( ! $record ) {
			$record = new TaxJar_Customer_Record( $user_id, true );
		}
		$record->load_object();

		$this->_log( 'Customer sync for customer # ' . $record->get_record_id() . ' (Queue # ' . $record->get_queue_id() . ') triggered.' );

		if ( ! apply_filters( 'taxjar_should_sync_customer', $record->should_sync() ) ) {
			return;
		}

		$result = $record->sync();
	}

	/**
	 * Attempts to sync customer to TaxJar when updated through the WooCommerce REST API
	 *
	 * @param object $user_data customer data
	 */
	function api_customer_updated( $user_data ) {
		$this->maybe_sync_customer_on_update( $user_data->ID );
	}

	/**
	 * Enqueues customer in sync queue when imported or updated through the WooCommerce CSV import suite
	 *
	 * @param mixed $id Customer ID
	 */
	function csv_customer_import_customer_updated( $id ) {
		$queue_id = TaxJar_Customer_Record::find_active_in_queue( $id );
		if ( $queue_id ) {
			return;
		}

		$record = new TaxJar_Customer_Record( $id, true );
		$record->load_object();
		if ( ! $record->object ) {
			return;
		}

		if ( ! apply_filters( 'taxjar_should_sync_customer', $record->should_sync() ) ) {
			return;
		}

		$taxjar_last_sync = $record->get_last_sync_time();
		if ( ! empty( $taxjar_last_sync ) ) {
			$record->set_status( 'awaiting' );
		}

		$record->save();
	}

	/**
	 * Creates array of valid option for customer exemption type dropdown
	 *
	 * @return array - customer exemption type options
	 */
	public static function get_customer_exemption_types() {
		return array(
			'wholesale'  => __( 'Wholesale / Resale', 'wc-taxjar' ),
			'government' => __( 'Government', 'wc-taxjar' ),
			'other'      => __( 'Other', 'wc-taxjar' ),
		);
	}

	/**
	 * Creates array of all valid exempt regions (states)
	 *
	 * @return array - available exempt regions
	 */
	public static function get_all_exempt_regions() {
		return array(
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DC' => 'District of Columbia',
			'DE' => 'Delaware',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
		);
	}

	/**
	 * Deletes customer from TaxJar when synced customer is deleted in WordPress
	 *
	 * @param $id - user id
	 */
	public function maybe_delete_customer( $id ) {
		$last_sync = get_user_meta( $id, '_taxjar_last_sync', true );
		$hash      = get_user_meta( $id, '_taxjar_hash', true );
		if ( $last_sync || $hash ) {
			$record = TaxJar_Customer_Record::find_active_in_queue( $id );
			if ( ! $record ) {
				$record = new TaxJar_Customer_Record( $id, true );
			}

			$record->delete_in_taxjar();
			$record->delete();
		}
	}

}
