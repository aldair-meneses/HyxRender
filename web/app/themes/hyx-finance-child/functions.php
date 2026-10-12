<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'HYX_FINANCE_CHILD_VERSION', '0.1.0' );
define( 'HYX_FINANCE_CHILD_DIR', __DIR__ );

require_once HYX_FINANCE_CHILD_DIR . '/inc/finance-table.php';
require_once HYX_FINANCE_CHILD_DIR . '/inc/rest-api.php';

add_action( 'wp_enqueue_scripts', function (): void {
	wp_enqueue_style(
		'hyx-finance-child',
		get_stylesheet_uri(),
		array( 'generatepress-style' ),
		HYX_FINANCE_CHILD_VERSION
	);
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'hyx-finance', 'Hyx_Finance_CLI' );
}

class Hyx_Finance_CLI {

	public function reset( array $args, array $assoc_args ): void {
		$seed_count = isset( $assoc_args['seed'] ) ? (int) $assoc_args['seed'] : 25;

		Hyx_Finance_Table::reset();
		WP_CLI::success( sprintf( 'Table %s recreated.', Hyx_Finance_Table::name() ) );

		if ( $seed_count > 0 ) {
			$inserted = Hyx_Finance_Table::seed( $seed_count );
			WP_CLI::success( sprintf( 'Seeded %d rows.', $inserted ) );
		}
	}
}
