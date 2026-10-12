<?php
/**
 * Registering Fragments with `hyx_render_register_fragment()`.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Tests\Integration;

/**
 * A registration either produces a Fragment the endpoint serves, or is refused
 * with a `_doing_it_wrong()` notice and leaves nothing behind.
 */
final class FragmentRegistrationTest extends FragmentTestCase {

	/**
	 * The registration action fires once WordPress has loaded plugins and the theme.
	 */
	public function test_the_registration_action_fires_on_init(): void {
		$this->assertSame( 1, did_action( 'hyx_render_init' ) );
	}

	/**
	 * Registration refuses names outside `vendor/name` form.
	 *
	 * @dataProvider data_malformed_names
	 *
	 * @param string $name Malformed Fragment name.
	 */
	public function test_a_name_outside_vendor_name_form_is_rejected( string $name ): void {
		$this->setExpectedIncorrectUsage( 'hyx_render_register_fragment' );

		$registered = $this->register_fragment( $name, array( 'render' => static fn(): string => '<p>Malformed</p>' ) );

		$this->assertFalse( $registered );
	}

	/**
	 * Names that break the `vendor/name` form.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_malformed_names(): array {
		return array(
			'no vendor'      => array( 'greeting' ),
			'uppercase'      => array( 'Acme/greeting' ),
			'underscore'     => array( 'acme_co/greeting' ),
			'empty name'     => array( 'acme/' ),
			'extra segment'  => array( 'acme/greeting/extra' ),
			'path traversal' => array( '../greeting' ),
		);
	}

	/**
	 * A second registration under a taken name is refused, and the original stays.
	 */
	public function test_a_duplicate_name_is_rejected_and_the_first_fragment_keeps_serving(): void {
		$this->register_fragment( 'acme/duplicate', array( 'render' => static fn(): string => '<p>First</p>' ) );

		$this->setExpectedIncorrectUsage( 'hyx_render_register_fragment' );
		$registered = $this->register_fragment( 'acme/duplicate', array( 'render' => static fn(): string => '<p>Second</p>' ) );

		$this->assertFalse( $registered );
		$this->assertSame( '<p>First</p>', $this->request( 'GET', 'acme/duplicate' )['body'] );
	}

	/**
	 * A Fragment has to say who may see it: a missing or uncallable permission callback is refused.
	 *
	 * @dataProvider data_missing_permission_callbacks
	 *
	 * @param array<string, mixed> $permission The permission part of the registration.
	 */
	public function test_a_registration_without_a_callable_permission_callback_is_rejected( array $permission ): void {
		$name = 'acme/unguarded-' . sanitize_title( (string) $this->dataName() );
		$this->setExpectedIncorrectUsage( 'hyx_render_register_fragment' );

		$registered = hyx_render_register_fragment(
			$name,
			array_merge(
				array(
					'label'  => 'Unguarded',
					'render' => static fn(): string => '<p>Unguarded</p>',
				),
				$permission
			)
		);

		$this->assertFalse( $registered );
		$this->assertSame( 404, $this->request( 'GET', $name )['status'] );
	}

	/**
	 * Registrations that leave the permission check out.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function data_missing_permission_callbacks(): array {
		return array(
			'missing'      => array( array() ),
			'not callable' => array( array( 'permission_callback' => 'hyx_render_no_such_function' ) ),
		);
	}

	/**
	 * A Fragment renders one way: a callable `render` or a `template` file, never both or neither.
	 *
	 * @dataProvider data_ambiguous_renderers
	 *
	 * @param array<string, mixed> $renderer The rendering part of the registration.
	 */
	public function test_a_registration_without_exactly_one_renderer_is_rejected( array $renderer ): void {
		$name = 'acme/renderer-' . sanitize_title( (string) $this->dataName() );
		$this->setExpectedIncorrectUsage( 'hyx_render_register_fragment' );

		$registered = $this->register_fragment( $name, $renderer );

		$this->assertFalse( $registered );
		$this->assertSame( 404, $this->request( 'GET', $name )['status'] );
	}

	/**
	 * Registrations that leave the renderer missing, doubled or broken.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function data_ambiguous_renderers(): array {
		return array(
			'neither'             => array( array() ),
			'both'                => array(
				array(
					'render'   => static fn(): string => '<p>Callable</p>',
					'template' => self::FIXTURES . '/templates/greeting.php',
				),
			),
			'render not callable' => array( array( 'render' => 'hyx_render_no_such_function' ) ),
		);
	}

	/**
	 * A template has to be an existing file inside the plugins, mu-plugins or theme folders.
	 *
	 * @dataProvider data_disallowed_templates
	 *
	 * @param callable $template Builds the template path from a PHP file outside the allowed folders.
	 */
	public function test_a_template_outside_the_allowed_folders_is_rejected( callable $template ): void {
		$name = 'acme/escaping-' . sanitize_title( (string) $this->dataName() );
		$this->setExpectedIncorrectUsage( 'hyx_render_register_fragment' );

		$registered = $this->register_fragment( $name, array( 'template' => $template( $this->file_outside_allowed_folders() ) ) );

		$this->assertFalse( $registered );
		$this->assertSame( 404, $this->request( 'GET', $name )['status'] );
	}

	/**
	 * Template paths that point outside the allowed folders, or nowhere.
	 *
	 * @return array<string, array{callable(string): string}>
	 */
	public static function data_disallowed_templates(): array {
		return array(
			'outside every allowed folder' => array( static fn( string $outside ): string => $outside ),
			'climbs out with ..'           => array(
				static fn( string $outside ): string => self::FIXTURES . str_repeat( '/..', substr_count( (string) realpath( self::FIXTURES ), '/' ) ) . $outside,
			),
			'missing file'                 => array( static fn(): string => self::FIXTURES . '/templates/missing.php' ),
		);
	}
}
