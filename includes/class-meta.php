<?php
/**
 * Metadados de posts e termos.
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade;

defined( 'ABSPATH' ) || exit;

class Meta {

	const POST_FIELDS = array(
		'keyphrase'      => 'string',
		'title'          => 'string',
		'description'    => 'string',
		'canonical'      => 'url',
		'noindex'        => 'bool',
		'nofollow'       => 'bool',
		'og_title'       => 'string',
		'og_description' => 'string',
		'og_image'       => 'url',
		'schema_type'    => 'string',
	);

	const TERM_FIELDS = array(
		'title'       => 'string',
		'description' => 'string',
		'noindex'     => 'bool',
	);

	public static function key( $field ) {
		return '_seol_' . $field;
	}

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	public static function register_meta() {
		$auth = static function ( $allowed, $meta_key, $object_id ) {
			return current_user_can( 'edit_post', $object_id );
		};
		foreach ( self::POST_FIELDS as $field => $type ) {
			register_post_meta(
				'',
				self::key( $field ),
				array(
					'type'              => 'bool' === $type ? 'boolean' : 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_' . $type ),
					'auth_callback'     => $auth,
				)
			);
		}
		foreach ( array( 'seo_score', 'readability_score', 'quality_score' ) as $field ) {
			register_post_meta(
				'',
				self::key( $field ),
				array(
					'type'          => 'integer',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);
		}
	}

	public static function sanitize_string( $value ) {
		return sanitize_text_field( (string) $value );
	}

	public static function sanitize_url( $value ) {
		return esc_url_raw( trim( (string) $value ) );
	}

	public static function sanitize_bool( $value ) {
		return ! empty( $value ) && 'false' !== $value;
	}

	public static function sanitize( $field, $value, $fields = self::POST_FIELDS ) {
		$type = isset( $fields[ $field ] ) ? $fields[ $field ] : 'string';
		return call_user_func( array( __CLASS__, 'sanitize_' . $type ), $value );
	}

	public static function post( $post_id, $field ) {
		return get_post_meta( $post_id, self::key( $field ), true );
	}

	public static function term( $term_id, $field ) {
		return get_term_meta( $term_id, self::key( $field ), true );
	}
}
