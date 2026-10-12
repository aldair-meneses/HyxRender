<?php
/**
 * Fragment arguments declared as a REST argument schema.
 *
 * @package HyxRender
 */

declare( strict_types=1 );

namespace Hyx\Render\Tests\Integration;

/**
 * Only declared arguments reach a Fragment, validated and sanitized the way the REST API does it.
 */
final class FragmentArgumentsTest extends FragmentTestCase {

	/**
	 * Declared arguments reach the render callable coerced to their schema type, and nothing else does.
	 */
	public function test_declared_arguments_reach_the_render_callable_sanitized(): void {
		$this->register_transactions_fragment( 'acme/transactions-sanitized' );

		$response = $this->request(
			'GET',
			'acme/transactions-sanitized',
			array(
				'category'   => 'food',
				'page'       => '3',
				'undeclared' => 'dropped',
			)
		);

		$this->assertSame( 200, $response['status'] );
		$this->assertSame( '{"category":"food","page":3}', $response['body'] );
	}

	/**
	 * An argument the request leaves out falls back to its schema default.
	 */
	public function test_an_omitted_argument_gets_its_default(): void {
		$this->register_transactions_fragment( 'acme/transactions-default' );

		$response = $this->request( 'GET', 'acme/transactions-default', array( 'category' => 'food' ) );

		$this->assertSame( '{"category":"food","page":1}', $response['body'] );
	}

	/**
	 * Arguments that break the schema are a 422 naming the argument, and the Fragment never renders.
	 *
	 * @dataProvider data_invalid_arguments
	 *
	 * @param array<string, string> $params  Query parameters.
	 * @param string                $invalid Name of the offending argument.
	 */
	public function test_invalid_arguments_return_422_as_html( array $params, string $invalid ): void {
		$name = 'acme/transactions-invalid-' . sanitize_title( (string) $this->dataName() );
		$this->register_transactions_fragment( $name );

		$response = $this->request( 'GET', $name, $params );

		$this->assert_html_error( 422, $response );
		$this->assertStringContainsString( $invalid, $response['body'] );
	}

	/**
	 * Query strings that break the transactions schema.
	 *
	 * @return array<string, array{array<string, string>, string}>
	 */
	public static function data_invalid_arguments(): array {
		return array(
			'value outside enum' => array( array( 'category' => 'travel' ), 'category' ),
			'below minimum'      => array(
				array(
					'category' => 'food',
					'page'     => '0',
				),
				'page',
			),
			'wrong type'         => array(
				array(
					'category' => 'food',
					'page'     => 'two',
				),
				'page',
			),
			'required missing'   => array( array( 'page' => '2' ), 'category' ),
		);
	}

	/**
	 * Registers a Fragment that echoes the arguments it receives as JSON.
	 *
	 * @param string $name Fragment name.
	 */
	private function register_transactions_fragment( string $name ): void {
		$this->register_fragment(
			$name,
			array(
				'args'   => array(
					'category' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'food', 'rent' ),
					),
					'page'     => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
				),
				'render' => static fn( array $args ): string => (string) wp_json_encode( $args ),
			)
		);
	}
}
