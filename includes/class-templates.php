<?php
/**
 * Substituição de variáveis nos modelos de título (%%title%%, %%sep%%…).
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade;

defined( 'ABSPATH' ) || exit;

class Templates {

	/**
	 * @param string $template Modelo com variáveis.
	 * @param array  $context  Valores conhecidos (title, term, excerpt...).
	 */
	public static function render( $template, array $context = array() ) {
		$paged = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$vars  = array_merge(
			array(
				'sitename'    => get_bloginfo( 'name' ),
				'sitedesc'    => get_bloginfo( 'description' ),
				'sep'         => Settings::get( 'separator' ),
				/* translators: %d: número da página. */
				'page'        => $paged > 1 ? sprintf( __( 'Página %d', 'seo-legibilidade' ), $paged ) : '',
				'currentyear' => wp_date( 'Y' ),
				'title'       => '',
				'term'        => '',
				'excerpt'     => '',
				'category'    => '',
			),
			$context
		);
		$out = preg_replace_callback(
			'/%%([a-z_]+)%%/',
			static function ( $m ) use ( $vars ) {
				return isset( $vars[ $m[1] ] ) ? (string) $vars[ $m[1] ] : '';
			},
			(string) $template
		);
		// Limpa separadores órfãos quando alguma variável ficou vazia.
		$sep = preg_quote( (string) $vars['sep'], '/' );
		if ( '' !== $sep ) {
			$out = preg_replace( '/(\s*' . $sep . '\s*){2,}/u', ' ' . $vars['sep'] . ' ', $out );
			$out = preg_replace( '/^\s*' . $sep . '\s*|\s*' . $sep . '\s*$/u', '', $out );
		}
		return trim( preg_replace( '/\s+/u', ' ', $out ) );
	}

	/**
	 * Contexto de variáveis para um post.
	 */
	public static function post_context( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return array();
		}
		$cats = get_the_category( $post->ID );
		return array(
			'title'    => get_the_title( $post ),
			'excerpt'  => has_excerpt( $post ) ? wp_strip_all_tags( get_the_excerpt( $post ) ) : '',
			'category' => $cats ? $cats[0]->name : '',
		);
	}

	/**
	 * Título SEO final de um post (personalizado ou pelo modelo padrão).
	 */
	public static function post_title( $post, $custom = null ) {
		$post   = get_post( $post );
		$custom = null === $custom ? (string) Meta::post( $post->ID, 'title' ) : (string) $custom;
		$tpl    = '' !== trim( $custom ) ? $custom : Settings::get( 'title_template_singular' );
		return self::render( $tpl, self::post_context( $post ) );
	}
}
