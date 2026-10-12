<?php
/**
 * Demo plugin composition root.
 *
 * @package HyxRenderDemo
 */

declare( strict_types=1 );

namespace Hyx\RenderDemo;

use WP_CLI;

/**
 * Wires the transactions table, REST API and WP-CLI commands.
 */
final class Plugin {

	/**
	 * Boots the demo plugin.
	 */
	public static function boot(): void {
		$table = new TransactionsTable();

		add_action( 'rest_api_init', array( new TransactionsController( $table ), 'register_routes' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'hyx-demo', new DemoCommand( $table ) );
		}
	}

	/**
	 * Creates the transactions table on activation.
	 */
	public static function activate(): void {
		( new TransactionsTable() )->create();
	}
}
