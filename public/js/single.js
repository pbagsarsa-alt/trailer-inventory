/**
 * Little River Trailer Inventory — single trailer gallery (Sprint 4.2).
 *
 * Lightweight, dependency-free gallery: thumbnail navigation, prev/next, a
 * full-size lightbox, and keyboard support. The page remains fully usable
 * without JavaScript (images and links still work); this only enhances.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var gallery = document.getElementById( 'lrti-gallery' );
		if ( ! gallery ) {
			return;
		}

		var stageImg = document.getElementById( 'lrti-stage-img' );
		var thumbs   = Array.prototype.slice.call( gallery.querySelectorAll( '.lrti-gallery-thumb' ) );
		var prevBtn  = document.getElementById( 'lrti-gallery-prev' );
		var nextBtn  = document.getElementById( 'lrti-gallery-next' );

		var lightbox   = document.getElementById( 'lrti-lightbox' );
		var lightImg   = document.getElementById( 'lrti-lightbox-img' );
		var closeBtn   = document.getElementById( 'lrti-lightbox-close' );
		var lbPrevBtn  = document.getElementById( 'lrti-lightbox-prev' );
		var lbNextBtn  = document.getElementById( 'lrti-lightbox-next' );
		var openBtn    = document.getElementById( 'lrti-gallery-open' );

		var current = 0;
		var loadSeq = 0;

		var stage = gallery.querySelector( '.lrti-gallery-stage' );

		function stageUrl( index ) {
			if ( thumbs[ index ] ) {
				return thumbs[ index ].getAttribute( 'data-stage' ) || thumbs[ index ].getAttribute( 'data-full' ) || '';
			}
			if ( stageImg ) {
				return stageImg.getAttribute( 'data-stage' ) || stageImg.getAttribute( 'src' ) || '';
			}
			return '';
		}

		function fullUrl( index ) {
			if ( thumbs[ index ] ) {
				return thumbs[ index ].getAttribute( 'data-full' ) || '';
			}
			// Single-image trailers have no thumbs; use the stage image source.
			if ( stageImg ) {
				return stageImg.getAttribute( 'data-full' ) || stageImg.getAttribute( 'src' ) || '';
			}
			return '';
		}

		// Preload a URL (used for adjacent images and target decode).
		function preload( url ) {
			if ( ! url ) {
				return null;
			}
			var img = new Image();
			img.src = url;
			return img;
		}

		function markThumbs( index ) {
			thumbs.forEach( function ( t, i ) {
				var active = i === index;
				t.classList.toggle( 'is-active', active );
				if ( active ) {
					t.setAttribute( 'aria-current', 'true' );
				} else {
					t.removeAttribute( 'aria-current' );
				}
			} );
		}

		function setActive( index ) {
			if ( thumbs.length ) {
				if ( index < 0 ) {
					index = thumbs.length - 1;
				}
				if ( index >= thumbs.length ) {
					index = 0;
				}
			} else {
				index = 0;
			}
			current = index;
			markThumbs( index );

			var url  = stageUrl( index );
			var full = fullUrl( index );

			// Keep the lightbox in sync if it is currently open.
			if ( lightbox && ! lightbox.hasAttribute( 'hidden' ) && lightImg && full ) {
				lightImg.setAttribute( 'src', full );
			}

			if ( ! url || ! stageImg ) {
				return;
			}
			// Already showing this image — nothing to do.
			if ( stageImg.getAttribute( 'src' ) === url ) {
				preloadAdjacent( index );
				return;
			}

			// Race guard: only the most recent request may update the stage.
			var seq = ++loadSeq;
			if ( stage ) {
				stage.classList.add( 'lrti-gallery-is-loading' );
			}

			var next = new Image();
			var apply = function () {
				if ( seq !== loadSeq ) {
					return; // A newer navigation superseded this one.
				}
				stageImg.setAttribute( 'src', url );
				stageImg.removeAttribute( 'srcset' );
				stageImg.removeAttribute( 'sizes' );
				if ( stage ) {
					stage.classList.remove( 'lrti-gallery-is-loading' );
				}
				preloadAdjacent( index );
			};

			next.onload = function () {
				if ( next.decode ) {
					next.decode().then( apply ).catch( apply );
				} else {
					apply();
				}
			};
			next.onerror = function () {
				// Never block navigation permanently if a load fails.
				if ( seq === loadSeq && stage ) {
					stage.classList.remove( 'lrti-gallery-is-loading' );
				}
			};
			next.src = url;
		}

		function preloadAdjacent( index ) {
			if ( thumbs.length < 2 ) {
				return;
			}
			var nextI = ( index + 1 ) % thumbs.length;
			var prevI = ( index - 1 + thumbs.length ) % thumbs.length;
			preload( stageUrl( nextI ) );
			preload( stageUrl( prevI ) );
		}

		// Preload neighbors once the page is interactive.
		if ( 'requestIdleCallback' in window ) {
			window.requestIdleCallback( function () { preloadAdjacent( current ); } );
		} else {
			window.setTimeout( function () { preloadAdjacent( current ); }, 400 );
		}

		thumbs.forEach( function ( thumb, i ) {
			thumb.addEventListener( 'click', function () {
				setActive( i );
			} );
		} );

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				setActive( current - 1 );
			} );
		}
		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				setActive( current + 1 );
			} );
		}

		// Keyboard navigation across the gallery region.
		gallery.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowLeft' === e.key ) {
				setActive( current - 1 );
			} else if ( 'ArrowRight' === e.key ) {
				setActive( current + 1 );
			}
		} );

		/* ---- Touch swipe on the stage ---- */
		var touchStartX = 0;
		var touchStartY = 0;
		if ( stage && thumbs.length > 1 ) {
			stage.addEventListener( 'touchstart', function ( e ) {
				if ( e.changedTouches && e.changedTouches.length ) {
					touchStartX = e.changedTouches[ 0 ].clientX;
					touchStartY = e.changedTouches[ 0 ].clientY;
				}
			}, { passive: true } );
			stage.addEventListener( 'touchend', function ( e ) {
				if ( ! e.changedTouches || ! e.changedTouches.length ) {
					return;
				}
				var dx = e.changedTouches[ 0 ].clientX - touchStartX;
				var dy = e.changedTouches[ 0 ].clientY - touchStartY;
				if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) {
					setActive( dx < 0 ? current + 1 : current - 1 );
				}
			}, { passive: true } );
		}

		/* ---- Lightbox ---- */

		function openLightbox() {
			if ( ! lightbox || ! lightImg ) {
				return;
			}
			var url = fullUrl( current );
			if ( url ) {
				lightImg.setAttribute( 'src', url );
			}
			lightbox.removeAttribute( 'hidden' );
			if ( closeBtn ) {
				closeBtn.focus();
			}
			document.addEventListener( 'keydown', onKeydown );
		}

		function closeLightbox() {
			if ( ! lightbox ) {
				return;
			}
			lightbox.setAttribute( 'hidden', '' );
			document.removeEventListener( 'keydown', onKeydown );
			if ( openBtn ) {
				openBtn.focus();
			}
		}

		function onKeydown( e ) {
			if ( 'Escape' === e.key ) {
				closeLightbox();
			} else if ( 'ArrowLeft' === e.key ) {
				setActive( current - 1 );
			} else if ( 'ArrowRight' === e.key ) {
				setActive( current + 1 );
			}
		}

		if ( openBtn ) {
			openBtn.addEventListener( 'click', openLightbox );
		}
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', closeLightbox );
		}
		if ( lbPrevBtn ) {
			lbPrevBtn.addEventListener( 'click', function () {
				setActive( current - 1 );
			} );
		}
		if ( lbNextBtn ) {
			lbNextBtn.addEventListener( 'click', function () {
				setActive( current + 1 );
			} );
		}
		if ( lightbox ) {
			lightbox.addEventListener( 'click', function ( e ) {
				if ( e.target === lightbox ) {
					closeLightbox();
				}
			} );
		}
	} );
}() );
