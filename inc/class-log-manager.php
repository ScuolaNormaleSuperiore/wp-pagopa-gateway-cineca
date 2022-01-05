<?php
/**
 * Copyright: © 2021-2022, SNS
 * License: GNU General Public License v3.0
 *
 * @author      ICT Scuola Normale Superiore
 * @category    Payment Module
 * @package     PagoPA Gateway Cineca
 * @version     1.0.10-b1
 * @copyright   Copyright (c) 2021 SNS)
 * @license     GNU General Public License v3.0
 */

define( 'LOG_TABLE_NAME', 'gwpagopa_log' );
define( 'LOG_TABLE_VERSION_OPTION', 'gwpagopa_log_db_version' );
define( 'LOG_DB_VERSION', '0.1' );

// Payment status.
define( 'STATUS_PAYMENT_SUBMITTED', 'submitted' );
define( 'STATUS_PAYMENT_CREATED', 'created' );
define( 'STATUS_PAYMENT_NOT_CREATED', 'not_created' );
define( 'STATUS_PAYMENT_EXECUTED', 'executed' );
define( 'STATUS_PAYMENT_NOT_EXECUTED', 'not_executed' );
define( 'STATUS_PAYMENT_CONFIRMED', 'confirmed' );
define( 'STATUS_PAYMENT_CONFIRMED_BY_SCRIPT', 'confirmed_by_script' );
define( 'STATUS_PAYMENT_NOT_CONFIRMED', 'not_confirmed' );

/**
 * Log_Manager class
 */
class Log_Manager {

	/**
	 * Create the Log Manager.
	 *
	 * @param WP_Order $order - The order of the payment.
	 */
	public function __construct( $order ) {
		$this->order = $order;
	}

	/**
	 * Log the status of the payment.
	 *
	 * @param string $status - The status of the payment.
	 * @param string $iuv - The iuv of the payment.
	 * @param string $description -  A decription of the status or of the error occurred.
	 * @return void
	 */
	public function log( $status, $iuv = null, $description = null ) {
		global $wpdb;
		$table_name     = $wpdb->prefix . LOG_TABLE_NAME;
		$logged_user_id = get_current_user_id();
		$order_id       = $this->order->get_order_number();
		$wpdb->insert(
			$table_name,
			array(
				'customer_id' => $logged_user_id,
				'order_id'    => $order_id,
				'status'      => $status,
				'iuv'         => $iuv ? $iuv : null,
				'description' => $description ? $description : null,
			)
		);
	}

	/**
	 * Create or update the log table
	 *
	 * @return void
	 */
	public static function init_table() {
		global $wpdb;
		$installed_version = get_option( LOG_TABLE_VERSION_OPTION );

		// Use the same table prefix in wp-config.php.
		$table_name = $wpdb->prefix . LOG_TABLE_NAME;

		// Set the default character set and collation for the table to avoid the "?" conversion.
		$charset_collate = $wpdb->get_charset_collate();
		if ( LOG_DB_VERSION !== $installed_version ) {
			// Add the SQL statement for creating a database table.
			$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			customer_id bigint(20) UNSIGNED NOT NULL,
			date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) NOT NULL,
			iuv varchar(256),
			description text, 
			PRIMARY KEY  (id)
			) $charset_collate;";
		}

		// To use the dbDelta class, we have to load this file, as it is not loaded by default.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// call the dbDelta class.
		dbDelta( $sql );

		update_option( LOG_TABLE_VERSION_OPTION, LOG_DB_VERSION );
	}

	/**
	 * Undocumented function
	 *
	 * @param  string $order_id - The number of the order.
	 * @param  string $iuv - The iuv of the payment.
	 * @return string - The status of the payment.
	 */
	public function get_current_status( $order_id, $iuv ) {
		global $wpdb;
		$table_name = $wpdb->prefix . LOG_TABLE_NAME;

		$sql    = $wpdb->prepare( "SELECT status FROM {$table_name}  WHERE order_id=%s AND iuv=%s ORDER BY id DESC LIMIT 1", $order_id, $iuv );
		$status = $wpdb->get_var( $sql );

		return $status ? $status : '';
	}

	/**
	 * Drop the log table
	 *
	 * @return void
	 */
	public static function drop_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . LOG_TABLE_NAME;
		$sql        = "DROP TABLE IF EXISTS $table_name;";
		$wpdb->query( $sql );

		// Delete the version number of the table.
		delete_option( LOG_TABLE_VERSION_OPTION );
	}

}
