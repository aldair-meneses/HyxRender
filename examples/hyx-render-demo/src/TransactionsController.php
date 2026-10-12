<?php
/**
 * REST API for the demo transactions.
 *
 * @package HyxRenderDemo
 */

declare( strict_types=1 );

namespace Hyx\RenderDemo;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The `hyx/v1/transactions` routes: public reads, writes for users who can edit posts.
 */
final class TransactionsController {

	private const ROUTE_NAMESPACE = 'hyx/v1';

	private const TYPES = array( 'income', 'expense' );

	/**
	 * Wires the controller to the transactions table.
	 *
	 * @param TransactionsTable $table Transactions table.
	 */
	public function __construct( private readonly TransactionsTable $table ) {
	}

	/**
	 * Registers the collection and single-item routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/transactions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => '__return_true',
					'args'                => $this->collection_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'write_permission' ),
					'args'                => $this->write_args(),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/transactions/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'write_permission' ),
					'args'                => $this->write_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'write_permission' ),
				),
			)
		);
	}

	/**
	 * Writes are limited to users who can edit posts.
	 */
	public function write_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Lists transactions with paging and optional type and category filters.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function list_items( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$table    = $this->table->name();
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$values = array();

		$type = $request->get_param( 'type' );
		if ( is_string( $type ) && '' !== $type ) {
			$where[]  = 'type = %s';
			$values[] = $type;
		}

		$category = $request->get_param( 'category' );
		if ( is_string( $category ) && '' !== $category ) {
			$where[]  = 'category = %s';
			$values[] = $category;
		}

		$where_sql = implode( ' AND ', $where );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( sprintf( 'SELECT COUNT(*) FROM `%s` WHERE %s', esc_sql( $table ), $where_sql ), $values )
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				sprintf(
					'SELECT * FROM `%s` WHERE %s ORDER BY transaction_date DESC, id DESC LIMIT %%d OFFSET %%d',
					esc_sql( $table ),
					$where_sql
				),
				array_merge( $values, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		$response = new WP_REST_Response( array_map( array( $this, 'format_row' ), $rows ? $rows : array() ) );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $total / $per_page ) ) );

		return $response;
	}

	/**
	 * Returns one transaction.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$row = $this->find_row( (int) $request->get_param( 'id' ) );

		if ( null === $row ) {
			return $this->not_found();
		}

		return new WP_REST_Response( $this->format_row( $row ) );
	}

	/**
	 * Creates a transaction.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function create_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$validated = $this->validate_payload( $request );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$wpdb->insert(
			$this->table->name(),
			array_merge( $validated, array( 'created_at' => current_time( 'mysql' ) ) ),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$response = new WP_REST_Response( $this->format_row( $this->find_row( (int) $wpdb->insert_id ) ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Updates the fields present in the request.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function update_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id  = (int) $request->get_param( 'id' );
		$row = $this->find_row( $id );

		if ( null === $row ) {
			return $this->not_found();
		}

		$validated = $this->validate_payload( $request, false );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		if ( array() === $validated ) {
			return new WP_REST_Response( $this->format_row( $row ) );
		}

		$formats = array(
			'description'      => '%s',
			'amount'           => '%s',
			'type'             => '%s',
			'category'         => '%s',
			'transaction_date' => '%s',
		);

		$wpdb->update(
			$this->table->name(),
			$validated,
			array( 'id' => $id ),
			array_values( array_intersect_key( $formats, $validated ) ),
			array( '%d' )
		);

		return new WP_REST_Response( $this->format_row( $this->find_row( $id ) ) );
	}

	/**
	 * Deletes a transaction.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function delete_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		if ( null === $this->find_row( $id ) ) {
			return $this->not_found();
		}

		$wpdb->delete( $this->table->name(), array( 'id' => $id ), array( '%d' ) );

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/**
	 * Collection query arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function collection_args(): array {
		return array(
			'page'     => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'default'           => 20,
				'sanitize_callback' => 'absint',
			),
			'type'     => array( 'sanitize_callback' => 'sanitize_key' ),
			'category' => array( 'sanitize_callback' => 'sanitize_text_field' ),
		);
	}

	/**
	 * Create and update arguments.
	 *
	 * @param bool $required Whether required fields are enforced (create) or optional (update).
	 * @return array<string, array<string, mixed>>
	 */
	private function write_args( bool $required = true ): array {
		return array(
			'description'      => array(
				'required'          => $required,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'amount'           => array(
				'required' => $required,
				'type'     => 'number',
			),
			'type'             => array(
				'required'          => $required,
				'type'              => 'string',
				'enum'              => self::TYPES,
				'sanitize_callback' => 'sanitize_key',
			),
			'category'         => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'transaction_date' => array(
				'required' => $required,
				'type'     => 'string',
				'format'   => 'date',
			),
		);
	}

	/**
	 * Fetches one row by ID.
	 *
	 * @param int $id Transaction ID.
	 * @return array<string, string>|null
	 */
	private function find_row( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( sprintf( 'SELECT * FROM `%s` WHERE id = %%d', esc_sql( $this->table->name() ) ), $id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Validates and normalizes the writable fields present in the request.
	 *
	 * @param WP_REST_Request $request     Request.
	 * @param bool            $require_all Whether every field except category must be present.
	 * @return array<string, string>|WP_Error
	 */
	private function validate_payload( WP_REST_Request $request, bool $require_all = true ): array|WP_Error {
		$fields = array( 'description', 'amount', 'type', 'category', 'transaction_date' );
		$data   = array();

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );

			if ( null === $value || '' === $value ) {
				if ( $require_all && 'category' !== $field ) {
					return new WP_Error( 'hyx_missing_field', sprintf( 'Missing field: %s.', $field ), array( 'status' => 400 ) );
				}
				continue;
			}

			$data[ $field ] = $value;
		}

		if ( isset( $data['description'] ) ) {
			$data['description'] = sanitize_text_field( (string) $data['description'] );
			if ( '' === $data['description'] ) {
				return new WP_Error( 'hyx_invalid_description', 'Description is empty.', array( 'status' => 400 ) );
			}
		}

		if ( isset( $data['amount'] ) ) {
			if ( ! is_numeric( $data['amount'] ) ) {
				return new WP_Error( 'hyx_invalid_amount', 'Amount must be numeric.', array( 'status' => 400 ) );
			}
			$data['amount'] = number_format( (float) $data['amount'], 2, '.', '' );
		}

		if ( isset( $data['type'] ) && ! in_array( $data['type'], self::TYPES, true ) ) {
			return new WP_Error( 'hyx_invalid_type', 'Type must be income or expense.', array( 'status' => 400 ) );
		}

		if ( isset( $data['category'] ) ) {
			$data['category'] = sanitize_text_field( (string) $data['category'] );
		}

		if ( isset( $data['transaction_date'] ) ) {
			$date   = (string) $data['transaction_date'];
			$parsed = date_create_from_format( 'Y-m-d', $date );
			if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
				return new WP_Error( 'hyx_invalid_date', 'transaction_date must use Y-m-d format.', array( 'status' => 400 ) );
			}
			$data['transaction_date'] = $date;
		}

		return $data;
	}

	/**
	 * Shapes a database row for the API.
	 *
	 * @param array<string, string> $row Database row.
	 * @return array<string, int|float|string>
	 */
	private function format_row( array $row ): array {
		return array(
			'id'               => (int) $row['id'],
			'description'      => (string) $row['description'],
			'amount'           => (float) $row['amount'],
			'type'             => (string) $row['type'],
			'category'         => (string) $row['category'],
			'transaction_date' => (string) $row['transaction_date'],
			'created_at'       => (string) $row['created_at'],
		);
	}

	/**
	 * The 404 error every single-item route returns.
	 */
	private function not_found(): WP_Error {
		return new WP_Error( 'hyx_not_found', 'Transaction not found.', array( 'status' => 404 ) );
	}
}
