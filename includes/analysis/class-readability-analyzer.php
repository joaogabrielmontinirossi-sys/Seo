<?php
/**
 * Análise de legibilidade.
 *
 * Limiares baseados nas recomendações consolidadas de mercado (Yoast/Rank Math),
 * no índice de Flesch e nas diretrizes de escrita para web (WCAG 3.1.5, Plain Language).
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade\Analysis;

defined( 'ABSPATH' ) || exit;

class Readability_Analyzer {

	const MAX_SENTENCE_WORDS    = 20;
	const MAX_PARAGRAPH_WORDS   = 150;
	const MAX_SECTION_WORDS     = 300;
	const MAX_PASSIVE_PERCENT   = 10;
	const MIN_TRANSITION_PERCENT = 30;

	/** @var string */
	private $lang;

	public function __construct( $lang = 'pt' ) {
		$this->lang = 'en' === $lang ? 'en' : 'pt';
	}

	/**
	 * @param string $html Conteúdo do post.
	 * @return array{score:int, rating:string, results:array, stats:array}
	 */
	public function analyze( $html ) {
		$text      = Text::strip( $html );
		$sentences = Text::sentences_from_html( $html );
		$words     = Text::words( $text );
		$results   = array();

		if ( count( $words ) < 50 ) {
			$results[] = new Result(
				'text_presence',
				__( 'Quantidade de texto', 'seo-legibilidade' ),
				Result::BAD,
				__( 'Há pouco texto para avaliar a legibilidade. Escreva pelo menos 50 palavras.', 'seo-legibilidade' )
			);
			return $this->finish( $results, $text, $sentences, $words );
		}

		$results[] = $this->flesch( $sentences, $words );
		$results[] = $this->sentence_length( $sentences );
		$results[] = $this->paragraph_length( $html );
		$results[] = $this->subheading_distribution( $html, count( $words ) );
		$results[] = $this->passive_voice( $sentences );
		if ( count( $words ) >= 200 ) {
			$results[] = $this->transition_words( $sentences );
		}
		$results[] = $this->consecutive_sentences( $sentences );
		$results[] = $this->word_complexity( $words );
		$results[] = $this->lists( $html, count( $words ) );

		return $this->finish( array_values( array_filter( $results ) ), $text, $sentences, $words );
	}

	private function finish( array $results, $text, array $sentences, array $words ) {
		$score = Result::score( $results );
		return array(
			'score'   => $score,
			'rating'  => Result::rating_for_score( $score ),
			'results' => array_map(
				static function ( Result $r ) {
					return $r->to_array();
				},
				Result::sort( $results )
			),
			'stats'   => array(
				'words'          => count( $words ),
				'sentences'      => count( $sentences ),
				'characters'     => mb_strlen( $text ),
				'reading_time'   => max( 1, (int) ceil( count( $words ) / 200 ) ),
				'flesch'         => $this->flesch_value( $sentences, $words ),
			),
		);
	}

	private function flesch_value( array $sentences, array $words ) {
		if ( ! $sentences || ! $words ) {
			return null;
		}
		$syllables = 0;
		foreach ( $words as $w ) {
			$syllables += Text::syllables( $w, $this->lang );
		}
		return Language::flesch( $this->lang, count( $words ) / count( $sentences ), $syllables / count( $words ) );
	}

	private function flesch( array $sentences, array $words ) {
		$score = $this->flesch_value( $sentences, $words );
		$label = __( 'Facilidade de leitura (Flesch)', 'seo-legibilidade' );
		if ( $score >= 90 ) {
			$level = __( 'muito fácil', 'seo-legibilidade' );
		} elseif ( $score >= 80 ) {
			$level = __( 'fácil', 'seo-legibilidade' );
		} elseif ( $score >= 70 ) {
			$level = __( 'razoavelmente fácil', 'seo-legibilidade' );
		} elseif ( $score >= 60 ) {
			$level = __( 'padrão', 'seo-legibilidade' );
		} elseif ( $score >= 50 ) {
			$level = __( 'razoavelmente difícil', 'seo-legibilidade' );
		} elseif ( $score >= 30 ) {
			$level = __( 'difícil', 'seo-legibilidade' );
		} else {
			$level = __( 'muito difícil', 'seo-legibilidade' );
		}
		/* translators: 1: pontuação, 2: nível. */
		$base = sprintf( __( 'O texto obteve %1$s pontos, considerado %2$s de ler.', 'seo-legibilidade' ), number_format( $score, 1, ',', '' ), $level );
		if ( $score >= 60 ) {
			return new Result( 'flesch', $label, Result::GOOD, $base . ' ' . __( 'Ótimo trabalho!', 'seo-legibilidade' ) );
		}
		$tip = __( 'Use frases mais curtas e palavras mais simples para facilitar a leitura.', 'seo-legibilidade' );
		return new Result( 'flesch', $label, $score >= 50 ? Result::OK : Result::BAD, $base . ' ' . $tip );
	}

	private function sentence_length( array $sentences ) {
		$long = 0;
		foreach ( $sentences as $s ) {
			if ( Text::word_count( $s ) > self::MAX_SENTENCE_WORDS ) {
				++$long;
			}
		}
		$pct   = $sentences ? round( $long / count( $sentences ) * 100, 1 ) : 0;
		$label = __( 'Comprimento das frases', 'seo-legibilidade' );
		if ( $pct <= 25 ) {
			/* translators: %s: percentual. */
			return new Result( 'sentence_length', $label, Result::GOOD, sprintf( __( 'Apenas %s%% das frases têm mais de 20 palavras. Ótimo!', 'seo-legibilidade' ), $this->num( $pct ) ) );
		}
		/* translators: %s: percentual. */
		$msg = sprintf( __( '%s%% das frases têm mais de 20 palavras; o recomendado é no máximo 25%%. Divida as frases longas.', 'seo-legibilidade' ), $this->num( $pct ) );
		return new Result( 'sentence_length', $label, $pct <= 30 ? Result::OK : Result::BAD, $msg );
	}

	private function paragraph_length( $html ) {
		$too_long = 0;
		$warn     = 0;
		foreach ( Text::paragraphs( $html ) as $p ) {
			$n = Text::word_count( $p );
			if ( $n > self::MAX_PARAGRAPH_WORDS ) {
				++$too_long;
			} elseif ( $n > 120 ) {
				++$warn;
			}
		}
		$label = __( 'Comprimento dos parágrafos', 'seo-legibilidade' );
		if ( $too_long ) {
			/* translators: %d: quantidade. */
			return new Result( 'paragraph_length', $label, Result::BAD, sprintf( _n( '%d parágrafo tem mais de 150 palavras. Divida-o em blocos menores.', '%d parágrafos têm mais de 150 palavras. Divida-os em blocos menores.', $too_long, 'seo-legibilidade' ), $too_long ) );
		}
		if ( $warn ) {
			/* translators: %d: quantidade. */
			return new Result( 'paragraph_length', $label, Result::OK, sprintf( _n( '%d parágrafo tem entre 120 e 150 palavras. Considere encurtá-lo.', '%d parágrafos têm entre 120 e 150 palavras. Considere encurtá-los.', $warn, 'seo-legibilidade' ), $warn ) );
		}
		return new Result( 'paragraph_length', $label, Result::GOOD, __( 'Nenhum parágrafo é longo demais. Ótimo!', 'seo-legibilidade' ) );
	}

	private function subheading_distribution( $html, $total_words ) {
		$label    = __( 'Distribuição de subtítulos', 'seo-legibilidade' );
		$sections = Text::sections( $html );
		$has_sub  = count( $sections ) > 1;

		if ( ! $has_sub ) {
			if ( $total_words <= self::MAX_SECTION_WORDS ) {
				return new Result( 'subheadings', $label, Result::GOOD, __( 'O texto é curto e não precisa de subtítulos.', 'seo-legibilidade' ) );
			}
			return new Result( 'subheadings', $label, Result::BAD, __( 'O texto tem mais de 300 palavras e nenhum subtítulo (H2, H3). Adicione subtítulos para organizar o conteúdo.', 'seo-legibilidade' ) );
		}
		$over = 0;
		$warn = 0;
		foreach ( $sections as $s ) {
			$n = Text::word_count( $s );
			if ( $n > 350 ) {
				++$over;
			} elseif ( $n > self::MAX_SECTION_WORDS ) {
				++$warn;
			}
		}
		if ( $over ) {
			/* translators: %d: quantidade. */
			return new Result( 'subheadings', $label, Result::BAD, sprintf( _n( '%d trecho tem mais de 300 palavras sem subtítulo. Adicione subtítulos.', '%d trechos têm mais de 300 palavras sem subtítulo. Adicione subtítulos.', $over, 'seo-legibilidade' ), $over ) );
		}
		if ( $warn ) {
			/* translators: %d: quantidade. */
			return new Result( 'subheadings', $label, Result::OK, sprintf( _n( '%d trecho está perto do limite de 300 palavras sem subtítulo.', '%d trechos estão perto do limite de 300 palavras sem subtítulo.', $warn, 'seo-legibilidade' ), $warn ) );
		}
		return new Result( 'subheadings', $label, Result::GOOD, __( 'Os subtítulos estão bem distribuídos. Ótimo!', 'seo-legibilidade' ) );
	}

	/**
	 * Detecta se uma frase está na voz passiva.
	 */
	public function is_passive( $sentence ) {
		$words       = array_map(
			static function ( $w ) {
				return mb_strtolower( $w, 'UTF-8' );
			},
			Text::words( $sentence )
		);
		$aux         = Language::passive_auxiliaries( $this->lang );
		$irregular   = Language::irregular_participles( $this->lang );
		$exceptions  = Language::participle_exceptions( $this->lang );
		$count       = count( $words );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! in_array( $words[ $i ], $aux, true ) ) {
				continue;
			}
			// O particípio pode vir logo após o auxiliar ou após um advérbio ("foi rapidamente aprovado").
			for ( $j = $i + 1; $j <= min( $i + 3, $count - 1 ); $j++ ) {
				$w = $words[ $j ];
				if ( in_array( $w, $exceptions, true ) ) {
					break;
				}
				if ( in_array( $w, $irregular, true ) ) {
					return true;
				}
				if ( 'pt' === $this->lang && preg_match( '/^\p{L}{2,}(ad|id)(o|a|os|as)$/u', $w ) ) {
					return true;
				}
				if ( 'en' === $this->lang && preg_match( '/^\p{L}{2,}ed$/u', $w ) ) {
					return true;
				}
				// Só pula advérbios (terminados em -mente/-ly) ou "não"/"not"; qualquer outra palavra encerra.
				if ( ! preg_match( '/(mente|ly)$/u', $w ) && ! in_array( $w, array( 'não', 'nunca', 'já', 'sempre', 'not', 'never', 'also', 'also', 'também' ), true ) ) {
					break;
				}
			}
		}
		return false;
	}

	private function passive_voice( array $sentences ) {
		$passive = 0;
		foreach ( $sentences as $s ) {
			if ( $this->is_passive( $s ) ) {
				++$passive;
			}
		}
		$pct   = $sentences ? round( $passive / count( $sentences ) * 100, 1 ) : 0;
		$label = __( 'Voz passiva', 'seo-legibilidade' );
		if ( $pct <= self::MAX_PASSIVE_PERCENT ) {
			/* translators: %s: percentual. */
			return new Result( 'passive_voice', $label, Result::GOOD, sprintf( __( '%s%% das frases estão na voz passiva. Ótimo!', 'seo-legibilidade' ), $this->num( $pct ) ) );
		}
		/* translators: %s: percentual. */
		$msg = sprintf( __( '%s%% das frases estão na voz passiva; o recomendado é no máximo 10%%. Prefira a voz ativa.', 'seo-legibilidade' ), $this->num( $pct ) );
		return new Result( 'passive_voice', $label, $pct <= 15 ? Result::OK : Result::BAD, $msg );
	}

	public function has_transition( $sentence ) {
		$norm = ' ' . implode( ' ', Text::words( mb_strtolower( $sentence, 'UTF-8' ) ) ) . ' ';
		foreach ( Language::transition_words( $this->lang ) as $t ) {
			if ( false !== strpos( $norm, ' ' . $t . ' ' ) ) {
				return true;
			}
		}
		return false;
	}

	private function transition_words( array $sentences ) {
		$with = 0;
		foreach ( $sentences as $s ) {
			if ( $this->has_transition( $s ) ) {
				++$with;
			}
		}
		$pct   = $sentences ? round( $with / count( $sentences ) * 100, 1 ) : 0;
		$label = __( 'Palavras de transição', 'seo-legibilidade' );
		if ( $pct >= self::MIN_TRANSITION_PERCENT ) {
			/* translators: %s: percentual. */
			return new Result( 'transition_words', $label, Result::GOOD, sprintf( __( '%s%% das frases têm palavras de transição. Ótimo!', 'seo-legibilidade' ), $this->num( $pct ) ) );
		}
		/* translators: %s: percentual. */
		$msg = sprintf( __( 'Apenas %s%% das frases têm palavras de transição (ex.: "além disso", "portanto", "no entanto"); o recomendado é pelo menos 30%%.', 'seo-legibilidade' ), $this->num( $pct ) );
		return new Result( 'transition_words', $label, $pct >= 20 ? Result::OK : Result::BAD, $msg );
	}

	private function consecutive_sentences( array $sentences ) {
		$max_run = 1;
		$run     = 1;
		$prev    = null;
		$repeated = '';
		foreach ( $sentences as $s ) {
			$words = Text::words( mb_strtolower( $s, 'UTF-8' ) );
			$first = $words ? $words[0] : '';
			if ( null !== $prev && '' !== $first && $first === $prev ) {
				++$run;
				if ( $run > $max_run ) {
					$max_run  = $run;
					$repeated = $first;
				}
			} else {
				$run = 1;
			}
			$prev = $first;
		}
		$label = __( 'Início de frases consecutivas', 'seo-legibilidade' );
		if ( $max_run >= 3 ) {
			/* translators: 1: quantidade, 2: palavra. */
			return new Result( 'consecutive_sentences', $label, Result::BAD, sprintf( __( '%1$d frases seguidas começam com "%2$s". Varie o início das frases.', 'seo-legibilidade' ), $max_run, $repeated ) );
		}
		return new Result( 'consecutive_sentences', $label, Result::GOOD, __( 'As frases começam de forma variada. Ótimo!', 'seo-legibilidade' ) );
	}

	private function word_complexity( array $words ) {
		$complex = 0;
		foreach ( $words as $w ) {
			if ( Text::syllables( $w, $this->lang ) >= ( 'pt' === $this->lang ? 5 : 4 ) ) {
				++$complex;
			}
		}
		$pct   = round( $complex / max( 1, count( $words ) ) * 100, 1 );
		$label = __( 'Palavras complexas', 'seo-legibilidade' );
		if ( $pct <= 10 ) {
			/* translators: %s: percentual. */
			return new Result( 'word_complexity', $label, Result::GOOD, sprintf( __( '%s%% das palavras são longas/complexas. Ótimo!', 'seo-legibilidade' ), $this->num( $pct ) ) );
		}
		/* translators: %s: percentual. */
		return new Result( 'word_complexity', $label, Result::OK, sprintf( __( '%s%% das palavras são longas/complexas. Troque algumas por sinônimos mais simples.', 'seo-legibilidade' ), $this->num( $pct ) ) );
	}

	private function lists( $html, $total_words ) {
		if ( $total_words < 600 ) {
			return null;
		}
		$label = __( 'Listas', 'seo-legibilidade' );
		if ( preg_match( '#<(ul|ol)\b#i', $html ) ) {
			return new Result( 'lists', $label, Result::GOOD, __( 'O texto usa listas, o que facilita a leitura rápida.', 'seo-legibilidade' ) );
		}
		return new Result( 'lists', $label, Result::OK, __( 'Textos longos ficam mais escaneáveis com listas. Considere usar uma lista de tópicos.', 'seo-legibilidade' ) );
	}

	private function num( $n ) {
		return rtrim( rtrim( number_format( (float) $n, 1, ',', '' ), '0' ), ',' );
	}
}
