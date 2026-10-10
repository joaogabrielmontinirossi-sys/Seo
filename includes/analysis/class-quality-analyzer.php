<?php
/**
 * Atualidade e confiança: sinais de conteúdo útil e atual.
 *
 * Cobre o que as atualizações recentes do Google valorizam e que dá para medir
 * no texto: datas e anos atualizados, experiência própria, fontes e dados
 * concretos, autoria, e respostas diretas a perguntas (formato que alimenta
 * trechos em destaque e AI Overviews).
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade\Analysis;

defined( 'ABSPATH' ) || exit;

class Quality_Analyzer {

	const STALE_DAYS        = 365;
	const VERY_STALE_DAYS   = 730;
	const ANSWER_MIN_WORDS  = 15;
	const ANSWER_MAX_WORDS  = 60;
	const YEAR_PATTERN      = '/(?<![\p{N}])(19\d{2}|20\d{2})(?![\p{N}])/u';

	/** @var string */
	private $lang;

	public function __construct( $lang = 'pt' ) {
		$this->lang = 'en' === $lang ? 'en' : 'pt';
	}

	/**
	 * @param array $data {
	 *     @type string    $content       HTML do conteúdo.
	 *     @type string    $post_title    Título do post.
	 *     @type string    $title         Título SEO.
	 *     @type string    $description   Meta descrição.
	 *     @type string    $site_url      URL do site.
	 *     @type int       $current_year  Ano atual.
	 *     @type int|null  $modified_days Dias desde a última atualização (null = desconhecido).
	 *     @type bool|null $author_bio    O autor tem biografia (null = desconhecido).
	 * }
	 */
	public function analyze( array $data ) {
		$data = array_merge(
			array(
				'content'       => '',
				'post_title'    => '',
				'title'         => '',
				'description'   => '',
				'site_url'      => '',
				'current_year'  => (int) gmdate( 'Y' ),
				'modified_days' => null,
				'author_bio'    => null,
			),
			$data
		);
		$html  = (string) $data['content'];
		$text  = Text::strip( $html );
		$words = Text::word_count( $text );

		$results = array(
			$this->outdated_years( $data, $html, (int) $data['current_year'] ),
			$this->last_updated( $data['modified_days'] ),
			$this->relative_dates( $text ),
			$this->sources( $html, (string) $data['site_url'], $words ),
			$this->author_bio( $data['author_bio'] ),
			$this->experience( $text, $words ),
			$this->data_points( $text, $words ),
			$this->question_headings( $html, $words ),
			$this->direct_answers( $html ),
		);
		$results = array_values( array_filter( $results ) );
		$score   = Result::score( $results );

		return array(
			'score'   => $score,
			'rating'  => Result::rating_for_score( $score ),
			'results' => array_map(
				static function ( Result $r ) {
					return $r->to_array();
				},
				Result::sort( $results )
			),
		);
	}

	/**
	 * Anos citados em um texto.
	 *
	 * @return int[]
	 */
	public static function years( $text ) {
		preg_match_all( self::YEAR_PATTERN, (string) $text, $m );
		return array_map( 'intval', $m[1] );
	}

	/**
	 * Texto normalizado para busca de expressões inteiras.
	 */
	private function padded( $text ) {
		return ' ' . implode( ' ', Text::words( mb_strtolower( (string) $text, 'UTF-8' ) ) ) . ' ';
	}

	private function find_phrases( $text, array $phrases ) {
		$padded = $this->padded( $text );
		$found  = array();
		foreach ( $phrases as $p ) {
			if ( false !== strpos( $padded, ' ' . $p . ' ' ) ) {
				$found[] = $p;
			}
		}
		return $found;
	}

	private function outdated_years( array $data, $html, $current ) {
		$label    = __( 'Anos citados no título', 'seo-legibilidade' );
		$titles   = trim( $data['post_title'] . ' ' . $data['title'] );
		$past     = array_values(
			array_unique(
				array_filter(
					self::years( $titles ),
					static function ( $y ) use ( $current ) {
						return $y < $current;
					}
				)
			)
		);
		if ( $past ) {
			/* translators: 1: anos antigos, 2: ano atual. */
			return new Result( 'outdated_year', $label, Result::BAD, sprintf( __( 'O título cita %1$s, mas estamos em %2$d. Se o conteúdo foi revisado, atualize o ano; títulos com ano vencido perdem cliques. Ignore se o texto for histórico.', 'seo-legibilidade' ), implode( ', ', $past ), $current ), 'year' );
		}
		$elsewhere = $data['description'];
		foreach ( Text::headings( $html ) as $h ) {
			$elsewhere .= ' ' . $h['text'];
		}
		$past_elsewhere = array_values(
			array_unique(
				array_filter(
					self::years( $elsewhere ),
					static function ( $y ) use ( $current ) {
						return $y < $current;
					}
				)
			)
		);
		if ( $past_elsewhere ) {
			/* translators: %s: anos antigos. */
			return new Result( 'outdated_year', $label, Result::OK, sprintf( __( 'A meta descrição ou os subtítulos citam %s. Confira se ainda está correto.', 'seo-legibilidade' ), implode( ', ', $past_elsewhere ) ) );
		}
		if ( in_array( $current, self::years( $titles ), true ) ) {
			return new Result( 'outdated_year', $label, Result::GOOD, __( 'O título cita o ano atual. Lembre-se de revisá-lo no ano que vem.', 'seo-legibilidade' ) );
		}
		return new Result( 'outdated_year', $label, Result::GOOD, __( 'O título não cita anos desatualizados.', 'seo-legibilidade' ) );
	}

	private function last_updated( $days ) {
		if ( null === $days || '' === $days ) {
			return null;
		}
		$days  = max( 0, (int) $days );
		$label = __( 'Última atualização', 'seo-legibilidade' );
		if ( $days <= self::STALE_DAYS ) {
			/* translators: %d: dias. */
			return new Result( 'last_updated', $label, Result::GOOD, 0 === $days ? __( 'Conteúdo atualizado hoje.', 'seo-legibilidade' ) : sprintf( _n( 'Conteúdo atualizado há %d dia.', 'Conteúdo atualizado há %d dias.', $days, 'seo-legibilidade' ), $days ) );
		}
		$months = (int) floor( $days / 30 );
		/* translators: %d: meses. */
		$msg = sprintf( __( 'O conteúdo não é atualizado há cerca de %d meses. Revise dados, preços, links e capturas de tela; o Google valoriza informação atual.', 'seo-legibilidade' ), $months );
		return new Result( 'last_updated', $label, $days <= self::VERY_STALE_DAYS ? Result::OK : Result::BAD, $msg );
	}

	private function relative_dates( $text ) {
		$found = $this->find_phrases( $text, Language::relative_time( $this->lang ) );
		if ( ! $found ) {
			return null;
		}
		$label = __( 'Referências de tempo', 'seo-legibilidade' );
		if ( self::years( $text ) ) {
			return new Result( 'relative_dates', $label, Result::GOOD, __( 'O texto situa o leitor no tempo com datas concretas.', 'seo-legibilidade' ) );
		}
		/* translators: %s: expressões encontradas. */
		return new Result( 'relative_dates', $label, Result::OK, sprintf( __( 'O texto usa "%s" sem citar nenhuma data. Com o tempo isso fica impreciso: inclua datas concretas (ex.: "em março de 2026").', 'seo-legibilidade' ), implode( '", "', $found ) ) );
	}

	private function sources( $html, $site_url, $words ) {
		$site  = preg_replace( '/^www\./', '', strtolower( (string) parse_url( $site_url, PHP_URL_HOST ) ) );
		$hosts = array();
		foreach ( Text::links( $html ) as $l ) {
			if ( ! preg_match( '#^(https?:)?//#i', $l['href'] ) ) {
				continue;
			}
			$host = preg_replace( '/^www\./', '', strtolower( (string) parse_url( ( 0 === strpos( $l['href'], '//' ) ? 'https:' : '' ) . $l['href'], PHP_URL_HOST ) ) );
			if ( '' !== $host && $host !== $site ) {
				$hosts[ $host ] = true;
			}
		}
		$n     = count( $hosts );
		$label = __( 'Fontes citadas', 'seo-legibilidade' );
		if ( $n >= 2 ) {
			/* translators: %d: quantidade de sites. */
			return new Result( 'sources', $label, Result::GOOD, sprintf( __( 'O texto cita %d fontes externas diferentes. Isso reforça a confiabilidade.', 'seo-legibilidade' ), $n ) );
		}
		if ( 1 === $n ) {
			return new Result( 'sources', $label, Result::OK, __( 'O texto cita só uma fonte externa. Apoie dados e afirmações importantes em mais fontes confiáveis (estudos, órgãos oficiais, especialistas).', 'seo-legibilidade' ) );
		}
		return new Result( 'sources', $label, $words >= 300 ? Result::BAD : Result::OK, __( 'O texto não cita nenhuma fonte externa. Link para estudos, órgãos oficiais ou especialistas que confirmem o que você afirma.', 'seo-legibilidade' ) );
	}

	private function author_bio( $has_bio ) {
		if ( null === $has_bio ) {
			return null;
		}
		$label = __( 'Autoria', 'seo-legibilidade' );
		if ( $has_bio ) {
			return new Result( 'author_bio', $label, Result::GOOD, __( 'O autor tem biografia. Mostre nela a experiência dele com o assunto.', 'seo-legibilidade' ) );
		}
		return new Result( 'author_bio', $label, Result::OK, __( 'O autor não tem biografia. Escreva uma (Usuários → Perfil → Informações biográficas) mostrando formação e experiência no assunto.', 'seo-legibilidade' ) );
	}

	private function experience( $text, $words ) {
		if ( $words < 300 ) {
			return null;
		}
		$label = __( 'Experiência própria', 'seo-legibilidade' );
		$found = $this->find_phrases( $text, Language::experience_phrases( $this->lang ) );
		if ( $found ) {
			return new Result( 'experience', $label, Result::GOOD, __( 'O texto mostra experiência própria com o assunto (testes, uso, comparações). O Google valoriza isso.', 'seo-legibilidade' ) );
		}
		return new Result( 'experience', $label, Result::OK, __( 'O texto não mostra experiência própria. Conte o que você testou, usou ou comparou, com detalhes e fotos próprias: é o que diferencia seu conteúdo do resto.', 'seo-legibilidade' ) );
	}

	private function data_points( $text, $words ) {
		if ( $words < 300 ) {
			return null;
		}
		$label = __( 'Dados concretos', 'seo-legibilidade' );
		if ( preg_match( '/\p{N}/u', $text ) ) {
			return new Result( 'data_points', $label, Result::GOOD, __( 'O texto traz números e dados concretos.', 'seo-legibilidade' ) );
		}
		return new Result( 'data_points', $label, Result::OK, __( 'O texto não traz nenhum número. Inclua dados concretos (preços, medidas, prazos, estatísticas com fonte) para torná-lo mais útil e verificável.', 'seo-legibilidade' ) );
	}

	/**
	 * Um subtítulo é uma pergunta se termina com "?" ou começa com palavra interrogativa.
	 */
	public function is_question( $heading ) {
		$heading = trim( (string) $heading );
		if ( '' === $heading ) {
			return false;
		}
		if ( '?' === mb_substr( $heading, -1 ) ) {
			return true;
		}
		$padded = $this->padded( $heading );
		foreach ( Language::question_words( $this->lang ) as $q ) {
			if ( 0 === strpos( $padded, ' ' . $q . ' ' ) ) {
				return true;
			}
		}
		return false;
	}

	private function question_headings( $html, $words ) {
		$subs = array_filter(
			Text::headings( $html ),
			static function ( $h ) {
				return in_array( $h['level'], array( 2, 3 ), true );
			}
		);
		if ( count( $subs ) < 2 && $words < 600 ) {
			return null;
		}
		$questions = 0;
		foreach ( $subs as $h ) {
			if ( $this->is_question( $h['text'] ) ) {
				++$questions;
			}
		}
		$label = __( 'Subtítulos em forma de pergunta', 'seo-legibilidade' );
		if ( $questions ) {
			/* translators: %d: quantidade. */
			return new Result( 'question_headings', $label, Result::GOOD, sprintf( _n( '%d subtítulo responde a uma pergunta do leitor. Isso ajuda a aparecer em trechos em destaque e nas AI Overviews.', '%d subtítulos respondem a perguntas do leitor. Isso ajuda a aparecer em trechos em destaque e nas AI Overviews.', $questions, 'seo-legibilidade' ), $questions ) );
		}
		return new Result( 'question_headings', $label, Result::OK, __( 'Nenhum subtítulo é uma pergunta. Transforme alguns nas perguntas que o leitor faria (ex.: "Como escolher…?", "Quanto custa…?") e responda logo abaixo.', 'seo-legibilidade' ) );
	}

	private function direct_answers( $html ) {
		$parts   = preg_split( '#(<h[23]\b[^>]*>.*?</h[23]>)#is', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$total   = 0;
		$long    = array();
		$missing = array();
		for ( $i = 1, $n = count( $parts ); $i < $n; $i += 2 ) {
			$heading = trim( Text::strip( $parts[ $i ] ) );
			if ( ! $this->is_question( $heading ) ) {
				continue;
			}
			++$total;
			$body = isset( $parts[ $i + 1 ] ) ? $parts[ $i + 1 ] : '';
			// Uma lista logo abaixo da pergunta também é uma resposta direta.
			$first_block = preg_replace( '#^(\s|<!--.*?-->)+#s', '', $body );
			if ( preg_match( '#^<(ul|ol)\b#i', $first_block ) ) {
				continue;
			}
			$paras = Text::paragraphs( $body );
			$count = $paras ? Text::word_count( $paras[0] ) : 0;
			if ( $count < self::ANSWER_MIN_WORDS ) {
				$missing[] = $heading;
			} elseif ( $count > self::ANSWER_MAX_WORDS ) {
				$long[] = $heading;
			}
		}
		if ( 0 === $total ) {
			return null;
		}
		$label = __( 'Respostas diretas', 'seo-legibilidade' );
		if ( ! $long && ! $missing ) {
			return new Result( 'direct_answers', $label, Result::GOOD, __( 'Cada pergunta é respondida logo no primeiro parágrafo, de forma objetiva. Formato ideal para trechos em destaque e AI Overviews.', 'seo-legibilidade' ) );
		}
		$problems = array_merge( $long, $missing );
		/* translators: %s: subtítulos. */
		return new Result( 'direct_answers', $label, Result::OK, sprintf( __( 'Responda logo abaixo de cada pergunta em um parágrafo de 15 a 60 palavras (ou com uma lista) e detalhe depois. Ajuste: "%s".', 'seo-legibilidade' ), implode( '", "', $problems ) ) );
	}
}
