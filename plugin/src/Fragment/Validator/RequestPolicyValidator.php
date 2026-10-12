<?php
/**
 * What a Fragment registration says about incoming requests.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Validator;

use Hyx\Render\Fragment\Enum\RegistrationError;
use Hyx\Render\Fragment\Exception\FragmentRegistrationException;
use Hyx\Render\Fragment\Sanitizer\HttpMethodsSanitizer;

/**
 * Checks the permission callback, methods and argument schema a request will be held to.
 */
final class RequestPolicyValidator {

	private const METHODS = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );

	/**
	 * Refuses a registration whose request policy breaks a rule, reporting the first one in this order.
	 *
	 * @param string               $name Fragment name.
	 * @param array<string, mixed> $args Registration arguments.
	 * @throws FragmentRegistrationException When a rule is broken.
	 */
	public function validate( string $name, array $args ): void {
		$rules = array(
			array( RegistrationError::MISSING_PERMISSION_CALLBACK, is_callable( $args['permission_callback'] ?? null ) ),
			array( RegistrationError::UNKNOWN_METHODS, $this->has_known_methods( $args ) ),
			array( RegistrationError::MALFORMED_SCHEMA, $this->has_valid_schema( $args ) ),
		);

		foreach ( $rules as [ $error, $holds ] ) {
			if ( ! $holds ) {
				throw new FragmentRegistrationException( esc_html( $error->message( $name ) ) );
			}
		}
	}

	/**
	 * Whether the declared methods name at least one method, and only methods a Fragment can answer.
	 *
	 * @param array<string, mixed> $args Registration arguments.
	 */
	private function has_known_methods( array $args ): bool {
		$methods = ( new HttpMethodsSanitizer( $args['methods'] ?? null ) )->sanitize();

		return array() !== $methods && array() === array_diff( $methods, self::METHODS );
	}

	/**
	 * Whether `args` maps each argument name to a schema array, as `register_rest_route()` takes them.
	 *
	 * @param array<string, mixed> $args Registration arguments.
	 */
	private function has_valid_schema( array $args ): bool {
		$schema = $args['args'] ?? array();

		return is_array( $schema ) && array() === array_filter(
			$schema,
			static fn( mixed $definition, int|string $argument ): bool => ! is_string( $argument ) || ! is_array( $definition ),
			ARRAY_FILTER_USE_BOTH
		);
	}
}
