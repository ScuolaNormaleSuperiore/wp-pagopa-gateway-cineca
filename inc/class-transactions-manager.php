<?php

/**
 * Copyright: © 2021-2022, SNS
 * License: GNU General Public License v3.0
 *
 * @author      ICT Scuola Normale Superiore
 * @category    Payment Module
 * @package     PagoPA 5
 * @copyright   Copyright (c) 2021 SNS)
 * @license     GNU General Public License v3.0
 */

require_once 'class-log-list-table.php';

/**
 * TRansaction manager.
 */
class Transaction_Manager
{
	/**
	 * Render inline styles and script for the transactions filters toolbar.
	 *
	 * @return void
	 */
	private function render_filters_assets()
	{
		$today = current_time( 'Y-m-d' );

		echo '<style>
			.pagopa-transactions-toolbar {
				display: flex;
				flex-wrap: wrap;
				align-items: flex-end;
				gap: 12px;
				margin: 12px 0 18px;
			}

			.pagopa-transactions-toolbar .search-box {
				float: none;
				margin: 0;
				padding: 0;
			}

			.pagopa-transactions-filter-field {
				display: flex;
				flex-direction: column;
				gap: 4px;
			}

			.pagopa-transactions-filter-field label {
				font-weight: 600;
			}

			.pagopa-transactions-filter-actions {
				display: flex;
				flex-wrap: wrap;
				gap: 8px;
				align-items: center;
			}
		</style>';

		echo '<script>
			document.addEventListener("DOMContentLoaded", function () {
				var todayButton = document.getElementById("pagopa-filter-today");
				var startDateField = document.getElementById("search_start_date");
				var endDateField = document.getElementById("search_end_date");
				var filterForm = document.getElementById("pagopa-transactions-filters");
				var today = "' . esc_js( $today ) . '";

				if (!todayButton || !startDateField || !endDateField || !filterForm) {
					return;
				}

				todayButton.addEventListener("click", function () {
					startDateField.value = today;
					endDateField.value = today;
					filterForm.submit();
				});
			});
		</script>';
	}

	/**
	 * Show the transactions occurred on the PagoPa Gateway.
	 *
	 * @return void
	 */
	public function admin_show_transactions_page()
	{
		$filters_allowed = pagopa_transactions_filter_request_is_authorized();
		$title           = __('Transactions', 'wp-pagopa-gateway-cineca');
		$start_date      = ( $filters_allowed && ! empty( $_REQUEST['search_start_date'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['search_start_date'] ) ) : '';
		$end_date        = ( $filters_allowed && ! empty( $_REQUEST['search_end_date'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['search_end_date'] ) ) : '';
		$search_string   = ( $filters_allowed && ! empty( $_REQUEST['s'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$sd_label        = __('Start date', 'wp-pagopa-gateway-cineca');
		$ed_label        = __('End date', 'wp-pagopa-gateway-cineca');
		$srch_label      = __('Search', 'wp-pagopa-gateway-cineca');
		$reset_label     = __('Reset filters', 'wp-pagopa-gateway-cineca');
		$today_label     = __('Today', 'wp-pagopa-gateway-cineca');
		$reset_url       = admin_url( 'admin.php?page=wc-edizioni-sns-activations-page' );

		$list_table = new Log_List_Table();
		$list_table->prepare_items();

		$this->render_filters_assets();

		echo '<div class="wrap">';
		echo '<h2>' . esc_attr($title) . '</h2>';
		echo '<form id="pagopa-transactions-filters" method="get">';
		echo '<input type="hidden" name="page" value="wc-edizioni-sns-activations-page" />';
		echo wp_nonce_field( PAGOPA_TRANSACTIONS_FILTER_NONCE_ACTION, PAGOPA_TRANSACTIONS_FILTER_NONCE_NAME, false, false );
		echo '<div class="pagopa-transactions-toolbar">';
		echo '<p class="search-box">
			<label class="screen-reader-text" for="search_id-search-input">' . esc_attr($srch_label) . ':</label>
			<input type="search" id="search_id-search-input" name="s" value="' . esc_attr($search_string) . '"></p>';
		echo '<div class="pagopa-transactions-filter-field">
			<label for="search_start_date">' . esc_attr($sd_label) . '</label>
			<input type="date" id="search_start_date" name="search_start_date" value="' . esc_attr($start_date) . '">
		</div>';
		echo '<div class="pagopa-transactions-filter-field">
			<label for="search_end_date">' . esc_attr($ed_label) . '</label>
			<input type="date" id="search_end_date" name="search_end_date" value="' . esc_attr($end_date) . '">
		</div>';
		echo '<div class="pagopa-transactions-filter-actions">
			<input type="submit" id="search-submit" class="button button-primary" value="' . esc_attr($srch_label) . '">
			<button type="button" id="pagopa-filter-today" class="button button-secondary">' . esc_html($today_label) . '</button>
			<a href="' . esc_url($reset_url) . '" class="button button-secondary">' . esc_html($reset_label) . '</a>
		</div>';
		echo '</div>';
		echo '</form>';

		$list_table->display();
		echo '</div>';
	}
}
