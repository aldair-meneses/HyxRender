<?php
/**
 * Transactions table schema and demo seeding.
 *
 * @package HyxRenderDemo
 */

declare( strict_types=1 );

namespace Hyx\RenderDemo;

/**
 * The `{prefix}hyx_transactions` custom table.
 */
final class TransactionsTable {

	private const CATALOG = array(
		'expense' => array(
			'Groceries' => array( 'Supermarket run', 'Weekly groceries', 'Corner market' ),
			'Rent'      => array( 'Monthly rent', 'Rent payment' ),
			'Transport' => array( 'Bus pass', 'Fuel refill', 'Rideshare trip' ),
			'Dining'    => array( 'Lunch out', 'Coffee shop', 'Dinner delivery' ),
			'Utilities' => array( 'Electricity bill', 'Internet bill', 'Water bill' ),
			'Health'    => array( 'Pharmacy', 'Gym membership', 'Doctor visit' ),
			'Leisure'   => array( 'Cinema tickets', 'Streaming subscription', 'Books' ),
		),
		'income'  => array(
			'Salary'    => array( 'Monthly salary', 'Salary deposit' ),
			'Freelance' => array( 'Client payment', 'Side project payout' ),
			'Dividends' => array( 'Dividend payout', 'Interest credit' ),
			'Refund'    => array( 'Store refund', 'Tax refund' ),
		),
	);

	/**
	 * Full table name, including the site prefix.
	 */
	public function name(): string {
		global $wpdb;

		return $wpdb->prefix . 'hyx_transactions';
	}

	/**
	 * Creates the table, or updates its schema when it already exists.
	 */
	public function create(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			sprintf(
				'CREATE TABLE `%s` (
					id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					description VARCHAR(191) NOT NULL,
					amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
					type VARCHAR(10) NOT NULL DEFAULT \'expense\',
					category VARCHAR(100) NOT NULL DEFAULT \'\',
					transaction_date DATE NOT NULL,
					created_at DATETIME NOT NULL,
					PRIMARY KEY (id),
					KEY type (type),
					KEY transaction_date (transaction_date)
				) %s;',
				esc_sql( $this->name() ),
				$wpdb->get_charset_collate()
			)
		);
	}

	/**
	 * Drops and recreates the table, leaving it empty.
	 */
	public function reset(): void {
		global $wpdb;

		$wpdb->query( sprintf( 'DROP TABLE IF EXISTS `%s`', esc_sql( $this->name() ) ) );
		$this->create();
	}

	/**
	 * Inserts random transactions from the last 180 days.
	 *
	 * @param int $count How many rows to insert.
	 * @return int Rows actually inserted.
	 */
	public function seed( int $count ): int {
		global $wpdb;

		$this->create();

		$inserted = 0;

		for ( $i = 0; $i < $count; $i++ ) {
			$type       = wp_rand( 0, 99 ) < 30 ? 'income' : 'expense';
			$categories = array_keys( self::CATALOG[ $type ] );
			$category   = $categories[ wp_rand( 0, count( $categories ) - 1 ) ];
			$labels     = self::CATALOG[ $type ][ $category ];
			$cents      = 'income' === $type ? wp_rand( 5000, 600000 ) : wp_rand( 300, 60000 );

			$result = $wpdb->insert(
				$this->name(),
				array(
					'description'      => $labels[ wp_rand( 0, count( $labels ) - 1 ) ] . ' #' . ( $i + 1 ),
					'amount'           => number_format( $cents / 100, 2, '.', '' ),
					'type'             => $type,
					'category'         => $category,
					'transaction_date' => gmdate( 'Y-m-d', strtotime( sprintf( '-%d days', wp_rand( 0, 180 ) ) ) ),
					'created_at'       => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false !== $result ) {
				++$inserted;
			}
		}

		return $inserted;
	}
}
