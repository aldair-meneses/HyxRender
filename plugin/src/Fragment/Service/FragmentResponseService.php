<?php
/**
 * Answers Fragment requests.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Service;

use Hyx\Render\Fragment\Data\Fragment;
use Hyx\Render\Fragment\Data\FragmentCollection;
use Hyx\Render\Fragment\View\FragmentErrorView;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Takes a request through the Fragment's checks, then renders it.
 */
final class FragmentResponseService {

	/**
	 * Wires the service to the registered Fragments.
	 *
	 * @param FragmentCollection       $fragments Registered Fragments.
	 * @param FragmentArgumentsService $arguments Reads the request's arguments.
	 * @param FragmentCacheService     $cache     Reuses rendered HTML.
	 * @param FragmentErrorView        $errors    Renders error messages.
	 */
	public function __construct(
		private readonly FragmentCollection $fragments,
		private readonly FragmentArgumentsService $arguments,
		private readonly FragmentCacheService $cache,
		private readonly FragmentErrorView $errors,
	) {
	}

	/**
	 * Answers a request: 404 for an unknown Fragment, 405 for an undeclared method,
	 * 403 for a missing nonce or a refused permission, 422 for invalid arguments,
	 * and otherwise the rendered HTML.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function respond( WP_REST_Request $request ): WP_REST_Response {
		$fragment = $this->requested_fragment( $request );

		if ( null === $fragment ) {
			return $this->error( __( 'Fragment not found.', 'hyx-render' ), 404 );
		}

		if ( ! in_array( $request->get_method(), $fragment->request->methods, true ) ) {
			return $this->error( __( 'This Fragment does not accept that HTTP method.', 'hyx-render' ), 405 );
		}

		if ( ! $this->is_get( $request ) && ! $this->has_valid_nonce( $request ) ) {
			return $this->error( __( 'Your session has expired. Reload the page and try again.', 'hyx-render' ), 403 );
		}

		$arguments = $this->arguments->from_request( $fragment, $request );

		if ( is_wp_error( $arguments ) ) {
			return $this->error( $arguments->get_error_message(), 422 );
		}

		if ( true !== ( $fragment->request->permission_callback )( $arguments ) ) {
			return $this->error( __( 'Sorry, you are not allowed to see this Fragment.', 'hyx-render' ), 403 );
		}

		return $this->is_get( $request ) ? $this->cached( $fragment, $arguments ) : new WP_REST_Response( ( $fragment->render )( $arguments ) );
	}

	/**
	 * The Fragment named in the route.
	 *
	 * Read from the URL params, because a query or body param called `name` would
	 * win in `get_param()`.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function requested_fragment( WP_REST_Request $request ): ?Fragment {
		return $this->fragments->find( (string) ( $request->get_url_params()['name'] ?? '' ) );
	}

	/**
	 * The rendered HTML through the cache, marked shareable when it is safe to share.
	 *
	 * @param Fragment             $fragment  Requested Fragment.
	 * @param array<string, mixed> $arguments Sanitized arguments.
	 */
	private function cached( Fragment $fragment, array $arguments ): WP_REST_Response {
		$response = new WP_REST_Response( $this->cache->remember( $fragment, $arguments ) );

		if ( $this->is_publicly_cacheable( $fragment ) ) {
			$response->header( 'Cache-Control', sprintf( 'public, max-age=%d', $fragment->cache->ttl ) );
		}

		return $response;
	}

	/**
	 * Whether shared caches may keep the response: the Fragment has a TTL and nobody is logged in.
	 *
	 * @param Fragment $fragment Requested Fragment.
	 */
	private function is_publicly_cacheable( Fragment $fragment ): bool {
		return $fragment->cache->ttl > 0 && ! is_user_logged_in();
	}

	/**
	 * Whether the request only reads: GETs skip the nonce and may be cached.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private function is_get( WP_REST_Request $request ): bool {
		return 'GET' === $request->get_method();
	}

	/**
	 * Whether the request carries the REST nonce the runtime sends.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private function has_valid_nonce( WP_REST_Request $request ): bool {
		return false !== wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
	}

	/**
	 * An error response with the HTML error paragraph.
	 *
	 * @param string $message Plain-text message.
	 * @param int    $status  HTTP status.
	 */
	private function error( string $message, int $status ): WP_REST_Response {
		return new WP_REST_Response( $this->errors->render( $message ), $status );
	}
}
