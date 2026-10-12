<?php
/**
 * Shared harness for the Fragment integration tests.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Drives `hyx-render/v1/fragments/{name}` through the WordPress REST server,
 * the way a browser request reaches it.
 *
 * Fragments stay registered for the whole run, so each test registers its own names.
 */
abstract class FragmentTestCase extends TestCase {

	protected const FIXTURES = __DIR__ . '/../fixtures';

	/**
	 * Request globals, restored after each test.
	 *
	 * @var array{server: array, get: array, post: array}
	 */
	private array $globals_backup;

	/**
	 * Files a test wrote, removed after it.
	 *
	 * @var list<string>
	 */
	private array $temp_files = array();

	/**
	 * Saves the request globals the REST server reads.
	 */
	public function set_up(): void {
		parent::set_up();

		// phpcs:disable WordPress.Security.NonceVerification -- Saved for restoring, never processed.
		$this->globals_backup = array(
			'server' => $_SERVER,
			'get'    => $_GET,
			'post'   => $_POST,
		);
		// phpcs:enable
	}

	/**
	 * Restores the request globals, drops the REST server and removes written files.
	 */
	public function tear_down(): void {
		$_SERVER = $this->globals_backup['server'];
		$_GET    = $this->globals_backup['get'];
		$_POST   = $this->globals_backup['post'];

		$GLOBALS['wp_rest_server'] = null;

		array_map( 'wp_delete_file', $this->temp_files );

		parent::tear_down();
	}

	/**
	 * Serves a request to a Fragment through a fresh REST server.
	 *
	 * @param string                $method  HTTP method.
	 * @param string                $name    Fragment name.
	 * @param array<string, mixed>  $params  Query parameters for GET, body parameters otherwise.
	 * @param array<string, string> $headers Request headers.
	 * @return array{status: int|null, headers: array<string, string>, body: string}
	 */
	protected function request( string $method, string $name, array $params = array(), array $headers = array() ): array {
		return $this->request_path( $method, '/hyx-render/v1/fragments/' . $name, $params, $headers );
	}

	/**
	 * Serves a request to any REST path through a fresh REST server.
	 *
	 * @param string                $method  HTTP method.
	 * @param string                $path    REST route path.
	 * @param array<string, mixed>  $params  Query parameters for GET, body parameters otherwise.
	 * @param array<string, string> $headers Request headers.
	 * @return array{status: int|null, headers: array<string, string>, body: string}
	 */
	protected function request_path( string $method, string $path, array $params = array(), array $headers = array() ): array {
		$_SERVER['REQUEST_METHOD'] = $method;
		$_GET                      = 'GET' === $method ? $params : array();
		$_POST                     = 'GET' === $method ? array() : $params;

		foreach ( $headers as $header => $value ) {
			$_SERVER[ 'HTTP_' . strtoupper( str_replace( '-', '_', $header ) ) ] = $value;
		}

		$GLOBALS['wp_rest_server'] = null;
		$server                    = rest_get_server();
		$server->serve_request( $path );

		return array(
			'status'  => $server->status,
			'headers' => $server->sent_headers,
			'body'    => $server->sent_body,
		);
	}

	/**
	 * Registers a public Fragment, filling in the keys a test doesn't care about.
	 *
	 * @param string               $name Fragment name.
	 * @param array<string, mixed> $args Registration arguments that matter to the test.
	 * @return bool Whether the Fragment was registered.
	 */
	protected function register_fragment( string $name, array $args ): bool {
		return hyx_render_register_fragment(
			$name,
			array_merge(
				array(
					'label'               => 'Test Fragment',
					'permission_callback' => '__return_true',
				),
				$args
			)
		);
	}

	/**
	 * Asserts the response is an HTML error paragraph with the given status.
	 *
	 * @param int                                                                   $status   Expected HTTP status.
	 * @param array{status: int|null, headers: array<string, string>, body: string} $response Response from `request()`.
	 */
	protected function assert_html_error( int $status, array $response ): void {
		$this->assertSame( $status, $response['status'], $response['body'] );
		$this->assertSame( 'text/html; charset=UTF-8', $response['headers']['Content-Type'] );
		$this->assertStringStartsWith( '<p class="hyx-render-error" role="alert">', $response['body'] );
	}

	/**
	 * Writes a harmless PHP template to the system temp folder, outside every allowed folder.
	 *
	 * @return string Absolute path, removed after the test.
	 */
	protected function file_outside_allowed_folders(): string {
		$path = sys_get_temp_dir() . '/hyx-render-outside-' . wp_generate_password( 8, false ) . '.php';
		file_put_contents( $path, '<p>Escaped the allowed folders</p>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->remove_after_test( $path );

		return $path;
	}

	/**
	 * Removes a file or link once the test ends.
	 *
	 * @param string $path File the test wrote.
	 */
	protected function remove_after_test( string $path ): void {
		$this->temp_files[] = $path;
	}
}
