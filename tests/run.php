<?php
/**
 * Testes das análises, sem dependências: `php tests/run.php`.
 *
 * @package SeoLegibilidade
 */

define( 'ABSPATH', __DIR__ . '/' );

function __( $text ) {
	return $text;
}
function _n( $single, $plural, $n ) {
	return 1 === $n ? $single : $plural;
}

require __DIR__ . '/../includes/analysis/class-text.php';
require __DIR__ . '/../includes/analysis/class-language.php';
require __DIR__ . '/../includes/analysis/class-result.php';
require __DIR__ . '/../includes/analysis/class-readability-analyzer.php';
require __DIR__ . '/../includes/analysis/class-seo-analyzer.php';
require __DIR__ . '/../includes/analysis/class-quality-analyzer.php';
require __DIR__ . '/../includes/class-autofix.php';

use SeoLegibilidade\Analysis\Text;
use SeoLegibilidade\Analysis\Readability_Analyzer;
use SeoLegibilidade\Analysis\Seo_Analyzer;
use SeoLegibilidade\Analysis\Quality_Analyzer;
use SeoLegibilidade\Autofix;

$failures = 0;
$total    = 0;

function check( $name, $cond ) {
	global $failures, $total;
	++$total;
	if ( $cond ) {
		echo "  ok   $name\n";
	} else {
		++$failures;
		echo "  FAIL $name\n";
	}
}

function find( array $analysis, $id ) {
	foreach ( $analysis['results'] as $r ) {
		if ( $r['id'] === $id ) {
			return $r;
		}
	}
	return null;
}

echo "Texto\n";
check( 'remove tags e shortcodes', 'Olá mundo' === Text::strip( '<p>Olá [gallery id="1"] <strong>mundo</strong></p>' ) );
check( 'conta palavras com acento', 4 === Text::word_count( 'Ação rápida é útil' ) );
check( 'divide frases', 3 === count( Text::sentences( 'Primeira frase. Segunda frase! Terceira?' ) ) );
check( 'não divide em abreviação', 1 === count( Text::sentences( 'O Dr. Silva chegou cedo.' ) ) );
check( 'sílabas: casa = 2', 2 === Text::syllables( 'casa' ) );
check( 'sílabas: coração = 3', 3 === Text::syllables( 'coração' ) );
check( 'sílabas: saúde = 3', 3 === Text::syllables( 'saúde' ) );
check( 'sílabas: queijo = 2', 2 === Text::syllables( 'queijo' ) );
check( 'sílabas: computador = 4', 4 === Text::syllables( 'computador' ) );
check( 'frase-chave ignora acentos e plural', 2 === Text::count_keyphrase( 'Os cafés especiais e o café especial.', 'café especial', array() ) );
check( 'frase-chave tolera palavra vazia no meio', Text::contains_keyphrase( 'Como comprar uma casa barata', 'comprar casa', array( 'uma' ) ) );
check( 'frase-chave não casa dentro de outra palavra', ! Text::contains_keyphrase( 'cascata', 'casa' ) );
check( 'truncate respeita palavras', 'Um texto bastante' === Text::truncate( 'Um texto bastante comprido', 20 ) );
check( 'truncate não corrompe letras acentuadas (Ó)', 'CASA DA AVÓ' === Text::truncate( 'CASA DA AVÓ — receitas', 13 ) );
check( 'largura em pixels cresce com texto', Text::pixel_width( 'WWWW' ) > Text::pixel_width( 'iiii' ) );
check( 'extrai alt de imagens', array( 'src' => 'a.jpg', 'alt' => 'Gato' ) === Text::images( '<img src="a.jpg" alt="Gato">' )[0] );
check( 'img sem alt retorna null', null === Text::images( '<img src="a.jpg">' )[0]['alt'] );

