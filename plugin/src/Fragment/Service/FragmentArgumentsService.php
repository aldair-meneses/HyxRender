<?php
/**
 * Fragment arguments from a request.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Service;

use Hyx\Render\Fragment\Data\Fragment;
use WP_Error;
use WP_REST_Request;

/**
 * Reads a request's values for a Fragment's declared arguments with WordPress's REST argument rules.
 */
final class FragmentArgumentsService {

	/**
	 * The request's values for the declared arguments, validated and sanitized by their schema.
	 *
	 * Works on a copy without the route's own `name` URL param, so a Fragment can
	 * declare an argument called `name`.
	 *
	 * @param Fragment        $fragment Requested Fragment.
	 * @param WP_REST_Request $original Request.
	 * @return array<string, mixed>|WP_Error The arguments, or why they are invalid.
	 */
	public function from_request( Fragment $fragment, WP_REST_Request $original ): array|WP_Error {
		$schema  = $fragment->request->args;
		$request = clone $original;
		$request->set_url_params( array() );
		$request->set_attributes( array_merge( $request->get_attributes(), array( 'args' => $schema ) ) );
		$request->set_default_params( $this->defaults( $schema ) );

		$valid = $request->has_valid_params();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$sanitized = $request->sanitize_params();
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}

		return $this->declared_values( $request, array_keys( $schema ) );
	}

	/**
	 * The default value of each argument that declares one.
	 *
	 * @param array<string, array<string, mixed>> $schema Argument schema.
	 * @return array<string, mixed>
	 */
	private function defaults( array $schema ): array {
		return wp_list_pluck( array_filter( $schema, static fn( array $arg ): bool => array_key_exists( 'default', $arg ) ), 'default' );
	}

	/**
	 * The values the request has for the declared arguments, in declaration order.
	 *
	 * @param WP_REST_Request $request Request with defaults applied.
	 * @param string[]        $keys    Declared argument names.
	 * @return array<string, mixed>
	 */
	private function declared_values( WP_REST_Request $request, array $keys ): array {
		$present = array_values( array_filter( $keys, array( $request, 'has_param' ) ) );

		return array_combine( $present, array_map( array( $request, 'get_param' ), $present ) );
	}
}
