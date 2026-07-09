<?php

/**
 * Copyright: © 2021-2022, SNS
 * License: GNU General Public License v3.0
 *
 * @author      ICT Scuola Normale Superiore
 * @category    Payment Module
 * @package     PagoPA Gateway Cineca
 * @version     1.2.6
 * @copyright   Copyright (c) 2021 SNS)
 * @license     GNU General Public License v3.0
 */

define('PER_PAGE_ITEMS', 20);
define( 'PAGOPA_TRANSACTIONS_FILTER_NONCE_ACTION', 'pagopa_transactions_filter' );
define( 'PAGOPA_TRANSACTIONS_FILTER_NONCE_NAME', 'pagopa_transactions_filter_nonce' );
require_once 'class-log-manager.php';

if (!class_exists('WP_List_Table')) {
	require_once ABSPATH . 'wp-admin/includes/screen.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! function_exists( 'pagopa_transactions_filter_request_is_authorized' ) ) {
	/**
	 * Check whether the transactions filter request can be trusted.
	 *
	 * The nonce is required only when the request actively applies filters.
	 *
	 * @return bool
	 */
	function pagopa_transactions_filter_request_is_authorized()
	{
		$has_filters = ! empty( $_REQUEST['s'] ) || ! empty( $_REQUEST['search_start_date'] ) || ! empty( $_REQUEST['search_end_date'] );
		if ( ! $has_filters ) {
			return true;
		}

		$nonce = ! empty( $_REQUEST[ PAGOPA_TRANSACTIONS_FILTER_NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( $_REQUEST[ PAGOPA_TRANSACTIONS_FILTER_NONCE_NAME ] ) )
			: '';

		return $nonce && wp_verify_nonce( $nonce, PAGOPA_TRANSACTIONS_FILTER_NONCE_ACTION );
	}
}

/**
 * Undocumented class
 */
class Log_List_Table extends WP_List_Table
{

	/**
	 * Constructor of the class.
	 */
	public function __construct()
	{
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
	public function get_columns()
	{
		return array(
			'id'           => __('ID', 'wp-pagopa-gateway-cineca'),
			'order_id'     => __('Order ID', 'wp-pagopa-gateway-cineca'),
			'status'       => __('Status', 'wp-pagopa-gateway-cineca'),
			'customer_id'  => __('Customer ID', 'wp-pagopa-gateway-cineca'),
			'date_created' => __('Date', 'wp-pagopa-gateway-cineca'),
			'iuv'          => __('Iuv', 'wp-pagopa-gateway-cineca'),
			'description'  => __('Description', 'wp-pagopa-gateway-cineca'),
		);
	}

	/**
	 * Return the array of the sortable columns.
	 *
	 * @return array - The array of the sortable columns.
	 */
	public function get_sortable_columns()
	{
		return array(
			'id'           => array('id', false),
			'order_id'     => array('order_id', false),
			'status'       => array('status', false),
			'customer_id'  => array('customer_id', false),
			'date_created' => array('date_created', false),
			'iuv'          => array('iuv', false),
			'description'  => array('description', false),
		);
	}

	/**
	 * Get the log items from the databse.
	 *
	 * @return array - The items read from the database.
	 */
	private function get_items()
	{
		global $wpdb;

		// Validate orderby and order against a whitelist to prevent SQL injection.
		$allowed_orderby = array( 'id', 'order_id', 'status', 'customer_id', 'date_created', 'iuv', 'description' );
		$allowed_order   = array( 'asc', 'desc' );
		$filters_allowed = pagopa_transactions_filter_request_is_authorized();
		$orderby         = (!empty($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'id');
		$order           = (!empty($_REQUEST['order']) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'desc');
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'id';
		$order           = in_array( strtolower( $order ), $allowed_order, true ) ? strtolower( $order ) : 'desc';
		$paged           = (!empty($_REQUEST['paged']) ? sanitize_text_field(wp_unslash($_REQUEST['paged'])) : '');
		$search_string   = ( $filters_allowed && ! empty( $_REQUEST['s'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$start_date      = ( $filters_allowed && ! empty( $_REQUEST['search_start_date'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['search_start_date'] ) ) : '';
		$end_date        = ( $filters_allowed && ! empty( $_REQUEST['search_end_date'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['search_end_date'] ) ) : '';
		// Get page number.
		if (empty($paged) || !is_numeric($paged) || ($paged <= 0)) {
			$paged = 1;
		}
		$perpage         = intval(PER_PAGE_ITEMS);
		$table_name      = $wpdb->prefix . LOG_TABLE_NAME;
		$base_query      = 'SELECT * FROM ' . $table_name;
		$count_query     = 'SELECT COUNT(*) FROM ' . $table_name;
		$query           = $base_query;
		$where_clauses   = array();

		// Find the query condition in all the fields, if required.
		if ($search_string) {
			$like = '%' . $wpdb->esc_like( $search_string ) . '%';
			$where_clauses[] =
				$wpdb->prepare( 'order_id LIKE %s', $like ) .
				$wpdb->prepare( ' OR status LIKE %s', $like ) .
				$wpdb->prepare( ' OR customer_id LIKE %s', $like ) .
				$wpdb->prepare( ' OR date_created LIKE %s', $like ) .
				$wpdb->prepare( ' OR iuv LIKE %s', $like ) .
				$wpdb->prepare( ' OR description LIKE %s', $like );
			$where_clauses[ count( $where_clauses ) - 1 ] = '( ' . $where_clauses[ count( $where_clauses ) - 1 ] . ' )';
		}

		// Add date condition, if required.
		if ($start_date || $end_date) {
			$date_from = $start_date ? $start_date : '0001-01-01';
			$date_to   = $end_date ? $end_date : '9999-12-31';
			$where_clauses[] = $wpdb->prepare( 'DATE(date_created) BETWEEN DATE(%s) AND DATE(%s)', $date_from, $date_to );
		}

		if ( ! empty( $where_clauses ) ) {
			$where_sql   = ' WHERE ' . implode( ' AND ', $where_clauses );
			$query      .= $where_sql;
			$count_query .= $where_sql;
		}

		$totalitems = (int) $wpdb->get_var( $count_query );

		// Add order condition (orderby and order are already validated against a whitelist).
		$order_contition  = ' ORDER BY ' . $orderby . ' ' . $order;
		$query            = $query . ' ' . $order_contition;

		// How many pages do we have in total?
		$totalpages = ceil($totalitems / $perpage);
		// Adjust the query to take pagination into account.
		if (!empty($paged) && !empty($perpage)) {
			$offset = ($paged - 1) * $perpage;
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
		$items = $wpdb->get_results($query, ARRAY_A);
		return $items;
	}

	/**
	 * Get the default value of a column.
	 *
	 * @param string $item - An item containing the columns.
	 * @param string $column_name - The name of the column.
	 * @return string - The value of the column.
	 */
	public function column_default($item, $column_name)
	{
		switch ($column_name) {
			case 'id':
			case 'order_id':
			case 'customer_id':
			case 'date_created':
			case 'iuv':
			case 'description':
				return esc_html( (string) $item[$column_name] );
			case 'status':
				$value     = esc_html( (string) $item[$column_name] );
				$str_value = '';
				switch ($value) {
					case STATUS_PAYMENT_NOT_EXECUTED:
					case STATUS_PAYMENT_NOT_CONFIRMED:
					case STATUS_PAYMENT_NOT_CREATED:
						$str_value = '<strong style="color:red">' . $value . '</strong>';
						break;
					case STATUS_PAYMENT_CONFIRMED:
						$str_value = '<strong style="color:green">' . $value . '</strong>';
						break;
					default:
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
	public function prepare_items()
	{
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items           = $this->get_items();
	}
}
