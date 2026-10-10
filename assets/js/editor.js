/**
 * Painel SEO & Legibilidade: análise em tempo real, prévia do Google e correções automáticas.
 * Funciona no editor de blocos (Gutenberg) e no editor clássico, sem etapa de build.
 */
( function () {
	'use strict';

	var cfg = window.SEOL || {};
	var i18n = cfg.i18n || {};
	var root = document.querySelector( '.seol' );
	if ( ! root ) {
		return;
	}

	var postId = parseInt( root.getAttribute( 'data-post-id' ), 10 );
	var $ = function ( sel ) {
		return root.querySelector( sel );
	};
	var fields = {
		keyphrase: $( '#seol_keyphrase' ),
		title: $( '#seol_title' ),
		description: $( '#seol_description' ),
		slug: $( '#seol_slug' ),
	};

	/* ---------------------------------------------------------------- */
	/* Acesso ao editor                                                  */
	/* ---------------------------------------------------------------- */

	function blockEditor() {
		return window.wp && wp.data && wp.data.select( 'core/editor' ) && typeof wp.data.select( 'core/editor' ).getEditedPostContent === 'function' && document.body.classList.contains( 'block-editor-page' )
			? wp.data.select( 'core/editor' )
			: null;
	}

	function getPost() {
		var be = blockEditor();
		if ( be ) {
			var slug = be.getEditedPostAttribute( 'slug' ) || ( be.getEditedPostSlug ? be.getEditedPostSlug() : '' );
			return {
				post_title: be.getEditedPostAttribute( 'title' ) || '',
				content: be.getEditedPostContent() || '',
				slug: slug || '',
			};
		}
		var content = '';
		if ( window.tinymce && tinymce.get( 'content' ) && ! tinymce.get( 'content' ).isHidden() ) {
			content = tinymce.get( 'content' ).getContent();
		} else if ( document.getElementById( 'content' ) ) {
			content = document.getElementById( 'content' ).value;
		}
		var slugEl = document.getElementById( 'post_name' ) || document.getElementById( 'editable-post-name-full' );
		return {
			post_title: ( document.getElementById( 'title' ) || {} ).value || '',
			content: content,
			slug: fields.slug.value || ( slugEl ? slugEl.value || slugEl.textContent : '' ),
		};
	}

	function payload() {
		var p = getPost();
		p.post_id = postId;
		p.keyphrase = fields.keyphrase.value;
		p.title = fields.title.value;
		p.description = fields.description.value;
		return p;
	}

	function request( path, body ) {
		return fetch( cfg.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( body ),
		} ).then( function ( r ) {
			if ( ! r.ok ) {
				throw new Error( r.status );
			}
			return r.json();
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Renderização                                                      */
	/* ---------------------------------------------------------------- */

	function el( tag, cls, text ) {
		var e = document.createElement( tag );
		if ( cls ) {
			e.className = cls;
		}
		if ( text !== undefined ) {
			e.textContent = text;
		}
		return e;
	}

	function renderResults( key, analysis ) {
		var list = root.querySelector( '[data-results="' + key + '"]' );
		list.innerHTML = '';
		analysis.results.forEach( function ( r ) {
			var li = el( 'li', 'seol-result is-' + r.rating );
			var dot = el( 'span', 'seol-dot' );
			dot.title = i18n[ r.rating ] || r.rating;
			li.appendChild( dot );
			var body = el( 'span', 'seol-result-body' );
			body.appendChild( el( 'strong', '', r.label + ': ' ) );
			body.appendChild( document.createTextNode( r.message ) );
			li.appendChild( body );
			if ( r.fix && r.rating !== 'good' ) {
				var b = el( 'button', 'button button-small seol-fix', i18n.fix );
				b.type = 'button';
				b.setAttribute( 'data-fix', r.fix );
				li.appendChild( b );
			}
			list.appendChild( li );
		} );
		var badge = root.querySelector( '[data-score="' + key + '"]' );
		badge.className = 'seol-badge is-' + analysis.rating;
		badge.querySelector( 'strong' ).textContent = analysis.score + '/100';
	}

	function setMeter( name, value, min, max ) {
		var m = root.querySelector( '[data-meter="' + name + '"]' );
		var bar = m.querySelector( 'span' );
		var pct = Math.min( 100, ( value / max ) * 100 );
		bar.style.width = pct + '%';
		m.className = 'seol-meter ' + ( value === 0 ? 'is-bad' : value < min || value > max ? 'is-ok' : 'is-good' );
	}

	function renderPreview( p ) {
		$( '.seol-snippet-name' ).textContent = p.site_name;
		$( '.seol-snippet-url' ).textContent = ( p.url || '' ).replace( /^https?:\/\/(www\.)?/, '' ).replace( /\/$/, '' ).split( '/' ).join( ' › ' );
		$( '.seol-snippet-title' ).textContent = p.title;
		var d = $( '.seol-snippet-desc' );
		d.textContent = p.description;
		d.classList.toggle( 'is-auto', !! p.description_auto );
		d.title = p.description_auto ? i18n.autoDesc : '';
		setMeter( 'title', p.title_px, 200, 580 );
	}

	function renderStats( s ) {
		var parts = [ s.words + ' ' + i18n.words, s.reading_time + ' ' + i18n.minutes ];
		if ( s.flesch !== null ) {
			parts.push( 'Flesch ' + String( s.flesch ).replace( '.', ',' ) );
		}
		$( '.seol-stats' ).textContent = parts.join( ' · ' );
	}

	function updateDescCount() {
		var n = fields.description.value.length;
		$( '.seol-desc-count' ).textContent = n;
		setMeter( 'description', n, 120, 156 );
	}

	/* ---------------------------------------------------------------- */
	/* Análise                                                           */
	/* ---------------------------------------------------------------- */

	var timer = null;
	var lastKey = '';
	function analyze( force ) {
		var data = payload();
		var key = JSON.stringify( data );
		if ( ! force && key === lastKey ) {
			return;
		}
		lastKey = key;
		request( '/analyze', data )
			.then( function ( res ) {
				renderResults( 'seo', res.seo );
				renderResults( 'readability', res.readability );
				renderPreview( res.preview );
				renderStats( res.readability.stats );
			} )
			.catch( function () {
				root.querySelectorAll( '.seol-loading' ).forEach( function ( li ) {
					li.textContent = i18n.error;
				} );
			} );
	}

	function schedule() {
		clearTimeout( timer );
		timer = setTimeout( analyze, 900 );
	}

	/* ---------------------------------------------------------------- */
	/* Correções automáticas                                             */
	/* ---------------------------------------------------------------- */

	function imageBlocks( blocks, out ) {
		( blocks || [] ).forEach( function ( b ) {
			if ( b.name === 'core/image' && b.attributes.url && ! ( b.attributes.alt || '' ).trim() ) {
				out.push( b );
			}
			imageBlocks( b.innerBlocks, out );
		} );
		return out;
	}

	function applyFix( which ) {
		var data = payload();
		var be = blockEditor();
		var images = be ? imageBlocks( wp.data.select( 'core/block-editor' ).getBlocks(), [] ) : [];
		data.images = images.map( function ( b ) {
			return b.attributes.url;
		} );
		return request( '/fix', data ).then( function ( fix ) {
			var all = which === 'all';
			if ( ( all || which === 'title' ) && fix.title ) {
				fields.title.value = fix.title;
			}
			if ( ( all || which === 'description' ) && fix.description && ( which === 'description' || ! fields.description.value.trim() || fields.description.value.length > 156 || fields.description.value.length < 120 ) ) {
				fields.description.value = fix.description;
			}
			if ( ( all || which === 'slug' ) && fix.slug ) {
				if ( be ) {
					wp.data.dispatch( 'core/editor' ).editPost( { slug: fix.slug } );
				} else {
					// Editor clássico: aplicado ao salvar (filtro wp_insert_post_data).
					fields.slug.value = fix.slug;
				}
			}
			if ( all || which === 'alt' ) {
				if ( be ) {
					images.forEach( function ( b, i ) {
						if ( fix.alts[ i ] ) {
							wp.data.dispatch( 'core/block-editor' ).updateBlockAttributes( b.clientId, { alt: fix.alts[ i ] } );
						}
					} );
				} else if ( which === 'alt' ) {
					window.alert( i18n.altClassic );
				}
			}
			updateDescCount();
			analyze( true );
			if ( be && wp.data.dispatch( 'core/notices' ) ) {
				wp.data.dispatch( 'core/notices' ).createNotice( 'success', i18n.fixed, { type: 'snackbar' } );
			}
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Eventos                                                           */
	/* ---------------------------------------------------------------- */

	root.querySelectorAll( '.seol-tabs button' ).forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			root.querySelectorAll( '.seol-tabs button' ).forEach( function ( t ) {
				t.classList.toggle( 'is-active', t === tab );
			} );
			root.querySelectorAll( '.seol-panel' ).forEach( function ( p ) {
				p.hidden = p.getAttribute( 'data-panel' ) !== tab.getAttribute( 'data-tab' );
			} );
		} );
	} );

	root.addEventListener( 'click', function ( e ) {
		var t = e.target;
		if ( t.classList.contains( 'seol-fix' ) ) {
			applyFix( t.getAttribute( 'data-fix' ) );
		} else if ( t.classList.contains( 'seol-fix-all' ) ) {
			applyFix( 'all' );
		}
	} );

	[ fields.keyphrase, fields.title, fields.description ].forEach( function ( f ) {
		f.addEventListener( 'input', schedule );
	} );
	fields.description.addEventListener( 'input', updateDescCount );

	if ( blockEditor() ) {
		var prev = '';
		wp.data.subscribe( function () {
			var be = blockEditor();
			if ( ! be ) {
				return;
			}
			// Só reanalisa quando título, conteúdo ou slug mudam.
			var content = be.getEditedPostContent();
			var cur = be.getEditedPostAttribute( 'title' ) + '|' + be.getEditedPostAttribute( 'slug' ) + '|' + content.length + '|' + content.slice( -200 );
			if ( cur !== prev ) {
				prev = cur;
				schedule();
			}
		} );
	} else {
		var title = document.getElementById( 'title' );
		if ( title ) {
			title.addEventListener( 'input', schedule );
		}
		var textarea = document.getElementById( 'content' );
		if ( textarea ) {
			textarea.addEventListener( 'input', schedule );
		}
		var hookTiny = function () {
			if ( window.tinymce && tinymce.get( 'content' ) ) {
				tinymce.get( 'content' ).on( 'keyup change SetContent', schedule );
			} else {
				setTimeout( hookTiny, 500 );
			}
		};
		hookTiny();
	}

	updateDescCount();
	analyze( true );
} )();
