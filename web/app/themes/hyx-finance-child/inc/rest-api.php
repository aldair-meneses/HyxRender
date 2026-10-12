<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

final class Hyx_Finance_Rest {

	public static function boot(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'hyx/v1',
			'/transactions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'list_items' ),
					'permission_callback' => '__return_true',
					'args'                => self::collection_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'create_item' ),
					'permission_callback' => array( self::class, 'write_permission' ),
					'args'                => self::write_args(),
				),
			)
		);

		register_rest_route(
			'hyx/v1',
			'/transactions/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_item' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update_item' ),
					'permission_callback' => array( self::class, 'write_permission' ),
					'args'                => self::write_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'delete_item' ),
					'permission_callback' => array( self::class, 'write_permission' ),
				),
			)
		);
	}

	public static function write_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	private static function collection_args(): array {
		return array(
			'page'     => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
			'per_page' => array( 'default' => 20, 'sanitize_callback' => 'absint' ),
			'type'     => array( 'sanitize_callback' => 'sanitize_key' ),
			'category' => array( 'sanitize_callback' => 'sanitize_text_field' ),
		);
	}

	private static function write_args( bool $required = true ): array {
		$req = static function ( bool $field_required ) use ( $required ): bool {
			return $required && $field_required;
		};

		return array(
			'description'      => array(
				'required'          => $req( true ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'amount'           => array(
				'required' => $req( true ),
				'type'     => 'number',
			),
			'type'             => array(
				'required'          => $req( true ),
				'type'              => 'string',
				'enum'              => array( 'income', 'expense' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'category'         => array(
				'required'          => $req( false ),
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'transaction_date' => array(
				'required' => $req( true ),
				'type'     => 'string',
				'format'   => 'date',
			),
		);
	}

	public static function list_items( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$table    = Hyx_Finance_Table::name();
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

		$response = new WP_REST_Response( array_map( array( self::class, 'format_row' ), $rows ?: array() ) );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $total / $per_page ) ) );

		return $response;
	}

	public static function get_item( WP_REST_Request $request ) {
		$row = self::find_row( (int) $request->get_param( 'id' ) );

		if ( null === $row ) {
			return new WP_Error( 'hyx_not_found', 'Transaction not found.', array( 'status' => 404 ) );
		}

		return new WP_REST_Response( self::format_row( $row ) );
	}

	public static function create_item( WP_REST_Request $request ) {
		global $wpdb;

		$validated = self::validate_payload( $request );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$wpdb->insert(
			Hyx_Finance_Table::name(),
			array_merge( $validated, array( 'created_at' => current_time( 'mysql' ) ) ),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$row = self::find_row( (int) $wpdb->insert_id );

		$response = new WP_REST_Response( self::format_row( $row ) );
		$response->set_status( 201 );

		return $response;
	}

	public static function update_item( WP_REST_Request $request ) {
		global $wpdb;

		$id  = (int) $request->get_param( 'id' );
		$row = self::find_row( $id );

		if ( null === $row ) {
			return new WP_Error( 'hyx_not_found', 'Transaction not found.', array( 'status' => 404 ) );
		}

		$validated = self::validate_payload( $request, false );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		if ( array() === $validated ) {
			return new WP_REST_Response( self::format_row( $row ) );
		}

		$formats = array(
			'description'      => '%s',
			'amount'           => '%s',
			'type'             => '%s',
			'category'         => '%s',
			'transaction_date' => '%s',
		);

		$wpdb->update(
			Hyx_Finance_Table::name(),
			$validated,
			array( 'id' => $id ),
			array_values( array_intersect_key( $formats, $validated ) ),
			array( '%d' )
		);

		return new WP_REST_Response( self::format_row( self::find_row( $id ) ) );
	}

	public static function delete_item( WP_REST_Request $request ) {
		global $wpdb;

		$id  = (int) $request->get_param( 'id' );
		$row = self::find_row( $id );

		if ( null === $row ) {
			return new WP_Error( 'hyx_not_found', 'Transaction not found.', array( 'status' => 404 ) );
		}

		$wpdb->delete( Hyx_Finance_Table::name(), array( 'id' => $id ), array( '%d' ) );

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ) );
	}

	private static function find_row( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( sprintf( 'SELECT * FROM `%s` WHERE id = %%d', esc_sql( Hyx_Finance_Table::name() ) ), $id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	private static function validate_payload( WP_REST_Request $request, bool $require_all = true ) {
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

		if ( isset( $data['type'] ) && ! in_array( $data['type'], array( 'income', 'expense' ), true ) ) {
			return new WP_Error( 'hyx_invalid_type', 'Type must be income or expense.', array( 'status' => 400 ) );
		}

		if ( isset( $data['category'] ) ) {
			$data['category'] = sanitize_text_field( (string) $data['category'] );
		}

		if ( isset( $data['transaction_date'] ) ) {
			$date = (string) $data['transaction_date'];
			$parsed = date_create_from_format( 'Y-m-d', $date );
			if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
				return new WP_Error( 'hyx_invalid_date', 'transaction_date must use Y-m-d format.', array( 'status' => 400 ) );
			}
			$data['transaction_date'] = $date;
		}

		return $data;
	}

	private static function format_row( array $row ): array {
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
}

Hyx_Finance_Rest::boot();
