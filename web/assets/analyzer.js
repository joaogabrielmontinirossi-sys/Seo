/**
 * Motor de análise SEO & Legibilidade em JavaScript.
 *
 * Port fiel de includes/analysis/*.php e includes/class-autofix.php (geração).
 * As listas de palavras vêm de language-data.js, gerado a partir do PHP.
 * tests/parity.js garante que os dois motores produzem o mesmo resultado.
 */
( function ( root, factory ) {
	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory( require( './language-data.js' ) );
	} else {
		root.SEOL = factory( root.SEOL_LANG );
	}
} )( this, function ( LANG ) {
	'use strict';

	/* ================================================================ */
	/* Utilitários equivalentes às funções do PHP                        */
	/* ================================================================ */

	function chars( s ) {
		return Array.from( String( s ) );
	}
	function len( s ) {
		return chars( s ).length;
	}
	function substr( s, start, length ) {
		var c = chars( s );
		return c.slice( start, length === undefined ? undefined : start + length ).join( '' );
	}
	// round() do PHP arredonda metade para longe do zero.
	function round( n, d ) {
		var f = Math.pow( 10, d || 0 );
		return ( Math.sign( n ) * Math.round( Math.abs( n ) * f + 1e-9 ) ) / f;
	}
	function numberFormat( n, d ) {
		return round( n, d ).toFixed( d ).replace( '.', ',' );
	}
	function num( n ) {
		return numberFormat( n, 1 ).replace( /0+$/, '' ).replace( /,$/, '' );
	}
	function sprintf( fmt ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return fmt.replace( /%(?:(\d+)\$)?([sd%])/g, function ( m, pos, type ) {
			if ( type === '%' ) {
				return '%';
			}
			var v = pos ? args[ pos - 1 ] : args[ i++ ];
			return type === 'd' ? String( parseInt( v, 10 ) ) : String( v );
		} );
	}
	function ucfirst( s ) {
		return s.charAt( 0 ).toUpperCase() + s.slice( 1 );
	}
	function unique( arr ) {
		return arr.filter( function ( v, i ) {
			return arr.indexOf( v ) === i;
		} );
	}

	var ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: '\u00a0', ndash: '–', mdash: '—', hellip: '…', laquo: '«', raquo: '»', ldquo: '“', rdquo: '”', lsquo: '‘', rsquo: '’', ordm: 'º', ordf: 'ª', copy: '©', reg: '®', euro: '€', middot: '·', bull: '•' };
	function decodeEntities( s ) {
		return s.replace( /&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, function ( m, e ) {
			if ( e[ 0 ] === '#' ) {
				var code = e[ 1 ] === 'x' || e[ 1 ] === 'X' ? parseInt( e.slice( 2 ), 16 ) : parseInt( e.slice( 1 ), 10 );
				return String.fromCodePoint( code );
			}
			if ( Object.prototype.hasOwnProperty.call( ENTITIES, e.toLowerCase() ) ) {
				return ENTITIES[ e.toLowerCase() ];
			}
			if ( typeof document !== 'undefined' ) {
				var t = document.createElement( 'textarea' );
				t.innerHTML = m;
				return t.value;
			}
			return m;
		} );
	}

	/* ================================================================ */
	/* Text                                                              */
	/* ================================================================ */

	var Text = {};

	Text.strip = function ( html ) {
		html = String( html || '' );
		html = html.replace( /<(script|style|noscript|svg)[^>]*>[\s\S]*?<\/\1>/gi, ' ' );
		html = html.replace( /<!--[\s\S]*?-->/g, ' ' );
		html = html.replace( /\[\/?[a-zA-Z_][^\]]*\]/g, ' ' );
		html = html.replace( /<\/?(p|div|h[1-6]|li|ul|ol|br|tr|td|th|blockquote|figure|figcaption|section|article|pre)\b[^>]*>/gi, '\n' );
		var text = decodeEntities( html.replace( /<[^>]*>/g, '' ) );
		text = text.replace( /\u00a0/g, ' ' );
		text = text.replace( /[ \t]+/g, ' ' );
		text = text.replace( /\n\s*\n+/g, '\n\n' );
		return text.trim();
	};

	Text.paragraphs = function ( html ) {
		html = String( html || '' );
		var out = [];
		var re = /<p\b[^>]*>([\s\S]*?)<\/p>/gi;
		var m;
		var found = false;
		while ( ( m = re.exec( html ) ) ) {
			found = true;
			var t = Text.strip( m[ 1 ] ).trim();
			if ( t !== '' ) {
				out.push( t );
			}
		}
		if ( found ) {
			return out;
		}
		var without = html.replace( /<h[1-6][^>]*>[\s\S]*?<\/h[1-6]>/gi, '\n\n' );
		Text.strip( without ).split( /\n\s*\n/ ).forEach( function ( p ) {
			if ( p.trim() !== '' ) {
				out.push( p.trim() );
			}
		} );
		return out;
	};

	Text.headings = function ( html ) {
		var out = [];
		var re = /<h([1-6])[^>]*>([\s\S]*?)<\/h\1>/gi;
		var m;
		while ( ( m = re.exec( String( html || '' ) ) ) ) {
			out.push( { level: parseInt( m[ 1 ], 10 ), text: Text.strip( m[ 2 ] ).trim(), offset: m.index } );
		}
		return out;
	};

	Text.sections = function ( html ) {
		return String( html || '' ).split( /<h[2-6][^>]*>[\s\S]*?<\/h[2-6]>/i ).map( Text.strip );
	};

	Text.sentences = function ( text ) {
		text = String( text || '' ).replace( /\s+/gu, ' ' ).trim();
		if ( text === '' ) {
			return [];
		}
		var tokens = text.split( /(?<=[.!?…])\s+/u );
		var sentences = [];
		var buffer = '';
		tokens.forEach( function ( token ) {
			buffer = buffer === '' ? token : buffer + ' ' + token;
			var m = token.match( /(?:^|\s)([\p{L}.]+)\.$/u );
			if ( m && LANG.abbreviations.indexOf( m[ 1 ].replace( /\.+$/, '' ).toLowerCase() ) !== -1 ) {
				return;
			}
			if ( /(?:^|\s)\p{Lu}\.$/u.test( token ) ) {
				return;
			}
			sentences.push( buffer.trim() );
			buffer = '';
		} );
		if ( buffer !== '' ) {
			sentences.push( buffer.trim() );
		}
		var result = [];
		sentences.forEach( function ( s ) {
			s.split( /\n+/ ).forEach( function ( piece ) {
				if ( Text.words( piece ).length > 0 ) {
					result.push( piece.trim() );
				}
			} );
		} );
		return result;
	};

	Text.sentencesFromHtml = function ( html ) {
		var out = [];
		Text.strip( html ).split( /\n+/ ).forEach( function ( line ) {
			Text.sentences( line ).forEach( function ( s ) {
				out.push( s );
			} );
		} );
		return out;
	};

	Text.words = function ( text ) {
		return String( text || '' )
			.split( /[^\p{L}\p{N}'’-]+/u )
			.filter( function ( w ) {
				return w !== '' && /[\p{L}\p{N}]/u.test( w );
			} );
	};

	Text.wordCount = function ( text ) {
		return Text.words( text ).length;
	};

	var ACCENTS = { 'á': 'a', 'à': 'a', 'â': 'a', 'ã': 'a', 'ä': 'a', 'é': 'e', 'è': 'e', 'ê': 'e', 'ë': 'e', 'í': 'i', 'ì': 'i', 'î': 'i', 'ï': 'i', 'ó': 'o', 'ò': 'o', 'ô': 'o', 'õ': 'o', 'ö': 'o', 'ú': 'u', 'ù': 'u', 'û': 'u', 'ü': 'u', 'ç': 'c', 'ñ': 'n', '’': "'" };
	Text.normalize = function ( text ) {
		return chars( String( text || '' ).toLowerCase() )
			.map( function ( c ) {
				return ACCENTS[ c ] || c;
			} )
			.join( '' );
	};

	Text.syllables = function ( word, lang ) {
		var w = String( word ).toLowerCase().replace( /[^\p{L}]/gu, '' );
		if ( w === '' ) {
			return 0;
		}
		if ( ( lang || 'pt' ) === 'pt' ) {
			w = w.replace( /([qg])u([eiéíê])/gu, '$1$2' );
			w = w.replace( /(ão|ãe|õe|ai|ei|oi|ui|au|eu|iu|ou|éi|ói|éu)/gu, 'a' );
			return Math.max( 1, ( w.match( /[aeiouáéíóúâêôãõàü]/gu ) || [] ).length );
		}
		if ( len( w ) <= 3 ) {
			return 1;
		}
		w = w.replace( /(?:[^laeiouy]es|ed|[^laeiouy]e)$/, '' );
		w = w.replace( /^y/, '' );
		return Math.max( 1, ( w.match( /[aeiouy]{1,2}/g ) || [] ).length );
	};

	var WIDTHS = null;
	var WIDTH_BASE = { 'á': 'a', 'à': 'a', 'â': 'a', 'ã': 'a', 'é': 'e', 'ê': 'e', 'í': 'i', 'ó': 'o', 'ô': 'o', 'õ': 'o', 'ú': 'u', 'ç': 'c', 'Á': 'A', 'É': 'E', 'Í': 'I', 'Ó': 'O', 'Ú': 'U', 'Ç': 'C', 'Ã': 'A', 'Õ': 'O', 'Â': 'A', 'Ê': 'E', 'Ô': 'O' };
	Text.pixelWidth = function ( text, fontSize ) {
		if ( ! WIDTHS ) {
			WIDTHS = {};
			[
				[ 0.278, "ijlI.,:;!|'`’ " ],
				[ 0.333, 'ftr()[]-/\\' ],
				[ 0.5, 'sczJ"*^' ],
				[ 0.556, 'abdeghknopquvxy0123456789$#_?L' ],
				[ 0.611, 'TFZ' ],
				[ 0.667, 'ABEKPSVXY&' ],
				[ 0.722, 'CDHNRUw' ],
				[ 0.778, 'GOQ' ],
				[ 0.833, 'mM' ],
				[ 0.944, 'W' ],
				[ 1.015, '@' ],
			].forEach( function ( g ) {
				chars( g[ 1 ] ).forEach( function ( c ) {
					WIDTHS[ c ] = g[ 0 ];
				} );
			} );
		}
		var total = 0;
		chars( text ).forEach( function ( c ) {
			var base = WIDTH_BASE[ c ] || c;
			total += Object.prototype.hasOwnProperty.call( WIDTHS, base ) ? WIDTHS[ base ] : 0.556;
		} );
		return round( total * ( fontSize || 20 ), 0 );
	};

	Text.trimPunctuation = function ( text, bothSides ) {
		text = String( text ).replace( /[\s,;:\-–—]+$/u, '' );
		return bothSides === false ? text : text.replace( /^[\s,;:\-–—]+/u, '' );
	};

	Text.truncate = function ( text, max, ellipsis ) {
		ellipsis = ellipsis || '';
		text = String( text ).replace( /\s+/gu, ' ' ).trim();
		if ( len( text ) <= max ) {
			return text;
		}
		var cut = substr( text, 0, max - len( ellipsis ) + 1 );
		var space = chars( cut ).lastIndexOf( ' ' );
		if ( space !== -1 && space > max * 0.6 ) {
			cut = substr( cut, 0, space );
		} else {
			cut = substr( cut, 0, max - len( ellipsis ) );
		}
		return Text.trimPunctuation( cut, false ) + ellipsis;
	};

	// Em expressões com a flag "u", "\-" é inválido fora de classes: usa \x2d.
	function escapeRegExp( s ) {
		return s.replace( /[.*+?^${}()|[\]\\\/]/g, '\\$&' ).replace( /-/g, '\\x2d' );
	}

	Text.keyphrasePattern = function ( keyphrase, stopwords ) {
		stopwords = stopwords || [];
		var words = Text.words( Text.normalize( keyphrase ) );
		if ( ! words.length ) {
			return null;
		}
		var content = words.filter( function ( w ) {
			return stopwords.indexOf( w ) === -1;
		} );
		if ( ! content.length ) {
			content = words;
		}
		var parts = content.map( function ( w ) {
			var stem = w.replace( /(oes|aes|ais|eis|ois|is|es|s|ao|l|m)$/u, '' );
			stem = len( stem ) >= 3 ? stem : w;
			return escapeRegExp( stem ) + '[\\p{L}]{0,4}';
		} );
		return new RegExp( '(?<![\\p{L}\\p{N}])' + parts.join( '(?:[^\\p{L}\\p{N}]+[\\p{L}\\p{N}]+){0,2}?[^\\p{L}\\p{N}]+' ) + '(?![\\p{L}\\p{N}])', 'gu' );
	};

	Text.countKeyphrase = function ( text, keyphrase, stopwords ) {
		var re = Text.keyphrasePattern( keyphrase, stopwords );
		if ( ! re ) {
			return 0;
		}
		return ( Text.normalize( text ).match( re ) || [] ).length;
	};

	Text.containsKeyphrase = function ( text, keyphrase, stopwords ) {
		return Text.countKeyphrase( text, keyphrase, stopwords ) > 0;
	};

	Text.attr = function ( tag, name ) {
		var re = new RegExp( '\\s' + escapeRegExp( name ) + '\\s*=\\s*("([^"]*)"|\'([^\']*)\'|([^\\s>]+))', 'i' );
		var m = String( tag ).match( re );
		if ( ! m ) {
			return null;
		}
		var v = m[ 4 ] !== undefined ? m[ 4 ] : m[ 3 ] !== undefined ? m[ 3 ] : m[ 2 ];
		return decodeEntities( v || '' );
	};

	Text.images = function ( html ) {
		return ( String( html || '' ).match( /<img\b[^>]*>/gi ) || [] ).map( function ( tag ) {
			return { src: Text.attr( tag, 'src' ), alt: Text.attr( tag, 'alt' ) };
		} );
	};

	Text.links = function ( html ) {
		var out = [];
		var re = /<a\b([^>]*)>([\s\S]*?)<\/a>/gi;
		var m;
		while ( ( m = re.exec( String( html || '' ) ) ) ) {
			var open = '<a ' + m[ 1 ] + '>';
			var href = Text.attr( open, 'href' );
			if ( href === null ) {
				continue;
			}
			var text = Text.strip( m[ 2 ] ).trim();
			if ( text === '' ) {
				text = Text.attr( m[ 2 ], 'alt' ) || '';
			}
			out.push( { href: href, text: text, rel: Text.attr( open, 'rel' ) || '' } );
		}
		return out;
	};

	/* ================================================================ */
	/* Result                                                            */
	/* ================================================================ */

	var POINTS = { good: 9, ok: 6, bad: 3 };
	function R( id, label, rating, message, fix ) {
		return { id: id, label: label, rating: rating, message: message, fix: fix || null };
	}
	function score( results ) {
		if ( ! results.length ) {
			return 0;
		}
		var sum = results.reduce( function ( a, r ) {
			return a + POINTS[ r.rating ];
		}, 0 );
		return round( ( sum / ( 9 * results.length ) ) * 100, 0 );
	}
	function ratingForScore( s ) {
		return s >= 71 ? 'good' : s >= 41 ? 'ok' : 'bad';
	}
	function sortResults( results ) {
		var order = { bad: 0, ok: 1, good: 2 };
		return results.slice().sort( function ( a, b ) {
			return order[ a.rating ] - order[ b.rating ];
		} );
	}
	function nPlural( one, many, n ) {
		return n === 1 ? one : many;
	}

	function flesch( lang, wps, spw ) {
		var base = lang === 'pt' ? 248.835 : 206.835;
		return Math.max( 0, Math.min( 100, round( base - 1.015 * wps - 84.6 * spw, 1 ) ) );
	}

	/* ================================================================ */
	/* Legibilidade                                                      */
	/* ================================================================ */

	function Readability( lang ) {
		this.lang = lang === 'en' ? 'en' : 'pt';
		this.L = LANG[ this.lang ];
	}

	Readability.prototype.analyze = function ( html ) {
		var text = Text.strip( html );
		var sentences = Text.sentencesFromHtml( html );
		var words = Text.words( text );
		var results = [];
		if ( words.length < 50 ) {
			results.push( R( 'text_presence', 'Quantidade de texto', 'bad', 'Há pouco texto para avaliar a legibilidade. Escreva pelo menos 50 palavras.' ) );
			return this.finish( results, text, sentences, words );
		}
		results.push( this.flesch( sentences, words ) );
		results.push( this.sentenceLength( sentences ) );
		results.push( this.paragraphLength( html ) );
		results.push( this.subheadings( html, words.length ) );
		results.push( this.passiveVoice( sentences ) );
		if ( words.length >= 200 ) {
			results.push( this.transitionWords( sentences ) );
		}
		results.push( this.consecutive( sentences ) );
		results.push( this.complexity( words ) );
		results.push( this.lists( html, words.length ) );
		return this.finish( results.filter( Boolean ), text, sentences, words );
	};

	Readability.prototype.finish = function ( results, text, sentences, words ) {
		var s = score( results );
		return {
			score: s,
			rating: ratingForScore( s ),
			results: sortResults( results ),
			stats: {
				words: words.length,
				sentences: sentences.length,
				characters: len( text ),
				reading_time: Math.max( 1, Math.ceil( words.length / 200 ) ),
				flesch: this.fleschValue( sentences, words ),
			},
		};
	};

	Readability.prototype.fleschValue = function ( sentences, words ) {
		if ( ! sentences.length || ! words.length ) {
			return null;
		}
		var lang = this.lang;
		var syl = words.reduce( function ( a, w ) {
			return a + Text.syllables( w, lang );
		}, 0 );
		return flesch( lang, words.length / sentences.length, syl / words.length );
	};

	Readability.prototype.flesch = function ( sentences, words ) {
		var s = this.fleschValue( sentences, words );
		var label = 'Facilidade de leitura (Flesch)';
		var level = s >= 90 ? 'muito fácil' : s >= 80 ? 'fácil' : s >= 70 ? 'razoavelmente fácil' : s >= 60 ? 'padrão' : s >= 50 ? 'razoavelmente difícil' : s >= 30 ? 'difícil' : 'muito difícil';
		var base = sprintf( 'O texto obteve %1$s pontos, considerado %2$s de ler.', numberFormat( s, 1 ), level );
		if ( s >= 60 ) {
			return R( 'flesch', label, 'good', base + ' Ótimo trabalho!' );
		}
		return R( 'flesch', label, s >= 50 ? 'ok' : 'bad', base + ' Use frases mais curtas e palavras mais simples para facilitar a leitura.' );
	};

	Readability.prototype.sentenceLength = function ( sentences ) {
		var long = sentences.filter( function ( s ) {
			return Text.wordCount( s ) > 20;
		} ).length;
		var pct = sentences.length ? round( ( long / sentences.length ) * 100, 1 ) : 0;
		var label = 'Comprimento das frases';
		if ( pct <= 25 ) {
			return R( 'sentence_length', label, 'good', sprintf( 'Apenas %s%% das frases têm mais de 20 palavras. Ótimo!', num( pct ) ) );
		}
		return R( 'sentence_length', label, pct <= 30 ? 'ok' : 'bad', sprintf( '%s%% das frases têm mais de 20 palavras; o recomendado é no máximo 25%%. Divida as frases longas.', num( pct ) ) );
	};

	Readability.prototype.paragraphLength = function ( html ) {
		var tooLong = 0;
		var warn = 0;
		Text.paragraphs( html ).forEach( function ( p ) {
			var n = Text.wordCount( p );
			if ( n > 150 ) {
				tooLong++;
			} else if ( n > 120 ) {
				warn++;
			}
		} );
		var label = 'Comprimento dos parágrafos';
		if ( tooLong ) {
			return R( 'paragraph_length', label, 'bad', sprintf( nPlural( '%d parágrafo tem mais de 150 palavras. Divida-o em blocos menores.', '%d parágrafos têm mais de 150 palavras. Divida-os em blocos menores.', tooLong ), tooLong ) );
		}
		if ( warn ) {
			return R( 'paragraph_length', label, 'ok', sprintf( nPlural( '%d parágrafo tem entre 120 e 150 palavras. Considere encurtá-lo.', '%d parágrafos têm entre 120 e 150 palavras. Considere encurtá-los.', warn ), warn ) );
		}
		return R( 'paragraph_length', label, 'good', 'Nenhum parágrafo é longo demais. Ótimo!' );
	};

	Readability.prototype.subheadings = function ( html, total ) {
		var label = 'Distribuição de subtítulos';
		var sections = Text.sections( html );
		if ( sections.length <= 1 ) {
			if ( total <= 300 ) {
				return R( 'subheadings', label, 'good', 'O texto é curto e não precisa de subtítulos.' );
			}
			return R( 'subheadings', label, 'bad', 'O texto tem mais de 300 palavras e nenhum subtítulo (H2, H3). Adicione subtítulos para organizar o conteúdo.' );
		}
		var over = 0;
		var warn = 0;
		sections.forEach( function ( s ) {
			var n = Text.wordCount( s );
			if ( n > 350 ) {
				over++;
			} else if ( n > 300 ) {
				warn++;
			}
		} );
		if ( over ) {
			return R( 'subheadings', label, 'bad', sprintf( nPlural( '%d trecho tem mais de 300 palavras sem subtítulo. Adicione subtítulos.', '%d trechos têm mais de 300 palavras sem subtítulo. Adicione subtítulos.', over ), over ) );
		}
		if ( warn ) {
			return R( 'subheadings', label, 'ok', sprintf( nPlural( '%d trecho está perto do limite de 300 palavras sem subtítulo.', '%d trechos estão perto do limite de 300 palavras sem subtítulo.', warn ), warn ) );
		}
		return R( 'subheadings', label, 'good', 'Os subtítulos estão bem distribuídos. Ótimo!' );
	};

	var ADVERB_SKIP = [ 'não', 'nunca', 'já', 'sempre', 'not', 'never', 'also', 'também' ];
	Readability.prototype.isPassive = function ( sentence ) {
		var L = this.L;
		var lang = this.lang;
		var words = Text.words( sentence ).map( function ( w ) {
			return w.toLowerCase();
		} );
		for ( var i = 0; i < words.length; i++ ) {
			if ( L.passiveAuxiliaries.indexOf( words[ i ] ) === -1 ) {
				continue;
			}
			for ( var j = i + 1; j <= Math.min( i + 3, words.length - 1 ); j++ ) {
				var w = words[ j ];
				if ( L.participleExceptions.indexOf( w ) !== -1 ) {
					break;
				}
				if ( L.irregularParticiples.indexOf( w ) !== -1 ) {
					return true;
				}
				if ( lang === 'pt' && /^\p{L}{2,}(ad|id)(o|a|os|as)$/u.test( w ) ) {
					return true;
				}
				if ( lang === 'en' && /^\p{L}{2,}ed$/u.test( w ) ) {
					return true;
				}
				if ( ! /(mente|ly)$/u.test( w ) && ADVERB_SKIP.indexOf( w ) === -1 ) {
					break;
				}
			}
		}
		return false;
	};

	Readability.prototype.passiveVoice = function ( sentences ) {
		var self = this;
		var passive = sentences.filter( function ( s ) {
			return self.isPassive( s );
		} ).length;
		var pct = sentences.length ? round( ( passive / sentences.length ) * 100, 1 ) : 0;
		var label = 'Voz passiva';
		if ( pct <= 10 ) {
			return R( 'passive_voice', label, 'good', sprintf( '%s%% das frases estão na voz passiva. Ótimo!', num( pct ) ) );
		}
		return R( 'passive_voice', label, pct <= 15 ? 'ok' : 'bad', sprintf( '%s%% das frases estão na voz passiva; o recomendado é no máximo 10%%. Prefira a voz ativa.', num( pct ) ) );
	};

	Readability.prototype.hasTransition = function ( sentence ) {
		var norm = ' ' + Text.words( String( sentence ).toLowerCase() ).join( ' ' ) + ' ';
		return this.L.transitionWords.some( function ( t ) {
			return norm.indexOf( ' ' + t + ' ' ) !== -1;
		} );
	};

	Readability.prototype.transitionWords = function ( sentences ) {
		var self = this;
		var withT = sentences.filter( function ( s ) {
			return self.hasTransition( s );
		} ).length;
		var pct = sentences.length ? round( ( withT / sentences.length ) * 100, 1 ) : 0;
		var label = 'Palavras de transição';
		if ( pct >= 30 ) {
			return R( 'transition_words', label, 'good', sprintf( '%s%% das frases têm palavras de transição. Ótimo!', num( pct ) ) );
		}
		return R( 'transition_words', label, pct >= 20 ? 'ok' : 'bad', sprintf( 'Apenas %s%% das frases têm palavras de transição (ex.: "além disso", "portanto", "no entanto"); o recomendado é pelo menos 30%%.', num( pct ) ) );
	};

	Readability.prototype.consecutive = function ( sentences ) {
		var maxRun = 1;
		var run = 1;
		var prev = null;
		var repeated = '';
		sentences.forEach( function ( s ) {
			var w = Text.words( s.toLowerCase() );
			var first = w.length ? w[ 0 ] : '';
			if ( prev !== null && first !== '' && first === prev ) {
				run++;
				if ( run > maxRun ) {
					maxRun = run;
					repeated = first;
				}
			} else {
				run = 1;
			}
			prev = first;
		} );
		var label = 'Início de frases consecutivas';
		if ( maxRun >= 3 ) {
			return R( 'consecutive_sentences', label, 'bad', sprintf( '%1$d frases seguidas começam com "%2$s". Varie o início das frases.', maxRun, repeated ) );
		}
		return R( 'consecutive_sentences', label, 'good', 'As frases começam de forma variada. Ótimo!' );
	};

	Readability.prototype.complexity = function ( words ) {
		var lang = this.lang;
		var complex = words.filter( function ( w ) {
			return Text.syllables( w, lang ) >= ( lang === 'pt' ? 5 : 4 );
		} ).length;
		var pct = round( ( complex / Math.max( 1, words.length ) ) * 100, 1 );
		var label = 'Palavras complexas';
		if ( pct <= 10 ) {
			return R( 'word_complexity', label, 'good', sprintf( '%s%% das palavras são longas/complexas. Ótimo!', num( pct ) ) );
		}
		return R( 'word_complexity', label, 'ok', sprintf( '%s%% das palavras são longas/complexas. Troque algumas por sinônimos mais simples.', num( pct ) ) );
	};

	Readability.prototype.lists = function ( html, total ) {
		if ( total < 600 ) {
			return null;
		}
		if ( /<(ul|ol)\b/i.test( html ) ) {
			return R( 'lists', 'Listas', 'good', 'O texto usa listas, o que facilita a leitura rápida.' );
		}
		return R( 'lists', 'Listas', 'ok', 'Textos longos ficam mais escaneáveis com listas. Considere usar uma lista de tópicos.' );
	};

	/* ================================================================ */
	/* SEO                                                               */
	/* ================================================================ */

	var SEO = {
		TITLE_MIN_PX: 200,
		TITLE_MAX_PX: 580,
		DESC_MIN_CHARS: 120,
		DESC_MAX_CHARS: 156,
		MIN_WORDS: 300,
		DENSITY_MIN: 0.5,
		DENSITY_MAX: 3.0,
		SLUG_MAX_CHARS: 75,
	};

	function Seo( lang ) {
		this.lang = lang === 'en' ? 'en' : 'pt';
		this.L = LANG[ this.lang ];
		this.stop = this.L.stopwords;
	}

	Seo.prototype.analyze = function ( data ) {
		var d = Object.assign( { keyphrase: '', title: '', description: '', description_is_auto: false, slug: '', content: '', site_url: '', keyphrase_uses: 0, min_words: SEO.MIN_WORDS }, data );
		var kp = String( d.keyphrase ).trim();
		var html = String( d.content );
		var text = Text.strip( html );
		var r = [];
		r.push( this.keyphraseSet( kp ) );
		if ( kp !== '' ) {
			r.push( this.keyphraseLength( kp ) );
			r.push( this.inTitle( kp, d.title ) );
			r.push( this.inDescription( kp, d.description ) );
			r.push( this.inSlug( kp, d.slug ) );
			r.push( this.inIntro( kp, html ) );
			r.push( this.density( kp, text ) );
			r.push( this.inSubheadings( kp, html ) );
			r.push( this.inAlt( kp, html ) );
			r.push( this.previouslyUsed( parseInt( d.keyphrase_uses, 10 ) || 0 ) );
		}
		r.push( this.titleWidth( d.title ) );
		r.push( this.descriptionLength( d.description, !! d.description_is_auto ) );
		r.push( this.textLength( text, parseInt( d.min_words, 10 ) ) );
		r.push( this.slugQuality( d.slug ) );
		r.push( this.images( html ) );
		r.push( this.links( html, d.site_url ) );
		r.push( this.anchors( html ) );
		r.push( this.headingStructure( html ) );
		r = r.filter( Boolean );
		var s = score( r );
		return { score: s, rating: ratingForScore( s ), results: sortResults( r ) };
	};

	Seo.prototype.contentWords = function ( kp ) {
		var stop = this.stop;
		return Text.words( Text.normalize( kp ) ).filter( function ( w ) {
			return stop.indexOf( w ) === -1;
		} );
	};

	Seo.prototype.keyphraseSet = function ( kp ) {
		if ( kp === '' ) {
			return R( 'keyphrase', 'Frase-chave de foco', 'bad', 'Defina a frase-chave de foco: o termo que as pessoas buscariam para encontrar este conteúdo.' );
		}
		return R( 'keyphrase', 'Frase-chave de foco', 'good', 'Frase-chave definida.' );
	};

	Seo.prototype.keyphraseLength = function ( kp ) {
		var n = this.contentWords( kp ).length;
		var label = 'Tamanho da frase-chave';
		if ( n >= 1 && n <= 4 ) {
			return R( 'keyphrase_length', label, 'good', 'A frase-chave tem um bom tamanho.' );
		}
		if ( n === 0 ) {
			return R( 'keyphrase_length', label, 'bad', 'A frase-chave só contém palavras vazias (artigos, preposições). Use termos com significado.' );
		}
		return R( 'keyphrase_length', label, n <= 8 ? 'ok' : 'bad', sprintf( 'A frase-chave tem %d palavras de conteúdo; o ideal é até 4. Use um termo mais curto e específico.', n ) );
	};

	Seo.prototype.inTitle = function ( kp, title ) {
		var label = 'Frase-chave no título SEO';
		var re = Text.keyphrasePattern( kp, this.stop );
		var m = re ? re.exec( Text.normalize( title ) ) : null;
		if ( m ) {
			if ( len( Text.normalize( title ).slice( 0, m.index ) ) <= 10 ) {
				return R( 'keyphrase_title', label, 'good', 'A frase-chave aparece no início do título SEO. Ótimo!' );
			}
			return R( 'keyphrase_title', label, 'ok', 'O título SEO contém a frase-chave, mas não no início. Mova-a para o começo do título.', 'title' );
		}
		return R( 'keyphrase_title', label, 'bad', 'O título SEO não contém a frase-chave. Inclua-a, de preferência no início.', 'title' );
	};

	Seo.prototype.inDescription = function ( kp, desc ) {
		var label = 'Frase-chave na meta descrição';
		if ( String( desc ).trim() === '' ) {
			return R( 'keyphrase_description', label, 'bad', 'Não há meta descrição para conter a frase-chave.', 'description' );
		}
		var c = Text.countKeyphrase( desc, kp, this.stop );
		if ( c === 1 || c === 2 ) {
			return R( 'keyphrase_description', label, 'good', 'A frase-chave aparece na meta descrição. Ótimo!' );
		}
		if ( c > 2 ) {
			return R( 'keyphrase_description', label, 'bad', 'A frase-chave aparece mais de 2 vezes na meta descrição. Evite repetições excessivas.' );
		}
		return R( 'keyphrase_description', label, 'bad', 'A meta descrição não contém a frase-chave. O Google destaca em negrito os termos buscados.', 'description' );
	};

	Seo.prototype.inSlug = function ( kp, slug ) {
		var label = 'Frase-chave na URL';
		var s;
		try {
			s = decodeURIComponent( String( slug ) );
		} catch ( e ) {
			s = String( slug );
		}
		s = s.replace( /-/g, ' ' );
		var words = this.contentWords( kp );
		if ( ! words.length ) {
			return null;
		}
		var found = words.filter( function ( w ) {
			return Text.containsKeyphrase( s, w );
		} ).length;
		if ( found === words.length ) {
			return R( 'keyphrase_slug', label, 'good', 'A URL (slug) contém a frase-chave. Ótimo!' );
		}
		if ( found > 0 ) {
			return R( 'keyphrase_slug', label, 'ok', 'A URL contém parte da frase-chave. Inclua todas as palavras importantes.', 'slug' );
		}
		return R( 'keyphrase_slug', label, 'bad', 'A URL (slug) não contém a frase-chave.', 'slug' );
	};

	Seo.prototype.inIntro = function ( kp, html ) {
		var label = 'Frase-chave na introdução';
		var paras = Text.paragraphs( html );
		if ( ! paras.length ) {
			return R( 'keyphrase_intro', label, 'bad', 'Escreva uma introdução que contenha a frase-chave.' );
		}
		var intro = paras[ 0 ];
		if ( Text.containsKeyphrase( intro, kp, this.stop ) ) {
			var first = Text.sentences( intro );
			if ( first.length && Text.containsKeyphrase( first[ 0 ], kp, this.stop ) ) {
				return R( 'keyphrase_intro', label, 'good', 'A frase-chave aparece logo na primeira frase. Ótimo!' );
			}
			return R( 'keyphrase_intro', label, 'good', 'A frase-chave aparece no primeiro parágrafo.' );
		}
		var words = this.contentWords( kp );
		var found = words.filter( function ( w ) {
			return Text.containsKeyphrase( intro, w );
		} ).length;
		if ( words.length && found === words.length ) {
			return R( 'keyphrase_intro', label, 'ok', 'As palavras da frase-chave aparecem no primeiro parágrafo, mas separadas. Use a frase completa.' );
		}
		return R( 'keyphrase_intro', label, 'bad', 'A frase-chave não aparece no primeiro parágrafo. Deixe claro logo no início do que o texto trata.' );
	};

	Seo.prototype.density = function ( kp, text ) {
		var label = 'Densidade da frase-chave';
		var total = Text.wordCount( text );
		if ( total < 100 ) {
			return null;
		}
		var count = Text.countKeyphrase( text, kp, this.stop );
		var density = round( ( count / total ) * 100, 2 );
		var min = Math.max( 1, Math.ceil( ( total * SEO.DENSITY_MIN ) / 100 ) );
		var max = Math.max( 2, Math.floor( ( total * SEO.DENSITY_MAX ) / 100 ) );
		var d = numberFormat( density, 2 ).replace( /0+$/, '' ).replace( /,$/, '' );
		if ( density < SEO.DENSITY_MIN ) {
			return R( 'keyphrase_density', label, count === 0 ? 'bad' : 'ok', sprintf( 'A frase-chave aparece %1$d vez(es) (%2$s%%). Para este tamanho de texto, use-a pelo menos %3$d vezes, de forma natural.', count, d, min ) );
		}
		if ( density > SEO.DENSITY_MAX ) {
			return R( 'keyphrase_density', label, 'bad', sprintf( 'A frase-chave aparece %1$d vezes (%2$s%%), o que pode parecer spam de palavras-chave. Use no máximo %3$d vezes.', count, d, max ) );
		}
		return R( 'keyphrase_density', label, 'good', sprintf( 'A frase-chave aparece %1$d vezes (%2$s%%). Ótimo!', count, d ) );
	};

	Seo.prototype.inSubheadings = function ( kp, html ) {
		var stop = this.stop;
		var subs = Text.headings( html ).filter( function ( h ) {
			return h.level === 2 || h.level === 3;
		} );
		if ( ! subs.length ) {
			return null;
		}
		var withKp = subs.filter( function ( h ) {
			return Text.containsKeyphrase( h.text, kp, stop );
		} ).length;
		var pct = ( withKp / subs.length ) * 100;
		var label = 'Frase-chave nos subtítulos';
		if ( withKp === 0 ) {
			return R( 'keyphrase_subheadings', label, 'ok', 'Nenhum subtítulo H2/H3 contém a frase-chave (ou sinônimos). Inclua-a em alguns deles.' );
		}
		if ( pct > 75 && subs.length > 1 ) {
			return R( 'keyphrase_subheadings', label, 'ok', 'A frase-chave está em quase todos os subtítulos. Varie com sinônimos para soar natural.' );
		}
		return R( 'keyphrase_subheadings', label, 'good', 'A frase-chave aparece nos subtítulos em boa proporção.' );
	};

	Seo.prototype.inAlt = function ( kp, html ) {
		var stop = this.stop;
		var images = Text.images( html );
		if ( ! images.length ) {
			return null;
		}
		var withKp = images.filter( function ( i ) {
			return i.alt !== null && Text.containsKeyphrase( i.alt, kp, stop );
		} ).length;
		var label = 'Frase-chave no texto alternativo';
		if ( withKp > 0 ) {
			return R( 'keyphrase_alt', label, 'good', 'Ao menos uma imagem tem a frase-chave no texto alternativo (alt).' );
		}
		return R( 'keyphrase_alt', label, 'ok', 'Nenhuma imagem tem a frase-chave no texto alternativo. Descreva ao menos uma imagem relevante usando o termo.' );
	};

	Seo.prototype.previouslyUsed = function ( uses ) {
		var label = 'Frase-chave já usada';
		if ( uses <= 0 ) {
			return R( 'keyphrase_unique', label, 'good', 'Você ainda não usou esta frase-chave em outro conteúdo. Ótimo!' );
		}
		return R( 'keyphrase_unique', label, uses === 1 ? 'ok' : 'bad', sprintf( nPlural( 'Esta frase-chave já é o foco de %d outro conteúdo. Evite canibalização: escolha outro termo ou consolide os conteúdos.', 'Esta frase-chave já é o foco de %d outros conteúdos. Evite canibalização: escolha outro termo ou consolide os conteúdos.', uses ), uses ) );
	};

	Seo.prototype.titleWidth = function ( title ) {
		var label = 'Largura do título SEO';
		title = String( title ).trim();
		if ( title === '' ) {
			return R( 'title_width', label, 'bad', 'Defina um título SEO.', 'title' );
		}
		var px = Text.pixelWidth( title );
		var n = len( title );
		if ( px > SEO.TITLE_MAX_PX ) {
			return R( 'title_width', label, 'ok', sprintf( 'O título tem %1$d caracteres (~%2$dpx) e será cortado nos resultados do Google (limite ~580px, cerca de 60 caracteres).', n, px ), 'title' );
		}
		if ( px < SEO.TITLE_MIN_PX ) {
			return R( 'title_width', label, 'ok', sprintf( 'O título tem só %1$d caracteres (~%2$dpx). Aproveite o espaço disponível (30–60 caracteres).', n, px ), 'title' );
		}
		return R( 'title_width', label, 'good', sprintf( 'O título tem %1$d caracteres (~%2$dpx). Tamanho ideal!', n, px ) );
	};

	Seo.prototype.descriptionLength = function ( desc, isAuto ) {
		var label = 'Tamanho da meta descrição';
		var n = len( String( desc ).trim() );
		if ( isAuto && n > 0 ) {
			return R( 'description_length', label, 'ok', 'Sem meta descrição própria: será usada uma gerada a partir do texto. Escreva uma personalizada para atrair mais cliques.', 'description' );
		}
		if ( n === 0 ) {
			return R( 'description_length', label, 'bad', 'Não há meta descrição. Escreva um resumo atrativo de 120 a 156 caracteres.', 'description' );
		}
		if ( n < SEO.DESC_MIN_CHARS ) {
			return R( 'description_length', label, 'ok', sprintf( 'A meta descrição tem %d caracteres; o ideal é entre 120 e 156.', n ), 'description' );
		}
		if ( n > SEO.DESC_MAX_CHARS ) {
			return R( 'description_length', label, 'ok', sprintf( 'A meta descrição tem %d caracteres e pode ser cortada; o ideal é até 156.', n ), 'description' );
		}
		return R( 'description_length', label, 'good', sprintf( 'A meta descrição tem %d caracteres. Tamanho ideal!', n ) );
	};

	Seo.prototype.textLength = function ( text, min ) {
		var n = Text.wordCount( text );
		var label = 'Tamanho do texto';
		if ( n >= min ) {
			return R( 'text_length', label, 'good', sprintf( 'O texto tem %d palavras. Bom volume de conteúdo!', n ) );
		}
		return R( 'text_length', label, n >= min * 0.8 ? 'ok' : 'bad', sprintf( 'O texto tem %1$d palavras; o recomendado é pelo menos %2$d para cobrir bem o assunto.', n, min ) );
	};

	Seo.prototype.slugQuality = function ( slug ) {
		var s;
		try {
			s = decodeURIComponent( String( slug ) );
		} catch ( e ) {
			s = String( slug );
		}
		if ( s === '' ) {
			return null;
		}
		var stop = this.stop;
		var issues = [];
		if ( len( s ) > SEO.SLUG_MAX_CHARS ) {
			issues.push( 'é longa demais (máx. 75 caracteres)' );
		}
		if ( /[^a-z0-9-]/.test( s ) ) {
			issues.push( 'contém acentos, maiúsculas ou caracteres especiais' );
		}
		var parts = s.split( '-' );
		var stopCount = parts.filter( function ( p ) {
			return stop.indexOf( p ) !== -1;
		} ).length;
		if ( stopCount > 1 || ( parts.length > 3 && stopCount ) ) {
			issues.push( 'contém palavras vazias (de, para, o, a…)' );
		}
		if ( /^\d+$/.test( s ) || /-[2-9]$/.test( s ) ) {
			issues.push( 'termina em número (possível duplicata)' );
		}
		if ( ! issues.length ) {
			return R( 'slug_quality', 'Qualidade da URL', 'good', 'A URL é curta, legível e descritiva.' );
		}
		return R( 'slug_quality', 'Qualidade da URL', 'ok', sprintf( 'A URL %s.', issues.join( '; ' ) ), 'slug' );
	};

	Seo.prototype.images = function ( html ) {
		var images = Text.images( html );
		var label = 'Imagens e texto alternativo';
		if ( ! images.length ) {
			return R( 'images', label, 'ok', 'Não há imagens no conteúdo. Imagens relevantes melhoram o engajamento e aparecem no Google Imagens.' );
		}
		var missing = images.filter( function ( i ) {
			return i.alt === null || i.alt.trim() === '';
		} ).length;
		if ( missing ) {
			return R( 'images', label, 'bad', sprintf( nPlural( '%d imagem está sem texto alternativo (alt). Descreva-a para acessibilidade e SEO.', '%d imagens estão sem texto alternativo (alt). Descreva-as para acessibilidade e SEO.', missing ), missing ), 'alt' );
		}
		return R( 'images', label, 'good', 'Todas as imagens têm texto alternativo. Ótimo!' );
	};

	function hostOf( url ) {
		var m = String( url ).match( /^(?:[a-z][a-z0-9+.-]*:)?\/\/([^\/?#:]+)/i );
		return m ? m[ 1 ].toLowerCase() : '';
	}

	Seo.prototype.isInternal = function ( href, siteUrl ) {
		if ( href === '' || href[ 0 ] === '#' ) {
			return null;
		}
		if ( /^(mailto|tel|javascript):/i.test( href ) ) {
			return null;
		}
		if ( href[ 0 ] === '/' && href[ 1 ] !== '/' ) {
			return true;
		}
		if ( ! /^(https?:)?\/\//i.test( href ) ) {
			return true;
		}
		var site = hostOf( siteUrl );
		return site !== '' && hostOf( href ).replace( /^www\./, '' ) === site.replace( /^www\./, '' );
	};

	Seo.prototype.links = function ( html, siteUrl ) {
		var self = this;
		var internal = 0;
		var external = 0;
		Text.links( html ).forEach( function ( l ) {
			var is = self.isInternal( l.href, siteUrl );
			if ( is === true ) {
				internal++;
			} else if ( is === false ) {
				external++;
			}
		} );
		var label = 'Links internos e externos';
		var tips = [];
		if ( internal === 0 ) {
			tips.push( 'adicione links internos para outros conteúdos do site' );
		}
		if ( external === 0 ) {
			tips.push( 'cite ao menos uma fonte externa confiável' );
		}
		if ( ! tips.length ) {
			return R( 'links', label, 'good', sprintf( '%1$d link(s) interno(s) e %2$d externo(s). Ótimo!', internal, external ) );
		}
		return R( 'links', label, internal === 0 ? 'bad' : 'ok', ucfirst( tips.join( '; ' ) ) + '.' );
	};

	Seo.prototype.anchors = function ( html ) {
		var generic = this.L.genericAnchors;
		var links = Text.links( html );
		if ( ! links.length ) {
			return null;
		}
		var bad = [];
		links.forEach( function ( l ) {
			var t = l.text.replace( /^[ \t\n.:!]+|[ \t\n.:!]+$/g, '' ).toLowerCase();
			if ( t === '' || generic.indexOf( t ) !== -1 ) {
				bad.push( t === '' ? '(vazio)' : l.text );
			}
		} );
		var label = 'Texto dos links';
		if ( bad.length ) {
			return R( 'anchor_text', label, 'ok', sprintf( 'Evite textos de link genéricos ou vazios (%s). Descreva o destino do link.', unique( bad ).join( ', ' ) ) );
		}
		return R( 'anchor_text', label, 'good', 'Os links têm textos descritivos.' );
	};

	Seo.prototype.headingStructure = function ( html ) {
		var headings = Text.headings( html );
		var label = 'Hierarquia de títulos';
		var issues = [];
		var h1 = 0;
		var prev = 1;
		headings.forEach( function ( h ) {
			if ( h.level === 1 ) {
				h1++;
			}
			if ( h.level > prev + 1 ) {
				issues.push( sprintf( 'salto de H%1$d para H%2$d', prev, h.level ) );
			}
			if ( h.text === '' ) {
				issues.push( 'título vazio' );
			}
			prev = h.level;
		} );
		if ( h1 ) {
			return R( 'heading_structure', label, 'bad', 'O conteúdo contém H1. O título do post já é o H1 da página; use H2 e H3 no texto.' );
		}
		if ( issues.length ) {
			return R( 'heading_structure', label, 'ok', sprintf( 'A hierarquia de títulos tem problemas: %s. Não pule níveis.', unique( issues ).join( ', ' ) ) );
		}
		if ( ! headings.length ) {
			return null;
		}
		return R( 'heading_structure', label, 'good', 'A hierarquia de títulos está correta.' );
	};

	/* ================================================================ */
	/* Atualidade e confiança                                            */
	/* ================================================================ */

	var QUALITY = { STALE_DAYS: 365, VERY_STALE_DAYS: 730, ANSWER_MIN_WORDS: 15, ANSWER_MAX_WORDS: 60 };

	function years( text ) {
		var out = [];
		var re = /(?<![\p{N}])(19\d{2}|20\d{2})(?![\p{N}])/gu;
		var m;
		while ( ( m = re.exec( String( text || '' ) ) ) ) {
			out.push( parseInt( m[ 1 ], 10 ) );
		}
		return out;
	}

	function Quality( lang ) {
		this.lang = lang === 'en' ? 'en' : 'pt';
		this.L = LANG[ this.lang ];
	}

	Quality.prototype.padded = function ( text ) {
		return ' ' + Text.words( String( text || '' ).toLowerCase() ).join( ' ' ) + ' ';
	};

	Quality.prototype.findPhrases = function ( text, phrases ) {
		var padded = this.padded( text );
		return phrases.filter( function ( p ) {
			return padded.indexOf( ' ' + p + ' ' ) !== -1;
		} );
	};

	Quality.prototype.analyze = function ( data ) {
		var d = Object.assign( { content: '', post_title: '', title: '', description: '', site_url: '', current_year: new Date().getFullYear(), modified_days: null, author_bio: null }, data );
		var html = String( d.content );
		var text = Text.strip( html );
		var words = Text.wordCount( text );
		var r = [
			this.outdatedYears( d, html, parseInt( d.current_year, 10 ) ),
			this.lastUpdated( d.modified_days ),
			this.relativeDates( text ),
			this.sources( html, String( d.site_url ), words ),
			this.authorBio( d.author_bio ),
			this.experience( text, words ),
			this.dataPoints( text, words ),
			this.questionHeadings( html, words ),
			this.directAnswers( html ),
		].filter( Boolean );
		var sc = score( r );
		return { score: sc, rating: ratingForScore( sc ), results: sortResults( r ) };
	};

	function pastYears( text, current ) {
		return unique(
			years( text ).filter( function ( y ) {
				return y < current;
			} )
		);
	}

	Quality.prototype.outdatedYears = function ( d, html, current ) {
		var label = 'Anos citados no título';
		var titles = ( d.post_title + ' ' + d.title ).trim();
		var past = pastYears( titles, current );
		if ( past.length ) {
			return R( 'outdated_year', label, 'bad', sprintf( 'O título cita %1$s, mas estamos em %2$d. Se o conteúdo foi revisado, atualize o ano; títulos com ano vencido perdem cliques. Ignore se o texto for histórico.', past.join( ', ' ), current ), 'year' );
		}
		var elsewhere = d.description;
		Text.headings( html ).forEach( function ( h ) {
			elsewhere += ' ' + h.text;
		} );
		var pastElsewhere = pastYears( elsewhere, current );
		if ( pastElsewhere.length ) {
			return R( 'outdated_year', label, 'ok', sprintf( 'A meta descrição ou os subtítulos citam %s. Confira se ainda está correto.', pastElsewhere.join( ', ' ) ) );
		}
		if ( years( titles ).indexOf( current ) !== -1 ) {
			return R( 'outdated_year', label, 'good', 'O título cita o ano atual. Lembre-se de revisá-lo no ano que vem.' );
		}
		return R( 'outdated_year', label, 'good', 'O título não cita anos desatualizados.' );
	};

	Quality.prototype.lastUpdated = function ( days ) {
		if ( days === null || days === undefined || days === '' ) {
			return null;
		}
		days = Math.max( 0, parseInt( days, 10 ) || 0 );
		var label = 'Última atualização';
		if ( days <= QUALITY.STALE_DAYS ) {
			return R( 'last_updated', label, 'good', days === 0 ? 'Conteúdo atualizado hoje.' : sprintf( nPlural( 'Conteúdo atualizado há %d dia.', 'Conteúdo atualizado há %d dias.', days ), days ) );
		}
		var msg = sprintf( 'O conteúdo não é atualizado há cerca de %d meses. Revise dados, preços, links e capturas de tela; o Google valoriza informação atual.', Math.floor( days / 30 ) );
		return R( 'last_updated', label, days <= QUALITY.VERY_STALE_DAYS ? 'ok' : 'bad', msg );
	};

	Quality.prototype.relativeDates = function ( text ) {
		var found = this.findPhrases( text, this.L.relativeTime );
		if ( ! found.length ) {
			return null;
		}
		var label = 'Referências de tempo';
		if ( years( text ).length ) {
			return R( 'relative_dates', label, 'good', 'O texto situa o leitor no tempo com datas concretas.' );
		}
		return R( 'relative_dates', label, 'ok', sprintf( 'O texto usa "%s" sem citar nenhuma data. Com o tempo isso fica impreciso: inclua datas concretas (ex.: "em março de 2026").', found.join( '", "' ) ) );
	};

	Quality.prototype.sources = function ( html, siteUrl, words ) {
		var site = hostOf( siteUrl ).replace( /^www\./, '' );
		var hosts = [];
		Text.links( html ).forEach( function ( l ) {
			if ( ! /^(https?:)?\/\//i.test( l.href ) ) {
				return;
			}
			var h = hostOf( l.href ).replace( /^www\./, '' );
			if ( h !== '' && h !== site && hosts.indexOf( h ) === -1 ) {
				hosts.push( h );
			}
		} );
		var n = hosts.length;
		var label = 'Fontes citadas';
		if ( n >= 2 ) {
			return R( 'sources', label, 'good', sprintf( 'O texto cita %d fontes externas diferentes. Isso reforça a confiabilidade.', n ) );
		}
		if ( n === 1 ) {
			return R( 'sources', label, 'ok', 'O texto cita só uma fonte externa. Apoie dados e afirmações importantes em mais fontes confiáveis (estudos, órgãos oficiais, especialistas).' );
		}
		return R( 'sources', label, words >= 300 ? 'bad' : 'ok', 'O texto não cita nenhuma fonte externa. Link para estudos, órgãos oficiais ou especialistas que confirmem o que você afirma.' );
	};

	Quality.prototype.authorBio = function ( has ) {
		if ( has === null || has === undefined ) {
			return null;
		}
		if ( has ) {
			return R( 'author_bio', 'Autoria', 'good', 'O autor tem biografia. Mostre nela a experiência dele com o assunto.' );
		}
		return R( 'author_bio', 'Autoria', 'ok', 'O autor não tem biografia. Escreva uma (Usuários → Perfil → Informações biográficas) mostrando formação e experiência no assunto.' );
	};

	Quality.prototype.experience = function ( text, words ) {
		if ( words < 300 ) {
			return null;
		}
		if ( this.findPhrases( text, this.L.experiencePhrases ).length ) {
			return R( 'experience', 'Experiência própria', 'good', 'O texto mostra experiência própria com o assunto (testes, uso, comparações). O Google valoriza isso.' );
		}
		return R( 'experience', 'Experiência própria', 'ok', 'O texto não mostra experiência própria. Conte o que você testou, usou ou comparou, com detalhes e fotos próprias: é o que diferencia seu conteúdo do resto.' );
	};

	Quality.prototype.dataPoints = function ( text, words ) {
		if ( words < 300 ) {
			return null;
		}
		if ( /\p{N}/u.test( text ) ) {
			return R( 'data_points', 'Dados concretos', 'good', 'O texto traz números e dados concretos.' );
		}
		return R( 'data_points', 'Dados concretos', 'ok', 'O texto não traz nenhum número. Inclua dados concretos (preços, medidas, prazos, estatísticas com fonte) para torná-lo mais útil e verificável.' );
	};

	Quality.prototype.isQuestion = function ( heading ) {
		heading = String( heading || '' ).trim();
		if ( heading === '' ) {
			return false;
		}
		if ( heading.slice( -1 ) === '?' ) {
			return true;
		}
		var padded = this.padded( heading );
		return this.L.questionWords.some( function ( q ) {
			return padded.indexOf( ' ' + q + ' ' ) === 0;
		} );
	};

	Quality.prototype.questionHeadings = function ( html, words ) {
		var self = this;
		var subs = Text.headings( html ).filter( function ( h ) {
			return h.level === 2 || h.level === 3;
		} );
		if ( subs.length < 2 && words < 600 ) {
			return null;
		}
		var q = subs.filter( function ( h ) {
			return self.isQuestion( h.text );
		} ).length;
		var label = 'Subtítulos em forma de pergunta';
		if ( q ) {
			return R( 'question_headings', label, 'good', sprintf( nPlural( '%d subtítulo responde a uma pergunta do leitor. Isso ajuda a aparecer em trechos em destaque e nas AI Overviews.', '%d subtítulos respondem a perguntas do leitor. Isso ajuda a aparecer em trechos em destaque e nas AI Overviews.', q ), q ) );
		}
		return R( 'question_headings', label, 'ok', 'Nenhum subtítulo é uma pergunta. Transforme alguns nas perguntas que o leitor faria (ex.: "Como escolher…?", "Quanto custa…?") e responda logo abaixo.' );
	};

	Quality.prototype.directAnswers = function ( html ) {
		var parts = String( html || '' ).split( /(<h[23]\b[^>]*>[\s\S]*?<\/h[23]>)/i );
		var total = 0;
		var long = [];
		var missing = [];
		for ( var i = 1; i < parts.length; i += 2 ) {
			var heading = Text.strip( parts[ i ] ).trim();
			if ( ! this.isQuestion( heading ) ) {
				continue;
			}
			total++;
			var body = parts[ i + 1 ] || '';
			var first = body.replace( /^(\s|<!--[\s\S]*?-->)+/, '' );
			if ( /^<(ul|ol)\b/i.test( first ) ) {
				continue;
			}
			var paras = Text.paragraphs( body );
			var count = paras.length ? Text.wordCount( paras[ 0 ] ) : 0;
			if ( count < QUALITY.ANSWER_MIN_WORDS ) {
				missing.push( heading );
			} else if ( count > QUALITY.ANSWER_MAX_WORDS ) {
				long.push( heading );
			}
		}
		if ( total === 0 ) {
			return null;
		}
		if ( ! long.length && ! missing.length ) {
			return R( 'direct_answers', 'Respostas diretas', 'good', 'Cada pergunta é respondida logo no primeiro parágrafo, de forma objetiva. Formato ideal para trechos em destaque e AI Overviews.' );
		}
		return R( 'direct_answers', 'Respostas diretas', 'ok', sprintf( 'Responda logo abaixo de cada pergunta em um parágrafo de 15 a 60 palavras (ou com uma lista) e detalhe depois. Ajuste: "%s".', long.concat( missing ).join( '", "' ) ) );
	};

	/* ================================================================ */
	/* Correções automáticas                                             */
	/* ================================================================ */

	var Autofix = {};

	Autofix.slug = function ( title, keyphrase, lang ) {
		var stop = LANG[ lang === 'en' ? 'en' : 'pt' ].stopwords.map( Text.normalize );
		function clean( text ) {
			return Text.words( Text.normalize( String( text || '' ).replace( /['’]/g, ' ' ) ) )
				.map( function ( w ) {
					return w.replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );
				} )
				.filter( function ( w ) {
					return w !== '' && stop.indexOf( w ) === -1;
				} );
		}
		var words = unique( clean( keyphrase ).concat( clean( title ) ) );
		words = words.slice( 0, Math.max( 6, clean( keyphrase ).length ) );
		var slug = words.join( '-' );
		while ( slug.length > SEO.SLUG_MAX_CHARS && words.length > 1 ) {
			words.pop();
			slug = words.join( '-' );
		}
		return slug;
	};

	Autofix.description = function ( html, keyphrase, lang ) {
		var sentences = [];
		Text.paragraphs( html ).forEach( function ( p ) {
			Text.sentences( p ).forEach( function ( s ) {
				sentences.push( s );
			} );
		} );
		if ( ! sentences.length ) {
			return '';
		}
		var start = 0;
		if ( String( keyphrase || '' ).trim() !== '' ) {
			var stop = LANG[ lang === 'en' ? 'en' : 'pt' ].stopwords;
			for ( var i = 0; i < sentences.length; i++ ) {
				if ( Text.containsKeyphrase( sentences[ i ], keyphrase, stop ) ) {
					start = i;
					break;
				}
			}
		}
		var desc = '';
		for ( var j = start; j < sentences.length; j++ ) {
			desc = ( desc + ' ' + sentences[ j ] ).trim();
			if ( len( desc ) >= SEO.DESC_MIN_CHARS ) {
				break;
			}
		}
		if ( len( desc ) > SEO.DESC_MAX_CHARS ) {
			desc = Text.truncate( desc, SEO.DESC_MAX_CHARS, '…' );
		}
		return desc;
	};

	function fit( text, maxPx ) {
		var words = text.split( ' ' );
		while ( words.length > 1 && Text.pixelWidth( words.join( ' ' ) ) > maxPx ) {
			words.pop();
		}
		return Text.trimPunctuation( words.join( ' ' ), false );
	}

	function startsWithKeyphrase( text, kp ) {
		var re = Text.keyphrasePattern( kp );
		var m = re ? re.exec( Text.normalize( text ) ) : null;
		return !! m && m.index <= 10;
	}

	function leadWithKeyphrase( title, kp ) {
		var rest = title;
		var re = Text.keyphrasePattern( kp );
		var norm = Text.normalize( title );
		var m = re ? re.exec( norm ) : null;
		if ( m ) {
			var start = len( norm.slice( 0, m.index ) );
			var l = len( m[ 0 ] );
			rest = substr( title, 0, start ) + substr( title, start + l );
			rest = Text.trimPunctuation( rest.replace( /\s+/gu, ' ' ).replace( /\s+([,.;:!?])/gu, '$1' ) );
			rest = rest.replace( /\s+(de|do|da|para|em|com|o|a|e)$/u, '' );
		}
		var lead = ucfirst( kp );
		if ( rest === '' ) {
			return lead;
		}
		return lead + ': ' + rest.charAt( 0 ).toLowerCase() + rest.slice( 1 );
	}

	Autofix.title = function ( postTitle, siteName, sep, keyphrase ) {
		postTitle = String( postTitle || '' ).replace( /\s+/gu, ' ' ).trim();
		keyphrase = String( keyphrase || '' ).trim();
		if ( keyphrase !== '' && ! startsWithKeyphrase( postTitle, keyphrase ) ) {
			postTitle = leadWithKeyphrase( postTitle, keyphrase );
		}
		var withSite = siteName ? postTitle + ' ' + ( sep || '-' ) + ' ' + siteName : postTitle;
		return Text.pixelWidth( withSite ) <= SEO.TITLE_MAX_PX ? withSite : fit( postTitle, SEO.TITLE_MAX_PX );
	};

	Autofix.updateYears = function ( text, currentYear ) {
		var ys = years( text );
		if ( ! ys.length || Math.max.apply( null, ys ) >= currentYear ) {
			return String( text || '' );
		}
		var latest = Math.max.apply( null, ys );
		return String( text ).replace( /(?<![\p{N}])(19\d{2}|20\d{2})(?![\p{N}])/gu, function ( m, y ) {
			return parseInt( y, 10 ) === latest ? String( currentYear ) : y;
		} );
	};

	Autofix.altFromFilename = function ( url ) {
		var path = String( url || '' ).replace( /[?#].*$/, '' );
		var name = path.split( '/' ).pop().replace( /\.[^.]+$/, '' );
		name = name.replace( /-(\d+x\d+|scaled|rotated|e\d{10,})$/, '' ).replace( /-(\d+x\d+|scaled)$/, '' );
		try {
			name = decodeURIComponent( name );
		} catch ( e ) {}
		name = name.replace( /[\s_-]+/g, ' ' ).trim().replace( /\s\d+$/, '' );
		if ( name === '' || /^(img|dsc|image|imagem|photo|foto|screenshot|captura)?\s?\d*$/i.test( name ) ) {
			return '';
		}
		return ucfirst( name );
	};

	return {
		Text: Text,
		Readability: Readability,
		Seo: Seo,
		Quality: Quality,
		Autofix: Autofix,
		limits: SEO,
		ratingForScore: ratingForScore,
		analyze: function ( data ) {
			var lang = data.lang === 'en' ? 'en' : 'pt';
			var auto = '';
			if ( String( data.description || '' ).trim() === '' && data.auto_description !== false ) {
				auto = Autofix.description( data.content, data.keyphrase, lang );
			}
			var seoData = Object.assign( {}, data, { description: auto || data.description || '', description_is_auto: auto !== '' } );
			return {
				seo: new Seo( lang ).analyze( seoData ),
				readability: new Readability( lang ).analyze( data.content ),
				quality: new Quality( lang ).analyze( {
					content: data.content,
					post_title: data.post_title || '',
					title: data.title || '',
					description: data.description || '',
					site_url: data.site_url || '',
					current_year: data.current_year || new Date().getFullYear(),
					modified_days: data.modified_days === undefined ? null : data.modified_days,
					author_bio: data.author_bio === undefined ? null : data.author_bio,
				} ),
				auto_description: auto,
			};
		},
	};
} );
