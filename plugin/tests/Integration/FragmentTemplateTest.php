<?php
/**
 * Fragments rendered from PHP template files.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Tests\Integration;

/**
 * A template file renders with the Fragment's arguments, and the active theme can override it.
 */
final class FragmentTemplateTest extends FragmentTestCase {

	/**
	 * Registered theme folders before a test added the fixture one.
	 *
	 * @var list<string>|null
	 */
	private ?array $theme_directories_backup = null;

	/**
	 * Unregisters the fixture theme folder.
	 */
	public function tear_down(): void {
		if ( null !== $this->theme_directories_backup ) {
			$GLOBALS['wp_theme_directories'] = $this->theme_directories_backup; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores what the test changed.
			search_theme_directories( true );
		}

		parent::tear_down();
	}

	/**
	 * A Fragment can render a PHP template file, which receives the sanitized arguments as `$args`.
	 */
	public function test_a_template_file_renders_with_the_arguments(): void {
		$this->register_greeting_fragment( 'acme/template-greeting' );

		$response = $this->request( 'GET', 'acme/template-greeting', array( 'name' => '<b>Ada</b>' ) );

		$this->assertSame( 200, $response['status'], $response['body'] );
		$this->assertSame( "<p>Plugin template greets &lt;b&gt;Ada&lt;/b&gt;</p>\n", $response['body'] );
	}

	/**
	 * The active theme's `hyx-render/{vendor}/{name}.php` replaces the plugin's template.
	 */
	public function test_a_theme_override_takes_precedence_over_the_plugin_template(): void {
		$this->switch_to_fixture_theme();
		$this->register_greeting_fragment( 'acme/themed-greeting' );

		$response = $this->request( 'GET', 'acme/themed-greeting', array( 'name' => 'Ada' ) );

		$this->assertSame( 200, $response['status'], $response['body'] );
		$this->assertSame( "<p>Theme template greets Ada</p>\n", $response['body'] );
	}

	/**
	 * An override that is a symlink out of the allowed folders is ignored, and the plugin's template renders.
	 */
	public function test_a_theme_override_linking_outside_the_allowed_folders_is_ignored(): void {
		$this->switch_to_fixture_theme();

		$link = get_stylesheet_directory() . '/hyx-render/acme/linked-greeting.php';
		symlink( $this->file_outside_allowed_folders(), $link );
		$this->remove_after_test( $link );

		$this->register_greeting_fragment( 'acme/linked-greeting' );

		$response = $this->request( 'GET', 'acme/linked-greeting', array( 'name' => 'Ada' ) );

		$this->assertSame( "<p>Plugin template greets Ada</p>\n", $response['body'] );
	}

	/**
	 * A render cached under one theme is never served after switching to a theme that overrides the template.
	 */
	public function test_switching_theme_skips_renders_cached_under_the_old_one(): void {
		$this->register_greeting_fragment( 'acme/cached-greeting', array( 'ttl' => 300 ) );

		$before = $this->request( 'GET', 'acme/cached-greeting', array( 'name' => 'Ada' ) );

		$this->switch_to_fixture_theme();
		$after = $this->request( 'GET', 'acme/cached-greeting', array( 'name' => 'Ada' ) );

		$this->assertSame( "<p>Plugin template greets Ada</p>\n", $before['body'] );
		$this->assertSame( "<p>Theme template greets Ada</p>\n", $after['body'] );
	}

	/**
	 * Registers a Fragment rendered by the plugin's greeting template.
	 *
	 * @param string               $name  Fragment name.
	 * @param array<string, mixed> $cache Cache settings.
	 */
	private function register_greeting_fragment( string $name, array $cache = array() ): void {
		$this->register_fragment(
			$name,
			array(
				'args'     => array( 'name' => array( 'type' => 'string' ) ),
				'template' => self::FIXTURES . '/templates/greeting.php',
				'cache'    => $cache,
			)
		);
	}

	/**
	 * Activates the fixture theme, which overrides some Fragment templates.
	 */
	private function switch_to_fixture_theme(): void {
		$this->theme_directories_backup = $GLOBALS['wp_theme_directories'];

		register_theme_directory( (string) realpath( self::FIXTURES . '/themes' ) );
		search_theme_directories( true );
		switch_theme( 'hyx-render-test-theme' );
	}
}
