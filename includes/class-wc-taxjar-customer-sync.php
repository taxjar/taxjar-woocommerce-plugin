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
		if ( isset( $this->taxjar_integration->settings['taxjar_download'] ) && 'yes' == $this->taxjar_integration->settings['taxjar_download'] ) {
			add_action( 'show_user_profile', array( $this, 'add_customer_meta_fields' ) );
			add_action( 'edit_user_profile', array( $this, 'add_customer_meta_fields' ) );

			add_action( 'personal_options_update', array( $this, 'save_customer_meta_fields' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_customer_meta_fields' ) );
		}
	}

	/**
	 * Prints debug info to wp-content/uploads/wc-logs/taxjar-transaction-sync-*.log
	 *
	 * @return void
	 */
	public function _log( $message ) {
		do_action( 'taxjar_transaction_sync_log', $message );
		if ( $this->taxjar_integration->debug ) {
			if ( ! isset( $this->log ) ) {
				$this->log = new WC_Logger();
			}
			if ( is_array( $message ) || is_object( $message ) ) {
				$this->log->add( 'taxjar-transaction-sync', print_r( $message, true ) );
			} else {
				$this->log->add( 'taxjar-transaction-sync', $message );
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
							'description' => __( 'Hold CTRL to select multiple states.', 'wc-taxjar' ),
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
							<select multiple name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( $field['class'] ); ?>" style="width: 25em;">
								<?php
								$selected = esc_attr( get_user_meta( $user->ID, $key, true ) );
								foreach ( $field['options'] as $option_key => $option_value ) :
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

		foreach ( $save_fields as $fieldset ) {
			foreach ( $fieldset['fields'] as $key => $field ) {
				if ( isset( $field['type'] ) && 'checkbox' === $field['type'] ) {
					update_user_meta( $user_id, $key, isset( $_POST[ $key ] ) );
				} elseif ( isset( $_POST[ $key ] ) ) {
					update_user_meta( $user_id, $key, wc_clean( $_POST[ $key ] ) );
				}
			}
		}
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
			'LA' => ' Louisiana',
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

}