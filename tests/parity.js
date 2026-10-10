/**
 * Garante que o motor JavaScript (web/assets/analyzer.js) produz exatamente o mesmo
 * resultado que o motor PHP. Uso: node tests/parity.js
 */
'use strict';

const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );
const assert = require( 'assert' );

const root = path.join( __dirname, '..' );
const dataFile = path.join( root, 'web/assets/language-data.js' );

// 1. language-data.js precisa estar sincronizado com o PHP.
const before = fs.readFileSync( dataFile, 'utf8' );
execFileSync( 'php', [ path.join( root, 'tools/build-language-data.php' ) ] );
const after = fs.readFileSync( dataFile, 'utf8' );
let failures = 0;
if ( before !== after ) {
	console.log( '  FAIL web/assets/language-data.js estava desatualizado (já regenerado; faça commit)' );
	failures++;
} else {
	console.log( '  ok   language-data.js sincronizado com o PHP' );
}

// 2. Mesmas entradas, mesmas saídas.
const SEOL = require( path.join( root, 'web/assets/analyzer.js' ) );
const cases = JSON.parse( fs.readFileSync( path.join( __dirname, 'fixtures/parity.json' ), 'utf8' ) );
const php = JSON.parse( execFileSync( 'php', [ path.join( __dirname, 'parity-dump.php' ) ], { encoding: 'utf8' } ) );

function content( c ) {
	if ( c !== 'REPEAT_PT' ) {
		return c;
	}
	const p = '<p>Uma loja virtual precisa de uma boa plataforma e de um plano claro. Uma loja virtual também exige atenção ao atendimento. Uma loja virtual sem estoque organizado perde vendas. O e-commerce cresce todos os anos no Brasil, e quem planeja bem colhe resultados consistentes ao longo do tempo.</p>';
	return p.repeat( 9 );
}

cases.forEach( ( c, i ) => {
	c.content = content( c.content );
	const res = SEOL.analyze( c );
	const js = {
		seo: res.seo,
		readability: res.readability,
		quality: res.quality,
		fix: {
			title: SEOL.Autofix.title( c.post_title, 'Meu Site', '-', c.keyphrase ),
			description: SEOL.Autofix.description( c.content, c.keyphrase, c.lang ),
			slug: SEOL.Autofix.slug( c.post_title, c.keyphrase, c.lang ),
			years: SEOL.Autofix.updateYears( c.post_title + ' ' + c.title, c.current_year ),
			alt: SEOL.Autofix.altFromFilename( 'https://x.com/wp-content/uploads/' + c.slug + '-800x600.jpg' ),
		},
	};
	try {
		assert.deepStrictEqual( js, php[ i ] );
		console.log( '  ok   ' + c.name );
	} catch ( e ) {
		failures++;
		console.log( '  FAIL ' + c.name );
		console.log( e.message.split( '\n' ).slice( 0, 40 ).join( '\n' ) );
	}
} );

console.log( `\n${ cases.length + 1 } verificações de paridade, ${ failures } falha(s)` );
process.exit( failures ? 1 : 0 );
