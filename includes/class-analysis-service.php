<?php
/**
 * Liga os analisadores aos dados do WordPress.
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade;

use SeoLegibilidade\Analysis\Readability_Analyzer;
use SeoLegibilidade\Analysis\Seo_Analyzer;
use SeoLegibilidade\Analysis\Text;

defined( 'ABSPATH' ) || exit;

class Analysis_Service {

	/**
	 * @param array $data post_id, post_title, content, slug, keyphrase, title (modelo), description.
	 */
	public static function run( array $data ) {
		$post_id = isset( $data['post_id'] ) ? (int) $data['post_id'] : 0;
		$lang    = Settings::language();
		$content = isset( $data['content'] ) ? (string) $data['content'] : '';
		// Renderiza blocos dinâmicos e shortcodes para analisar o que o visitante vê.
		$rendered = function_exists( 'do_blocks' ) ? do_blocks( $content ) : $content;
		$rendered = do_shortcode( $rendered );

		$context = $post_id ? Templates::post_context( $post_id ) : array();
		if ( isset( $data['post_title'] ) ) {
			$context['title'] = (string) $data['post_title'];
		}
		$custom_title = isset( $data['title'] ) ? trim( (string) $data['title'] ) : '';
		$seo_title    = Templates::render( '' !== $custom_title ? $custom_title : Settings::get( 'title_template_singular' ), $context );
		$keyphrase    = isset( $data['keyphrase'] ) ? trim( (string) $data['keyphrase'] ) : '';
		$description  = isset( $data['description'] ) ? trim( (string) $data['description'] ) : '';
		$slug         = isset( $data['slug'] ) ? (string) $data['slug'] : '';

		$auto_desc = '' === $description && Settings::get( 'auto_description' ) ? Autofix::description( $rendered, $keyphrase, $lang ) : '';

		$seo = ( new Seo_Analyzer( $lang ) )->analyze(
			array(
				'keyphrase'      => $keyphrase,
				'title'          => $seo_title,
				'description'    => '' !== $auto_desc ? $auto_desc : $description,
				'description_is_auto' => '' !== $auto_desc,
				'slug'           => $slug,
				'content'        => $rendered,
				'site_url'       => home_url( '/' ),
				'keyphrase_uses' => self::keyphrase_uses( $keyphrase, $post_id ),
				'min_words'      => (int) Settings::get( 'min_words' ),
			)
		);
		$readability = ( new Readability_Analyzer( $lang ) )->analyze( $rendered );

		$preview_desc = '' !== $description ? $description : $auto_desc;

		return array(
			'seo'         => $seo,
			'readability' => $readability,
			'preview'     => array(
				'title'             => $seo_title,
				'title_px'          => Text::pixel_width( $seo_title ),
				'description'       => $preview_desc,
				'description_auto'  => '' === $description && '' !== $preview_desc,
				'url'               => $post_id ? self::preview_url( $post_id, $slug ) : home_url( '/' . $slug ),
				'site_name'         => get_bloginfo( 'name' ),
			),
		);
	}

	private static function preview_url( $post_id, $slug ) {
		$link = get_permalink( $post_id );
		if ( $slug && function_exists( 'get_sample_permalink' ) ) {
			list( $tpl ) = get_sample_permalink( $post_id, null, $slug );
			$link        = str_replace( array( '%postname%', '%pagename%' ), $slug, $tpl );
		}
		return $link;
	}

	/**
	 * Quantos outros conteúdos usam a mesma frase-chave.
	 */
	public static function keyphrase_uses( $keyphrase, $exclude_id ) {
		if ( '' === $keyphrase ) {
			return 0;
		}
		$q = new \WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'post__not_in'   => $exclude_id ? array( $exclude_id ) : array(),
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => Meta::key( 'keyphrase' ),
						'value'   => $keyphrase,
						'compare' => '=',
					),
				),
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Calcula e salva as notas de um post (usadas na listagem de posts).
	 */
	public static function store_scores( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$result = self::run(
			array(
				'post_id'     => $post_id,
				'content'     => $post->post_content,
				'slug'        => $post->post_name,
				'keyphrase'   => Meta::post( $post_id, 'keyphrase' ),
				'title'       => Meta::post( $post_id, 'title' ),
				'description' => Meta::post( $post_id, 'description' ),
			)
		);
		update_post_meta( $post_id, Meta::key( 'seo_score' ), $result['seo']['score'] );
		update_post_meta( $post_id, Meta::key( 'readability_score' ), $result['readability']['score'] );
	}
}
