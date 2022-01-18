<?php
/**
 * Copyright: © 2021-2022, SNS
 * License: GNU General Public License v3.0
 *
 * @author      ICT Scuola Normale Superiore
 * @category    Payment Module
 * @package     PagoPA Gateway Cineca
 * @version     1.0.11-beta
 * @copyright   Copyright (c) 2021 SNS)
 * @license     GNU General Public License v3.0
 */

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
		$title         = __( 'Transactions', 'wp-pagopa-gateway-cineca' );
		$start_date    = ( ! empty( $_POST['search_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['search_start_date'] ) ) : '' );
		$end_date      = ( ! empty( $_POST['search_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['search_end_date'] ) ) : '' );
		$search_string = ( ! empty( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '' );
		$sd_label      = __( 'Start date', 'wp-pagopa-gateway-cineca' );
		$ed_label      = __( 'End date', 'wp-pagopa-gateway-cineca' );
		$srch_label    = __( 'Search', 'wp-pagopa-gateway-cineca' );

		$list_table = new Log_List_Table();
		$list_table->prepare_items();

		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="wc-edizioni-sns-activations-page" />';
		echo '<p class="search-box">
			<label class="screen-reader-text" for="search_id-search-input">' . esc_attr( $srch_label ) . ':</label>
			<input type="search" id="search_id-search-input" name="s" value="' . esc_attr( $search_string ) . '">
			<input type="submit" id="search-submit" class="button" value="' . esc_attr( $srch_label ) . '"></p>';
		echo '<span style="float: right"><input type="date" id="search_end_date" name="search_end_date" value="' . esc_attr( $end_date ) . '"> <b>' . esc_attr( $ed_label ) . '</b>&nbsp;</span>';
		echo '<span style="float: right"><input type="date" id="search_start_date" name="search_start_date" value="' . esc_attr( $start_date ) . '"> <b>' . esc_attr( $sd_label ) . '</b>&nbsp;</span>';
		echo '</form>';

		echo '<div class=\"wrap\">';
		echo '<h2>' . esc_attr( $title ) . '</h2>';

		$list_table->display();
		echo '</div>';
	}

}
