<?php
/**
 * Fragments next to the REST server's own features.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Tests\Integration;

/**
 * Core REST parameters, other plugins' filters and other HTTP methods never break a Fragment response.
 */
final class FragmentServingTest extends FragmentTestCase {

	/**
	 * Core's `_fields` filter leaves the Fragment's HTML alone.
	 */
	public function test_the_fields_parameter_does_not_break_a_fragment(): void {
		$this->register_fragment( 'acme/with-fields', array( 'render' => static fn(): string => '<p>Fields ignored</p>' ) );

		$response = $this->request( 'GET', 'acme/with-fields', array( '_fields' => 'x' ) );

		$this->assertSame( 200, $response['status'], $response['body'] );
		$this->assertSame( '<p>Fields ignored</p>', $response['body'] );
	}

	/**
	 * WordPress matches routes case-insensitively, and the answers stay HTML.
	 *
	 * @dataProvider data_uppercase_paths
	 *
	 * @param string $path   Uppercase REST path, with `{name}` standing for a registered Fragment.
	 * @param int    $status Expected HTTP status.
	 */
	public function test_an_uppercase_path_is_still_answered_in_html( string $path, int $status ): void {
		$name = 'acme/shouting-' . sanitize_title( (string) $this->dataName() );
		$this->register_fragment( $name, array( 'render' => static fn(): string => '<p>Shouting</p>' ) );

		$response = $this->request_path( 'GET', str_replace( '{name}', $name, $path ) );

		$this->assertSame( $status, $response['status'], $response['body'] );
		$this->assertSame( 'text/html; charset=UTF-8', $response['headers']['Content-Type'] );
		$this->assertStringStartsWith( '<p', $response['body'] );
	}

	/**
	 * Uppercase paths to a registered Fragment and to nothing.
	 *
	 * @return array<string, array{string, int}>
	 */
	public static function data_uppercase_paths(): array {
		return array(
			'registered fragment' => array( '/HYX-RENDER/V1/FRAGMENTS/{name}', 200 ),
			'unroutable path'     => array( '/HYX-RENDER/V1/FRAGMENTS/greeting', 404 ),
		);
	}

	/**
	 * An uppercase path still reports the Fragment's own methods.
	 */
	public function test_an_uppercase_path_keeps_the_fragment_allow_header(): void {
		$this->register_fragment( 'acme/shouting-read-only', array( 'render' => static fn(): string => '<p>Shouting</p>' ) );

		$response = $this->request_path( 'POST', '/HYX-RENDER/V1/FRAGMENTS/acme/shouting-read-only' );

		$this->assertSame( 405, $response['status'], $response['body'] );
		$this->assertSame( 'GET', $response['headers']['Allow'] );
	}

	/**
	 * Another plugin's serving filter that returns nothing doesn't stop the Fragment from being served.
	 */
	public function test_a_serving_filter_returning_nothing_does_not_break_a_fragment(): void {
		add_filter( 'rest_pre_serve_request', '__return_null', 5 );
		$this->register_fragment( 'acme/after-null-filter', array( 'render' => static fn(): string => '<p>Still served</p>' ) );

		$response = $this->request( 'GET', 'acme/after-null-filter' );

		$this->assertSame( '<p>Still served</p>', $response['body'] );
	}

	/**
	 * HEAD is answered like GET, without the body.
	 */
	public function test_a_head_request_is_answered_like_get_without_a_body(): void {
		$this->register_fragment( 'acme/headable', array( 'render' => static fn(): string => '<p>Headable</p>' ) );

		$response = $this->request( 'HEAD', 'acme/headable' );

		$this->assertSame( 200, $response['status'], $response['body'] );
		$this->assertSame( 'text/html; charset=UTF-8', $response['headers']['Content-Type'] );
		$this->assertSame( '', $response['body'] );
	}

	/**
	 * An OPTIONS request still gets WordPress's JSON description of the route.
	 */
	public function test_an_options_request_still_describes_the_route_in_json(): void {
		$this->register_fragment( 'acme/discoverable', array( 'render' => static fn(): string => '<p>Discoverable</p>' ) );

		$response = $this->request( 'OPTIONS', 'acme/discoverable' );

		$this->assertSame( 200, $response['status'] );
		$this->assertStringStartsWith( 'application/json', $response['headers']['Content-Type'] );
		$this->assertSame( 'hyx-render/v1', json_decode( $response['body'], true )['namespace'] );
	}

	/**
	 * A client that asks for a JSON envelope gets one, with the Fragment's HTML inside.
	 */
	public function test_an_enveloped_request_carries_the_html_in_the_envelope(): void {
		$this->register_fragment( 'acme/enveloped', array( 'render' => static fn(): string => '<p>Enveloped</p>' ) );

		$response = $this->request( 'GET', 'acme/enveloped', array( '_envelope' => '1' ) );

		$this->assertSame( '<p>Enveloped</p>', json_decode( $response['body'], true )['body']['html'] );
	}
}
