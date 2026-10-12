<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Hyx_Finance_Table' ) ) {
	require_once dirname( __DIR__ ) . '/inc/finance-table.php';
}

$count = (int) ( $args[0] ?? 25 );

Hyx_Finance_Table::reset();
printf( "Table %s recreated.\n", Hyx_Finance_Table::name() );

if ( $count > 0 ) {
	$inserted = Hyx_Finance_Table::seed( $count );
	printf( "Seeded %d rows.\n", $inserted );
}
