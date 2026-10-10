<?php
/**
 * Correções automáticas: gera título, meta descrição, slug e textos alternativos
 * dentro dos padrões recomendados.
 *
 * Os métodos de geração são puros (testáveis sem WordPress); register() liga
 * os filtros de saída no site.
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade;

use SeoLegibilidade\Analysis\Language;
use SeoLegibilidade\Analysis\Seo_Analyzer;
use SeoLegibilidade\Analysis\Text;

defined( 'ABSPATH' ) || exit;

class Autofix {

	const SLUG_MAX_WORDS = 6;

	public static function register() {
		if ( Settings::get( 'auto_alt' ) ) {
			add_filter( 'wp_content_img_tag', array( __CLASS__, 'filter_content_img' ), 10, 3 );
			add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'filter_attachment_attrs' ), 10, 2 );
		}
	}

	/**
	 * Slug curto, sem acentos nem palavras vazias, com a frase-chave no início.
	 */
	public static function slug( $title, $keyphrase = '', $lang = 'pt' ) {
		$stop  = array_map( array( Text::class, 'normalize' ), Language::stopwords( $lang ) );
		$clean = static function ( $text ) use ( $stop ) {
			$words = Text::words( Text::normalize( str_replace( array( "'", '’' ), ' ', $text ) ) );
			$words = array_map(
				static function ( $w ) {
					return trim( preg_replace( '/[^a-z0-9]+/', '-', $w ), '-' );
				},
				$words
			);
			return array_values(
				array_filter(
					$words,
					static function ( $w ) use ( $stop ) {
						return '' !== $w && ! in_array( $w, $stop, true );
					}
				)
			);
		};
		$words = array_values( array_unique( array_merge( $clean( $keyphrase ), $clean( $title ) ) ) );
		$words = array_slice( $words, 0, max( self::SLUG_MAX_WORDS, count( $clean( $keyphrase ) ) ) );
		$slug  = implode( '-', $words );
		while ( strlen( $slug ) > Seo_Analyzer::SLUG_MAX_CHARS && count( $words ) > 1 ) {
			array_pop( $words );
			$slug = implode( '-', $words );
		}
		return $slug;
	}

	/**
	 * Meta descrição de até 156 caracteres a partir do conteúdo, priorizando
	 * o trecho que contém a frase-chave.
	 */
	public static function description( $html, $keyphrase = '', $lang = 'pt' ) {
		$sentences = array();
		foreach ( Text::paragraphs( $html ) as $p ) {
			foreach ( Text::sentences( $p ) as $s ) {
				$sentences[] = $s;
			}
		}
		if ( ! $sentences ) {
			return '';
		}
		$start = 0;
		if ( '' !== trim( (string) $keyphrase ) ) {
			$stop = Language::stopwords( $lang );
			foreach ( $sentences as $i => $s ) {
				if ( Text::contains_keyphrase( $s, $keyphrase, $stop ) ) {
					$start = $i;
					break;
				}
			}
		}
		$desc = '';
		for ( $i = $start, $n = count( $sentences ); $i < $n; $i++ ) {
			$desc = trim( $desc . ' ' . $sentences[ $i ] );
			if ( mb_strlen( $desc ) >= Seo_Analyzer::DESC_MIN_CHARS ) {
				break;
			}
		}
		if ( mb_strlen( $desc ) > Seo_Analyzer::DESC_MAX_CHARS ) {
			$desc = Text::truncate( $desc, Seo_Analyzer::DESC_MAX_CHARS, '…' );
		}
		return $desc;
	}

	/**
	 * Título SEO que cabe em ~580px e começa pela frase-chave.
	 */
	public static function title( $post_title, $site_name = '', $sep = '-', $keyphrase = '' ) {
		$post_title = trim( preg_replace( '/\s+/u', ' ', (string) $post_title ) );
		$keyphrase  = trim( (string) $keyphrase );
		$max        = Seo_Analyzer::TITLE_MAX_PX;

		if ( '' !== $keyphrase && ! self::starts_with_keyphrase( $post_title, $keyphrase ) ) {
			$post_title = self::lead_with_keyphrase( $post_title, $keyphrase );
		}
		$with_site = '' !== $site_name ? $post_title . ' ' . $sep . ' ' . $site_name : $post_title;
		return Text::pixel_width( $with_site ) <= $max ? $with_site : self::fit( $post_title, $max );
	}

	private static function starts_with_keyphrase( $text, $keyphrase ) {
		$pattern = Text::keyphrase_pattern( $keyphrase );
		return $pattern && preg_match( $pattern, Text::normalize( $text ), $m, PREG_OFFSET_CAPTURE ) && $m[0][1] <= 10;
	}

	/**
	 * "Como escolher café especial em casa" → "Café especial: como escolher em casa".
	 */
	private static function lead_with_keyphrase( $title, $keyphrase ) {
		$rest    = $title;
		$pattern = Text::keyphrase_pattern( $keyphrase );
		$norm    = Text::normalize( $title );
		if ( $pattern && preg_match( $pattern, $norm, $m, PREG_OFFSET_CAPTURE ) ) {
			// normalize() troca caractere por caractere, então as posições em caracteres coincidem.
			$start = mb_strlen( substr( $norm, 0, $m[0][1] ) );
			$len   = mb_strlen( $m[0][0] );
			$rest  = mb_substr( $title, 0, $start ) . mb_substr( $title, $start + $len );
			$rest  = Text::trim_punctuation( preg_replace( '/\s+([,.;:!?])/u', '$1', preg_replace( '/\s+/u', ' ', $rest ) ) );
			$rest  = preg_replace( '/\s+(de|do|da|para|em|com|o|a|e)$/u', '', $rest );
		}
		$lead = mb_strtoupper( mb_substr( $keyphrase, 0, 1 ) ) . mb_substr( $keyphrase, 1 );
		if ( '' === $rest ) {
			return $lead;
		}
		return $lead . ': ' . mb_strtolower( mb_substr( $rest, 0, 1 ) ) . mb_substr( $rest, 1 );
	}

	private static function fit( $text, $max_px ) {
		$words = explode( ' ', $text );
		while ( count( $words ) > 1 && Text::pixel_width( implode( ' ', $words ) ) > $max_px ) {
			array_pop( $words );
		}
		return Text::trim_punctuation( implode( ' ', $words ), false );
	}

	/**
	 * Troca o ano mais recente, se já passou, pelo ano atual
	 * ("Melhores de 2024 (desde 2019)" → "Melhores de 2026 (desde 2019)").
	 * Anos mais antigos costumam ser referências históricas e ficam como estão.
	 */
	public static function update_years( $text, $current_year ) {
		$years = Analysis\Quality_Analyzer::years( $text );
		if ( ! $years || max( $years ) >= $current_year ) {
			return (string) $text;
		}
		$latest = max( $years );
		return preg_replace_callback(
			Analysis\Quality_Analyzer::YEAR_PATTERN,
			static function ( $m ) use ( $latest, $current_year ) {
				return (int) $m[1] === $latest ? (string) $current_year : $m[1];
			},
			(string) $text
		);
	}

	/**
	 * Texto alternativo legível a partir do nome do arquivo.
	 */
	public static function alt_from_filename( $url ) {
		$name = (string) parse_url( (string) $url, PHP_URL_PATH );
		$name = pathinfo( $name, PATHINFO_FILENAME );
		$name = preg_replace( '/-(\d+x\d+|scaled|rotated|e\d{10,})$/', '', $name );
		$name = preg_replace( '/-(\d+x\d+|scaled)$/', '', $name );
		$name = trim( preg_replace( '/[\s_\-]+/', ' ', urldecode( $name ) ) );
		$name = preg_replace( '/\s\d+$/', '', $name );
		if ( '' === $name || preg_match( '/^(img|dsc|image|imagem|photo|foto|screenshot|captura)?\s?\d*$/i', $name ) ) {
			return '';
		}
		return mb_strtoupper( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 );
	}

	/**
	 * ID do anexo a partir de uma URL, inclusive de tamanhos intermediários (-1024x768).
	 */
	public static function attachment_id_from_url( $url ) {
		$id = attachment_url_to_postid( $url );
		if ( ! $id ) {
			$id = attachment_url_to_postid( preg_replace( '/-(\d+x\d+|scaled)(?=\.\w+$)/', '', (string) $url ) );
		}
		return (int) $id;
	}

	/**
	 * Melhor alt disponível para um anexo.
	 */
	public static function alt_for_attachment( $attachment_id, $src = '' ) {
		if ( $attachment_id ) {
			$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
			if ( '' !== $alt ) {
				return $alt;
			}
			$caption = trim( wp_strip_all_tags( (string) wp_get_attachment_caption( $attachment_id ) ) );
			if ( '' !== $caption ) {
				return $caption;
			}
			$title = trim( (string) get_the_title( $attachment_id ) );
			if ( '' !== $title && ! preg_match( '/^(img|dsc|image|imagem|photo|foto)[\s_\-]?\d+$/i', $title ) ) {
				return $title;
			}
		}
		return self::alt_from_filename( $src );
	}

	/**
	 * Preenche alt ausente ou vazio nas imagens do conteúdo.
	 */
	public static function filter_content_img( $tag, $context, $attachment_id ) {
		$alt = Text::attr( $tag, 'alt' );
		if ( null !== $alt && '' !== trim( $alt ) ) {
			return $tag;
		}
		$new = self::alt_for_attachment( $attachment_id, (string) Text::attr( $tag, 'src' ) );
		if ( '' === $new ) {
			return $tag;
		}
		$attr = 'alt="' . esc_attr( $new ) . '"';
		if ( null === $alt ) {
			return preg_replace( '/^<img\b/i', '<img ' . $attr, $tag );
		}
		return preg_replace( '/\salt\s*=\s*("\s*"|\'\s*\')/i', ' ' . $attr, $tag, 1 );
	}

	public static function filter_attachment_attrs( $attr, $attachment ) {
		if ( empty( $attr['alt'] ) && $attachment instanceof \WP_Post ) {
			$alt = self::alt_for_attachment( $attachment->ID, isset( $attr['src'] ) ? $attr['src'] : '' );
			if ( '' !== $alt ) {
				$attr['alt'] = $alt;
			}
		}
		return $attr;
	}
}
