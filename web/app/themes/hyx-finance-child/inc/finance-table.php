<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

final class Hyx_Finance_Table {

	public static function name(): string {
		global $wpdb;

		return $wpdb->prefix . 'hyx_transactions';
	}

	public static function reset(): void {
		global $wpdb;

		$table = self::name();

		$wpdb->query( sprintf( 'DROP TABLE IF EXISTS `%s`', esc_sql( $table ) ) );

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$sql = sprintf(
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
			esc_sql( $table ),
			$charset
		);

		dbDelta( $sql );
	}

	public static function seed( int $count = 25 ): int {
		global $wpdb;

		$table = self::name();

		$catalog = array(
			'expense' => array(
				'Groceries'   => array( 'Supermarket run', 'Weekly groceries', 'Corner market' ),
				'Rent'        => array( 'Monthly rent', 'Rent payment' ),
				'Transport'   => array( 'Bus pass', 'Fuel refill', 'Rideshare trip' ),
				'Dining'      => array( 'Lunch out', 'Coffee shop', 'Dinner delivery' ),
				'Utilities'   => array( 'Electricity bill', 'Internet bill', 'Water bill' ),
				'Health'      => array( 'Pharmacy', 'Gym membership', 'Doctor visit' ),
				'Leisure'     => array( 'Cinema tickets', 'Streaming subscription', 'Books' ),
			),
			'income'  => array(
				'Salary'      => array( 'Monthly salary', 'Salary deposit' ),
				'Freelance'   => array( 'Client payment', 'Side project payout' ),
				'Dividends'   => array( 'Dividend payout', 'Interest credit' ),
				'Refund'      => array( 'Store refund', 'Tax refund' ),
			),
		);

		$inserted = 0;

		for ( $i = 0; $i < $count; $i++ ) {
			$type       = wp_rand( 0, 99 ) < 30 ? 'income' : 'expense';
			$categories = array_keys( $catalog[ $type ] );
			$category   = $categories[ wp_rand( 0, count( $categories ) - 1 ) ];
			$labels     = $catalog[ $type ][ $category ];

			if ( 'income' === $type ) {
				$amount = mt_rand( 5000, 600000 ) / 100;
			} else {
				$amount = mt_rand( 300, 60000 ) / 100;
			}

			$days_ago = wp_rand( 0, 180 );

			$result = $wpdb->insert(
				$table,
				array(
					'description'      => $labels[ wp_rand( 0, count( $labels ) - 1 ) ] . ' #' . ( $i + 1 ),
					'amount'           => number_format( $amount, 2, '.', '' ),
					'type'             => $type,
					'category'         => $category,
					'transaction_date' => gmdate( 'Y-m-d', strtotime( sprintf( '-%d days', $days_ago ) ) ),
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
