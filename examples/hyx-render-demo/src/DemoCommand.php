<?php
/**
 * WP-CLI commands for the demo data.
 *
 * @package HyxRenderDemo
 */

declare( strict_types=1 );

namespace Hyx\RenderDemo;

use WP_CLI;

/**
 * Manages the HyxRender demo transactions.
 */
final class DemoCommand {

	/**
	 * Wires the command to the transactions table.
	 *
	 * @param TransactionsTable $table Transactions table.
	 */
	public function __construct( private readonly TransactionsTable $table ) {
	}

	/**
	 * Adds random transactions, creating the table if needed.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<count>]
	 * : How many transactions to add.
	 * ---
	 * default: 25
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp hyx-demo seed --count=50
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function seed( array $args, array $assoc_args ): void {
		$inserted = $this->table->seed( max( 0, (int) ( $assoc_args['count'] ?? 25 ) ) );

		WP_CLI::success( sprintf( 'Seeded %d rows into %s.', $inserted, $this->table->name() ) );
	}

	/**
	 * Drops and recreates the transactions table, leaving it empty.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hyx-demo reset
	 */
	public function reset(): void {
		$this->table->reset();

		WP_CLI::success( sprintf( 'Table %s recreated empty.', $this->table->name() ) );
	}
}
