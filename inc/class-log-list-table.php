<?php
/**
 * Copyright: © 2021-2022, SNS
 * License: GNU General Public License v3.0
 *
 * @author      ICT Scuola Normale Superiore
 * @category    Payment Module
 * @package     PagoPA Gateway Cineca
 * @version     1.0.17
 * @copyright   Copyright (c) 2021 SNS)
 * @license     GNU General Public License v3.0
 */

define( 'PER_PAGE_ITEMS', 20 );
require_once 'class-log-manager.php';

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/screen.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Undocumented class
 */
class Log_List_Table extends WP_List_Table {

	/**
	 * Constructor of the class.
	 */
	public function __construct() {
		// Set parent defaults.
		parent::__construct(
			array(
				'singular' => 'Log',  // singular name of the listed records.
				'plural'   => 'Logs', // plural name of the listed records.
				'ajax'     => false,  // does this table support ajax?
			)
		);
	}

	/**
	 * Undocumented function
	 *
	 * @return array - The names of the columns of the table.
	 */
	public function get_columns() {
		return array(
			'id'           => __( 'ID', 'wp-pagopa-gateway-cineca' ),
			'order_id'     => __( 'Order ID', 'wp-pagopa-gateway-cineca' ),
			'status'       => __( 'Status', 'wp-pagopa-gateway-cineca' ),
			'customer_id'  => __( 'Customer ID', 'wp-pagopa-gateway-cineca' ),
			'date_created' => __( 'Date', 'wp-pagopa-gateway-cineca' ),
			'iuv'          => __( 'Iuv', 'wp-pagopa-gateway-cineca' ),
			'description'  => __( 'Description', 'wp-pagopa-gateway-cineca' ),
		);
	}

	/**
	 * Return the array of the sortable columns.
	 *
	 * @return array - The array of the sortable columns.
	 */
	public function get_sortable_columns() {
		return array(
			'id'           => array( 'id', false ),
			'order_id'     => array( 'order_id', false ),
			'status'       => array( 'status', false ),
			'customer_id'  => array( 'customer_id', false ),
			'date_created' => array( 'date_created', false ),
			'iuv'          => array( 'iuv', false ),
			'description'  => array( 'description', false ),
		);
	}

	/**
	 * Get the log items from the databse.
	 *
	 * @return array - The items read from the database.
	 */
	private function get_items() {
		global $wpdb;

		$orderby         = ( ! empty( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'id' );
		$order           = ( ! empty( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'desc' );
		$paged           = ( ! empty( $_GET['paged'] ) ? sanitize_text_field( wp_unslash( $_GET['paged'] ) ) : '' );
		$search_string   = ( ! empty( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '' );
		$start_date      = ( ! empty( $_POST['search_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['search_start_date'] ) ) : '' );
		$end_date        = ( ! empty( $_POST['search_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['search_end_date'] ) ) : '' );
		// Get page number.
		if ( empty( $paged ) || ! is_numeric( $paged ) || ( $paged <= 0 ) ) {
			$paged = 1;
		}
		$perpage          = intval( PER_PAGE_ITEMS );
		$table_name       = $wpdb->prefix . LOG_TABLE_NAME;
		$base_query       = 'SELECT * FROM ' . $table_name;
		$totalitems       = $wpdb->query( $base_query );
		$query            = $base_query;
		$query_condition  = '';

		// Add where condition.
		if ( $search_string || $start_date ) {
			$query .= ' WHERE ';
		}

		// Find the query condition in all the fields, if required.
		if ( $search_string ) {
			$query_condition .= ' ( ';
			$query_condition .= " order_id LIKE '%{$search_string}%'";
			$query_condition .= " OR status LIKE '%{$search_string}%'";
			$query_condition .= " OR customer_id LIKE '%{$search_string}%'";
			$query_condition .= " OR date_created LIKE '%{$search_string}%'";
			$query_condition .= " OR iuv LIKE '%{$search_string}%'";
			$query_condition .= " OR description LIKE '%{$search_string}%'";
			$query_condition .= ' ) ';
			$query           .= $query_condition;
		}

		// Add and condition for the date.
		if ( $search_string && $start_date ) {
			$query           .= ' AND ';
		}

		// Add date condition, if required.
		if ( $start_date || $end_date ) {
			$query .= " date_created BETWEEN DATE('{$start_date}') AND DATE('{$end_date}')";
		}

		// Add order condition.
		$order_contition  = ' ORDER BY ' . $orderby . ' ' . $order;
		$query            = $query . ' ' . $order_contition;

		// How many pages do we have in total?
		$totalpages = ceil( $totalitems / $perpage );
		// Adjust the query to take pagination into account.
		if ( ! empty( $paged ) && ! empty( $perpage ) ) {
			$offset = ( $paged - 1 ) * $perpage;
			$query .= ' LIMIT ' . (int) $offset . ',' . (int) $perpage;
		}

		// Register the pagination.
		$this->set_pagination_args(
			array(
				'total_items' => $totalitems,
				'total_pages' => $totalpages,
				'per_page'    => $perpage,
			)
		);
		$items = $wpdb->get_results( $query, ARRAY_A );
		return $items;
	}

	/**
	 * Get the default value of a column.
	 *
	 * @param string $item - An item containing the columns.
	 * @param string $column_name - The name of the column.
	 * @return string - The value of the column.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
			case 'order_id':
			case 'customer_id':
			case 'date_created':
			case 'iuv':
			case 'description':
				return $item[ $column_name ];
			case 'status':
				$value     = $item [ $column_name ];
				$str_value = '';
				switch ( $value ) {
					case STATUS_PAYMENT_NOT_EXECUTED:
					case STATUS_PAYMENT_NOT_CONFIRMED:
					case STATUS_PAYMENT_NOT_CREATED:
						// $str_value = '<b style="color:red">' . __( $value, 'wp-pagopa-gateway-cineca' ) . '</b>';
						$str_value = '<b style="color:red">' . $value . '</b>';
						break;
					case STATUS_PAYMENT_CONFIRMED:
						// $str_value = '<b style="color:green">' . __( $value, 'wp-pagopa-gateway-cineca' ) . '</b>';
						$str_value = '<b style="color:green">' . $value . '</b>';
						break;
					default:
						// $str_value = __( $value, 'wp-pagopa-gateway-cineca' );
						$str_value = $value;
						break;
				}
				return $str_value;
		}
	}

	/**
	 * Prepare the items to show in the table.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $this->get_items();
	}

}
