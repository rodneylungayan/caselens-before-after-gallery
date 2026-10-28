<?php
/**
 * REST endpoints the front-end gallery talks to.
 *
 * All three are read-only and return published cases, which are public content,
 * so they are open in the same way the core posts endpoint is.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Routes under the caselens/v1 namespace.
 */
class CaseLens_REST {

	const NAMESPACE_V1 = 'caselens/v1';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the gallery routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/cases',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cases' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'procedure' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'number'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'      => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'  => array(
						'type'              => 'integer',
						'default'           => (int) CaseLens_Settings::get( 'per_page', 12 ),
						'sanitize_callback' => 'absint',
					),
					'f'         => array(
						'type'        => 'object',
						'default'     => array(),
						'description' => __( 'Choice filters, as field key => list of values.', 'caselens-before-after-gallery' ),
					),
					'r'         => array(
						'type'        => 'object',
						'default'     => array(),
						'description' => __( 'Range filters, as field key => { min, max }.', 'caselens-before-after-gallery' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/filters',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_filters' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'procedure' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/procedures',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_procedures' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * A page of gallery cards.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_cases( $request ) {
		$result = CaseLens_Query::cards(
			array(
				'procedure' => (string) $request->get_param( 'procedure' ),
				'number'    => (string) $request->get_param( 'number' ),
				'page'      => max( 1, (int) $request->get_param( 'page' ) ),
				'per_page'  => (int) $request->get_param( 'per_page' ),
				'filters'   => $this->clean_filters( $request->get_param( 'f' ) ),
				'ranges'    => $this->clean_ranges( $request->get_param( 'r' ) ),
			)
		);

		$response = rest_ensure_response( $result );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );

		return $response;
	}

	/**
	 * Filter options for the current procedure.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_filters( $request ) {
		return rest_ensure_response(
			array(
				'groups' => CaseLens_Query::filter_options( (string) $request->get_param( 'procedure' ) ),
			)
		);
	}

	/**
	 * The procedure tree.
	 *
	 * @return WP_REST_Response
	 */
	public function get_procedures() {
		return rest_ensure_response(
			array(
				'procedures' => CaseLens_Query::procedure_tree(),
			)
		);
	}

	/**
	 * Keep only known choice fields and scalar values.
	 *
	 * @param mixed $raw Submitted filters.
	 * @return array
	 */
	private function clean_filters( $raw ) {
		$out = array();

		foreach ( (array) $raw as $key => $values ) {
			$key   = sanitize_key( $key );
			$field = CaseLens_Fields::get( $key );
			if ( ! $field || empty( $field['filter'] ) || 'number' === $field['type'] ) {
				continue;
			}

			$clean = array();
			foreach ( (array) $values as $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}
				$value = sanitize_text_field( (string) $value );
				if ( '' !== $value ) {
					$clean[] = $value;
				}
			}

			if ( ! empty( $clean ) ) {
				$out[ $key ] = array_slice( array_unique( $clean ), 0, 40 );
			}
		}

		return $out;
	}

	/**
	 * Keep only known number fields and numeric ends.
	 *
	 * @param mixed $raw Submitted ranges.
	 * @return array
	 */
	private function clean_ranges( $raw ) {
		$out = array();

		foreach ( (array) $raw as $key => $ends ) {
			$key   = sanitize_key( $key );
			$field = CaseLens_Fields::get( $key );
			if ( ! $field || empty( $field['filter'] ) || 'number' !== $field['type'] || ! is_array( $ends ) ) {
				continue;
			}

			$clean = array();
			foreach ( array( 'min', 'max' ) as $end ) {
				if ( isset( $ends[ $end ] ) && is_numeric( $ends[ $end ] ) ) {
					$clean[ $end ] = (float) $ends[ $end ];
				}
			}

			if ( ! empty( $clean ) ) {
				$out[ $key ] = $clean;
			}
		}

		return $out;
	}
}
