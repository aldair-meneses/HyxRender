<?php
/**
 * PHPUnit bootstrap for the WordPress integration suite.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

use function Yoast\WPTestUtils\WPIntegration\bootstrap_it;
use function Yoast\WPTestUtils\WPIntegration\get_path_to_wp_test_dir;

$hyx_render_root = dirname( __DIR__, levels: 2 );

require_once $hyx_render_root . '/vendor/autoload.php';
require_once $hyx_render_root . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

define( 'WP_TESTS_CONFIG_FILE_PATH', $hyx_render_root . '/docker/phpunit/wp-tests-config.php' );

require_once get_path_to_wp_test_dir() . 'includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		$plugin_file = WP_PLUGIN_DIR . '/hyx-render/hyx-render.php';

		wp_register_plugin_realpath( $plugin_file );
		require $plugin_file;
	}
);

bootstrap_it();
