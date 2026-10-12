<?php
/**
 * A Fragment's answer to a REST request.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Data;

use WP_REST_Response;

/**
 * A REST response whose body is HTML.
 *
 * The HTML lives in its own property because core filters such as `_fields`
 * rewrite the response data. The data mirrors it as an array, which those
 * filters can handle.
 */
final class FragmentResponse extends WP_REST_Response {

	/**
	 * Builds the response.
	 *
	 * @param string $html   Response body.
	 * @param int    $status HTTP status.
	 */
	public function __construct( public readonly string $html, int $status = 200 ) {
		parent::__construct( array( 'html' => $html ), $status );
	}
}
