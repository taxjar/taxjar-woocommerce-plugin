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
		if ( apply_filters( 'taxjar_enabled', isset( $this->taxjar_integration->settings['enabled'] ) && 'yes' == $this->taxjar_integration->settings['enabled'] ) ) {
            add_action( 'show_user_profile', array( $this, 'add_customer_meta_fields' ) );
            add_action( 'edit_user_profile', array( $this, 'add_customer_meta_fields' ) );

            add_action( 'personal_options_update', array( $this, 'save_customer_meta_fields' ) );
            add_action( 'edit_user_profile_update', array( $this, 'save_customer_meta_fields' ) );

            add_action( 'taxjar_customer_exemption_settings_updated', array( $this, 'maybe_sync_customer_on_update' ) );

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
			'taxjar_customer_meta_fields', array(
				'exemptions'  => array(
					'title'  => __( 'TaxJar Sales Tax Exemptions', 'wc-taxjar' ),
					'fields' => array(
						'tax_exemption_type'  => array(
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
                                    if ( ! empty( $saved_value ) && in_array( $option_key, $saved_value ) ) {
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

	public function save_customer_meta_fields( $user_id ) {
		if ( ! apply_filters( 'taxjar_current_user_can_edit_customer_meta_fields', current_user_can( 'manage_woocommerce' ), $user_id ) ) {
			return;
		}

		$save_fields = $this->get_customer_meta_fields();
		$change = false;
		foreach ( $save_fields as $fieldset ) {
			foreach ( $fieldset['fields'] as $key => $field ) {
				if ( isset( $field['type'] ) && 'multi-select' === $field['type'] ) {
				    $prev_value = get_user_meta( $user_id, $key, true );
				    if ( empty( $_POST[ $key ] ) ) {
				        $exempt_regions = '';
                    } else {
				        $exempt_regions = array_map( 'wc_clean',  $_POST[ $key ] );
				        $exempt_regions = implode( ',', $exempt_regions );
                    }

				    if ( $exempt_regions != $prev_value ) {
					    update_user_meta( $user_id, $key, $exempt_regions );
					    $change = true;
                    }
				} elseif ( isset( $_POST[ $key ] ) ) {
					$prev_value = get_user_meta( $user_id, $key, true );
					$new_value = wc_clean( $_POST[ $key ] );
					if ( $prev_value != $new_value ) {
						update_user_meta( $user_id, $key, $new_value );
						$change = true;
                    }
				}
			}
		}

		if ( $change ) {
		    do_action( 'taxjar_customer_exemption_settings_updated', $user_id );
        }
	}

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

	public static function get_customer_exemption_types() {
		return array(
			'wholesale' => __( 'Wholesale / Resale', 'wc-taxjar' ),
			'government' => __( 'Government', 'wc-taxjar' ),
			'other' => __( 'Other', 'wc-taxjar' ),
		);
	}

	public static function get_all_exempt_regions() {
		return array(
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
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
	 * @param $id - user id
	 */
	public function maybe_delete_customer( $id ) {
		$last_sync = get_user_meta( $id, '_taxjar_last_sync', true );
		$hash = get_user_meta( $id, '_taxjar_hash', true );
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