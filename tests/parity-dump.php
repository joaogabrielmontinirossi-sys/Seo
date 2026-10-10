<?php
/**
 * Saída do motor PHP para os casos de tests/fixtures/parity.json (usado por tests/parity.js).
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
require __DIR__ . '/../includes/class-autofix.php';

use SeoLegibilidade\Analysis\Readability_Analyzer;
use SeoLegibilidade\Analysis\Seo_Analyzer;
use SeoLegibilidade\Autofix;

$cases = json_decode( file_get_contents( __DIR__ . '/fixtures/parity.json' ), true );
$out   = array();
foreach ( $cases as $c ) {
	$c['content'] = parity_content( $c['content'] );
	$auto         = '' === $c['description'] ? Autofix::description( $c['content'], $c['keyphrase'], $c['lang'] ) : '';
	$out[]        = array(
		'seo'         => ( new Seo_Analyzer( $c['lang'] ) )->analyze(
			array(
				'keyphrase'           => $c['keyphrase'],
				'title'               => $c['title'],
				'description'         => '' !== $auto ? $auto : $c['description'],
				'description_is_auto' => '' !== $auto,
				'slug'                => $c['slug'],
				'content'             => $c['content'],
				'site_url'            => $c['site_url'],
			)
		),
		'readability' => ( new Readability_Analyzer( $c['lang'] ) )->analyze( $c['content'] ),
		'fix'         => array(
			'title'       => Autofix::title( $c['post_title'], 'Meu Site', '-', $c['keyphrase'] ),
			'description' => Autofix::description( $c['content'], $c['keyphrase'], $c['lang'] ),
			'slug'        => Autofix::slug( $c['post_title'], $c['keyphrase'], $c['lang'] ),
			'alt'         => Autofix::alt_from_filename( 'https://x.com/wp-content/uploads/' . $c['slug'] . '-800x600.jpg' ),
		),
	);
}
echo json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION );

function parity_content( $content ) {
	if ( 'REPEAT_PT' !== $content ) {
		return $content;
	}
	$p = '<p>Uma loja virtual precisa de uma boa plataforma e de um plano claro. Uma loja virtual também exige atenção ao atendimento. Uma loja virtual sem estoque organizado perde vendas. O e-commerce cresce todos os anos no Brasil, e quem planeja bem colhe resultados consistentes ao longo do tempo.</p>';
	return str_repeat( $p, 9 );
}
