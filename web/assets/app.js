/**
 * Interface da versão web: editor, análise em tempo real, prévia do Google e correções.
 */
( function () {
	'use strict';

	var SEOL = window.SEOL;
	var STORAGE_KEY = 'seol-web-v1';
	var $ = function ( id ) {
		return document.getElementById( id );
	};

	var f = {
		keyphrase: $( 'keyphrase' ),
		lang: $( 'lang' ),
		postTitle: $( 'postTitle' ),
		seoTitle: $( 'seoTitle' ),
		description: $( 'description' ),
		slug: $( 'slug' ),
		siteName: $( 'siteName' ),
		siteUrl: $( 'siteUrl' ),
	};
	var editor = $( 'content' );
	var htmlBox = $( 'contentHtml' );
	var htmlMode = false;

	/* ---------------------------------------------------------------- */
	/* Limpeza do HTML colado (Google Docs, Word, WordPress)             */
	/* ---------------------------------------------------------------- */

	var ALLOWED = { P: 1, H1: 1, H2: 1, H3: 1, H4: 1, H5: 1, H6: 1, UL: 1, OL: 1, LI: 1, A: 1, IMG: 1, STRONG: 1, EM: 1, BR: 1, BLOCKQUOTE: 1, FIGURE: 1, FIGCAPTION: 1, TABLE: 1, THEAD: 1, TBODY: 1, TR: 1, TD: 1, TH: 1 };
	var RENAME = { B: 'STRONG', I: 'EM', DIV: 'P' };
	var DROP = { SCRIPT: 1, STYLE: 1, META: 1, LINK: 1, TITLE: 1, IFRAME: 1, OBJECT: 1, NOSCRIPT: 1, SVG: 1, TEMPLATE: 1 };

	function cleanNode( node, doc ) {
		var out = doc.createDocumentFragment();
		Array.prototype.forEach.call( node.childNodes, function ( child ) {
			if ( child.nodeType === 3 ) {
				out.appendChild( doc.createTextNode( child.nodeValue ) );
				return;
			}
			if ( child.nodeType !== 1 || DROP[ child.tagName ] ) {
				return;
			}
			var tag = RENAME[ child.tagName ] || child.tagName;
			// O Google Docs envolve tudo em <b style="font-weight:normal">: desembrulha.
			if ( child.tagName === 'B' && /font-weight:\s*normal/i.test( child.getAttribute( 'style' ) || '' ) ) {
				tag = null;
			}
			if ( ! tag || ! ALLOWED[ tag ] ) {
				out.appendChild( cleanNode( child, doc ) );
				return;
			}
			var el = doc.createElement( tag );
			if ( tag === 'A' && child.getAttribute( 'href' ) && ! /^\s*javascript:/i.test( child.getAttribute( 'href' ) ) ) {
				el.setAttribute( 'href', child.getAttribute( 'href' ) );
			}
			if ( tag === 'IMG' ) {
				if ( ! child.getAttribute( 'src' ) ) {
					return;
				}
				el.setAttribute( 'src', child.getAttribute( 'src' ) );
				if ( child.hasAttribute( 'alt' ) ) {
					el.setAttribute( 'alt', child.getAttribute( 'alt' ) );
				}
			}
			el.appendChild( cleanNode( child, doc ) );
			out.appendChild( el );
		} );
		return out;
	}

	function sanitize( html ) {
		var parsed = new DOMParser().parseFromString( '<body>' + html + '</body>', 'text/html' );
		var box = document.createElement( 'div' );
		box.appendChild( cleanNode( parsed.body, document ) );
		// Parágrafos vazios deixados por editores.
		box.querySelectorAll( 'p' ).forEach( function ( p ) {
			if ( ! p.textContent.trim() && ! p.querySelector( 'img' ) ) {
				p.remove();
			}
		} );
		return box.innerHTML;
	}

	function escapeHtml( s ) {
		return s.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}

	function textToHtml( text ) {
		var blocks = /\n\s*\n/.test( text ) ? text.split( /\n\s*\n/ ) : text.split( /\n/ );
		return blocks
			.map( function ( b ) {
				return b.trim();
			} )
			.filter( Boolean )
			.map( function ( b ) {
				return '<p>' + escapeHtml( b ).replace( /\n/g, '<br>' ) + '</p>';
			} )
			.join( '' );
	}

	editor.addEventListener( 'paste', function ( e ) {
		var data = e.clipboardData;
		if ( ! data ) {
			return;
		}
		e.preventDefault();
		var html = data.getData( 'text/html' );
		var clean = html ? sanitize( html ) : textToHtml( data.getData( 'text/plain' ) );
		document.execCommand( 'insertHTML', false, clean );
	} );

	/* ---------------------------------------------------------------- */
	/* Estado                                                            */
	/* ---------------------------------------------------------------- */

	function contentHtml() {
		return htmlMode ? htmlBox.value : editor.innerHTML;
	}

	function setContent( html ) {
		var clean = sanitize( html );
		editor.innerHTML = clean;
		htmlBox.value = clean;
	}

	function save() {
		try {
			var state = { content: contentHtml() };
			Object.keys( f ).forEach( function ( k ) {
				state[ k ] = f[ k ].value;
			} );
			localStorage.setItem( STORAGE_KEY, JSON.stringify( state ) );
		} catch ( e ) {}
	}

	function load() {
		try {
			var state = JSON.parse( localStorage.getItem( STORAGE_KEY ) || 'null' );
			if ( state ) {
				Object.keys( f ).forEach( function ( k ) {
					if ( typeof state[ k ] === 'string' ) {
						f[ k ].value = state[ k ];
					}
				} );
				setContent( state.content || '' );
				return true;
			}
		} catch ( e ) {}
		return false;
	}

	function slugify( text ) {
		return SEOL.Text.normalize( text )
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' );
	}

	function effective() {
		var post = f.postTitle.value.trim();
		var site = f.siteName.value.trim();
		var title = f.seoTitle.value.trim() || ( post && site ? post + ' - ' + site : post );
		return {
			title: title,
			slug: f.slug.value.trim() || slugify( post ),
		};
	}

	/* ---------------------------------------------------------------- */
	/* Renderização                                                      */
	/* ---------------------------------------------------------------- */

	var LABELS = { good: 'Bom', ok: 'Pode melhorar', bad: 'Problema' };
	var FIXABLE = { title: 1, description: 1, slug: 1, alt: 1 };

	function renderList( ul, results ) {
		ul.innerHTML = '';
		results.forEach( function ( r ) {
			var li = document.createElement( 'li' );
			li.className = 'result is-' + r.rating;
			var dot = document.createElement( 'span' );
			dot.className = 'dot';
			dot.title = LABELS[ r.rating ];
			li.appendChild( dot );
			var body = document.createElement( 'span' );
			body.className = 'result-body';
			var strong = document.createElement( 'strong' );
			strong.textContent = r.label + ': ';
			body.appendChild( strong );
			body.appendChild( document.createTextNode( r.message ) );
			li.appendChild( body );
			if ( r.fix && FIXABLE[ r.fix ] && r.rating !== 'good' ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'button button-small';
				b.textContent = 'Corrigir';
				b.setAttribute( 'data-fix', r.fix );
				li.appendChild( b );
			}
			ul.appendChild( li );
		} );
	}

	function counts( results ) {
		var bad = results.filter( function ( r ) {
			return r.rating === 'bad';
		} ).length;
		var ok = results.filter( function ( r ) {
			return r.rating === 'ok';
		} ).length;
		return bad || ok ? '(' + ( bad ? bad + ' ✕' : '' ) + ( bad && ok ? ' · ' : '' ) + ( ok ? ok + ' !' : '' ) + ')' : '';
	}

	function renderScore( el, analysis, empty ) {
		el.className = 'score' + ( empty ? '' : ' is-' + analysis.rating );
		el.querySelector( 'strong' ).textContent = empty ? '–' : analysis.score;
		el.querySelector( '.value' ).style.strokeDasharray = ( empty ? 0 : analysis.score ) + ' 100';
	}

	function meter( name, value, min, max ) {
		var m = document.querySelector( '[data-meter="' + name + '"]' );
		m.querySelector( 'span' ).style.width = Math.min( 100, ( value / max ) * 100 ) + '%';
		m.className = 'meter ' + ( value === 0 ? 'is-bad' : value < min || value > max ? 'is-ok' : 'is-good' );
	}

	function hostOf( url ) {
		var m = String( url ).match( /^(?:https?:)?\/\/([^\/?#]+)/i );
		return m ? m[ 1 ].replace( /^www\./, '' ) : '';
	}

	function analyze() {
		var html = contentHtml();
		var eff = effective();
		var lang = f.lang.value;
		var empty = ! SEOL.Text.strip( html ) && ! f.keyphrase.value.trim() && ! eff.title;

		var res = SEOL.analyze( {
			lang: lang,
			keyphrase: f.keyphrase.value,
			title: eff.title,
			description: f.description.value,
			slug: eff.slug,
			content: html,
			site_url: f.siteUrl.value,
		} );

		renderScore( $( 'scoreSeo' ), res.seo, empty );
		renderScore( $( 'scoreRead' ), res.readability, empty );
		if ( empty ) {
			[ 'resultsSeo', 'resultsRead' ].forEach( function ( id ) {
				$( id ).innerHTML = '<li class="empty">Escreva ou cole um texto para ver a análise, ou clique em “Carregar exemplo”.</li>';
			} );
			$( 'countSeo' ).textContent = '';
			$( 'countRead' ).textContent = '';
		} else {
			renderList( $( 'resultsSeo' ), res.seo.results );
			renderList( $( 'resultsRead' ), res.readability.results );
			$( 'countSeo' ).textContent = counts( res.seo.results );
			$( 'countRead' ).textContent = counts( res.readability.results );
		}

		var st = res.readability.stats;
		$( 'stats' ).textContent = empty
			? ''
			: [ st.words + ' palavras', st.sentences + ' frases', st.reading_time + ' min de leitura', st.flesch === null ? '' : 'Flesch ' + String( st.flesch ).replace( '.', ',' ) ].filter( Boolean ).join( ' · ' );

		// Prévia no Google.
		var host = hostOf( f.siteUrl.value ) || 'meusite.com.br';
		$( 'serpName' ).textContent = f.siteName.value.trim() || host;
		$( 'serpUrl' ).textContent = 'https://' + host + ( eff.slug ? ' › ' + eff.slug : '' );
		$( 'serpTitle' ).textContent = eff.title || 'Título SEO do seu post';
		var desc = f.description.value.trim() || res.auto_description;
		var serpDesc = $( 'serpDesc' );
		serpDesc.textContent = desc || 'A meta descrição aparece aqui. Escreva um resumo de 120 a 156 caracteres que convide ao clique.';
		serpDesc.classList.toggle( 'is-auto', ! f.description.value.trim() && !! res.auto_description );

		var px = SEOL.Text.pixelWidth( eff.title );
		meter( 'title', px, SEOL.limits.TITLE_MIN_PX, SEOL.limits.TITLE_MAX_PX );
		$( 'titleInfo' ).textContent = eff.title ? Array.from( eff.title ).length + ' caracteres · ~' + px + ' px de 580 px' : '';
		var dl = Array.from( f.description.value.trim() ).length;
		$( 'descCount' ).textContent = dl;
		meter( 'description', dl, SEOL.limits.DESC_MIN_CHARS, SEOL.limits.DESC_MAX_CHARS );
		f.seoTitle.placeholder = eff.title || '';
		f.slug.placeholder = slugify( f.postTitle.value ) || 'cafe-especial';
	}

	var timer = null;
	function schedule() {
		clearTimeout( timer );
		timer = setTimeout( function () {
			analyze();
			save();
		}, 200 );
	}

	/* ---------------------------------------------------------------- */
	/* Correções automáticas                                             */
	/* ---------------------------------------------------------------- */

	function toast( msg ) {
		var t = document.createElement( 'div' );
		t.className = 'toast';
		t.setAttribute( 'role', 'status' );
		t.textContent = msg;
		document.body.appendChild( t );
		setTimeout( function () {
			t.remove();
		}, 3500 );
	}

	function fixAlts() {
		var box = document.createElement( 'div' );
		box.innerHTML = contentHtml();
		var fixed = 0;
		box.querySelectorAll( 'img' ).forEach( function ( img ) {
			if ( ( img.getAttribute( 'alt' ) || '' ).trim() ) {
				return;
			}
			var alt = SEOL.Autofix.altFromFilename( img.getAttribute( 'src' ) ) || f.postTitle.value.trim() || f.keyphrase.value.trim();
			if ( alt ) {
				img.setAttribute( 'alt', alt );
				fixed++;
			}
		} );
		if ( fixed ) {
			setContent( box.innerHTML );
		}
		return fixed;
	}

	function applyFix( which ) {
		var lang = f.lang.value;
		var kp = f.keyphrase.value.trim();
		var post = f.postTitle.value.trim() || f.seoTitle.value.trim();
		var all = which === 'all';
		var done = [];

		if ( ( all || which === 'title' ) && post ) {
			f.seoTitle.value = SEOL.Autofix.title( post, f.siteName.value.trim(), '-', kp );
			done.push( 'título' );
		}
		var dl = Array.from( f.description.value.trim() ).length;
		if ( which === 'description' || ( all && ( dl < SEOL.limits.DESC_MIN_CHARS || dl > SEOL.limits.DESC_MAX_CHARS ) ) ) {
			var d = SEOL.Autofix.description( contentHtml(), kp, lang );
			if ( d ) {
				f.description.value = d;
				done.push( 'meta descrição' );
			}
		}
		if ( ( all || which === 'slug' ) && post ) {
			f.slug.value = SEOL.Autofix.slug( post, kp, lang );
			done.push( 'URL' );
		}
		if ( all || which === 'alt' ) {
			var n = fixAlts();
			if ( n ) {
				done.push( n + ' texto(s) alternativo(s)' );
			}
		}
		analyze();
		save();
		toast( done.length ? 'Corrigido: ' + done.join( ', ' ) + '. Revise os textos gerados.' : 'Preencha o título do post e o conteúdo para gerar as correções.' );
	}

	/* ---------------------------------------------------------------- */
	/* Exemplo                                                           */
	/* ---------------------------------------------------------------- */

	var EXAMPLE = {
		keyphrase: 'café especial',
		postTitle: 'Como escolher café especial para a sua casa',
		seoTitle: '',
		description: '',
		slug: 'como-escolher-o-melhor-cafe-especial-para-a-sua-casa',
		siteName: 'Blog do Café',
		siteUrl: 'https://blogdocafe.com.br',
		content:
			'<p>Escolher um café especial muda a sua manhã. Neste guia, você aprende a avaliar torra, aroma e origem dos grãos antes de comprar.</p>' +
			'<h2>O que é café especial</h2>' +
			'<p>O café especial recebe pelo menos 80 pontos na escala da Specialty Coffee Association. Além disso, ele tem origem rastreável e grãos sem defeitos. Por isso, o sabor é mais doce e equilibrado. Segundo a <a href="https://sca.coffee">SCA</a>, a avaliação considera aroma, acidez, corpo e finalização.</p>' +
			'<p>As fazendas que produzem esse tipo de grão foram certificadas por avaliadores independentes, e os lotes foram classificados um a um. O processo inteiro foi acompanhado de perto, desde a colheita manual até a secagem lenta em terreiros suspensos, o que encarece bastante o produto final.</p>' +
			'<img src="https://blogdocafe.com.br/wp-content/uploads/2026/10/xicara-de-cafe-especial-1024x768.jpg">' +
			'<h2>Como escolher</h2>' +
			'<ul><li>Veja a data de torra no pacote.</li><li>Prefira embalagens com válvula.</li><li>Compre grãos inteiros e moa na hora.</li></ul>' +
			'<p>Primeiro, observe a data de torra. O ideal é consumir o café até 30 dias depois dela. Em seguida, cheire os grãos. Um bom aroma indica frescor. Para mais detalhes, <a href="/guia-de-moedores/">clique aqui</a>.</p>',
	};

	/* ---------------------------------------------------------------- */
	/* Eventos                                                           */
	/* ---------------------------------------------------------------- */

	Object.keys( f ).forEach( function ( k ) {
		f[ k ].addEventListener( 'input', schedule );
	} );
	editor.addEventListener( 'input', schedule );
	htmlBox.addEventListener( 'input', schedule );

	document.querySelector( '.toolbar' ).addEventListener( 'click', function ( e ) {
		var b = e.target.closest( 'button' );
		if ( ! b ) {
			return;
		}
		if ( b.id === 'toggleHtml' ) {
			htmlMode = ! htmlMode;
			if ( htmlMode ) {
				htmlBox.value = editor.innerHTML;
			} else {
				setContent( htmlBox.value );
			}
			editor.hidden = htmlMode;
			htmlBox.hidden = ! htmlMode;
			b.setAttribute( 'aria-pressed', String( htmlMode ) );
			schedule();
			return;
		}
		if ( htmlMode ) {
			return;
		}
		editor.focus();
		var cmd = b.getAttribute( 'data-cmd' );
		var arg = b.getAttribute( 'data-arg' );
		if ( cmd === 'createLink' ) {
			arg = window.prompt( 'Endereço do link (ex.: https://… ou /pagina-interna/):' );
			if ( ! arg ) {
				return;
			}
		}
		if ( cmd === 'insertImage' ) {
			var src = window.prompt( 'Endereço da imagem:' );
			if ( ! src ) {
				return;
			}
			var alt = window.prompt( 'Texto alternativo (descreva a imagem):' ) || '';
			document.execCommand( 'insertHTML', false, '<img src="' + src.replace( /"/g, '&quot;' ) + '" alt="' + escapeHtml( alt ).replace( /"/g, '&quot;' ) + '">' );
			schedule();
			return;
		}
		document.execCommand( cmd, false, cmd === 'formatBlock' ? '<' + arg + '>' : arg );
		schedule();
	} );

	document.querySelector( '.tabs' ).addEventListener( 'click', function ( e ) {
		var tab = e.target.closest( '[data-tab]' );
		if ( ! tab ) {
			return;
		}
		document.querySelectorAll( '.tabs [data-tab]' ).forEach( function ( t ) {
			t.setAttribute( 'aria-selected', String( t === tab ) );
		} );
		$( 'resultsSeo' ).hidden = tab.getAttribute( 'data-tab' ) !== 'seo';
		$( 'resultsRead' ).hidden = tab.getAttribute( 'data-tab' ) !== 'readability';
	} );

	document.querySelector( '.results-panel' ).addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-fix]' );
		if ( b ) {
			applyFix( b.getAttribute( 'data-fix' ) );
		}
	} );

	$( 'fixAll' ).addEventListener( 'click', function () {
		applyFix( 'all' );
	} );

	$( 'loadExample' ).addEventListener( 'click', function () {
		Object.keys( EXAMPLE ).forEach( function ( k ) {
			if ( f[ k ] ) {
				f[ k ].value = EXAMPLE[ k ];
			}
		} );
		f.lang.value = 'pt';
		setContent( EXAMPLE.content );
		analyze();
		save();
	} );

	$( 'clearAll' ).addEventListener( 'click', function () {
		if ( ! window.confirm( 'Apagar o texto e todos os campos?' ) ) {
			return;
		}
		Object.keys( f ).forEach( function ( k ) {
			if ( k !== 'lang' && k !== 'siteName' && k !== 'siteUrl' ) {
				f[ k ].value = '';
			}
		} );
		setContent( '' );
		analyze();
		save();
	} );

	// Garante o salvamento mesmo se a página for fechada antes do intervalo do schedule().
	window.addEventListener( 'pagehide', save );
	document.addEventListener( 'visibilitychange', function () {
		if ( document.visibilityState === 'hidden' ) {
			save();
		}
	} );

	if ( ! load() && /^en\b/i.test( navigator.language || '' ) ) {
		f.lang.value = 'en';
	}
	analyze();
} )();
