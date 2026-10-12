<?php
/**
 * Reaching Fragments over the REST API.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Tests\Integration;

/**
 * Every answer from the Fragment route is text/html, success or error.
 */
final class FragmentRoutingTest extends FragmentTestCase {

	/**
	 * A GET to a registered Fragment returns its HTML as text/html.
	 */
	public function test_a_registered_fragment_is_served_as_html(): void {
		hyx_render_register_fragment(
			'acme/greeting',
			array(
				'label'               => 'Greeting',
				'render'              => static fn(): string => '<p>Hello from a Fragment</p>',
				'permission_callback' => '__return_true',
			)
		);

		$response = $this->request( 'GET', 'acme/greeting' );

		$this->assertSame( 200, $response['status'] );
		$this->assertSame( 'text/html; charset=UTF-8', $response['headers']['Content-Type'] );
		$this->assertSame( '<p>Hello from a Fragment</p>', $response['body'] );
	}

	/**
	 * A render callable that echoes its HTML instead of returning it is served the same way.
	 */
	public function test_echoed_html_from_the_render_callable_is_served(): void {
		$this->register_fragment(
			'acme/echoing',
			array(
				'render' => static function (): void {
					echo '<p>Echoed</p>';
				},
			)
		);

		$response = $this->request( 'GET', 'acme/echoing' );

		$this->assertSame( 200, $response['status'], $response['body'] );
		$this->assertSame( '<p>Echoed</p>', $response['body'] );
	}

	/**
	 * A render callable that returns something other than HTML is a 500 in HTML, and the developer is told.
	 *
	 * @dataProvider data_non_string_renders
	 *
	 * @param mixed $result What the render callable returns.
	 */
	public function test_a_render_callable_returning_a_non_string_is_a_500_as_html( mixed $result ): void {
		$name = 'acme/non-string-' . sanitize_title( (string) $this->dataName() );
		$this->register_fragment( $name, array( 'render' => static fn(): mixed => $result ) );
		$this->setExpectedIncorrectUsage( 'hyx_render_register_fragment' );

		$this->assert_html_error( 500, $this->request( 'GET', $name ) );
	}

	/**
	 * Render results that aren't HTML.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function data_non_string_renders(): array {
		return array(
			'integer' => array( 42 ),
			'array'   => array( array( '<p>In an array</p>' ) ),
		);
	}

	/**
	 * A name nobody registered is a 404, still answered in HTML.
	 */
	public function test_an_unknown_fragment_returns_404_as_html(): void {
		$response = $this->request( 'GET', 'acme/never-registered' );

		$this->assertSame( 404, $response['status'] );
		$this->assertSame( 'text/html; charset=UTF-8', $response['headers']['Content-Type'] );
		$this->assertSame( '<p class="hyx-render-error" role="alert">Fragment not found.</p>', $response['body'] );
	}

	/**
	 * A path WordPress can't route inside the namespace is answered in HTML, not JSON.
	 *
	 * @dataProvider data_unroutable_paths
	 *
	 * @param string $path Path after `fragments/`.
	 */
	public function test_an_unroutable_fragment_path_returns_404_as_html( string $path ): void {
		$this->assert_html_error( 404, $this->request( 'GET', $path ) );
	}

	/**
	 * Paths the Fragment route never matches.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_unroutable_paths(): array {
		return array(
			'no vendor'      => array( 'greeting' ),
			'uppercase'      => array( 'Acme/Greeting' ),
			'path traversal' => array( '../../wp-config' ),
		);
	}

	/**
	 * Fragments answer GET only unless they declare more, and say which methods they allow.
	 */
	public function test_an_undeclared_method_returns_405_as_html(): void {
		$this->register_fragment( 'acme/read-only', array( 'render' => static fn(): string => '<p>Read only</p>' ) );

		$response = $this->request( 'POST', 'acme/read-only' );

		$this->assert_html_error( 405, $response );
		$this->assertSame( 'GET', $response['headers']['Allow'] );
	}
}
