/**
 * TWC Trailer Inventory — User Guide interactions.
 *
 * Vanilla JS, loaded only on the User Guide admin page. Provides accordion
 * toggling, a live search filter, and copy-to-clipboard buttons.
 *
 * @package LRTI
 */
( function () {
	'use strict';

	var i18n = window.lrtiGuide || {};

	/* ---- Accordions ---- */
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.lrti-guide-acc-btn' ) : null;
		if ( ! btn ) {
			return;
		}
		var acc = btn.closest( '.lrti-guide-acc' );
		var panel = document.getElementById( btn.getAttribute( 'aria-controls' ) );
		var open = btn.getAttribute( 'aria-expanded' ) === 'true';
		btn.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
		if ( acc ) {
			acc.classList.toggle( 'is-open', ! open );
		}
		if ( panel ) {
			if ( open ) {
				panel.setAttribute( 'hidden', '' );
			} else {
				panel.removeAttribute( 'hidden' );
			}
		}
	} );

	/* ---- Copy buttons ---- */
	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}
		// Fallback for older browsers.
		return new Promise( function ( resolve, reject ) {
			try {
				var ta = document.createElement( 'textarea' );
				ta.value = text;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild( ta );
				ta.focus();
				ta.select();
				document.execCommand( 'copy' );
				document.body.removeChild( ta );
				resolve();
			} catch ( err ) {
				reject( err );
			}
		} );
	}

	function flash( btn ) {
		var label = btn.querySelector( '.lrti-guide-copy-label' );
		var original = label ? label.textContent : '';
		btn.classList.add( 'is-copied' );
		if ( label ) {
			label.textContent = i18n.copied || 'Copied';
		}
		window.setTimeout( function () {
			btn.classList.remove( 'is-copied' );
			if ( label ) {
				label.textContent = original || ( i18n.copy || 'Copy' );
			}
		}, 1500 );
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.lrti-guide-copy' ) : null;
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		var text = btn.getAttribute( 'data-copy' ) || '';
		copyText( text ).then( function () {
			flash( btn );
		} );
	} );

	/* ---- Copy all shortcodes ---- */
	var copyAll = document.querySelector( '[data-copyall]' );
	if ( copyAll ) {
		copyAll.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var tags = Array.prototype.map.call(
				document.querySelectorAll( '.lrti-guide-sc-tag' ),
				function ( el ) {
					return el.textContent.trim();
				}
			);
			copyText( tags.join( '\n' ) ).then( function () {
				var original = copyAll.textContent;
				copyAll.textContent = i18n.copied || 'Copied';
				window.setTimeout( function () {
					copyAll.textContent = original;
				}, 1500 );
			} );
		} );
	}

	/* ---- Live search filter ---- */
	var search = document.getElementById( 'lrti-guide-search' );
	var noResult = document.querySelector( '.lrti-guide-noresult' );

	function normalize( s ) {
		return ( s || '' ).toLowerCase();
	}

	if ( search ) {
		var sections = Array.prototype.slice.call(
			document.querySelectorAll( '[data-guide-section]' )
		);
		var timer = null;

		search.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				var q = normalize( search.value ).trim();
				var anyVisible = false;

				sections.forEach( function ( sec ) {
					if ( '' === q ) {
						sec.style.display = '';
						anyVisible = true;
						return;
					}
					var hay = normalize(
						( sec.getAttribute( 'data-keywords' ) || '' ) + ' ' + sec.textContent
					);
					var match = hay.indexOf( q ) !== -1;
					sec.style.display = match ? '' : 'none';
					if ( match ) {
						anyVisible = true;
						// Open matching accordions so the answer is visible.
						if ( sec.classList.contains( 'lrti-guide-acc' ) ) {
							var b = sec.querySelector( '.lrti-guide-acc-btn' );
							var p = sec.querySelector( '.lrti-guide-acc-panel' );
							if ( b && p ) {
								b.setAttribute( 'aria-expanded', 'true' );
								sec.classList.add( 'is-open' );
								p.removeAttribute( 'hidden' );
							}
						}
					}
				} );

				if ( noResult ) {
					if ( anyVisible ) {
						noResult.setAttribute( 'hidden', '' );
					} else {
						noResult.removeAttribute( 'hidden' );
					}
				}
			}, 150 );
		} );
	}
}() );
