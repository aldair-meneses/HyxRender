<?php
/**
 * The options a Fragment registration takes.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Tests\Integration;

use WP_REST_Server;

/**
 * Registration options that would break the Fragment on every request are refused up front.
 */
final class FragmentRegistrationOptionsTest extends FragmentTestCase {

	/**
	 * Methods can be given the way core's REST routes take them, including as a comma-separated string.
	 *
	 * @dataProvider data_comma_separated_methods
	 *
	 * @param string $methods Declared methods.
	 */
	public function test_methods_given_as_a_comma_separated_string_are_served( string $methods ): void {
		$name = 'acme/methods-' . sanitize_title( (string) $this->dataName() );
		$this->register_fragment(
			$name,
			array(
				'methods' => $methods,
				'render'  => static fn(): string => '<p>Posted</p>',
			)
		);

		$response = $this->request( 'POST', $name, array(), array( 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) ) );

		$this->assertSame( 200, $response['status'], $response['body'] );
		$this->assertSame( '<p>Posted</p>', $response['body'] );
	}

	/**
	 * Method strings that include POST.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_comma_separated_methods(): array {
		return array(
			'core constant'  => array( WP_REST_Server::EDITABLE ),
			'lowercase list' => array( 'get, post' ),
		);
	}

	/**
	 * Methods that don't parse into HTTP methods a Fragment can answer are refused.
	 *
	 * @dataProvider data_invalid_methods
	 *
	 * @param mixed $methods Declared methods.
	 */
	public function test_methods_that_are_not_http_methods_are_rejected( mixed $methods ): void {
		$name = 'acme/bad-methods-' . sanitize_title( (string) $this->dataName() );
		$this->setExpectedIncorrectUsage( 'hyx_render_register_fragment' );

		$registered = $this->register_fragment(
			$name,
			array(
				'methods' => $methods,
				'render'  => static fn(): string => '<p>Never</p>',
			)
		);

		$this->assertFalse( $registered );
		$this->assertSame( 404, $this->request( 'GET', $name )['status'] );
	}

	/**
	 * Method declarations that name no usable HTTP method.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function data_invalid_methods(): array {
		return array(
			'empty list'     => array( array() ),
			'empty string'   => array( '' ),
			'unknown method' => array( array( 'FETCH' ) ),
			'flag map'       => array( array( 'POST' => true ) ),
		);
	}

	/**
	 * An argument schema or cache setting the endpoint couldn't use is refused at registration.
	 *
	 * @dataProvider data_malformed_options
	 *
	 * @param array<string, mixed> $options The malformed part of the registration.
	 */
	public function test_a_malformed_schema_or_cache_setting_is_rejected( array $options ): void {
		$name = 'acme/malformed-' . sanitize_title( (string) $this->dataName() );
		$this->setExpectedIncorrectUsage( 'hyx_render_register_fragment' );

		$registered = $this->register_fragment( $name, array_merge( array( 'render' => static fn(): string => '<p>Never</p>' ), $options ) );

		$this->assertFalse( $registered );
		$this->assertSame( 404, $this->request( 'GET', $name )['status'] );
	}

	/**
	 * Schemas and cache settings in shapes the endpoint can't read.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function data_malformed_options(): array {
		return array(
			'args as a string'       => array( array( 'args' => 'category' ) ),
			'args as a list'         => array( array( 'args' => array( 'category' ) ) ),
			'arg with a bare type'   => array( array( 'args' => array( 'page' => 'integer' ) ) ),
			'cache as a number'      => array( array( 'cache' => 300 ) ),
			'ttl as text'            => array( array( 'cache' => array( 'ttl' => 'soon' ) ) ),
			'negative ttl'           => array( array( 'cache' => array( 'ttl' => -1 ) ) ),
			'vary by user as a word' => array( array( 'cache' => array( 'vary_by_user' => 'yes' ) ) ),
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
