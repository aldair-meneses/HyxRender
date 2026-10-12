<?php
/**
 * Fragment caching.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Tests\Integration;

/**
 * With a TTL, a Fragment's HTML is reused per arguments (and per user when asked),
 * and anonymous GETs say shared caches may keep it.
 */
final class FragmentCacheTest extends FragmentTestCase {

	/**
	 * How many times the counting Fragment rendered.
	 *
	 * @var int
	 */
	private int $renders = 0;

	/**
	 * With a TTL, the same arguments are served from cache, and different arguments render again.
	 */
	public function test_a_cached_fragment_renders_once_per_set_of_arguments(): void {
		$this->register_counting_fragment( 'acme/cached-per-arguments', array( 'ttl' => 300 ) );

		$first  = $this->request( 'GET', 'acme/cached-per-arguments', array( 'category' => 'food' ) );
		$second = $this->request( 'GET', 'acme/cached-per-arguments', array( 'category' => 'food' ) );
		$other  = $this->request( 'GET', 'acme/cached-per-arguments', array( 'category' => 'rent' ) );

		$this->assertSame( '<p>Render 1 for food</p>', $first['body'] );
		$this->assertSame( '<p>Render 1 for food</p>', $second['body'] );
		$this->assertSame( '<p>Render 2 for rent</p>', $other['body'] );
	}

	/**
	 * Caching is opt-in: without a TTL every request renders.
	 *
	 * @dataProvider data_uncached_policies
	 *
	 * @param array<string, mixed> $cache Cache settings.
	 */
	public function test_a_fragment_without_a_ttl_renders_every_time( array $cache ): void {
		$name = 'acme/uncached-' . sanitize_title( (string) $this->dataName() );
		$this->register_counting_fragment( $name, $cache );

		$this->request( 'GET', $name, array( 'category' => 'food' ) );
		$second = $this->request( 'GET', $name, array( 'category' => 'food' ) );

		$this->assertSame( '<p>Render 2 for food</p>', $second['body'] );
	}

	/**
	 * Cache settings that leave caching off.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function data_uncached_policies(): array {
		return array(
			'no cache settings' => array( array() ),
			'zero ttl'          => array( array( 'ttl' => 0 ) ),
		);
	}

	/**
	 * With vary_by_user, each user gets their own cached render.
	 */
	public function test_vary_by_user_keeps_a_separate_cache_per_user(): void {
		$this->register_counting_fragment(
			'acme/cached-per-user',
			array(
				'ttl'          => 300,
				'vary_by_user' => true,
			)
		);

		wp_set_current_user( self::factory()->user->create() );
		$first_user       = $this->request( 'GET', 'acme/cached-per-user', array( 'category' => 'food' ) );
		$first_user_again = $this->request( 'GET', 'acme/cached-per-user', array( 'category' => 'food' ) );

		wp_set_current_user( self::factory()->user->create() );
		$second_user = $this->request( 'GET', 'acme/cached-per-user', array( 'category' => 'food' ) );

		$this->assertSame( '<p>Render 1 for food</p>', $first_user['body'] );
		$this->assertSame( '<p>Render 1 for food</p>', $first_user_again['body'] );
		$this->assertSame( '<p>Render 2 for food</p>', $second_user['body'] );
	}

	/**
	 * A render cached for someone allowed is never served to someone the permission callback refuses.
	 */
	public function test_a_cached_render_is_never_served_past_the_permission_callback(): void {
		$this->register_fragment(
			'acme/cached-for-editors',
			array(
				'render'              => static fn(): string => '<p>Editors only</p>',
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'cache'               => array( 'ttl' => 300 ),
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$editor = $this->request( 'GET', 'acme/cached-for-editors' );

		wp_set_current_user( 0 );
		$visitor = $this->request( 'GET', 'acme/cached-for-editors' );

		$this->assertSame( '<p>Editors only</p>', $editor['body'] );
		$this->assertSame( 403, $visitor['status'] );
		$this->assertStringNotContainsString( 'Editors only', $visitor['body'] );
	}

	/**
	 * A request that can change state always renders, even when the Fragment has a TTL.
	 */
	public function test_a_non_get_request_is_never_cached(): void {
		$this->register_fragment(
			'acme/cached-post',
			array(
				'methods' => array( 'POST' ),
				'render'  => fn(): string => sprintf( '<p>Render %d</p>', ++$this->renders ),
				'cache'   => array( 'ttl' => 300 ),
			)
		);
		$nonce = array( 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) );

		$this->request( 'POST', 'acme/cached-post', array(), $nonce );
		$second = $this->request( 'POST', 'acme/cached-post', array(), $nonce );

		$this->assertSame( '<p>Render 2</p>', $second['body'] );
	}

	/**
	 * An anonymous GET to a cached Fragment tells shared caches they may keep it for the TTL.
	 */
	public function test_an_anonymous_cached_get_is_publicly_cacheable_for_its_ttl(): void {
		$this->register_counting_fragment( 'acme/public-cache', array( 'ttl' => 300 ) );

		$response = $this->request( 'GET', 'acme/public-cache', array( 'category' => 'food' ) );

		$this->assertSame( 'public, max-age=300', $response['headers']['Cache-Control'] );
	}

	/**
	 * Logged-in requests, uncached Fragments and non-GET requests are never marked publicly cacheable.
	 *
	 * @dataProvider data_privately_served_requests
	 *
	 * @param string               $method HTTP method.
	 * @param array<string, mixed> $cache  Cache settings.
	 * @param string|null          $role   Role of the logged-in user, or null for a visitor.
	 */
	public function test_a_response_not_safe_to_share_is_never_publicly_cacheable( string $method, array $cache, ?string $role ): void {
		$name = 'acme/private-cache-' . sanitize_title( (string) $this->dataName() );
		$this->register_fragment(
			$name,
			array(
				'methods' => array( $method ),
				'render'  => static fn(): string => '<p>Private</p>',
				'cache'   => $cache,
			)
		);

		if ( null !== $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
		}

		$response = $this->request( $method, $name, array(), array( 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) ) );

		$this->assertSame( 200, $response['status'], $response['body'] );
		$this->assertStringNotContainsString( 'public', $response['headers']['Cache-Control'] ?? '' );
	}

	/**
	 * Requests whose response must not land in a shared cache.
	 *
	 * @return array<string, array{string, array<string, mixed>, string|null}>
	 */
	public static function data_privately_served_requests(): array {
		return array(
			'logged-in user' => array( 'GET', array( 'ttl' => 300 ), 'subscriber' ),
			'no ttl'         => array( 'GET', array(), null ),
			'post request'   => array( 'POST', array( 'ttl' => 300 ), null ),
		);
	}

	/**
	 * Registers a public Fragment whose output counts how many times it rendered.
	 *
	 * @param string               $name  Fragment name.
	 * @param array<string, mixed> $cache Cache settings.
	 */
	private function register_counting_fragment( string $name, array $cache ): void {
		$this->register_fragment(
			$name,
			array(
				'args'   => array( 'category' => array( 'type' => 'string' ) ),
				'render' => fn( array $args ): string => sprintf( '<p>Render %d for %s</p>', ++$this->renders, $args['category'] ),
				'cache'  => $cache,
			)
		);
	}
}
