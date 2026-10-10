<?php
/**
 * Endpoints REST usados pelo painel do editor.
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade;

defined( 'ABSPATH' ) || exit;

class Rest {

	const NS = 'seo-legibilidade/v1';

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		$args = array(
			'post_id'     => array(
				'type'     => 'integer',
				'required' => true,
			),
			'post_title'  => array( 'type' => 'string' ),
			'content'     => array( 'type' => 'string' ),
			'slug'        => array( 'type' => 'string' ),
			'keyphrase'   => array( 'type' => 'string' ),
			'title'       => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
			'images'      => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
		);
		$permission = static function ( \WP_REST_Request $request ) {
			return current_user_can( 'edit_post', (int) $request['post_id'] );
		};
		register_rest_route(
			self::NS,
			'/analyze',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'analyze' ),
				'permission_callback' => $permission,
				'args'                => $args,
			)
		);
		register_rest_route(
			self::NS,
			'/fix',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'fix' ),
				'permission_callback' => $permission,
				'args'                => $args,
			)
		);
	}

	private static function data( \WP_REST_Request $request ) {
		$data = array( 'post_id' => (int) $request['post_id'] );
		foreach ( array( 'post_title', 'content', 'slug', 'keyphrase', 'title', 'description' ) as $key ) {
			if ( null !== $request[ $key ] ) {
				$data[ $key ] = 'content' === $key ? (string) $request[ $key ] : sanitize_text_field( (string) $request[ $key ] );
			}
		}
		return $data;
	}

	public static function analyze( \WP_REST_Request $request ) {
		return rest_ensure_response( Analysis_Service::run( self::data( $request ) ) );
	}

	/**
	 * Sugestões de correção automática dentro dos padrões.
	 */
	public static function fix( \WP_REST_Request $request ) {
		$data      = self::data( $request );
		$lang      = Settings::language();
		$post      = get_post( $data['post_id'] );
		$kp        = isset( $data['keyphrase'] ) ? $data['keyphrase'] : '';
		// Valores vazios do editor caem para o que está salvo.
		$title     = ! empty( $data['post_title'] ) ? $data['post_title'] : ( $post ? $post->post_title : '' );
		$content   = ! empty( $data['content'] ) ? $data['content'] : ( $post ? $post->post_content : '' );
		$rendered  = do_shortcode( do_blocks( $content ) );

		$alts = array();
		foreach ( (array) $request['images'] as $src ) {
			// Mesma ordem das imagens enviadas.
			$src    = esc_url_raw( (string) $src );
			$alts[] = Autofix::alt_for_attachment( Autofix::attachment_id_from_url( $src ), $src );
		}

		return rest_ensure_response(
			array(
				'title'       => Autofix::title( $title, get_bloginfo( 'name' ), Settings::get( 'separator' ), $kp ),
				'description' => Autofix::description( $rendered, $kp, $lang ),
				'slug'        => Autofix::slug( $title, $kp, $lang ),
				'alts'        => $alts,
				// Ano vencido trocado pelo atual no título do post e no título SEO.
				'year_title'     => Autofix::update_years( $title, (int) wp_date( 'Y' ) ),
				'title_and_year' => Autofix::title( Autofix::update_years( $title, (int) wp_date( 'Y' ) ), get_bloginfo( 'name' ), Settings::get( 'separator' ), $kp ),
				'year_seo_title' => Autofix::update_years( isset( $data['title'] ) ? $data['title'] : '', (int) wp_date( 'Y' ) ),
			)
		);
	}
}
