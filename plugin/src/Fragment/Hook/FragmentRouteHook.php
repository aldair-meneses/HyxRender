<?php
/**
 * The Fragment REST route.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Fragment\Hook;

use Hyx\Render\Fragment\Service\FragmentResponseService;
use Hyx\Render\Fragment\View\FragmentErrorView;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Serves `hyx-render/v1/fragments/{name}` as text/html.
 */
final class FragmentRouteHook {

	private const ROUTE_NAMESPACE = '/hyx-render/v1/';

	/**
	 * Hooks the route into the REST API.
	 *
	 * @param FragmentResponseService $responses Answers Fragment requests.
	 * @param FragmentErrorView       $errors    Renders error messages.
	 */
	public function __construct(
		private readonly FragmentResponseService $responses,
		private readonly FragmentErrorView $errors,
	) {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'allow_fragment_methods' ), 11, 3 );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_html' ), 10, 4 );
	}

	/**
	 * Registers one route for every Fragment, so unknown names and methods get HTML answers too.
	 */
	public function register_route(): void {
		register_rest_route(
			trim( self::ROUTE_NAMESPACE, '/' ),
			'/fragments/(?P<name>[a-z0-9-]+/[a-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => array( $this->responses, 'respond' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Replaces the Allow header WordPress builds from the catch-all route with the Fragment's own methods.
	 *
	 * @param WP_HTTP_Response $response Response.
	 * @param WP_REST_Server   $server   Server instance.
	 * @param WP_REST_Request  $request  Request.
	 */
	public function allow_fragment_methods( WP_HTTP_Response $response, WP_REST_Server $server, WP_REST_Request $request ): WP_HTTP_Response {
		$fragment = $this->responses->requested_fragment( $request );

		if ( null !== $fragment && $this->is_route_under( $request, 'fragments/' ) ) {
			$response->header( 'Allow', implode( ', ', $fragment->request->methods ) );
		}

		return $response;
	}

	/**
	 * Writes the namespace's responses as text/html instead of JSON.
	 *
	 * @mago-expect lint:no-boolean-flag-parameter
	 *
	 * @param bool             $served  Whether the request was already served.
	 * @param WP_HTTP_Response $result  Response to send.
	 * @param WP_REST_Request  $request Request.
	 * @param WP_REST_Server   $server  Server instance.
	 */
	public function serve_html( bool $served, WP_HTTP_Response $result, WP_REST_Request $request, WP_REST_Server $server ): bool {
		if ( $served || ! $this->is_route_under( $request, '' ) ) {
			return $served;
		}

		$server->send_header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset' ) );
		echo $this->html( $result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fragment HTML is the developer's rendered markup; errors are escaped by the view.

		return true;
	}

	/**
	 * The response body as HTML.
	 *
	 * Errors WordPress raises before the route callback runs arrive as JSON-shaped
	 * arrays, so they become the same HTML error paragraph.
	 *
	 * @param WP_HTTP_Response $result Response to send.
	 */
	private function html( WP_HTTP_Response $result ): string {
		$data = $result->get_data();

		return is_string( $data ) ? $data : $this->errors->render( (string) ( $data['message'] ?? '' ) );
	}

	/**
	 * Whether the request's route sits under a path of this namespace.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $path    Path inside the namespace.
	 */
	private function is_route_under( WP_REST_Request $request, string $path ): bool {
		return str_starts_with( $request->get_route(), self::ROUTE_NAMESPACE . $path );
	}
}
