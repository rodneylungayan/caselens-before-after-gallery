<?php
/**
 * Everything that reads one Gallery Case: its photo pairs, number, heading and neighbours.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Case model helpers.
 */
class CaseLens_Case {

	const META_PAIRS = '_caselens_pairs';

	/**
	 * Before/after photo pairs saved on a case.
	 *
	 * @param int $post_id Case ID.
	 * @return array List of array( 'before' => int, 'after' => int, 'caption' => string ).
	 */
	public static function pairs( $post_id ) {
		$raw = get_post_meta( $post_id, self::META_PAIRS, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $pair ) {
			if ( ! is_array( $pair ) ) {
				continue;
			}
			$before = isset( $pair['before'] ) ? absint( $pair['before'] ) : 0;
			$after  = isset( $pair['after'] ) ? absint( $pair['after'] ) : 0;
			if ( ! $before && ! $after ) {
				continue;
			}
			$out[] = array(
				'before'  => $before,
				'after'   => $after,
				'caption' => isset( $pair['caption'] ) ? (string) $pair['caption'] : '',
			);
		}

		return $out;
	}

	/**
	 * Save photo pairs on a case.
	 *
	 * @param int   $post_id Case ID.
	 * @param array $pairs   Raw pairs.
	 * @return void
	 */
	public static function save_pairs( $post_id, $pairs ) {
		$clean = array();

		foreach ( (array) $pairs as $pair ) {
			if ( ! is_array( $pair ) ) {
				continue;
			}
			$before = isset( $pair['before'] ) ? absint( $pair['before'] ) : 0;
			$after  = isset( $pair['after'] ) ? absint( $pair['after'] ) : 0;

			// Only keep IDs that really are images in the media library.
			foreach ( array( 'before', 'after' ) as $slot ) {
				$id = 'before' === $slot ? $before : $after;
				if ( $id && 'attachment' !== get_post_type( $id ) ) {
					$id = 0;
				}
				if ( 'before' === $slot ) {
					$before = $id;
				} else {
					$after = $id;
				}
			}

			if ( ! $before && ! $after ) {
				continue;
			}

			$clean[] = array(
				'before'  => $before,
				'after'   => $after,
				'caption' => isset( $pair['caption'] ) ? sanitize_text_field( (string) $pair['caption'] ) : '',
			);
		}

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, self::META_PAIRS );
			return;
		}

		update_post_meta( $post_id, self::META_PAIRS, $clean );
	}

	/**
	 * Pairs expanded with image URLs, ready for output or JSON.
	 *
	 * @param int    $post_id Case ID.
	 * @param string $size    Image size.
	 * @return array
	 */
	public static function photos( $post_id, $size = 'large' ) {
		$out = array();

		foreach ( self::pairs( $post_id ) as $pair ) {
			$slide = array( 'caption' => $pair['caption'] );
			foreach ( array( 'before', 'after' ) as $slot ) {
				$id = $pair[ $slot ];
				if ( ! $id ) {
					$slide[ $slot ] = null;
					continue;
				}
				$slide[ $slot ] = array(
					'id'    => $id,
					'url'   => wp_get_attachment_image_url( $id, $size ),
					'full'  => wp_get_attachment_image_url( $id, 'full' ),
					'srcset' => wp_get_attachment_image_srcset( $id, $size ),
					'alt'   => trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
					'width' => 0,
					'height' => 0,
				);
				$meta = wp_get_attachment_metadata( $id );
				if ( is_array( $meta ) ) {
					$slide[ $slot ]['width']  = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
					$slide[ $slot ]['height'] = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
				}
			}
			$out[] = $slide;
		}

		return $out;
	}

	/**
	 * The two thumbnails shown on a grid card: the first pair, or the featured image.
	 *
	 * @param int    $post_id Case ID.
	 * @param string $size    Image size.
	 * @return array List of array( 'url' => string, 'alt' => string ).
	 */
	public static function card_images( $post_id, $size = 'medium_large' ) {
		$out   = array();
		$pairs = self::pairs( $post_id );

		if ( ! empty( $pairs ) ) {
			foreach ( array( 'before', 'after' ) as $slot ) {
				$id = $pairs[0][ $slot ];
				if ( ! $id ) {
					continue;
				}
				$url = wp_get_attachment_image_url( $id, $size );
				if ( $url ) {
					$out[] = array(
						'url' => $url,
						'alt' => trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
					);
				}
			}
		}

		if ( empty( $out ) ) {
			$thumb = get_post_thumbnail_id( $post_id );
			if ( $thumb ) {
				$url = wp_get_attachment_image_url( $thumb, $size );
				if ( $url ) {
					$out[] = array(
						'url' => $url,
						'alt' => trim( (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) ),
					);
				}
			}
		}

		return $out;
	}

	/**
	 * The case number: the "Case number" field if defined, else a number in the title or slug.
	 *
	 * @param int $post_id Case ID.
	 * @return string
	 */
	public static function number( $post_id ) {
		foreach ( array( 'case_number', 'patient_number', 'number' ) as $key ) {
			$field = CaseLens_Fields::get( $key );
			if ( $field ) {
				$values = CaseLens_Fields::raw_values( $field, $post_id );
				if ( ! empty( $values ) ) {
					return (string) $values[0];
				}
			}
		}

		$title = wp_strip_all_tags( (string) get_the_title( $post_id ) );
		if ( preg_match( '/#\s*([0-9]+)/', $title, $m ) ) {
			return $m[1];
		}

		$slug = (string) get_post_field( 'post_name', $post_id );
		if ( ctype_digit( $slug ) ) {
			return $slug;
		}

		return '';
	}

	/**
	 * Procedure terms on a case, most specific first.
	 *
	 * @param int $post_id Case ID.
	 * @return WP_Term[]
	 */
	public static function terms( $post_id ) {
		$terms = get_the_terms( $post_id, CASELENS_TAX );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		$terms = array_values( $terms );
		usort(
			$terms,
			static function ( $a, $b ) {
				$da = count( get_ancestors( $a->term_id, CASELENS_TAX, 'taxonomy' ) );
				$db = count( get_ancestors( $b->term_id, CASELENS_TAX, 'taxonomy' ) );
				if ( $da === $db ) {
					return strnatcasecmp( $a->name, $b->name );
				}
				return $db - $da;
			}
		);

		return $terms;
	}

	/**
	 * The term that owns a case's URL: the Yoast primary term when set, else the most specific one.
	 *
	 * @param int $post_id Case ID.
	 * @return WP_Term|null
	 */
	public static function primary_term( $post_id ) {
		$terms = self::terms( $post_id );
		if ( empty( $terms ) ) {
			return null;
		}

		$primary = (int) get_post_meta( $post_id, '_yoast_wpseo_primary_' . CASELENS_TAX, true );
		if ( $primary ) {
			foreach ( $terms as $term ) {
				if ( (int) $term->term_id === $primary ) {
					return $term;
				}
			}
		}

		return $terms[0];
	}

	/**
	 * Heading for a case: its own title, or "{Procedure} {noun} #{number}" when it has none.
	 *
	 * @param int $post_id Case ID.
	 * @return string
	 */
	public static function heading( $post_id ) {
		$title = trim( wp_strip_all_tags( (string) get_the_title( $post_id ) ) );
		if ( '' !== $title ) {
			return html_entity_decode( $title, ENT_QUOTES, 'UTF-8' );
		}

		$noun   = (string) CaseLens_Settings::get( 'case_noun' );
		$number = self::number( $post_id );
		$term   = self::primary_term( $post_id );
		$parts  = array();

		if ( $term ) {
			$parts[] = wp_strip_all_tags( $term->name );
		}
		if ( '' !== $noun ) {
			$parts[] = $noun;
		}

		$heading = trim( implode( ' ', $parts ) );
		if ( '' !== $number ) {
			$heading = trim( $heading . ' #' . $number );
		}

		return '' !== $heading ? $heading : __( 'Case', 'caselens-before-after-gallery' );
	}

	/**
	 * The case before or after this one, inside the same procedure.
	 *
	 * @param int  $post_id  Case ID.
	 * @param bool $previous True for the previous case.
	 * @return WP_Post|null
	 */
	public static function sibling( $post_id, $previous = false ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		$term = self::primary_term( $post_id );
		$args = array(
			'post_type'              => CASELENS_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'post__not_in'           => array( $post_id ),
			'orderby'                => 'date',
			'order'                  => $previous ? 'DESC' : 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'ignore_sticky_posts'    => true,
			'date_query'             => array(
				array(
					( $previous ? 'before' : 'after' ) => $post->post_date,
					'inclusive'                        => false,
				),
			),
		);

		if ( $term ) {
			$args['tax_query'] = array(
				array(
					'taxonomy'         => CASELENS_TAX,
					'field'            => 'term_id',
					'terms'            => (int) $term->term_id,
					'include_children' => true,
				),
			);
		}

		$found = get_posts( $args );

		return ! empty( $found ) ? $found[0] : null;
	}
}