echo "Legibilidade\n";
$r = new Readability_Analyzer( 'pt' );
check( 'voz passiva: "O bolo foi feito pela avó."', $r->is_passive( 'O bolo foi feito pela avó.' ) );
check( 'voz passiva: "A lei foi rapidamente aprovada."', $r->is_passive( 'A lei foi rapidamente aprovada.' ) );
check( 'voz ativa: "A avó fez o bolo."', ! $r->is_passive( 'A avó fez o bolo.' ) );
check( 'não-passiva: "O resultado é importante."', ! $r->is_passive( 'O resultado é importante.' ) );
check( 'transição: "Além disso, ..."', $r->has_transition( 'Além disso, o texto ficou bom.' ) );
check( 'sem transição', ! $r->has_transition( 'O gato dorme no sofá.' ) );

$easy = str_repeat( '<p>O sol nasce cedo. A casa fica clara. Além disso, o café está pronto. Por isso, todos acordam bem. A rua tem paz.</p>', 10 );
$a    = $r->analyze( $easy );
check( 'texto simples tem Flesch bom', 'good' === find( $a, 'flesch' )['rating'] );
check( 'texto simples tem frases curtas', 'good' === find( $a, 'sentence_length' )['rating'] );
check( 'texto simples tem transições suficientes', 'good' === find( $a, 'transition_words' )['rating'] );
check( 'nota de legibilidade boa', $a['score'] >= 71 );

$long_sentence = 'Esta é uma frase extremamente longa que foi escrita propositalmente com muitas palavras complicadas para que o analisador de legibilidade identifique corretamente que ela ultrapassa o limite recomendado de vinte palavras por frase. ';
$hard          = '<p>' . str_repeat( $long_sentence, 12 ) . '</p>';
$a             = $r->analyze( $hard );
check( 'frases longas detectadas', 'bad' === find( $a, 'sentence_length' )['rating'] );
check( 'parágrafo longo detectado', 'bad' === find( $a, 'paragraph_length' )['rating'] );
check( 'texto longo sem subtítulo', 'bad' === find( $a, 'subheadings' )['rating'] );
check( 'início repetido detectado', 'bad' === find( $a, 'consecutive_sentences' )['rating'] );
check( 'pouco texto avisa', 'text_presence' === $r->analyze( '<p>Curto.</p>' )['results'][0]['id'] );

echo "SEO\n";
$s       = new Seo_Analyzer( 'pt' );
$body    = '<p>O café especial é produzido com grãos selecionados. Neste guia você aprende a escolher.</p>'
	. '<h2>Como escolher café especial</h2>'
	. str_repeat( '<p>Observe a torra, o aroma e a origem dos grãos antes de comprar. Prefira pacotes com data recente e válvula. Além disso, moa na hora para preservar o sabor.</p>', 12 )
	. '<p>Veja também <a href="/blog/moedores">nossa análise de moedores</a> e o <a href="https://www.bsca.com.br">guia da BSCA</a>.</p>'
	. '<p>Mais uma dica sobre café especial: guarde em pote fechado.</p>'
	. '<img src="cafe.jpg" alt="Xícara de café especial">';
$a       = $s->analyze(
	array(
		'keyphrase'   => 'café especial',
		'title'       => 'Café especial: guia completo para escolher o melhor',
		'description' => 'Aprenda a escolher café especial: veja como avaliar torra, aroma e origem dos grãos e descubra dicas práticas para comprar e guardar bem.',
		'slug'        => 'cafe-especial',
		'content'     => $body,
		'site_url'    => 'https://exemplo.com.br',
	)
);
check( 'frase-chave no título (início)', 'good' === find( $a, 'keyphrase_title' )['rating'] );
check( 'frase-chave na descrição', 'good' === find( $a, 'keyphrase_description' )['rating'] );
check( 'frase-chave no slug', 'good' === find( $a, 'keyphrase_slug' )['rating'] );
check( 'frase-chave na introdução', 'good' === find( $a, 'keyphrase_intro' )['rating'] );
check( 'tamanho da descrição', 'good' === find( $a, 'description_length' )['rating'] );
check( 'largura do título', 'good' === find( $a, 'title_width' )['rating'] );
check( 'links internos e externos', 'good' === find( $a, 'links' )['rating'] );
check( 'imagens com alt', 'good' === find( $a, 'images' )['rating'] );
check( 'frase-chave no alt', 'good' === find( $a, 'keyphrase_alt' )['rating'] );
check( 'hierarquia correta', 'good' === find( $a, 'heading_structure' )['rating'] );
check( 'texto suficiente', 'good' === find( $a, 'text_length' )['rating'] );

