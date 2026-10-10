<?php
/**
 * Ajustes no sitemap XML nativo do WordPress (/wp-sitemap.xml):
 * remove conteúdo noindex, adiciona <lastmod> e tira arquivos irrelevantes.
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade;

defined( 'ABSPATH' ) || exit;

class Sitemap {

	public static function register() {
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'exclude_noindex_posts' ) );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( __CLASS__, 'exclude_noindex_terms' ) );
		add_filter( 'wp_sitemaps_posts_entry', array( __CLASS__, 'post_lastmod' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', array( __CLASS__, 'taxonomies' ) );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'providers' ), 10, 2 );
	}

	public static function exclude_noindex_posts( $args ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'OR',
			array(
				'key'     => Meta::key( 'noindex' ),
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => Meta::key( 'noindex' ),
				'value'   => '1',
				'compare' => '!=',
			),
		);
		// Posts com canonical apontando para outra URL não devem estar no sitemap.
		add_filter( 'the_posts', array( __CLASS__, 'drop_canonicalized' ) );
		return $args;
	}

	public static function drop_canonicalized( $posts ) {
		remove_filter( 'the_posts', array( __CLASS__, 'drop_canonicalized' ) );
		return array_values(
			array_filter(
				$posts,
				static function ( $p ) {
					$c = (string) Meta::post( $p->ID, 'canonical' );
					return '' === $c || untrailingslashit( $c ) === untrailingslashit( get_permalink( $p ) );
				}
			)
		);
	}

	public static function exclude_noindex_terms( $args ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'OR',
			array(
				'key'     => Meta::key( 'noindex' ),
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => Meta::key( 'noindex' ),
				'value'   => '1',
				'compare' => '!=',
			),
		);
		return $args;
	}

	public static function post_lastmod( $entry, $post ) {
		$entry['lastmod'] = get_post_modified_time( 'c', true, $post );
		return $entry;
	}

	public static function taxonomies( $taxonomies ) {
		unset( $taxonomies['post_format'] );
		return $taxonomies;
	}

	public static function providers( $provider, $name ) {
		if ( 'users' === $name && Settings::get( 'noindex_author_archives' ) ) {
			return false;
		}
		return $provider;
	}
}
