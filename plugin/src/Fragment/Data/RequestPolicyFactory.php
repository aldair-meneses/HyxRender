<?php
/**
 * Builds a Fragment's request policy from its registration.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Data;

use Closure;
use Hyx\Render\Fragment\Enum\RegistrationError;
use Hyx\Render\Fragment\Exception\FragmentRegistrationException;

/**
 * Reads the permission callback, methods and argument schema a request will be held to.
 */
final class RequestPolicyFactory {

	private const METHODS = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );

	/**
	 * Builds the policy, defaulting to GET only and no arguments.
	 *
	 * @param string $name                Fragment name.
	 * @param mixed  $permission_callback The `permission_callback` option.
	 * @param mixed  $methods             The `methods` option: a list, or a comma-separated string as core REST routes take it.
	 * @param mixed  $schema              The `args` option.
	 * @throws FragmentRegistrationException When an option can't be used.
	 */
	public function create_from_registration( string $name, mixed $permission_callback, mixed $methods, mixed $schema ): RequestPolicy {
		if ( ! is_callable( $permission_callback ) ) {
			throw new FragmentRegistrationException( esc_html( RegistrationError::MISSING_PERMISSION_CALLBACK->message( $name ) ) );
		}

		return new RequestPolicy( $this->schema( $name, $schema ), $this->methods( $name, $methods ), Closure::fromCallable( $permission_callback ) );
	}

	/**
	 * The methods as unique uppercase names, all of them methods a Fragment can answer.
	 *
	 * @param string $name     Fragment name.
	 * @param mixed  $declared The `methods` option.
	 * @return string[]
	 * @throws FragmentRegistrationException When no method, or an unknown one, is declared.
	 */
	private function methods( string $name, mixed $declared ): array {
		$declared ??= 'GET';
		$list       = is_string( $declared ) ? explode( ',', $declared ) : (array) $declared;
		$methods    = array_values(
			array_unique(
				array_map( static fn( mixed $method ): string => is_string( $method ) ? strtoupper( trim( $method ) ) : '', $list )
			)
		);

		if ( array() === $methods || array() !== array_diff( $methods, self::METHODS ) ) {
			throw new FragmentRegistrationException( esc_html( RegistrationError::UNKNOWN_METHODS->message( $name ) ) );
		}

		return $methods;
	}

	/**
	 * The argument schema, mapping each argument name to its schema array as `register_rest_route()` takes it.
	 *
	 * @param string $name   Fragment name.
	 * @param mixed  $schema The `args` option.
	 * @return array<string, array<string, mixed>>
	 * @throws FragmentRegistrationException When the schema has another shape.
	 */
	private function schema( string $name, mixed $schema ): array {
		$schema ??= array();

		if ( ! is_array( $schema ) || array() !== array_filter( $schema, array( $this, 'is_malformed_definition' ), ARRAY_FILTER_USE_BOTH ) ) {
			throw new FragmentRegistrationException( esc_html( RegistrationError::MALFORMED_SCHEMA->message( $name ) ) );
		}

		return $schema;
	}

	/**
	 * Whether one schema entry lacks a string name or an array definition.
	 *
	 * @param mixed      $definition Argument schema.
	 * @param int|string $argument   Argument name.
	 */
	private function is_malformed_definition( mixed $definition, int|string $argument ): bool {
		return ! is_string( $argument ) || ! is_array( $definition );
	}
}