$a = $s->analyze(
	array(
		'keyphrase' => 'café especial',
		'title'     => 'Um título extremamente longo que certamente vai ser cortado pelo Google nos resultados de busca',
		'slug'      => 'Café-de-Minas-para-o-Brasil-2',
		'content'   => '<h1>Título</h1><h4>Pulo</h4><p>Texto curto sem nada. <a href="/x">clique aqui</a></p><img src="a.jpg">',
		'site_url'  => 'https://exemplo.com.br',
	)
);
check( 'título longo avisa', 'ok' === find( $a, 'title_width' )['rating'] );
check( 'sem descrição', 'bad' === find( $a, 'description_length' )['rating'] );
check( 'H1 no conteúdo', 'bad' === find( $a, 'heading_structure' )['rating'] );
check( 'imagem sem alt', 'bad' === find( $a, 'images' )['rating'] );
check( 'link genérico', 'ok' === find( $a, 'anchor_text' )['rating'] );
check( 'slug com problemas', 'ok' === find( $a, 'slug_quality' )['rating'] );
check( 'texto curto', 'bad' === find( $a, 'text_length' )['rating'] );
check( 'nota de SEO ruim', $a['score'] < 71 );
check( 'sem frase-chave é ruim', 'bad' === find( $s->analyze( array( 'content' => $body ) ), 'keyphrase' )['rating'] );

$short = '<p>' . str_repeat( 'Palavra comum de texto. ', 30 ) . 'O café especial é bom. Prove café especial hoje. Café especial sempre.</p>';
$a     = $s->analyze( array( 'keyphrase' => 'café especial', 'content' => $short ) );
check( 'densidade: 3 ocorrências em ~100 palavras é aceitável', 'good' === find( $a, 'keyphrase_density' )['rating'] );
$a = $s->analyze( array( 'keyphrase' => 'café especial', 'content' => '<p>' . str_repeat( 'Café especial. ', 40 ) . str_repeat( 'Outra coisa aqui. ', 10 ) . '</p>' ) );
check( 'densidade: repetição excessiva é ruim', 'bad' === find( $a, 'keyphrase_density' )['rating'] );
$a = $s->analyze( array( 'description' => str_repeat( 'Descrição gerada. ', 8 ), 'description_is_auto' => true ) );
check( 'descrição automática conta como "pode melhorar"', 'ok' === find( $a, 'description_length' )['rating'] );

