<?php
/**
 * Class WC_Report_Customer_List file.
 *
 * @package WooCommerce\Reports
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WC_Taxjar_Queue_List.
 */
class WC_Taxjar_Queue_List extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct(
			array(
				'singular' => 'record',
				'plural'   => 'records',
				'ajax'     => false,
			)
		);
	}

	/**
	 * No items found text.
	 */
	public function no_items() {
		esc_html_e( 'No records found in queue.', 'wc-taxjar' );
	}

	/**
	 * Output the report.
	 */
	public function output_report() {
        echo '</form>';
        $this->prepare_items();

        echo '<div class="wrap">';
        echo '<form method="get" id="taxjar_sync_queue">';

        $this->search_box( __( 'Search by record (post) ID', 'wc-taxjar' ), 'record_search' );
        $this->display();

        echo '</form>';
        echo '</div>';
    }

	/**
	 * Display tablenav
	 */
	protected function display_tablenav( $which ) {
		?>
        <input type="hidden" name="page" value="wc-settings">
        <input type="hidden" name="tab" value="taxjar-integration">
        <input type="hidden" name="section" value="sync_queue">
        <div class="tablenav <?php echo esc_attr( $which ); ?>">

			<?php if ( $this->has_items() ) : ?>
                <div class="alignleft actions bulkactions">
					<?php $this->bulk_actions( $which ); ?>
                </div>
			<?php
			endif;
			$this->extra_tablenav( $which );
			$this->pagination( $which );
			?>

            <br class="clear" />
        </div>
		<?php
	}

	/**
	 * Get column value.
	 *
	 * @param WP_User $user WP User object.
	 * @param string  $column_name Column name.
	 * @return string
	 */
	public function column_default( $record, $column_name ) {
		switch ( $column_name ) {

			case 'queue_id':
				return $record->queue_id;

			case 'record_id':
				return $record->record_id;

			case 'record_type':
				return ucfirst( $record->record_type );

			case 'status':
				if ( $record->status == 'new' || $record->status == 'awaiting' ) {
					return 'Awaiting';
				} else if ( $record->status == 'completed' ) {
					return 'Synced';
                } else if ( $record->status == 'failed' ) {
				    return 'Failed';
                }
				return $record->status;

			case 'created_datetime':
				return get_date_from_gmt( $record->created_datetime );

			case 'processed_datetime':
			    if ( $record->processed_datetime == '0000-00-00 00:00:00' ) {
			        return '';
                }
				return get_date_from_gmt( $record->processed_datetime );

			case 'retry_count':
				return $record->retry_count;

            case 'last_error':
                return $record->last_error;

			case 'taxjar_actions':
				ob_start();
				?><p>
				<?php
				do_action( 'taxjar_admin_record_actions_start', $record );

				$actions = array();

				$post_id = $record->record_id;
				if ( $record->record_type == 'refund' ) {
				    $post_id = wp_get_post_parent_id( $record->record_id );
                }

				$actions['view'] = array(
					'url'    => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
					'name'   => __( 'View Record', 'wc-taxjar' ),
					'action' => 'view',
				);

				$actions = apply_filters( 'taxjar_admin_record_actions', $actions, $record );

				foreach ( $actions as $action ) {
					printf( '<a class="button tips %s" href="%s" data-tip="%s">%s</a>', esc_attr( $action['action'] ), esc_url( $action['url'] ), esc_attr( $action['name'] ), esc_attr( $action['name'] ) );
				}

				do_action( 'taxjar_admin_record_actions_end', $record );
				?>
				</p>
				<?php
				$record_actions = ob_get_contents();
				ob_end_clean();

				return $record_actions;
		}

		return '';
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'queue_id'              => __( 'Queue ID', 'wc-taxjar' ),
			'record_id'             => __( 'Record ID', 'wc-taxjar' ),
			'record_type'           => __( 'Record Type', 'wc-taxjar' ),
			'status'                => __( 'Sync Status', 'wc-taxjar' ),
			'created_datetime'      => __( 'Created Time', 'wc-taxjar' ),
			'processed_datetime'    => __( 'Sync Time', 'wc-taxjar' ),
			'retry_count'           => __( 'Retry Count', 'wc-taxjar' ),
			'last_error'            => __( 'Last Error', 'wc-taxjar' ),
			'taxjar_actions'        => __( 'Actions', 'wc-taxjar' ),
		);

		return $columns;
	}

	/**
	 * Prepare customer list items.
	 */
	public function prepare_items() {
		$current_page          = absint( $this->get_pagenum() );
		$per_page              = absint( apply_filters( 'taxjar_queue_list_per_page', 20 ) );
		$offset                = absint( ( $current_page - 1 ) * $per_page );

		/**
		 * Init column headers.
		 */
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		global $wpdb;
		$table_name = WC_Taxjar_Record_Queue::get_queue_table_name();
		$where = " WHERE 1=1 " ;
		$query = "SELECT * FROM {$table_name}";

		if ( isset( $_REQUEST[ 'taxjar_record_status' ] ) ) {
			if ( $_REQUEST[ 'taxjar_record_status' ] == 'completed' ) {
				$where .= "AND status = 'completed' ";
			} else if ( $_REQUEST[ 'taxjar_record_status' ] == 'awaiting' ) {
				$where .= "AND status IN ( 'new', 'awaiting' ) ";
			} else if ( $_REQUEST[ 'taxjar_record_status' ] == 'failed' ) {
				$where .= "AND status = 'failed' ";
			}
        }

		if ( isset( $_REQUEST[ 'taxjar_record_type' ] ) ) {
			if ( $_REQUEST[ 'taxjar_record_type' ] == 'order' ) {
				$where .= "AND record_type = 'order' ";
			} else if ( $_REQUEST[ 'taxjar_record_type' ] == 'refund' ) {
				$where .= "AND record_type = 'refund' ";
			} else if ( $_REQUEST[ 'taxjar_record_type' ] == 'customer' ) {
				$where .= "AND record_type = 'customer' ";
			}
		}

		if ( isset( $_REQUEST[ 's' ] ) && ! empty( $_REQUEST[ 's' ] ) ) {
		    $search = sanitize_text_field(  $_REQUEST[ 's' ] );
			$where .= "AND record_id = '{$search}' ";
		}

        $query .= $where . "ORDER BY queue_id DESC LIMIT {$offset}, {$per_page}";
		$this->items = $wpdb->get_results( $query );

		$total_query = "SELECT COUNT(*) FROM {$table_name}" . $where;
		$total_records = $wpdb->get_var( $total_query );

		$this->set_pagination_args(
			array(
				'total_items' => $total_records,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_records / $per_page ),
			)
		);
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination
	 *
	 * @since 3.1.0
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' != $which ) {
			return;
		}
		?>
        <select name='taxjar_record_type' id='dropdown_taxjar_record_type'>
            <option value=""><?php esc_html_e( 'All record types', 'wc-taxjar' ); ?></option>
			<?php
			$record_types = apply_filters( 'taxjar_record_type_dropdown', array(
				'order'    => _x( 'Orders', 'A record type', 'wc-taxjar' ),
				'refund'      => _x( 'Refunds', 'A record type', 'wc-taxjar' ),
				'customer'      => _x( 'Customers', 'A record type', 'wc-taxjar' ),
			) );

			foreach ( $record_types as $record_type_key => $record_type_description ) {
				echo '<option value="' . esc_attr( $record_type_key ) . '"';

				if ( isset( $_REQUEST['taxjar_record_type'] ) && $_REQUEST['taxjar_record_type'] ) {
					selected( $record_type_key, $_REQUEST['taxjar_record_type'] );
				}

				echo '>' . esc_html( $record_type_description ) . '</option>';
			}
			?>
        </select>

        <select name='taxjar_record_status' id='dropdown_taxjar_record_status'>
            <option value=""><?php esc_html_e( 'All sync statuses', 'wc-taxjar' ); ?></option>
			<?php
			$record_statuses = apply_filters( 'taxjar_record_status_dropdown', array(
				'awaiting'    => _x( 'Awaiting', 'A sync status', 'wc-taxjar' ),
				'completed'   => _x( 'Completed', 'A sync status', 'wc-taxjar' ),
				'failed'      => _x( 'Failed', 'A sync status', 'wc-taxjar' ),
			) );

			foreach ( $record_statuses as $record_status_key => $record_status_description ) {
				echo '<option value="' . esc_attr( $record_status_key ) . '"';

				if ( isset( $_REQUEST['taxjar_record_status'] ) && $_REQUEST['taxjar_record_status'] ) {
					selected( $record_status_key, $_REQUEST['taxjar_record_status'] );
				}

				echo '>' . esc_html( $record_status_description ) . '</option>';
			}
			?>
        </select>
        <input type="submit" class="button">
		<?php
    }
}
