<?php
/**
 * Smoke test for the integration test harness.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Tests\Integration;

use Hyx\Render\Plugin;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Proves WordPress loads the plugin and runs its boot action.
 */
final class PluginBootTest extends TestCase {

	/**
	 * The plugin boots on plugins_loaded and announces it.
	 */
	public function test_plugin_boots_inside_wordpress(): void {
		$this->assertSame( 1, did_action( 'hyx_render_loaded' ) );
		$this->assertSame( Plugin::VERSION, HYX_RENDER_VERSION );
	}

	/**
	 * WordPress resolves the plugin through its symlink in the plugins folder.
	 */
	public function test_plugin_basename_resolves_through_the_plugins_folder(): void {
		$this->assertSame( 'hyx-render/hyx-render.php', plugin_basename( HYX_RENDER_FILE ) );
	}
}