echo "Atualidade e confiança\n";
$q = new Quality_Analyzer( 'pt' );
check( 'pergunta com "?"', $q->is_question( 'Isso funciona?' ) );
check( 'pergunta por palavra interrogativa', $q->is_question( 'Como escolher um celular' ) );
check( 'afirmação não é pergunta', ! $q->is_question( 'Dicas para escolher' ) );
$a = $q->analyze( array( 'post_title' => 'Melhores celulares de 2024', 'current_year' => 2026, 'modified_days' => 800, 'author_bio' => false, 'content' => '<p>Atualmente os preços caíram.</p>' ) );
check( 'ano vencido no título', 'bad' === find( $a, 'outdated_year' )['rating'] && false !== strpos( find( $a, 'outdated_year' )['message'], '2024' ) );
check( 'conteúdo parado há mais de 2 anos', 'bad' === find( $a, 'last_updated' )['rating'] );
check( '"atualmente" sem data concreta', 'ok' === find( $a, 'relative_dates' )['rating'] );
check( 'autor sem biografia', 'ok' === find( $a, 'author_bio' )['rating'] );
$a = $q->analyze( array( 'post_title' => 'Melhores celulares de 2026', 'current_year' => 2026, 'modified_days' => 10, 'content' => '<p>Em 2026, atualmente, tudo mudou.</p>' ) );
check( 'ano atual no título é bom', 'good' === find( $a, 'outdated_year' )['rating'] );
check( 'atualizado há 10 dias', 'good' === find( $a, 'last_updated' )['rating'] );
check( 'tempo relativo com data concreta é bom', 'good' === find( $a, 'relative_dates' )['rating'] );
check( 'sem dados de autor, a verificação é omitida', null === find( $a, 'author_bio' ) );
$answers = '<h2>Quanto custa?</h2><p>' . str_repeat( 'palavra ', 30 ) . '</p><h2>Como instalar?</h2><ul><li>Passo um</li></ul><h2>Onde comprar?</h2><p>' . str_repeat( 'palavra ', 80 ) . '</p>';
$a = $q->analyze( array( 'content' => $answers ) );
check( 'resposta longa demais é apontada', 'ok' === find( $a, 'direct_answers' )['rating'] && false !== strpos( find( $a, 'direct_answers' )['message'], 'Onde comprar?' ) && false === strpos( find( $a, 'direct_answers' )['message'], 'Quanto custa?' ) );
check( 'subtítulos em pergunta reconhecidos', 'good' === find( $a, 'question_headings' )['rating'] );
$a = $q->analyze( array( 'site_url' => 'https://meu.com', 'content' => '<p>' . str_repeat( 'Texto sem números nem fontes aqui. ', 60 ) . '<a href="https://meu.com/x">interno</a></p>' ) );
check( 'sem fontes externas em texto longo', 'bad' === find( $a, 'sources' )['rating'] );
check( 'sem dados concretos', 'ok' === find( $a, 'data_points' )['rating'] );
check( 'sem experiência própria', 'ok' === find( $a, 'experience' )['rating'] );

echo "Correções automáticas\n";
$slug = Autofix::slug( 'Como escolher o melhor café especial para a sua casa', 'café especial', 'pt' );
check( "slug otimizado ($slug)", 'cafe-especial-escolher-melhor-casa' === $slug );
$desc = Autofix::description( $body, 'café especial', 'pt' );
check( 'descrição gerada entre 120 e 156', mb_strlen( $desc ) >= 100 && mb_strlen( $desc ) <= 156 );
check( 'descrição gerada contém a frase-chave', Text::contains_keyphrase( $desc, 'café especial' ) );
$title = Autofix::title( 'Guia definitivo para escolher café especial em casa sem errar nunca mais', 'Meu Blog de Café', '-', 'café especial' );
check( "título gerado cabe em 580px ($title)", Text::pixel_width( $title ) <= 580 );
check( 'título gerado mantém a frase-chave', Text::contains_keyphrase( $title, 'café especial' ) );
$title = Autofix::title( 'Como escolher café especial para a sua casa', 'Blog', '-', 'café especial' );
check( "título gerado começa pela frase-chave ($title)", 'Café especial: como escolher para a sua casa - Blog' === $title );
check( 'título que já começa pela frase-chave é mantido', 'Café especial em casa - Blog' === Autofix::title( 'Café especial em casa', 'Blog', '-', 'café especial' ) );
check( 'atualiza só o ano mais recente', 'Melhores celulares de 2026 (desde 2019)' === Autofix::update_years( 'Melhores celulares de 2024 (desde 2019)', 2026 ) );
check( 'mantém anos futuros e números longos', 'Plano 2030, código 120245' === Autofix::update_years( 'Plano 2030, código 120245', 2026 ) );
check( 'alt a partir do nome do arquivo', 'Xicara de cafe especial' === Autofix::alt_from_filename( 'https://x.com/wp-content/uploads/2026/10/xicara-de-cafe_especial-1024x768.jpg' ) );

echo "\n$total testes, $failures falha(s)\n";
exit( $failures ? 1 : 0 );
