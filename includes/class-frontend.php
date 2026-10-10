<?php
/**
 * Saída no <head>: título, meta descrição, robots, canonical, Open Graph,
 * Twitter Cards e verificação de webmasters.
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade;

defined( 'ABSPATH' ) || exit;

class Frontend {

	public static function register() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_attachment' ) );
		remove_action( 'wp_head', 'rel_canonical' );
		// Evita shortlink duplicado (?p=123), que não é indexável.
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
	}

	/* ------------------------------------------------------------------ */
	/* Título                                                              */
	/* ------------------------------------------------------------------ */

	public static function document_title( $title ) {
		$new = self::title();
		return '' !== $new ? $new : $title;
	}

	public static function title() {
		if ( is_front_page() && is_home() ) {
			return Templates::render( Settings::get( 'title_template_home' ) );
		}
		if ( is_singular() || ( is_home() && get_option( 'page_for_posts' ) ) ) {
			$post = is_home() ? get_post( get_option( 'page_for_posts' ) ) : get_queried_object();
			if ( is_front_page() && '' === trim( (string) Meta::post( $post->ID, 'title' ) ) ) {
				return Templates::render( Settings::get( 'title_template_home' ) );
			}
			return Templates::post_title( $post );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term   = get_queried_object();
			$custom = (string) Meta::term( $term->term_id, 'title' );
			$tpl    = '' !== trim( $custom ) ? $custom : Settings::get( 'title_template_archive' );
			return Templates::render( $tpl, array( 'term' => $term->name, 'title' => $term->name ) );
		}
		if ( is_author() ) {
			$author = get_queried_object();
			return Templates::render( Settings::get( 'title_template_archive' ), array( 'term' => $author ? $author->display_name : '' ) );
		}
		if ( is_post_type_archive() ) {
			return Templates::render( Settings::get( 'title_template_archive' ), array( 'term' => post_type_archive_title( '', false ) ) );
		}
		if ( is_search() ) {
			/* translators: %s: termo buscado. */
			return Templates::render( '%%term%% %%sep%% %%sitename%%', array( 'term' => sprintf( __( 'Resultados da busca por “%s”', 'seo-legibilidade' ), get_search_query() ) ) );
		}
		if ( is_404() ) {
			return Templates::render( '%%term%% %%sep%% %%sitename%%', array( 'term' => __( 'Página não encontrada', 'seo-legibilidade' ) ) );
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Descrição                                                           */
	/* ------------------------------------------------------------------ */

	public static function description() {
		$desc = '';
		if ( is_front_page() && is_home() ) {
			$desc = Settings::get( 'home_description' );
			if ( '' === $desc ) {
				$desc = get_bloginfo( 'description' );
			}
		} elseif ( is_singular() || ( is_home() && get_option( 'page_for_posts' ) ) ) {
			$post = is_home() ? get_post( get_option( 'page_for_posts' ) ) : get_queried_object();
			$desc = self::post_description( $post );
			if ( '' === $desc && is_front_page() ) {
				$desc = Settings::get( 'home_description' );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$desc = (string) Meta::term( $term->term_id, 'description' );
			if ( '' === $desc ) {
				$desc = wp_strip_all_tags( term_description( $term ) );
			}
		} elseif ( is_author() ) {
			$desc = get_the_author_meta( 'description', get_queried_object_id() );
		}
		return \SeoLegibilidade\Analysis\Text::truncate( wp_strip_all_tags( (string) $desc ), 160, '…' );
	}

	public static function post_description( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return '';
		}
		$desc = trim( (string) Meta::post( $post->ID, 'description' ) );
		if ( '' !== $desc ) {
			return $desc;
		}
		if ( has_excerpt( $post ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}
		if ( Settings::get( 'auto_description' ) && ! post_password_required( $post ) ) {
			return Autofix::description( do_blocks( $post->post_content ), (string) Meta::post( $post->ID, 'keyphrase' ), Settings::language() );
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Robots                                                              */
	/* ------------------------------------------------------------------ */

	public static function is_noindex() {
		if ( is_singular() ) {
			return (bool) Meta::post( get_queried_object_id(), 'noindex' ) || post_password_required();
		}
		if ( is_category() || is_tag() || is_tax() ) {
			return (bool) Meta::term( get_queried_object_id(), 'noindex' );
		}
		if ( is_date() ) {
			return (bool) Settings::get( 'noindex_date_archives' );
		}
		if ( is_author() ) {
			return (bool) Settings::get( 'noindex_author_archives' );
		}
		return is_search() || is_404();
	}

	public static function robots( array $robots ) {
		if ( ! get_option( 'blog_public' ) ) {
			return $robots; // O WordPress já trata sites ocultos.
		}
		if ( self::is_noindex() ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
		} else {
			// Permite rich snippets completos (recomendação do Google para Discover).
			$robots['max-image-preview'] = 'large';
			$robots['max-snippet']       = '-1';
			$robots['max-video-preview'] = '-1';
		}
		if ( is_singular() && Meta::post( get_queried_object_id(), 'nofollow' ) ) {
			$robots['nofollow'] = true;
		} elseif ( self::is_noindex() ) {
			// Páginas fora do índice continuam distribuindo autoridade pelos links.
			$robots['follow'] = true;
		}
		return $robots;
	}

	/* ------------------------------------------------------------------ */
	/* Canonical                                                           */
	/* ------------------------------------------------------------------ */

	public static function canonical() {
		if ( self::is_noindex() ) {
			return '';
		}
		if ( is_singular() ) {
			$id     = get_queried_object_id();
			$custom = (string) Meta::post( $id, 'canonical' );
			if ( '' !== $custom ) {
				return $custom;
			}
			return (string) wp_get_canonical_url( $id );
		}
		$paged = max( 1, (int) get_query_var( 'paged' ) );
		if ( is_front_page() ) {
			$url = home_url( '/' );
		} elseif ( is_home() ) {
			$url = get_permalink( get_option( 'page_for_posts' ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$url = get_term_link( get_queried_object() );
		} elseif ( is_author() ) {
			$url = get_author_posts_url( get_queried_object_id() );
		} elseif ( is_post_type_archive() ) {
			$url = get_post_type_archive_link( get_query_var( 'post_type' ) );
		} else {
			return '';
		}
		if ( is_wp_error( $url ) || ! $url ) {
			return '';
		}
		// Páginas paginadas apontam para si mesmas (recomendação do Google).
		if ( $paged > 1 ) {
			global $wp_rewrite;
			$url = $wp_rewrite->using_permalinks()
				? user_trailingslashit( trailingslashit( $url ) . $wp_rewrite->pagination_base . '/' . $paged, 'paged' )
				: add_query_arg( 'paged', $paged, $url );
		}
		return $url;
	}

	/* ------------------------------------------------------------------ */
	/* Imagem para redes sociais                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array{url:string,width:int,height:int,alt:string}|null
	 */
	public static function image() {
		if ( is_singular() ) {
			$id     = get_queried_object_id();
			$custom = (string) Meta::post( $id, 'og_image' );
			if ( '' !== $custom ) {
				$att = Autofix::attachment_id_from_url( $custom );
				return $att ? self::attachment_image( $att ) : array( 'url' => $custom, 'width' => 0, 'height' => 0, 'alt' => '' );
			}
			if ( has_post_thumbnail( $id ) ) {
				return self::attachment_image( get_post_thumbnail_id( $id ) );
			}
			$post = get_post( $id );
			if ( $post && preg_match( '/<img[^>]+src=["\']([^"\']+)/i', $post->post_content, $m ) ) {
				$att = Autofix::attachment_id_from_url( $m[1] );
				return $att ? self::attachment_image( $att ) : array( 'url' => $m[1], 'width' => 0, 'height' => 0, 'alt' => '' );
			}
		}
		$default = (string) Settings::get( 'default_og_image' );
		if ( '' !== $default ) {
			$att = Autofix::attachment_id_from_url( $default );
			return $att ? self::attachment_image( $att ) : array( 'url' => $default, 'width' => 0, 'height' => 0, 'alt' => '' );
		}
		return null;
	}

	private static function attachment_image( $id ) {
		$src = wp_get_attachment_image_src( $id, 'full' );
		if ( ! $src ) {
			return null;
		}
		return array(
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
			'alt'    => Autofix::alt_for_attachment( $id, $src[0] ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Saída                                                               */
	/* ------------------------------------------------------------------ */

	public static function head() {
		echo "\n<!-- SEO & Legibilidade -->\n";
		$desc = self::description();
		if ( '' !== $desc ) {
			printf( "<meta name=\"description\" content=\"%s\" />\n", esc_attr( $desc ) );
		}
		$canonical = self::canonical();
		if ( '' !== $canonical ) {
			printf( "<link rel=\"canonical\" href=\"%s\" />\n", esc_url( $canonical ) );
		}
		self::social( $desc, $canonical );
		foreach ( array( 'google_verification' => 'google-site-verification', 'bing_verification' => 'msvalidate.01' ) as $key => $name ) {
			$code = (string) Settings::get( $key );
			if ( '' !== $code && is_front_page() ) {
				printf( "<meta name=\"%s\" content=\"%s\" />\n", esc_attr( $name ), esc_attr( $code ) );
			}
		}
		echo "<!-- /SEO & Legibilidade -->\n\n";
	}

	private static function social( $desc, $canonical ) {
		$title = self::title();
		if ( '' === $title ) {
			$title = wp_get_document_title();
		}
		$og = array(
			'og:locale'    => get_locale(),
			'og:type'      => is_singular( 'post' ) ? 'article' : 'website',
			'og:title'     => $title,
			'og:description' => $desc,
			'og:url'       => '' !== $canonical ? $canonical : home_url( add_query_arg( array() ) ),
			'og:site_name' => get_bloginfo( 'name' ),
		);
		if ( is_singular() ) {
			$id = get_queried_object_id();
			$t  = (string) Meta::post( $id, 'og_title' );
			$d  = (string) Meta::post( $id, 'og_description' );
			if ( '' !== $t ) {
				$og['og:title'] = $t;
			}
			if ( '' !== $d ) {
				$og['og:description'] = $d;
			}
			if ( is_singular( 'post' ) ) {
				$og['article:published_time'] = get_post_time( 'c', true, $id );
				$og['article:modified_time']  = get_post_modified_time( 'c', true, $id );
				$cats                         = get_the_category( $id );
				if ( $cats ) {
					$og['article:section'] = $cats[0]->name;
				}
			}
		}
		$image = self::image();
		if ( $image ) {
			$og['og:image'] = $image['url'];
			if ( $image['width'] ) {
				$og['og:image:width']  = $image['width'];
				$og['og:image:height'] = $image['height'];
			}
			if ( '' !== $image['alt'] ) {
				$og['og:image:alt'] = $image['alt'];
			}
		}
		foreach ( $og as $property => $content ) {
			if ( '' !== (string) $content ) {
				$is_url = in_array( $property, array( 'og:url', 'og:image' ), true );
				printf( "<meta property=\"%s\" content=\"%s\" />\n", esc_attr( $property ), $is_url ? esc_url( $content ) : esc_attr( $content ) );
			}
		}
		$tw = array(
			'twitter:card'        => $image ? 'summary_large_image' : 'summary',
			'twitter:title'       => $og['og:title'],
			'twitter:description' => $og['og:description'],
			'twitter:image'       => $image ? $image['url'] : '',
			'twitter:site'        => Settings::get( 'twitter_site' ) ? '@' . Settings::get( 'twitter_site' ) : '',
		);
		foreach ( $tw as $name => $content ) {
			if ( '' !== (string) $content ) {
				printf( "<meta name=\"%s\" content=\"%s\" />\n", esc_attr( $name ), 'twitter:image' === $name ? esc_url( $content ) : esc_attr( $content ) );
			}
		}
	}

	/**
	 * Páginas de anexo são conteúdo raso: redireciona (301) para o post ou o arquivo.
	 */
	public static function redirect_attachment() {
		if ( ! is_attachment() || ! Settings::get( 'redirect_attachments' ) ) {
			return;
		}
		$post   = get_queried_object();
		$target = $post->post_parent ? get_permalink( $post->post_parent ) : wp_get_attachment_url( $post->ID );
		if ( $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	}
}
