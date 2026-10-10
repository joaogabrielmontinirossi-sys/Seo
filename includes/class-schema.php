<?php
/**
 * Dados estruturados (JSON-LD) em um único @graph conectado:
 * WebSite, Organization/Person, WebPage, Article/BlogPosting e BreadcrumbList.
 *
 * Segue a documentação do Google Search Central para Article, Breadcrumb
 * e Logo (a caixa de pesquisa de sitelinks foi descontinuada pelo Google em 2024).
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade;

defined( 'ABSPATH' ) || exit;

class Schema {

	public static function register() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 2 );
	}

	public static function output() {
		if ( is_404() || Frontend::is_noindex() ) {
			return;
		}
		$graph = apply_filters( 'seol_schema_graph', self::graph() );
		if ( ! $graph ) {
			return;
		}
		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array_values( $graph ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		// Impede que "</script>" dentro de um valor encerre a tag.
		$json = str_replace( '</', '<\/', $json );
		echo '<script type="application/ld+json" class="seol-schema">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function id( $fragment, $url = null ) {
		return ( $url ? $url : home_url( '/' ) ) . '#' . $fragment;
	}

	public static function graph() {
		$home  = home_url( '/' );
		$graph = array();

		$entity_type = Settings::get( 'entity_type' );
		$entity_name = Settings::get( 'entity_name' ) ? Settings::get( 'entity_name' ) : get_bloginfo( 'name' );
		$entity      = array(
			'@type' => $entity_type,
			'@id'   => self::id( 'Person' === $entity_type ? 'person' : 'organization' ),
			'name'  => $entity_name,
			'url'   => $home,
		);
		$logo = Settings::get( 'entity_logo' );
		if ( ! $logo && has_custom_logo() ) {
			$logo = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
		}
		if ( $logo ) {
			$entity['Person' === $entity_type ? 'image' : 'logo'] = array(
				'@type' => 'ImageObject',
				'@id'   => self::id( 'logo' ),
				'url'   => $logo,
			);
		}
		$same_as = array_filter( array_map( 'trim', explode( "\n", (string) Settings::get( 'same_as' ) ) ) );
		if ( Settings::get( 'twitter_site' ) ) {
			$same_as[] = 'https://x.com/' . Settings::get( 'twitter_site' );
		}
		if ( $same_as ) {
			$entity['sameAs'] = array_values( array_unique( $same_as ) );
		}
		$graph[] = $entity;

		$graph[] = array_filter( array(
			'@type'           => 'WebSite',
			'@id'             => self::id( 'website' ),
			'url'             => $home,
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'inLanguage'      => get_bloginfo( 'language' ),
			'publisher'       => array( '@id' => $entity['@id'] ),
		) );

		$url = Frontend::canonical();
		if ( '' === $url ) {
			return $graph;
		}
		$page_type = 'WebPage';
		if ( is_search() ) {
			$page_type = 'SearchResultsPage';
		} elseif ( is_archive() || is_home() ) {
			$page_type = 'CollectionPage';
		}
		$page = array(
			'@type'      => $page_type,
			'@id'        => $url,
			'url'        => $url,
			'name'       => wp_get_document_title(),
			'isPartOf'   => array( '@id' => self::id( 'website' ) ),
			'inLanguage' => get_bloginfo( 'language' ),
		);
		$desc = Frontend::description();
		if ( '' !== $desc ) {
			$page['description'] = $desc;
		}
		$image = Frontend::image();
		if ( $image ) {
			$page['primaryImageOfPage'] = array( '@id' => $url . '#primaryimage' );
			$img                        = array(
				'@type'      => 'ImageObject',
				'@id'        => $url . '#primaryimage',
				'url'        => $image['url'],
				'contentUrl' => $image['url'],
			);
			if ( $image['width'] ) {
				$img['width']  = $image['width'];
				$img['height'] = $image['height'];
			}
			if ( '' !== $image['alt'] ) {
				$img['caption'] = $image['alt'];
			}
			$graph[] = $img;
		}
		$crumbs = self::breadcrumbs();
		if ( count( $crumbs ) > 1 ) {
			$page['breadcrumb'] = array( '@id' => $url . '#breadcrumb' );
			$items              = array();
			foreach ( $crumbs as $i => $c ) {
				$item = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $c['name'],
				);
				// O último item (página atual) dispensa URL, conforme o Google.
				if ( $i < count( $crumbs ) - 1 ) {
					$item['item'] = $c['url'];
				}
				$items[] = $item;
			}
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $url . '#breadcrumb',
				'itemListElement' => $items,
			);
		}

		if ( is_singular() ) {
			$post                  = get_queried_object();
			$page['datePublished'] = get_post_time( 'c', true, $post );
			$page['dateModified']  = get_post_modified_time( 'c', true, $post );

			$type = (string) Meta::post( $post->ID, 'schema_type' );
			if ( '' === $type && 'post' === $post->post_type ) {
				$type = Settings::get( 'article_type' );
			}
			if ( in_array( $type, array( 'Article', 'BlogPosting', 'NewsArticle' ), true ) ) {
				$graph[] = self::article( $post, $type, $url, $entity['@id'], $image );
			} elseif ( in_array( $type, array( 'AboutPage', 'ContactPage', 'FAQPage', 'ProfilePage' ), true ) ) {
				$page['@type'] = $type;
			}
		}
		$graph[] = $page;
		return $graph;
	}

	private static function article( $post, $type, $url, $publisher_id, $image ) {
		$author  = get_userdata( $post->post_author );
		$text    = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$article = array(
			'@type'            => $type,
			'@id'              => $url . '#article',
			'isPartOf'         => array( '@id' => $url ),
			'mainEntityOfPage' => array( '@id' => $url ),
			// O Google recomenda títulos concisos; textos longos são cortados.
			'headline'         => \SeoLegibilidade\Analysis\Text::truncate( get_the_title( $post ), 110 ),
			'datePublished'    => get_post_time( 'c', true, $post ),
			'dateModified'     => get_post_modified_time( 'c', true, $post ),
			'publisher'        => array( '@id' => $publisher_id ),
			'wordCount'        => \SeoLegibilidade\Analysis\Text::word_count( $text ),
			'inLanguage'       => get_bloginfo( 'language' ),
		);
		if ( $author ) {
			$person = array(
				'@type' => 'Person',
				'name'  => $author->display_name,
				'url'   => get_author_posts_url( $author->ID ),
			);
			if ( $author->user_url ) {
				$person['sameAs'] = array( $author->user_url );
			}
			$article['author'] = $person;
		}
		if ( $image ) {
			$article['image'] = array( '@id' => $url . '#primaryimage' );
		}
		$keyphrase = (string) Meta::post( $post->ID, 'keyphrase' );
		$tags      = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
		$keywords  = array_filter( array_merge( array( $keyphrase ), is_array( $tags ) ? $tags : array() ) );
		if ( $keywords ) {
			$article['keywords'] = array_values( array_unique( $keywords ) );
		}
		$cats = get_the_category( $post->ID );
		if ( $cats ) {
			$article['articleSection'] = wp_list_pluck( $cats, 'name' );
		}
		if ( comments_open( $post ) ) {
			$article['commentCount'] = (int) get_comments_number( $post );
		}
		return $article;
	}

	/**
	 * Trilha de navegação (início → categoria/ancestrais → página atual).
	 *
	 * @return array<int, array{name:string, url:string}>
	 */
	public static function breadcrumbs() {
		$crumbs = array(
			array(
				'name' => __( 'Início', 'seo-legibilidade' ),
				'url'  => home_url( '/' ),
			),
		);
		if ( is_front_page() ) {
			return $crumbs;
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( 'post' === $post->post_type ) {
				$blog = (int) get_option( 'page_for_posts' );
				if ( $blog ) {
					$crumbs[] = array( 'name' => get_the_title( $blog ), 'url' => get_permalink( $blog ) );
				}
				$cats = get_the_category( $post->ID );
				if ( $cats ) {
					$cat = $cats[0];
					foreach ( array_reverse( get_ancestors( $cat->term_id, 'category' ) ) as $anc ) {
						$t        = get_term( $anc, 'category' );
						$crumbs[] = array( 'name' => $t->name, 'url' => get_term_link( $t ) );
					}
					$crumbs[] = array( 'name' => $cat->name, 'url' => get_term_link( $cat ) );
				}
			} elseif ( is_post_type_hierarchical( $post->post_type ) ) {
				foreach ( array_reverse( get_post_ancestors( $post ) ) as $anc ) {
					$crumbs[] = array( 'name' => get_the_title( $anc ), 'url' => get_permalink( $anc ) );
				}
			} else {
				$archive = get_post_type_archive_link( $post->post_type );
				if ( $archive ) {
					$crumbs[] = array( 'name' => get_post_type_object( $post->post_type )->labels->name, 'url' => $archive );
				}
			}
			$crumbs[] = array( 'name' => get_the_title( $post ), 'url' => get_permalink( $post ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy ) ) as $anc ) {
				$t        = get_term( $anc, $term->taxonomy );
				$crumbs[] = array( 'name' => $t->name, 'url' => get_term_link( $t ) );
			}
			$crumbs[] = array( 'name' => $term->name, 'url' => get_term_link( $term ) );
		} elseif ( is_home() ) {
			$crumbs[] = array( 'name' => get_the_title( get_option( 'page_for_posts' ) ), 'url' => get_permalink( get_option( 'page_for_posts' ) ) );
		} elseif ( is_author() ) {
			$crumbs[] = array( 'name' => get_the_author_meta( 'display_name', get_queried_object_id() ), 'url' => get_author_posts_url( get_queried_object_id() ) );
		}
		return array_values(
			array_filter(
				$crumbs,
				static function ( $c ) {
					return ! is_wp_error( $c['url'] );
				}
			)
		);
	}
}
