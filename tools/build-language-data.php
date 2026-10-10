<?php
/**
 * Gera web/assets/language-data.js a partir das listas em PHP (fonte única).
 * Uso: php tools/build-language-data.php
 *
 * @package SeoLegibilidade
 */

define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../includes/analysis/class-text.php';
require __DIR__ . '/../includes/analysis/class-language.php';

use SeoLegibilidade\Analysis\Language;
use SeoLegibilidade\Analysis\Text;

$data = array( 'abbreviations' => Text::ABBREVIATIONS );
foreach ( array( 'pt', 'en' ) as $lang ) {
	$data[ $lang ] = array(
		'stopwords'             => Language::stopwords( $lang ),
		'transitionWords'       => Language::transition_words( $lang ),
		'passiveAuxiliaries'    => Language::passive_auxiliaries( $lang ),
		'irregularParticiples'  => Language::irregular_participles( $lang ),
		'participleExceptions'  => Language::participle_exceptions( $lang ),
		'genericAnchors'        => Language::generic_anchors( $lang ),
	);
}

$js = "/* Gerado por tools/build-language-data.php — não edite à mão. */\n"
	. "( function ( root ) {\n\tvar data = " . json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ";\n"
	. "\tif ( typeof module === 'object' && module.exports ) {\n\t\tmodule.exports = data;\n\t} else {\n\t\troot.SEOL_LANG = data;\n\t}\n} )( this );\n";

file_put_contents( __DIR__ . '/../web/assets/language-data.js', $js );
echo "web/assets/language-data.js atualizado\n";
