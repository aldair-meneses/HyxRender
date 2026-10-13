<?php
/**
 * Builds a Fragment's cache policy from its registration.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Data;

use Hyx\Render\Fragment\Enum\RegistrationError;
use Hyx\Render\Fragment\Exception\FragmentRegistrationException;

/**
 * Reads the `cache` option.
 */
final class CachePolicyFactory {

	/**
	 * Builds the policy: a TTL of zero or more seconds, and whether each user gets their own render.
	 * Without the option, nothing is cached.
	 *
	 * @param string $name  Fragment name.
	 * @param mixed  $cache The `cache` option.
	 * @throws FragmentRegistrationException When the option has another shape.
	 */
	public function create_from_registration( string $name, mixed $cache ): CachePolicy {
		$cache      ??= array();
		$ttl          = is_array( $cache ) ? ( $cache['ttl'] ?? 0 ) : null;
		$vary_by_user = is_array( $cache ) ? ( $cache['vary_by_user'] ?? false ) : null;

		if ( ! is_int( $ttl ) || $ttl < 0 || ! is_bool( $vary_by_user ) ) {
			throw new FragmentRegistrationException( esc_html( RegistrationError::MALFORMED_CACHE->message( $name ) ) );
		}

		return new CachePolicy( $ttl, $vary_by_user );
	}
}
