<?php
/**
 * Who may reach a Fragment.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Tests\Integration;

/**
 * The permission callback guards every request, and requests that can change state need the REST nonce.
 */
final class FragmentAccessTest extends FragmentTestCase {

	/**
	 * A permission callback that says no is a 403, and the Fragment never renders.
	 */
	public function test_a_failed_permission_callback_returns_403_as_html(): void {
		$this->register_fragment(
			'acme/editors-only',
			array(
				'render'              => static fn(): string => '<p>Editors only</p>',
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			)
		);

		$response = $this->request( 'GET', 'acme/editors-only' );

		$this->assert_html_error( 403, $response );
		$this->assertStringNotContainsString( 'Editors only', $response['body'] );
	}

	/**
	 * The permission callback sees the sanitized arguments, so access can depend on them.
	 */
	public function test_the_permission_callback_decides_from_the_arguments(): void {
		$this->register_fragment(
			'acme/scoped-report',
			array(
				'args'                => array( 'scope' => array( 'type' => 'string' ) ),
				'render'              => static fn(): string => '<p>Report</p>',
				'permission_callback' => static fn( array $args ): bool => 'public' === $args['scope'],
			)
		);

		$this->assertSame( 200, $this->request( 'GET', 'acme/scoped-report', array( 'scope' => 'public' ) )['status'] );
		$this->assertSame( 403, $this->request( 'GET', 'acme/scoped-report', array( 'scope' => 'internal' ) )['status'] );
	}

	/**
	 * A request that can change state needs the REST nonce, or it is a 403.
	 *
	 * @dataProvider data_bad_nonce_headers
	 *
	 * @param callable $headers Builds the request headers.
	 */
	public function test_a_non_get_request_without_a_valid_nonce_returns_403_as_html( callable $headers ): void {
		$name = 'acme/subscribe-' . sanitize_title( (string) $this->dataName() );
		$this->register_subscribe_fragment( $name );

		$response = $this->request( 'POST', $name, array(), $headers() );

		$this->assert_html_error( 403, $response );
		$this->assertStringNotContainsString( 'Subscribed', $response['body'] );
	}

	/**
	 * Header sets that don't carry a valid REST nonce.
	 *
	 * @return array<string, array{callable(): array<string, string>}>
	 */
	public static function data_bad_nonce_headers(): array {
		return array(
			'no nonce'           => array( static fn(): array => array() ),
			'garbage nonce'      => array( static fn(): array => array( 'X-WP-Nonce' => 'not-a-nonce' ) ),
			'other action nonce' => array( static fn(): array => array( 'X-WP-Nonce' => wp_create_nonce( 'hyx_render_other_action' ) ) ),
		);
	}

	/**
	 * With the REST nonce, a declared non-GET method is served.
	 */
	public function test_a_non_get_request_with_a_valid_nonce_is_served(): void {
		$this->register_subscribe_fragment( 'acme/subscribe-valid' );

		$response = $this->request( 'POST', 'acme/subscribe-valid', array(), array( 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) ) );

		$this->assertSame( 200, $response['status'] );
		$this->assertSame( '<p>Subscribed</p>', $response['body'] );
	}

	/**
	 * Registers a public Fragment that only answers POST.
	 *
	 * @param string $name Fragment name.
	 */
	private function register_subscribe_fragment( string $name ): void {
		$this->register_fragment(
			$name,
			array(
				'methods' => array( 'POST' ),
				'render'  => static fn(): string => '<p>Subscribed</p>',
			)
		);
	}
}
