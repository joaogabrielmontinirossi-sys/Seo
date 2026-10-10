<?php
/**
 * Utilitários de processamento de texto usados pelas análises.
 *
 * Não depende do WordPress para que possa ser testado isoladamente.
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade\Analysis;

defined( 'ABSPATH' ) || exit;

class Text {

	/**
	 * Abreviações comuns que não encerram frases.
	 */
	const ABBREVIATIONS = array(
		'sr', 'sra', 'srs', 'sras', 'dr', 'dra', 'drs', 'dras', 'prof', 'profa', 'eng', 'arq', 'adv',
		'av', 'pg', 'pág', 'págs', 'p', 'pp', 'n', 'nº', 'no', 'vol', 'cap', 'ex', 'etc', 'obs',
		'mr', 'mrs', 'ms', 'jr', 'st', 'vs', 'e.g', 'i.e', 'fig', 'inc', 'ltda', 'cia', 'tel', 'aprox',
	);

	/**
	 * Remove marcação, shortcodes e comentários de blocos, deixando somente texto.
	 */
	public static function strip( $html ) {
		$html = (string) $html;
		$html = preg_replace( '#<(script|style|noscript|svg)[^>]*>.*?</\1>#is', ' ', $html );
		$html = preg_replace( '#<!--.*?-->#s', ' ', $html );
		$html = preg_replace( '#\[/?[a-zA-Z_][^\]]*\]#', ' ', $html );
		// Quebras estruturais viram separadores para não grudar palavras de blocos distintos.
		$html = preg_replace( '#</?(p|div|h[1-6]|li|ul|ol|br|tr|td|th|blockquote|figure|figcaption|section|article|pre)\b[^>]*>#i', "\n", $html );
		$text = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\xC2\xA0", ' ', $text );
		$text = preg_replace( "/[ \t]+/u", ' ', $text );
		$text = preg_replace( "/\n\s*\n+/u", "\n\n", $text );
		return trim( $text );
	}

	/**
	 * Divide o conteúdo HTML em parágrafos de texto.
	 *
	 * @return string[]
	 */
	public static function paragraphs( $html ) {
		$html = (string) $html;
		$out  = array();
		if ( preg_match_all( '#<p\b[^>]*>(.*?)</p>#is', $html, $m ) ) {
			foreach ( $m[1] as $p ) {
				$t = trim( self::strip( $p ) );
				if ( '' !== $t ) {
					$out[] = $t;
				}
			}
			return $out;
		}
		// Editor clássico sem <p>: parágrafos separados por linhas em branco.
		$withoutHeadings = preg_replace( '#<h[1-6][^>]*>.*?</h[1-6]>#is', "\n\n", $html );
		foreach ( preg_split( "/\n\s*\n/u", self::strip( $withoutHeadings ) ) as $p ) {
			$t = trim( $p );
			if ( '' !== $t ) {
				$out[] = $t;
			}
		}
		return $out;
	}

	/**
	 * Retorna os títulos (h1–h6) na ordem em que aparecem.
	 *
	 * @return array<int, array{level:int, text:string, offset:int}>
	 */
	public static function headings( $html ) {
		$out = array();
		if ( preg_match_all( '#<h([1-6])[^>]*>(.*?)</h\1>#is', (string) $html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m as $h ) {
				$out[] = array(
					'level'  => (int) $h[1][0],
					'text'   => trim( self::strip( $h[2][0] ) ),
					'offset' => $h[0][1],
				);
			}
		}
		return $out;
	}

	/**
	 * Divide o texto em blocos de texto separados por subtítulos.
	 *
	 * @return string[] Texto antes do primeiro subtítulo e entre cada subtítulo.
	 */
	public static function sections( $html ) {
		$parts = preg_split( '#<h[2-6][^>]*>.*?</h[2-6]>#is', (string) $html );
		return array_map( array( __CLASS__, 'strip' ), $parts );
	}

	/**
	 * Divide texto puro em frases.
	 *
	 * @return string[]
	 */
	public static function sentences( $text ) {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( '' === $text ) {
			return array();
		}
		$tokens    = preg_split( '/(?<=[.!?…])\s+/u', $text );
		$sentences = array();
		$buffer    = '';
		foreach ( $tokens as $token ) {
			$buffer = '' === $buffer ? $token : $buffer . ' ' . $token;
			if ( preg_match( '/(?:^|\s)([\p{L}.]+)\.$/u', $token, $m ) && in_array( mb_strtolower( rtrim( $m[1], '.' ) ), self::ABBREVIATIONS, true ) ) {
				continue;
			}
			// Iniciais de nomes ("J. R. R. Tolkien").
			if ( preg_match( '/(?:^|\s)\p{Lu}\.$/u', $token ) ) {
				continue;
			}
			$sentences[] = trim( $buffer );
			$buffer      = '';
		}
		if ( '' !== $buffer ) {
			$sentences[] = trim( $buffer );
		}
		// Linhas soltas (itens de lista, legendas) também encerram frases.
		$result = array();
		foreach ( $sentences as $s ) {
			foreach ( preg_split( "/\n+/u", $s ) as $piece ) {
				if ( count( self::words( $piece ) ) > 0 ) {
					$result[] = trim( $piece );
				}
			}
		}
		return $result;
	}

	/**
	 * Divide texto em frases respeitando quebras de linha (blocos).
	 *
	 * @return string[]
	 */
	public static function sentences_from_html( $html ) {
		$out = array();
		foreach ( preg_split( "/\n+/u", self::strip( $html ) ) as $line ) {
			foreach ( self::sentences( $line ) as $s ) {
				$out[] = $s;
			}
		}
		return $out;
	}

	/**
	 * @return string[]
	 */
	public static function words( $text ) {
		$parts = preg_split( "/[^\p{L}\p{N}'’\-]+/u", (string) $text, -1, PREG_SPLIT_NO_EMPTY );
		return array_values(
			array_filter(
				$parts,
				static function ( $w ) {
					return (bool) preg_match( '/[\p{L}\p{N}]/u', $w );
				}
			)
		);
	}

	public static function word_count( $text ) {
		return count( self::words( $text ) );
	}

	/**
	 * Minúsculas sem acentos, para comparação tolerante.
	 */
	public static function normalize( $text ) {
		$text = mb_strtolower( (string) $text, 'UTF-8' );
		$map  = array(
			'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
			'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
			'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
			'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
			'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
			'ç' => 'c', 'ñ' => 'n', '’' => "'",
		);
		return strtr( $text, $map );
	}

	/**
	 * Estimativa de sílabas de uma palavra.
	 */
	public static function syllables( $word, $lang = 'pt' ) {
		$w = mb_strtolower( (string) $word, 'UTF-8' );
		$w = preg_replace( '/[^\p{L}]/u', '', $w );
		if ( '' === $w ) {
			return 0;
		}
		if ( 'pt' === $lang ) {
			// Ditongos e tritongos contam como uma sílaba; "qu"/"gu" antes de e/i não formam sílaba.
			$w = preg_replace( '/([qg])u([eiéíê])/u', '$1$2', $w );
			$w = preg_replace( '/(ão|ãe|õe|ai|ei|oi|ui|au|eu|iu|ou|éi|ói|éu)/u', 'a', $w );
			// O que sobra de vogais adjacentes são hiatos (sa-ú-de, di-a): cada vogal é uma sílaba.
			return max( 1, preg_match_all( '/[aeiouáéíóúâêôãõàü]/u', $w ) );
		}
		// Inglês: heurística clássica.
		if ( mb_strlen( $w ) <= 3 ) {
			return 1;
		}
		$w = preg_replace( '/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $w );
		$w = preg_replace( '/^y/', '', $w );
		preg_match_all( '/[aeiouy]{1,2}/', $w, $m );
		return max( 1, count( $m[0] ) );
	}

	/**
	 * Largura aproximada em pixels de um texto na fonte de títulos do Google (Arial 20px).
	 */
	public static function pixel_width( $text, $font_size = 20 ) {
		static $widths = null;
		if ( null === $widths ) {
			$widths = array();
			// Larguras em "em" da Arial (pares, pois chaves float viram int em arrays PHP).
			$groups = array(
				array( 0.278, "ijlI.,:;!|'`’ " ),
				array( 0.333, 'ftr()[]-/\\' ),
				array( 0.5, 'sczJ"*^' ),
				array( 0.556, 'abdeghknopquvxy0123456789$#_?L' ),
				array( 0.611, 'TFZ' ),
				array( 0.667, 'ABEKPSVXY&' ),
				array( 0.722, 'CDHNRUw' ),
				array( 0.778, 'GOQ' ),
				array( 0.833, 'mM' ),
				array( 0.944, 'W' ),
				array( 1.015, '@' ),
			);
			foreach ( $groups as list( $em, $chars ) ) {
				foreach ( preg_split( '//u', $chars, -1, PREG_SPLIT_NO_EMPTY ) as $c ) {
					$widths[ $c ] = (float) $em;
				}
			}
		}
		$total = 0.0;
		foreach ( preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY ) as $c ) {
			$base = strtr( $c, array( 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ç' => 'C', 'Ã' => 'A', 'Õ' => 'O', 'Â' => 'A', 'Ê' => 'E', 'Ô' => 'O' ) );
			$total += isset( $widths[ $base ] ) ? $widths[ $base ] : 0.556;
		}
		return (int) round( $total * $font_size );
	}

	/**
	 * Corta o texto no limite de caracteres sem partir palavras.
	 */
	public static function truncate( $text, $max, $ellipsis = '' ) {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $max - mb_strlen( $ellipsis ) + 1 );
		$space = mb_strrpos( $cut, ' ' );
		if ( false !== $space && $space > $max * 0.6 ) {
			$cut = mb_substr( $cut, 0, $space );
		} else {
			$cut = mb_substr( $cut, 0, $max - mb_strlen( $ellipsis ) );
		}
		return self::trim_punctuation( $cut, false ) . $ellipsis;
	}

	/**
	 * Remove espaços e pontuação de ligação das pontas (seguro para UTF-8,
	 * ao contrário de trim() com caracteres multibyte na lista).
	 */
	public static function trim_punctuation( $text, $both_sides = true ) {
		$text = preg_replace( '/[\s,;:\-–—]+$/u', '', (string) $text );
		return $both_sides ? preg_replace( '/^[\s,;:\-–—]+/u', '', $text ) : $text;
	}

	/**
	 * Conta ocorrências da frase-chave (todas as palavras de conteúdo, na ordem, tolerando acentos e plurais simples).
	 */
	public static function count_keyphrase( $text, $keyphrase, array $stopwords = array() ) {
		$pattern = self::keyphrase_pattern( $keyphrase, $stopwords );
		if ( null === $pattern ) {
			return 0;
		}
		return preg_match_all( $pattern, self::normalize( $text ) );
	}

	public static function contains_keyphrase( $text, $keyphrase, array $stopwords = array() ) {
		return self::count_keyphrase( $text, $keyphrase, $stopwords ) > 0;
	}

	/**
	 * Monta uma expressão regular para a frase-chave. Palavras vazias opcionais
	 * podem aparecer entre as palavras de conteúdo ("comprar casa" casa com "comprar uma casa").
	 */
	public static function keyphrase_pattern( $keyphrase, array $stopwords = array() ) {
		$words = self::words( self::normalize( $keyphrase ) );
		if ( ! $words ) {
			return null;
		}
		$content = array_values(
			array_filter(
				$words,
				static function ( $w ) use ( $stopwords ) {
					return ! in_array( $w, $stopwords, true );
				}
			)
		);
		if ( ! $content ) {
			$content = $words;
		}
		$parts = array();
		foreach ( $content as $w ) {
			// Radical simples para casar singular/plural (especial/especiais, homem/homens, coração/corações).
			$stem    = preg_replace( '/(oes|aes|ais|eis|ois|is|es|s|ao|l|m)$/u', '', $w );
			$stem    = mb_strlen( $stem ) >= 3 ? $stem : $w;
			$parts[] = preg_quote( $stem, '/' ) . '[\p{L}]{0,4}';
		}
		return '/(?<![\p{L}\p{N}])' . implode( '(?:[^\p{L}\p{N}]+[\p{L}\p{N}]+){0,2}?[^\p{L}\p{N}]+', $parts ) . '(?![\p{L}\p{N}])/u';
	}

	/**
	 * Extrai as tags <img> e seus atributos.
	 *
	 * @return array<int, array{src:string, alt:?string}>
	 */
	public static function images( $html ) {
		$out = array();
		if ( preg_match_all( '#<img\b[^>]*>#i', (string) $html, $m ) ) {
			foreach ( $m[0] as $tag ) {
				$out[] = array(
					'src' => self::attr( $tag, 'src' ),
					'alt' => self::attr( $tag, 'alt' ),
				);
			}
		}
		return $out;
	}

	/**
	 * @return array<int, array{href:string, text:string, rel:string}>
	 */
	public static function links( $html ) {
		$out = array();
		if ( preg_match_all( '#<a\b([^>]*)>(.*?)</a>#is', (string) $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $a ) {
				$href = self::attr( '<a ' . $a[1] . '>', 'href' );
				if ( null === $href ) {
					continue;
				}
				$text = trim( self::strip( $a[2] ) );
				if ( '' === $text ) {
					// Link de imagem: usa o alt como texto âncora.
					$text = (string) self::attr( $a[2], 'alt' );
				}
				$out[] = array(
					'href' => $href,
					'text' => $text,
					'rel'  => (string) self::attr( '<a ' . $a[1] . '>', 'rel' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Lê um atributo de uma tag HTML. Retorna null se ausente.
	 */
	public static function attr( $tag, $name ) {
		if ( preg_match( '/\s' . preg_quote( $name, '/' ) . '\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', (string) $tag, $m ) ) {
			$value = isset( $m[4] ) && '' !== $m[4] ? $m[4] : ( isset( $m[3] ) && '' !== $m[3] ? $m[3] : $m[2] );
			return html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		return null;
	}
}
