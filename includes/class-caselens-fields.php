<?php
/**
 * Case detail fields: the list of extra fields a Gallery Case can hold.
 *
 * Site owners define these themselves under Gallery Cases > Case Fields, so the
 * plugin is not tied to one kind of gallery. Each field becomes an input on the
 * case editor, a row in the details table and, when enabled, a front-end filter.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field registry stored in the `caselens_fields` option.
 */
class CaseLens_Fields {

	const OPTION     = 'caselens_fields';
	const META_PREFIX = '_caselens_f_';

	/**
	 * Cached field list.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Field types and their labels.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'text'        => __( 'Text', 'caselens-before-and-after-gallery' ),
			'number'      => __( 'Number (filtered as a range)', 'caselens-before-and-after-gallery' ),
			'select'      => __( 'Choice (one)', 'caselens-before-and-after-gallery' ),
			'multiselect' => __( 'Choice (several)', 'caselens-before-and-after-gallery' ),
		);
	}

	/**
	 * The field set a new install starts with.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			array(
				'key'     => 'case_number',
				'label'   => __( 'Case number', 'caselens-before-and-after-gallery' ),
				'group'   => __( 'Overview', 'caselens-before-and-after-gallery' ),
				'type'    => 'text',
				'choices' => array(),
				'suffix'  => '',
				'filter'  => 0,
				'show'    => 1,
			),
			array(
				'key'     => 'age',
				'label'   => __( 'Age', 'caselens-before-and-after-gallery' ),
				'group'   => __( 'Overview', 'caselens-before-and-after-gallery' ),
				'type'    => 'number',
				'choices' => array(),
				'suffix'  => '',
				'filter'  => 1,
				'show'    => 1,
			),
			array(
				'key'     => 'gender',
				'label'   => __( 'Gender', 'caselens-before-and-after-gallery' ),
				'group'   => __( 'Overview', 'caselens-before-and-after-gallery' ),
				'type'    => 'select',
				'choices' => array(
					'female' => __( 'Female', 'caselens-before-and-after-gallery' ),
					'male'   => __( 'Male', 'caselens-before-and-after-gallery' ),
					'other'  => __( 'Other', 'caselens-before-and-after-gallery' ),
				),
				'suffix'  => '',
				'filter'  => 1,
				'show'    => 1,
			),
			array(
				'key'     => 'height',
				'label'   => __( 'Height', 'caselens-before-and-after-gallery' ),
				'group'   => __( 'Overview', 'caselens-before-and-after-gallery' ),
				'type'    => 'text',
				'choices' => array(),
				'suffix'  => '',
				'filter'  => 1,
				'show'    => 1,
			),
			array(
				'key'     => 'weight',
				'label'   => __( 'Weight', 'caselens-before-and-after-gallery' ),
				'group'   => __( 'Overview', 'caselens-before-and-after-gallery' ),
				'type'    => 'number',
				'choices' => array(),
				'suffix'  => __( 'lbs', 'caselens-before-and-after-gallery' ),
				'filter'  => 1,
				'show'    => 1,
			),
		);
	}

	/**
	 * Every defined field, in order.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, null );
			if ( ! is_array( $stored ) ) {
				$stored = self::defaults();
			}
			self::$cache = self::normalize( $stored );
		}
		return self::$cache;
	}

	/**
	 * One field definition.
	 *
	 * @param string $key Field key.
	 * @return array|null
	 */
	public static function get( $key ) {
		foreach ( self::all() as $field ) {
			if ( $field['key'] === $key ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * Fields that may be used as front-end filters.
	 *
	 * @return array
	 */
	public static function filterable() {
		return array_values(
			array_filter(
				self::all(),
				function ( $field ) {
					return ! empty( $field['filter'] );
				}
			)
		);
	}

	/**
	 * Fields shown in the details table on a case.
	 *
	 * @return array
	 */
	public static function visible() {
		return array_values(
			array_filter(
				self::all(),
				function ( $field ) {
					return ! empty( $field['show'] );
				}
			)
		);
	}

	/**
	 * Fields keyed by their group heading, in definition order.
	 *
	 * @param array|null $fields Field list, defaults to every field.
	 * @return array Group label => field list.
	 */
	public static function by_group( $fields = null ) {
		$fields = null === $fields ? self::all() : $fields;
		$out    = array();
		foreach ( $fields as $field ) {
			$group = '' !== $field['group'] ? $field['group'] : __( 'Details', 'caselens-before-and-after-gallery' );
			$out[ $group ][] = $field;
		}
		return $out;
	}

	/**
	 * Post meta key for a field.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	public static function meta_key( $key ) {
		return self::META_PREFIX . $key;
	}

	/**
	 * Replace the stored field list.
	 *
	 * @param array $fields Raw field definitions.
	 * @return void
	 */
	public static function update( array $fields ) {
		update_option( self::OPTION, self::normalize( $fields ) );
		self::$cache = null;
	}

	/**
	 * Clean a list of field definitions: valid keys, known types, unique keys.
	 *
	 * @param array $fields Raw field definitions.
	 * @return array
	 */
	public static function normalize( $fields ) {
		$out   = array();
		$seen  = array();
		$types = self::types();

		foreach ( (array) $fields as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$key = isset( $raw['key'] ) ? sanitize_key( $raw['key'] ) : '';
			if ( '' === $key && isset( $raw['label'] ) ) {
				$key = sanitize_key( str_replace( '-', '_', sanitize_title( $raw['label'] ) ) );
			}
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$type = isset( $raw['type'] ) ? (string) $raw['type'] : 'text';
			if ( ! isset( $types[ $type ] ) ) {
				$type = 'text';
			}

			$label = isset( $raw['label'] ) ? sanitize_text_field( $raw['label'] ) : '';
			if ( '' === $label ) {
				$label = ucwords( str_replace( '_', ' ', $key ) );
			}

			$out[] = array(
				'key'     => $key,
				'label'   => $label,
				'group'   => isset( $raw['group'] ) ? sanitize_text_field( $raw['group'] ) : '',
				'type'    => $type,
				'choices' => self::normalize_choices( isset( $raw['choices'] ) ? $raw['choices'] : array() ),
				'suffix'  => isset( $raw['suffix'] ) ? sanitize_text_field( $raw['suffix'] ) : '',
				'filter'  => empty( $raw['filter'] ) ? 0 : 1,
				'show'    => empty( $raw['show'] ) ? 0 : 1,
			);
		}

		return $out;
	}

	/**
	 * Accept choices either as a `value : Label` text block or as an array.
	 *
	 * @param mixed $choices Raw choices.
	 * @return array value => label
	 */
	public static function normalize_choices( $choices ) {
		$out = array();

		if ( is_string( $choices ) ) {
			$lines   = preg_split( '/[\r\n]+/', $choices );
			$choices = array();
			foreach ( (array) $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				if ( false !== strpos( $line, ':' ) ) {
					list( $value, $label ) = array_map( 'trim', explode( ':', $line, 2 ) );
				} else {
					$value = $line;
					$label = $line;
				}
				$choices[ $value ] = $label;
			}
		}

		foreach ( (array) $choices as $value => $label ) {
			$value = sanitize_text_field( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			$out[ $value ] = sanitize_text_field( (string) $label );
		}

		return $out;
	}

	/**
	 * Choices rendered back into the `value : Label` text block the editor uses.
	 *
	 * @param array $choices value => label.
	 * @return string
	 */
	public static function choices_to_text( $choices ) {
		$lines = array();
		foreach ( (array) $choices as $value => $label ) {
			$lines[] = $value === $label ? $value : $value . ' : ' . $label;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Readable label for one stored value.
	 *
	 * @param array  $field Field definition.
	 * @param string $value Stored value.
	 * @return string
	 */
	public static function value_label( $field, $value ) {
		$value = (string) $value;
		if ( isset( $field['choices'][ $value ] ) ) {
			return $field['choices'][ $value ];
		}
		return $value;
	}

	/**
	 * The value of a field on a case, ready to display.
	 *
	 * @param array $field   Field definition.
	 * @param int   $post_id Case ID.
	 * @return string Empty string when the case has no value.
	 */
	public static function display_value( $field, $post_id ) {
		$raw = get_post_meta( $post_id, self::meta_key( $field['key'] ), true );

		$values = is_array( $raw ) ? $raw : array( $raw );
		$values = array_filter(
			array_map(
				static function ( $value ) {
					return is_scalar( $value ) ? trim( (string) $value ) : '';
				},
				$values
			),
			static function ( $value ) {
				return '' !== $value;
			}
		);

		if ( empty( $values ) ) {
			return '';
		}

		$labels = array();
		foreach ( $values as $value ) {
			$label = self::value_label( $field, $value );
			if ( '' !== $field['suffix'] ) {
				$label .= ' ' . $field['suffix'];
			}
			$labels[] = $label;
		}

		return implode( ', ', $labels );
	}

	/**
	 * Raw stored value of a field as an array of strings.
	 *
	 * @param array $field   Field definition.
	 * @param int   $post_id Case ID.
	 * @return string[]
	 */
	public static function raw_values( $field, $post_id ) {
		$raw    = get_post_meta( $post_id, self::meta_key( $field['key'] ), true );
		$values = is_array( $raw ) ? $raw : array( $raw );

		return array_values(
			array_filter(
				array_map(
					static function ( $value ) {
						return is_scalar( $value ) ? trim( (string) $value ) : '';
					},
					$values
				),
				static function ( $value ) {
					return '' !== $value;
				}
			)
		);
	}

	/**
	 * Sanitise a submitted value for one field, ready for post meta.
	 *
	 * @param array $field Field definition.
	 * @param mixed $raw   Submitted value.
	 * @return string|array Empty string when nothing was submitted.
	 */
	public static function sanitize_value( $field, $raw ) {
		if ( 'multiselect' === $field['type'] ) {
			$values = is_array( $raw ) ? $raw : array();
			$out    = array();
			foreach ( $values as $value ) {
				$value = sanitize_text_field( (string) $value );
				if ( '' !== $value && isset( $field['choices'][ $value ] ) ) {
					$out[] = $value;
				}
			}
			return $out;
		}

		$value = is_scalar( $raw ) ? trim( sanitize_text_field( (string) $raw ) ) : '';

		if ( 'select' === $field['type'] && '' !== $value && ! isset( $field['choices'][ $value ] ) ) {
			return '';
		}

		if ( 'number' === $field['type'] && '' !== $value ) {
			if ( ! is_numeric( $value ) ) {
				return '';
			}
			return (string) ( 0 + $value );
		}

		return $value;
	}
}
