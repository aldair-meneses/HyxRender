<?php
/**
 * The shape of a Fragment registration.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Validator;

use Hyx\Render\Fragment\Enum\RegistrationError;
use Hyx\Render\Fragment\Exception\FragmentRegistrationException;

/**
 * Checks a registration's name, renderer and cache settings.
 */
final class FragmentRegistrationValidator {

	/**
	 * Refuses a registration that breaks a rule, reporting the first one in this order.
	 *
	 * @param string               $name Fragment name.
	 * @param array<string, mixed> $args Registration arguments.
	 * @throws FragmentRegistrationException When a rule is broken.
	 */
	public function validate( string $name, array $args ): void {
		$rules = array(
			array( RegistrationError::MALFORMED_NAME, 1 === preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name ) ),
			array( RegistrationError::AMBIGUOUS_RENDERER, $this->has_one_renderer( $args ) ),
			array( RegistrationError::MALFORMED_CACHE, $this->has_valid_cache( $args ) ),
		);

		foreach ( $rules as [ $error, $holds ] ) {
			if ( ! $holds ) {
				throw new FragmentRegistrationException( esc_html( $error->message( $name ) ) );
			}
		}
	}

	/**
	 * Whether the registration names exactly one way to render: a callable or a template.
	 *
	 * @param array<string, mixed> $args Registration arguments.
	 */
	private function has_one_renderer( array $args ): bool {
		$render   = $args['render'] ?? null;
		$template = $args['template'] ?? null;

		if ( null !== $render ) {
			return null === $template && is_callable( $render );
		}

		return null !== $template;
	}

	/**
	 * Whether `cache` is an array with a TTL of zero or more seconds and a boolean vary_by_user.
	 *
	 * @param array<string, mixed> $args Registration arguments.
	 */
	private function has_valid_cache( array $args ): bool {
		$cache = $args['cache'] ?? array();

		if ( ! is_array( $cache ) ) {
			return false;
		}

		$ttl = $cache['ttl'] ?? 0;

		return is_int( $ttl ) && $ttl >= 0 && is_bool( $cache['vary_by_user'] ?? false );
	}
}
