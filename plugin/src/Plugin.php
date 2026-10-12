<?php
/**
 * HyxRender core plugin class.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render;

use Hyx\Render\Fragment\Data\FragmentCollection;
use Hyx\Render\Fragment\Data\FragmentFactory;
use Hyx\Render\Fragment\Hook\FragmentRouteHook;
use Hyx\Render\Fragment\Service\FragmentArgumentsService;
use Hyx\Render\Fragment\Service\FragmentCacheService;
use Hyx\Render\Fragment\Service\FragmentRegistrationService;
use Hyx\Render\Fragment\Service\FragmentRenderService;
use Hyx\Render\Fragment\Service\FragmentResponseService;
use Hyx\Render\Fragment\Service\TemplateLocationService;
use Hyx\Render\Fragment\View\FragmentErrorView;
use Hyx\Render\Fragment\View\FragmentTemplateView;

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
	 * Fragment registration, reached by the public PHP API.
	 *
	 * @var FragmentRegistrationService
	 */
	private static FragmentRegistrationService $fragments;

	/**
	 * Boots the plugin.
	 */
	public static function boot(): void {
		$fragments = new FragmentCollection();
		$locations = new TemplateLocationService();
		$errors    = new FragmentErrorView();
		$renderer  = new FragmentRenderService();

		self::$fragments = new FragmentRegistrationService(
			$fragments,
			new FragmentFactory( new FragmentTemplateView(), $locations ),
			$locations
		);

		new FragmentRouteHook(
			new FragmentResponseService( $fragments, new FragmentArgumentsService(), $renderer, new FragmentCacheService( $renderer ), $errors ),
			$errors
		);

		add_action( 'init', static fn() => do_action( 'hyx_render_init' ) );

		do_action( 'hyx_render_loaded' );
	}

	/**
	 * Fragment registration, for `hyx_render_register_fragment()`.
	 */
	public static function fragments(): FragmentRegistrationService {
		return self::$fragments;
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
