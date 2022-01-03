<?php
/**
 * Copyright: © 2021-2022, SNS
 * License: GNU General Public License v3.0
 *
 * @author      ICT Scuola Normale Superiore
 * @category    Payment Module
 * @package     PagoPA Gateway Cineca
 * @version     1.0.6-b1
 * @copyright   Copyright (c) 2021 SNS)
 * @license     GNU General Public License v3.0
 */

// require_once 'class-log-manager.php';
require_once 'class-log-list-table.php';

/**
 * TRansaction manager.
 */
class Transaction_Manager {

	/**
	 * Show the transactions occurred on the PagoPa Gateway.
	 *
	 * @return void
	 */
	public function admin_show_transactions_page() {
		error_log( '@@@ admin_show_transactions_page' );
		$title      = __( 'Transactions', 'wp-pagopa-gateway-cineca' );
		$start_date = ( ! empty( $_POST['search_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['search_start_date'] ) ) : '' );
		$end_date   = ( ! empty( $_POST['search_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['search_end_date'] ) ) : '' );
		$sd_label   = __( 'Start date', 'wp-pagopa-gateway-cineca' );
		$ed_label   = __( 'End date', 'wp-pagopa-gateway-cineca' );
		$srch_label = __( 'Search', 'wp-pagopa-gateway-cineca' );

		$list_table = new Log_List_Table();
		$list_table->prepare_items();

		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="wc-edizioni-sns-activations-page" />';
		// $list_table->search_box( __( 'Search', 'wp-pagopa-gateway-cineca' ), 'search_id' );
		echo '<p class="search-box">
			<label class="screen-reader-text" for="search_id-search-input">' . $srch_label . ':</label>
			<input type="search" id="search_id-search-input" name="s" value="">
			<input type="submit" id="search-submit" class="button" value="Search"></p>';
		echo '<span style="float: right"><input type="date" id="search_end_date" name="search_end_date" value="' . $end_date . '"> <b>' . $ed_label . '</b>&nbsp;</span>';
		echo '<span style="float: right"><input type="date" id="search_start_date" name="search_start_date" value="' . $start_date . '" <b>' . $sd_label . '</b>&nbsp;</span>';
		echo '</form>';

		echo '<div class=\"wrap\">';
		echo '<h2>' . $title . '</h2>';

		$list_table->display();
		echo '</div>';
	}

}
