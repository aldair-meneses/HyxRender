<?php
/**
 * HyxRender core plugin class.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render;

/**
 * Core plugin class.
 */
final class Plugin {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public const VERSION = '0.1.0';

	/**
	 * Boots the plugin.
	 */
	public static function boot(): void {
		// Put a breakpoint here to verify Xdebug.
		do_action( 'hyx_render_loaded' );
	}

	/**
	 * Runs on plugin activation.
	 */
	public static function activate(): void {
		self::boot();
		flush_rewrite_rules();
	}

	/**
	 * Runs on plugin deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Sample method to have something to test and debug.
	 *
	 * @return string Greeting from the plugin.
	 */
	public static function hello(): string {
		return 'Hello from HyxRender';
	}
}
