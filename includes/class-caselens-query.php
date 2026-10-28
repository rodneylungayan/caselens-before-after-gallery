<?php
/**
 * Queries behind the gallery: finding cases, counting them and building the filter options.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read-only queries over Gallery Cases.
 */
class CaseLens_Query {

	/**
	 * Published case count per procedure term (term_id => count).
	 *
	 * @return int[]
	 */
	public static function counts() {
		$cached = wp_cache_get( 'counts', 'caselens' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT tt.term_id AS term_id, COUNT( DISTINCT p.ID ) AS total
				 FROM {$wpdb->term_relationships} tr
				 INNER JOIN {$wpdb->term_taxonomy} tt
				     ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
				 INNER JOIN {$wpdb->posts} p
				     ON p.ID = tr.object_id AND p.post_type = %s AND p.post_status = 'publish'
				 GROUP BY tt.term_id",
				CASELENS_TAX,
				CASELENS_CPT
			)
		);

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->term_id ] = (int) $row->total;
		}

		wp_cache_set( 'counts', $counts, 'caselens', 5 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * Drop the cached counts after a case or term changes.
	 *
	 * @return void
	 */
	public static function flush_counts() {
		wp_cache_delete( 'counts', 'caselens' );
	}

	/**
	 * Number of published cases in a term, including its children.
	 *
	 * @param WP_Term $term Procedure term.
	 * @return int
	 */
	public static function term_count( $term ) {
		$counts = self::counts();
		$total  = isset( $counts[ (int) $term->term_id ] ) ? $counts[ (int) $term->term_id ] : 0;

		foreach ( get_term_children( (int) $term->term_id, CASELENS_TAX ) as $child_id ) {
			$total += isset( $counts[ (int) $child_id ] ) ? $counts[ (int) $child_id ] : 0;
		}

		return $total;
	}

	/**
	 * True when a term, or any term inside it, has a published case.
	 *
	 * @param WP_Term $term Procedure term.
	 * @return bool
	 */
	public static function term_has_cases( $term ) {
		return self::term_count( $term ) > 0;
	}

	/**
	 * The procedure tree for the sidebar, ordered by the setting then alphabetically.
	 *
	 * @return array Nested array of array( 'id', 'name', 'slug', 'url', 'count', 'children' ).
	 */
	public static function procedure_tree() {
		$terms = get_terms(
			array(
				'taxonomy'   => CASELENS_TAX,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$hide_empty = (bool) CaseLens_Settings::get( 'hide_empty' );
		$order      = CaseLens_Settings::order_slugs();
		$by_parent  = array();

		foreach ( $terms as $term ) {
			$count = self::term_count( $term );
			if ( $hide_empty && $count < 1 ) {
				continue;
			}
			$by_parent[ (int) $term->parent ][] = array(
				'id'       => (int) $term->term_id,
				'name'     => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'slug'     => $term->slug,
				'url'      => CaseLens_Rewrites::term_url( $term ),
				'desc'     => wp_strip_all_tags( (string) $term->description ),
				'count'    => $count,
				'children' => array(),
			);
		}

		return self::build_branch( $by_parent, 0, $order );
	}

	/**
	 * Recursively assemble one level of the procedure tree.
	 *
	 * @param array $by_parent Terms grouped by parent ID.
	 * @param int   $parent    Parent term ID.
	 * @param array $order     Preferred slug order.
	 * @return array
	 */
	private static function build_branch( $by_parent, $parent, $order ) {
		if ( empty( $by_parent[ $parent ] ) ) {
			return array();
		}

		$branch = $by_parent[ $parent ];

		usort(
			$branch,
			static function ( $a, $b ) use ( $order ) {
				$pa = array_search( $a['slug'], $order, true );
				$pb = array_search( $b['slug'], $order, true );
				if ( false !== $pa && false !== $pb ) {
					return $pa - $pb;
				}
				if ( false !== $pa ) {
					return -1;
				}
				if ( false !== $pb ) {
					return 1;
				}
				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);

		foreach ( $branch as &$node ) {
			$node['children'] = self::build_branch( $by_parent, $node['id'], $order );
		}
		unset( $node );

		return $branch;
	}

	/**
	 * Build the WP_Query arguments for a gallery request.
	 *
	 * @param array $args Keys: procedure (slug or id), filters, ranges, number, page, per_page.
	 * @return array
	 */
	public static function build_args( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'procedure' => '',
				'filters'   => array(),
				'ranges'    => array(),
				'number'    => '',
				'page'      => 1,
				'per_page'  => (int) CaseLens_Settings::get( 'per_page', 12 ),
			)
		);

		$query = array(
			'post_type'              => CASELENS_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => max( 1, min( 60, (int) $args['per_page'] ) ),
			'paged'                  => max( 1, (int) $args['page'] ),
			'orderby'                => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		);

		if ( '' !== $args['procedure'] ) {
			$query['tax_query'] = array(
				array(
					'taxonomy'         => CASELENS_TAX,
					'field'            => is_numeric( $args['procedure'] ) ? 'term_id' : 'slug',
					'terms'            => is_numeric( $args['procedure'] ) ? (int) $args['procedure'] : sanitize_title( $args['procedure'] ),
					'include_children' => true,
				),
			);
		}

		$meta = self::meta_query( $args['filters'], $args['ranges'] );

		$number = trim( (string) $args['number'] );
		if ( '' !== $number ) {
			$number_meta = self::number_clause( $number );
			if ( $number_meta ) {
				$meta[] = $number_meta;
			} else {
				$query['name'] = sanitize_title( $number );
			}
		}

		if ( count( $meta ) > 1 ) {
			$query['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		return $query;
	}

	/**
	 * Meta query clauses for the chosen field filters.
	 *
	 * @param array $filters Field key => list of values.
	 * @param array $ranges  Field key => array( 'min' => , 'max' => ).
	 * @return array
	 */
	private static function meta_query( $filters, $ranges ) {
		$meta = array( 'relation' => 'AND' );

		foreach ( (array) $filters as $key => $values ) {
			$field = CaseLens_Fields::get( sanitize_key( $key ) );
			if ( ! $field || empty( $field['filter'] ) || ! is_array( $values ) ) {
				continue;
			}

			$any = array( 'relation' => 'OR' );
			foreach ( array_slice( $values, 0, 40 ) as $value ) {
				$value = sanitize_text_field( (string) $value );
				if ( '' === $value ) {
					continue;
				}
				$meta_key = CaseLens_Fields::meta_key( $field['key'] );
				$any[]    = array(
					'key'     => $meta_key,
					'value'   => $value,
					'compare' => '=',
				);
				// Fields that store several values are serialised, so match inside the string too.
				$any[] = array(
					'key'     => $meta_key,
					'value'   => '"' . $value . '"',
					'compare' => 'LIKE',
				);
			}

			if ( count( $any ) > 1 ) {
				$meta[] = $any;
			}
		}

		foreach ( (array) $ranges as $key => $ends ) {
			$field = CaseLens_Fields::get( sanitize_key( $key ) );
			if ( ! $field || empty( $field['filter'] ) || 'number' !== $field['type'] || ! is_array( $ends ) ) {
				continue;
			}
			foreach ( array( 'min' => '>=', 'max' => '<=' ) as $end => $compare ) {
				if ( isset( $ends[ $end ] ) && is_numeric( $ends[ $end ] ) ) {
					$meta[] = array(
						'key'     => CaseLens_Fields::meta_key( $field['key'] ),
						'value'   => (float) $ends[ $end ],
						'type'    => 'NUMERIC',
						'compare' => $compare,
					);
				}
			}
		}

		return $meta;
	}

	/**
	 * Meta clause for a case-number search, when a number field exists.
	 *
	 * @param string $number Submitted number.
	 * @return array|null
	 */
	private static function number_clause( $number ) {
		foreach ( array( 'case_number', 'patient_number', 'number' ) as $key ) {
			$field = CaseLens_Fields::get( $key );
			if ( $field ) {
				return array(
					'key'     => CaseLens_Fields::meta_key( $field['key'] ),
					'value'   => sanitize_text_field( $number ),
					'compare' => '=',
				);
			}
		}

		return null;
	}

	/**
	 * Cases for the grid, as plain arrays ready for JSON.
	 *
	 * @param array $args See build_args().
	 * @return array array( 'cases' => array, 'total' => int, 'pages' => int )
	 */
	public static function cards( $args ) {
		$query = new WP_Query( self::build_args( $args ) );
		$cases = array();

		foreach ( $query->posts as $post ) {
			$term    = CaseLens_Case::primary_term( $post->ID );
			$cases[] = array(
				'id'        => (int) $post->ID,
				'title'     => CaseLens_Case::heading( $post->ID ),
				'url'       => get_permalink( $post ),
				'procedure' => $term ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : '',
				'images'    => CaseLens_Case::card_images( $post->ID ),
			);
		}

		return array(
			'cases' => $cases,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Filter options: every value the cases in scope actually have, with counts.
	 *
	 * @param string $procedure Procedure slug or ID to narrow the options to.
	 * @return array Groups, each array( 'title' => string, 'fields' => array ).
	 */
	public static function filter_options( $procedure = '' ) {
		$fields = CaseLens_Fields::filterable();
		if ( empty( $fields ) ) {
			return array();
		}

		$id_args = self::build_args(
			array(
				'procedure' => $procedure,
				'per_page'  => -1,
			)
		);
		$id_args['posts_per_page']         = -1;
		$id_args['fields']                 = 'ids';
		$id_args['no_found_rows']          = true;
		$id_args['update_post_meta_cache'] = false;

		$query = new WP_Query( $id_args );
		$ids   = array_map( 'intval', $query->posts );

		if ( empty( $ids ) ) {
			return array();
		}

		$rows = self::meta_rows( $fields, $ids );

		$seen   = array(); // field key => label => array( values, posts ).
		$ranges = array(); // field key => array( min, max ).
		$by_key = array();
		foreach ( $fields as $field ) {
			$by_key[ CaseLens_Fields::meta_key( $field['key'] ) ] = $field;
		}

		foreach ( $rows as $row ) {
			if ( ! isset( $by_key[ $row->meta_key ] ) ) {
				continue;
			}
			$field  = $by_key[ $row->meta_key ];
			$values = maybe_unserialize( $row->meta_value );
			$values = is_array( $values ) ? $values : array( $values );

			foreach ( $values as $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}
				$value = trim( (string) $value );
				if ( '' === $value ) {
					continue;
				}

				if ( 'number' === $field['type'] ) {
					if ( preg_match( '/-?\d+(\.\d+)?/', $value, $m ) ) {
						$number = (float) $m[0];
						$ranges[ $field['key'] ] = isset( $ranges[ $field['key'] ] )
							? array( min( $ranges[ $field['key'] ][0], $number ), max( $ranges[ $field['key'] ][1], $number ) )
							: array( $number, $number );
					}
					continue;
				}

				$label = CaseLens_Fields::value_label( $field, $value );
				$seen[ $field['key'] ][ $label ]['values'][ $value ]            = 1;
				$seen[ $field['key'] ][ $label ]['posts'][ (int) $row->post_id ] = 1;
			}
		}

		$groups = array();
		foreach ( CaseLens_Fields::by_group( $fields ) as $title => $list ) {
			$items = array();

			foreach ( $list as $field ) {
				if ( 'number' === $field['type'] ) {
					if ( isset( $ranges[ $field['key'] ] ) && $ranges[ $field['key'] ][0] < $ranges[ $field['key'] ][1] ) {
						$items[] = array(
							'key'    => $field['key'],
							'label'  => $field['label'],
							'type'   => 'range',
							'suffix' => $field['suffix'],
							'min'    => $ranges[ $field['key'] ][0],
							'max'    => $ranges[ $field['key'] ][1],
						);
					}
					continue;
				}

				if ( empty( $seen[ $field['key'] ] ) ) {
					continue;
				}

				$options = array();
				foreach ( $seen[ $field['key'] ] as $label => $info ) {
					$options[] = array(
						'label'  => (string) $label,
						'values' => array_map( 'strval', array_keys( $info['values'] ) ),
						'count'  => count( $info['posts'] ),
					);
				}

				$preferred = array_values( $field['choices'] );
				usort(
					$options,
					static function ( $a, $b ) use ( $preferred ) {
						$pa = array_search( $a['label'], $preferred, true );
						$pb = array_search( $b['label'], $preferred, true );
						if ( false !== $pa && false !== $pb ) {
							return $pa - $pb;
						}
						return strnatcasecmp( $a['label'], $b['label'] );
					}
				);

				$items[] = array(
					'key'     => $field['key'],
					'label'   => $field['label'],
					'type'    => 'choice',
					'options' => $options,
				);
			}

			if ( ! empty( $items ) ) {
				$groups[] = array(
					'title'  => (string) $title,
					'fields' => $items,
				);
			}
		}

		return $groups;
	}

	/**
	 * Read the filterable meta of a set of cases in one query.
	 *
	 * @param array $fields Field definitions.
	 * @param int[] $ids    Case IDs.
	 * @return array Rows of post_id, meta_key, meta_value.
	 */
	private static function meta_rows( $fields, $ids ) {
		global $wpdb;

		$meta_keys = array();
		foreach ( $fields as $field ) {
			$meta_keys[] = CaseLens_Fields::meta_key( $field['key'] );
		}

		if ( empty( $meta_keys ) || empty( $ids ) ) {
			return array();
		}

		$key_slots = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
		$id_slots  = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_key, meta_value
				 FROM {$wpdb->postmeta}
				 WHERE meta_key IN ( {$key_slots} ) AND post_id IN ( {$id_slots} )",
				array_merge( $meta_keys, $ids )
			)
		);
		// phpcs:enable
	}
}
