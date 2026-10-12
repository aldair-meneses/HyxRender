<?php
/**
 * A Fragment's declared HTTP methods.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Sanitizer;

/**
 * Normalizes `methods` the way core REST routes read it: a list, or a comma-separated string.
 */
final class HttpMethodsSanitizer {

	private const DEFAULT_METHOD = 'GET';

	/**
	 * Takes the declared methods.
	 *
	 * @param mixed $methods Declared methods, null when the registration leaves them out.
	 */
	public function __construct( private readonly mixed $methods ) {
	}

	/**
	 * The methods as unique, trimmed, uppercase names. Anything that isn't a string becomes an empty name.
	 *
	 * @return string[]
	 */
	public function sanitize(): array {
		$methods = $this->methods ?? self::DEFAULT_METHOD;
		$methods = is_string( $methods ) ? explode( ',', $methods ) : (array) $methods;

		return array_values(
			array_unique(
				array_map( static fn( mixed $method ): string => is_string( $method ) ? strtoupper( trim( $method ) ) : '', $methods )
			)
		);
	}
}
