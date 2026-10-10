<?php
/**
 * Análise de SEO on-page.
 *
 * Baseada nas diretrizes do Google Search Central (títulos, snippets, links,
 * imagens, URLs) e nas faixas consolidadas pelo mercado para frase-chave.
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade\Analysis;

defined( 'ABSPATH' ) || exit;

class Seo_Analyzer {

	const TITLE_MIN_PX       = 200;
	const TITLE_MAX_PX       = 580;
	const DESC_MIN_CHARS     = 120;
	const DESC_MAX_CHARS     = 156;
	const MIN_WORDS          = 300;
	const DENSITY_MIN        = 0.5;
	const DENSITY_MAX        = 3.0;
	const SLUG_MAX_CHARS     = 75;

	/** @var string */
	private $lang;
	/** @var string[] */
	private $stopwords;

	public function __construct( $lang = 'pt' ) {
		$this->lang      = 'en' === $lang ? 'en' : 'pt';
		$this->stopwords = Language::stopwords( $this->lang );
	}

	/**
	 * @param array $data {
	 *     @type string $keyphrase      Frase-chave de foco.
	 *     @type string $title          Título SEO já com variáveis substituídas.
	 *     @type string $description    Meta description.
	 *     @type string $slug           Slug do post.
	 *     @type string $content        HTML do conteúdo.
	 *     @type string $site_url       URL do site, para detectar links internos.
	 *     @type int    $keyphrase_uses Quantos outros posts já usam esta frase-chave.
	 *     @type int    $min_words      Mínimo de palavras (padrão 300).
	 *     @type bool   $description_is_auto A descrição foi gerada automaticamente.
	 * }
	 * @return array{score:int, rating:string, results:array}
	 */
	public function analyze( array $data ) {
		$data = array_merge(
			array(
				'keyphrase'      => '',
				'title'          => '',
				'description'    => '',
				'slug'           => '',
				'content'        => '',
				'site_url'       => '',
				'keyphrase_uses' => 0,
				'min_words'      => self::MIN_WORDS,
			),
			$data
		);

		$kp      = trim( (string) $data['keyphrase'] );
		$html    = (string) $data['content'];
		$text    = Text::strip( $html );
		$results = array();

		$results[] = $this->keyphrase_set( $kp );
		if ( '' !== $kp ) {
			$results[] = $this->keyphrase_length( $kp );
			$results[] = $this->keyphrase_in_title( $kp, $data['title'] );
			$results[] = $this->keyphrase_in_description( $kp, $data['description'] );
			$results[] = $this->keyphrase_in_slug( $kp, $data['slug'] );
			$results[] = $this->keyphrase_in_intro( $kp, $html );
			$results[] = $this->keyphrase_density( $kp, $text );
			$results[] = $this->keyphrase_in_subheadings( $kp, $html );
			$results[] = $this->keyphrase_in_alt( $kp, $html );
			$results[] = $this->previously_used( (int) $data['keyphrase_uses'] );
		}
		$results[] = $this->title_width( $data['title'] );
		$results[] = $this->description_length( $data['description'], ! empty( $data['description_is_auto'] ) );
		$results[] = $this->text_length( $text, (int) $data['min_words'] );
		$results[] = $this->slug_quality( $data['slug'] );
		$results[] = $this->images( $html );
		$results[] = $this->links( $html, $data['site_url'] );
		$results[] = $this->anchor_texts( $html );
		$results[] = $this->heading_structure( $html );

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

	private function keyphrase_set( $kp ) {
		$label = __( 'Frase-chave de foco', 'seo-legibilidade' );
		if ( '' === $kp ) {
			return new Result( 'keyphrase', $label, Result::BAD, __( 'Defina a frase-chave de foco: o termo que as pessoas buscariam para encontrar este conteúdo.', 'seo-legibilidade' ) );
		}
		return new Result( 'keyphrase', $label, Result::GOOD, __( 'Frase-chave definida.', 'seo-legibilidade' ) );
	}

	private function content_words( $kp ) {
		$sw = $this->stopwords;
		return array_values(
			array_filter(
				Text::words( Text::normalize( $kp ) ),
				static function ( $w ) use ( $sw ) {
					return ! in_array( $w, $sw, true );
				}
			)
		);
	}

	private function keyphrase_length( $kp ) {
		$n     = count( $this->content_words( $kp ) );
		$label = __( 'Tamanho da frase-chave', 'seo-legibilidade' );
		if ( $n >= 1 && $n <= 4 ) {
			return new Result( 'keyphrase_length', $label, Result::GOOD, __( 'A frase-chave tem um bom tamanho.', 'seo-legibilidade' ) );
		}
		if ( 0 === $n ) {
			return new Result( 'keyphrase_length', $label, Result::BAD, __( 'A frase-chave só contém palavras vazias (artigos, preposições). Use termos com significado.', 'seo-legibilidade' ) );
		}
		/* translators: %d: número de palavras. */
		$msg = sprintf( __( 'A frase-chave tem %d palavras de conteúdo; o ideal é até 4. Use um termo mais curto e específico.', 'seo-legibilidade' ), $n );
		return new Result( 'keyphrase_length', $label, $n <= 8 ? Result::OK : Result::BAD, $msg );
	}

	private function keyphrase_in_title( $kp, $title ) {
		$label = __( 'Frase-chave no título SEO', 'seo-legibilidade' );
		$norm  = Text::normalize( $title );
		$pat   = Text::keyphrase_pattern( $kp, $this->stopwords );
		if ( $pat && preg_match( $pat, $norm, $m, PREG_OFFSET_CAPTURE ) ) {
			// Posição em caracteres (a correspondência é sobre o texto normalizado).
			$pos = mb_strlen( substr( $norm, 0, $m[0][1] ) );
			if ( $pos <= 10 ) {
				return new Result( 'keyphrase_title', $label, Result::GOOD, __( 'A frase-chave aparece no início do título SEO. Ótimo!', 'seo-legibilidade' ) );
			}
			return new Result( 'keyphrase_title', $label, Result::OK, __( 'O título SEO contém a frase-chave, mas não no início. Mova-a para o começo do título.', 'seo-legibilidade' ), 'title' );
		}
		return new Result( 'keyphrase_title', $label, Result::BAD, __( 'O título SEO não contém a frase-chave. Inclua-a, de preferência no início.', 'seo-legibilidade' ), 'title' );
	}

	private function keyphrase_in_description( $kp, $desc ) {
		$label = __( 'Frase-chave na meta descrição', 'seo-legibilidade' );
		if ( '' === trim( (string) $desc ) ) {
			return new Result( 'keyphrase_description', $label, Result::BAD, __( 'Não há meta descrição para conter a frase-chave.', 'seo-legibilidade' ), 'description' );
		}
		$count = Text::count_keyphrase( $desc, $kp, $this->stopwords );
		if ( 1 === $count || 2 === $count ) {
			return new Result( 'keyphrase_description', $label, Result::GOOD, __( 'A frase-chave aparece na meta descrição. Ótimo!', 'seo-legibilidade' ) );
		}
		if ( $count > 2 ) {
			return new Result( 'keyphrase_description', $label, Result::BAD, __( 'A frase-chave aparece mais de 2 vezes na meta descrição. Evite repetições excessivas.', 'seo-legibilidade' ) );
		}
		return new Result( 'keyphrase_description', $label, Result::BAD, __( 'A meta descrição não contém a frase-chave. O Google destaca em negrito os termos buscados.', 'seo-legibilidade' ), 'description' );
	}

	private function keyphrase_in_slug( $kp, $slug ) {
		$label = __( 'Frase-chave na URL', 'seo-legibilidade' );
		$slug  = str_replace( '-', ' ', urldecode( (string) $slug ) );
		$words = $this->content_words( $kp );
		if ( ! $words ) {
			return null;
		}
		$found = 0;
		foreach ( $words as $w ) {
			if ( Text::contains_keyphrase( $slug, $w ) ) {
				++$found;
			}
		}
		if ( $found === count( $words ) ) {
			return new Result( 'keyphrase_slug', $label, Result::GOOD, __( 'A URL (slug) contém a frase-chave. Ótimo!', 'seo-legibilidade' ) );
		}
		if ( $found > 0 ) {
			return new Result( 'keyphrase_slug', $label, Result::OK, __( 'A URL contém parte da frase-chave. Inclua todas as palavras importantes.', 'seo-legibilidade' ), 'slug' );
		}
		return new Result( 'keyphrase_slug', $label, Result::BAD, __( 'A URL (slug) não contém a frase-chave.', 'seo-legibilidade' ), 'slug' );
	}

	private function keyphrase_in_intro( $kp, $html ) {
		$label = __( 'Frase-chave na introdução', 'seo-legibilidade' );
		$paras = Text::paragraphs( $html );
		if ( ! $paras ) {
			return new Result( 'keyphrase_intro', $label, Result::BAD, __( 'Escreva uma introdução que contenha a frase-chave.', 'seo-legibilidade' ) );
		}
		$intro = $paras[0];
		if ( Text::contains_keyphrase( $intro, $kp, $this->stopwords ) ) {
			$first = Text::sentences( $intro );
			if ( $first && Text::contains_keyphrase( $first[0], $kp, $this->stopwords ) ) {
				return new Result( 'keyphrase_intro', $label, Result::GOOD, __( 'A frase-chave aparece logo na primeira frase. Ótimo!', 'seo-legibilidade' ) );
			}
			return new Result( 'keyphrase_intro', $label, Result::GOOD, __( 'A frase-chave aparece no primeiro parágrafo.', 'seo-legibilidade' ) );
		}
		// Palavras separadas na mesma frase contam parcialmente.
		$words = $this->content_words( $kp );
		$found = 0;
		foreach ( $words as $w ) {
			if ( Text::contains_keyphrase( $intro, $w ) ) {
				++$found;
			}
		}
		if ( $words && $found === count( $words ) ) {
			return new Result( 'keyphrase_intro', $label, Result::OK, __( 'As palavras da frase-chave aparecem no primeiro parágrafo, mas separadas. Use a frase completa.', 'seo-legibilidade' ) );
		}
		return new Result( 'keyphrase_intro', $label, Result::BAD, __( 'A frase-chave não aparece no primeiro parágrafo. Deixe claro logo no início do que o texto trata.', 'seo-legibilidade' ) );
	}

	private function keyphrase_density( $kp, $text ) {
		$label = __( 'Densidade da frase-chave', 'seo-legibilidade' );
		$total = Text::word_count( $text );
		if ( $total < 100 ) {
			return null;
		}
		// Densidade = ocorrências da frase completa / total de palavras.
		$count   = Text::count_keyphrase( $text, $kp, $this->stopwords );
		$density = round( $count / $total * 100, 2 );
		$min     = max( 1, (int) ceil( $total * self::DENSITY_MIN / 100 ) );
		$max     = max( 2, (int) floor( $total * self::DENSITY_MAX / 100 ) );
		$d       = rtrim( rtrim( number_format( $density, 2, ',', '' ), '0' ), ',' );

		if ( $density < self::DENSITY_MIN ) {
			/* translators: 1: vezes encontrada, 2: densidade, 3: mínimo recomendado. */
			$msg = sprintf( __( 'A frase-chave aparece %1$d vez(es) (%2$s%%). Para este tamanho de texto, use-a pelo menos %3$d vezes, de forma natural.', 'seo-legibilidade' ), $count, $d, $min );
			return new Result( 'keyphrase_density', $label, 0 === $count ? Result::BAD : Result::OK, $msg );
		}
		if ( $density > self::DENSITY_MAX ) {
			/* translators: 1: vezes encontrada, 2: densidade, 3: máximo recomendado. */
			$msg = sprintf( __( 'A frase-chave aparece %1$d vezes (%2$s%%), o que pode parecer spam de palavras-chave. Use no máximo %3$d vezes.', 'seo-legibilidade' ), $count, $d, $max );
			return new Result( 'keyphrase_density', $label, Result::BAD, $msg );
		}
		/* translators: 1: vezes encontrada, 2: densidade. */
		return new Result( 'keyphrase_density', $label, Result::GOOD, sprintf( __( 'A frase-chave aparece %1$d vezes (%2$s%%). Ótimo!', 'seo-legibilidade' ), $count, $d ) );
	}

	private function keyphrase_in_subheadings( $kp, $html ) {
		$subs = array_filter(
			Text::headings( $html ),
			static function ( $h ) {
				return in_array( $h['level'], array( 2, 3 ), true );
			}
		);
		if ( ! $subs ) {
			return null;
		}
		$with = 0;
		foreach ( $subs as $h ) {
			if ( Text::contains_keyphrase( $h['text'], $kp, $this->stopwords ) ) {
				++$with;
			}
		}
		$pct   = $with / count( $subs ) * 100;
		$label = __( 'Frase-chave nos subtítulos', 'seo-legibilidade' );
		if ( 0 === $with ) {
			return new Result( 'keyphrase_subheadings', $label, Result::OK, __( 'Nenhum subtítulo H2/H3 contém a frase-chave (ou sinônimos). Inclua-a em alguns deles.', 'seo-legibilidade' ) );
		}
		if ( $pct > 75 && count( $subs ) > 1 ) {
			return new Result( 'keyphrase_subheadings', $label, Result::OK, __( 'A frase-chave está em quase todos os subtítulos. Varie com sinônimos para soar natural.', 'seo-legibilidade' ) );
		}
		return new Result( 'keyphrase_subheadings', $label, Result::GOOD, __( 'A frase-chave aparece nos subtítulos em boa proporção.', 'seo-legibilidade' ) );
	}

	private function keyphrase_in_alt( $kp, $html ) {
		$images = Text::images( $html );
		if ( ! $images ) {
			return null;
		}
		$with = 0;
		foreach ( $images as $img ) {
			if ( null !== $img['alt'] && Text::contains_keyphrase( $img['alt'], $kp, $this->stopwords ) ) {
				++$with;
			}
		}
		$label = __( 'Frase-chave no texto alternativo', 'seo-legibilidade' );
		if ( $with > 0 ) {
			return new Result( 'keyphrase_alt', $label, Result::GOOD, __( 'Ao menos uma imagem tem a frase-chave no texto alternativo (alt).', 'seo-legibilidade' ) );
		}
		return new Result( 'keyphrase_alt', $label, Result::OK, __( 'Nenhuma imagem tem a frase-chave no texto alternativo. Descreva ao menos uma imagem relevante usando o termo.', 'seo-legibilidade' ) );
	}

	private function previously_used( $uses ) {
		$label = __( 'Frase-chave já usada', 'seo-legibilidade' );
		if ( $uses <= 0 ) {
			return new Result( 'keyphrase_unique', $label, Result::GOOD, __( 'Você ainda não usou esta frase-chave em outro conteúdo. Ótimo!', 'seo-legibilidade' ) );
		}
		/* translators: %d: quantidade de posts. */
		$msg = sprintf( _n( 'Esta frase-chave já é o foco de %d outro conteúdo. Evite canibalização: escolha outro termo ou consolide os conteúdos.', 'Esta frase-chave já é o foco de %d outros conteúdos. Evite canibalização: escolha outro termo ou consolide os conteúdos.', $uses, 'seo-legibilidade' ), $uses );
		return new Result( 'keyphrase_unique', $label, 1 === $uses ? Result::OK : Result::BAD, $msg );
	}

	private function title_width( $title ) {
		$label = __( 'Largura do título SEO', 'seo-legibilidade' );
		$title = trim( (string) $title );
		if ( '' === $title ) {
			return new Result( 'title_width', $label, Result::BAD, __( 'Defina um título SEO.', 'seo-legibilidade' ), 'title' );
		}
		$px    = Text::pixel_width( $title );
		$chars = mb_strlen( $title );
		if ( $px > self::TITLE_MAX_PX ) {
			/* translators: 1: caracteres, 2: pixels. */
			return new Result( 'title_width', $label, Result::OK, sprintf( __( 'O título tem %1$d caracteres (~%2$dpx) e será cortado nos resultados do Google (limite ~580px, cerca de 60 caracteres).', 'seo-legibilidade' ), $chars, $px ), 'title' );
		}
		if ( $px < self::TITLE_MIN_PX ) {
			/* translators: 1: caracteres, 2: pixels. */
			return new Result( 'title_width', $label, Result::OK, sprintf( __( 'O título tem só %1$d caracteres (~%2$dpx). Aproveite o espaço disponível (30–60 caracteres).', 'seo-legibilidade' ), $chars, $px ), 'title' );
		}
		/* translators: 1: caracteres, 2: pixels. */
		return new Result( 'title_width', $label, Result::GOOD, sprintf( __( 'O título tem %1$d caracteres (~%2$dpx). Tamanho ideal!', 'seo-legibilidade' ), $chars, $px ) );
	}

	private function description_length( $desc, $is_auto = false ) {
		$label = __( 'Tamanho da meta descrição', 'seo-legibilidade' );
		$len   = mb_strlen( trim( (string) $desc ) );
		if ( $is_auto && $len > 0 ) {
			return new Result( 'description_length', $label, Result::OK, __( 'Sem meta descrição própria: será usada uma gerada a partir do texto. Escreva uma personalizada para atrair mais cliques.', 'seo-legibilidade' ), 'description' );
		}
		if ( 0 === $len ) {
			return new Result( 'description_length', $label, Result::BAD, __( 'Não há meta descrição. Escreva um resumo atrativo de 120 a 156 caracteres.', 'seo-legibilidade' ), 'description' );
		}
		if ( $len < self::DESC_MIN_CHARS ) {
			/* translators: %d: caracteres. */
			return new Result( 'description_length', $label, Result::OK, sprintf( __( 'A meta descrição tem %d caracteres; o ideal é entre 120 e 156.', 'seo-legibilidade' ), $len ), 'description' );
		}
		if ( $len > self::DESC_MAX_CHARS ) {
			/* translators: %d: caracteres. */
			return new Result( 'description_length', $label, Result::OK, sprintf( __( 'A meta descrição tem %d caracteres e pode ser cortada; o ideal é até 156.', 'seo-legibilidade' ), $len ), 'description' );
		}
		/* translators: %d: caracteres. */
		return new Result( 'description_length', $label, Result::GOOD, sprintf( __( 'A meta descrição tem %d caracteres. Tamanho ideal!', 'seo-legibilidade' ), $len ) );
	}

	private function text_length( $text, $min ) {
		$n     = Text::word_count( $text );
		$label = __( 'Tamanho do texto', 'seo-legibilidade' );
		if ( $n >= $min ) {
			/* translators: %d: palavras. */
			return new Result( 'text_length', $label, Result::GOOD, sprintf( __( 'O texto tem %d palavras. Bom volume de conteúdo!', 'seo-legibilidade' ), $n ) );
		}
		/* translators: 1: palavras, 2: mínimo. */
		$msg = sprintf( __( 'O texto tem %1$d palavras; o recomendado é pelo menos %2$d para cobrir bem o assunto.', 'seo-legibilidade' ), $n, $min );
		return new Result( 'text_length', $label, $n >= $min * 0.8 ? Result::OK : Result::BAD, $msg );
	}

	private function slug_quality( $slug ) {
		$slug  = urldecode( (string) $slug );
		$label = __( 'Qualidade da URL', 'seo-legibilidade' );
		if ( '' === $slug ) {
			return null;
		}
		$issues = array();
		if ( mb_strlen( $slug ) > self::SLUG_MAX_CHARS ) {
			$issues[] = __( 'é longa demais (máx. 75 caracteres)', 'seo-legibilidade' );
		}
		if ( preg_match( '/[^a-z0-9\-]/', $slug ) ) {
			$issues[] = __( 'contém acentos, maiúsculas ou caracteres especiais', 'seo-legibilidade' );
		}
		$parts = explode( '-', $slug );
		$stop  = array_intersect( $parts, $this->stopwords );
		if ( count( $stop ) > 1 || ( count( $parts ) > 3 && $stop ) ) {
			$issues[] = __( 'contém palavras vazias (de, para, o, a…)', 'seo-legibilidade' );
		}
		if ( preg_match( '/^\d+$/', $slug ) || preg_match( '/-[2-9]$/', $slug ) ) {
			$issues[] = __( 'termina em número (possível duplicata)', 'seo-legibilidade' );
		}
		if ( ! $issues ) {
			return new Result( 'slug_quality', $label, Result::GOOD, __( 'A URL é curta, legível e descritiva.', 'seo-legibilidade' ) );
		}
		/* translators: %s: lista de problemas. */
		return new Result( 'slug_quality', $label, Result::OK, sprintf( __( 'A URL %s.', 'seo-legibilidade' ), implode( '; ', $issues ) ), 'slug' );
	}

	private function images( $html ) {
		$images = Text::images( $html );
		$label  = __( 'Imagens e texto alternativo', 'seo-legibilidade' );
		if ( ! $images ) {
			return new Result( 'images', $label, Result::OK, __( 'Não há imagens no conteúdo. Imagens relevantes melhoram o engajamento e aparecem no Google Imagens.', 'seo-legibilidade' ) );
		}
		$missing = 0;
		foreach ( $images as $img ) {
			if ( null === $img['alt'] || '' === trim( $img['alt'] ) ) {
				++$missing;
			}
		}
		if ( $missing ) {
			/* translators: %d: quantidade. */
			return new Result( 'images', $label, Result::BAD, sprintf( _n( '%d imagem está sem texto alternativo (alt). Descreva-a para acessibilidade e SEO.', '%d imagens estão sem texto alternativo (alt). Descreva-as para acessibilidade e SEO.', $missing, 'seo-legibilidade' ), $missing ), 'alt' );
		}
		return new Result( 'images', $label, Result::GOOD, __( 'Todas as imagens têm texto alternativo. Ótimo!', 'seo-legibilidade' ) );
	}

	private function is_internal( $href, $site_url ) {
		if ( '' === $href || '#' === $href[0] ) {
			return null; // Âncora na própria página: não conta.
		}
		if ( preg_match( '#^(mailto|tel|javascript):#i', $href ) ) {
			return null;
		}
		if ( '/' === $href[0] && ( ! isset( $href[1] ) || '/' !== $href[1] ) ) {
			return true;
		}
		if ( ! preg_match( '#^(https?:)?//#i', $href ) ) {
			return true; // Relativo.
		}
		$host = strtolower( (string) parse_url( ( 0 === strpos( $href, '//' ) ? 'https:' : '' ) . $href, PHP_URL_HOST ) );
		$site = strtolower( (string) parse_url( $site_url, PHP_URL_HOST ) );
		return '' !== $site && preg_replace( '/^www\./', '', $host ) === preg_replace( '/^www\./', '', $site );
	}

	private function links( $html, $site_url ) {
		$internal = 0;
		$external = 0;
		foreach ( Text::links( $html ) as $l ) {
			$is = $this->is_internal( $l['href'], $site_url );
			if ( true === $is ) {
				++$internal;
			} elseif ( false === $is ) {
				++$external;
			}
		}
		$label = __( 'Links internos e externos', 'seo-legibilidade' );
		$tips  = array();
		if ( 0 === $internal ) {
			$tips[] = __( 'adicione links internos para outros conteúdos do site', 'seo-legibilidade' );
		}
		if ( 0 === $external ) {
			$tips[] = __( 'cite ao menos uma fonte externa confiável', 'seo-legibilidade' );
		}
		if ( ! $tips ) {
			/* translators: 1: links internos, 2: externos. */
			return new Result( 'links', $label, Result::GOOD, sprintf( __( '%1$d link(s) interno(s) e %2$d externo(s). Ótimo!', 'seo-legibilidade' ), $internal, $external ) );
		}
		$msg = ucfirst( implode( '; ', $tips ) ) . '.';
		return new Result( 'links', $label, 0 === $internal ? Result::BAD : Result::OK, $msg );
	}

	private function anchor_texts( $html ) {
		$generic = Language::generic_anchors( $this->lang );
		$bad     = array();
		$links   = Text::links( $html );
		if ( ! $links ) {
			return null;
		}
		foreach ( $links as $l ) {
			$t = mb_strtolower( trim( $l['text'], " \t\n.:!" ), 'UTF-8' );
			if ( '' === $t || in_array( $t, $generic, true ) ) {
				$bad[] = '' === $t ? __( '(vazio)', 'seo-legibilidade' ) : $l['text'];
			}
		}
		$label = __( 'Texto dos links', 'seo-legibilidade' );
		if ( $bad ) {
			/* translators: %s: textos encontrados. */
			return new Result( 'anchor_text', $label, Result::OK, sprintf( __( 'Evite textos de link genéricos ou vazios (%s). Descreva o destino do link.', 'seo-legibilidade' ), implode( ', ', array_unique( $bad ) ) ) );
		}
		return new Result( 'anchor_text', $label, Result::GOOD, __( 'Os links têm textos descritivos.', 'seo-legibilidade' ) );
	}

	private function heading_structure( $html ) {
		$headings = Text::headings( $html );
		$label    = __( 'Hierarquia de títulos', 'seo-legibilidade' );
		$issues   = array();
		$h1       = 0;
		$prev     = 1; // O título do post é o H1 da página.
		foreach ( $headings as $h ) {
			if ( 1 === $h['level'] ) {
				++$h1;
			}
			if ( $h['level'] > $prev + 1 ) {
				/* translators: 1: nível anterior, 2: nível atual. */
				$issues[] = sprintf( __( 'salto de H%1$d para H%2$d', 'seo-legibilidade' ), $prev, $h['level'] );
			}
			if ( '' === $h['text'] ) {
				$issues[] = __( 'título vazio', 'seo-legibilidade' );
			}
			$prev = $h['level'];
		}
		if ( $h1 ) {
			return new Result( 'heading_structure', $label, Result::BAD, __( 'O conteúdo contém H1. O título do post já é o H1 da página; use H2 e H3 no texto.', 'seo-legibilidade' ) );
		}
		if ( $issues ) {
			/* translators: %s: problemas. */
			return new Result( 'heading_structure', $label, Result::OK, sprintf( __( 'A hierarquia de títulos tem problemas: %s. Não pule níveis.', 'seo-legibilidade' ), implode( ', ', array_unique( $issues ) ) ) );
		}
		if ( ! $headings ) {
			return null;
		}
		return new Result( 'heading_structure', $label, Result::GOOD, __( 'A hierarquia de títulos está correta.', 'seo-legibilidade' ) );
	}
}
