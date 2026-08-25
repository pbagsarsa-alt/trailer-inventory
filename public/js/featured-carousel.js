/**
 * TWC Trailer Inventory — Featured trailers carousel.
 *
 * Vanilla JS, no dependencies. Infinite loop that auto-advances one card at a
 * time from right to left. Pauses on hover/focus, respects reduced motion,
 * recomputes on resize, and exposes accessible prev/next controls. The card
 * order is already randomized server-side on each page load.
 *
 * @package LRTI
 */
( function () {
	'use strict';

	var reduceMotion = window.matchMedia
		? window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
		: false;

	function visibleCount( root ) {
		var max = parseInt( root.getAttribute( 'data-columns' ), 10 ) || 4;
		var w = window.innerWidth;
		if ( w < 600 ) {
			return Math.min( 1, max );
		}
		if ( w < 1024 ) {
			return Math.min( 2, max );
		}
		return max;
	}

	function Carousel( root ) {
		this.root = root;
		this.viewport = root.querySelector( '.lrti-carousel-viewport' );
		this.track = root.querySelector( '.lrti-carousel-track' );
		this.prevBtn = root.querySelector( '.lrti-carousel-prev' );
		this.nextBtn = root.querySelector( '.lrti-carousel-next' );
		this.interval = parseInt( root.getAttribute( 'data-interval' ), 10 );
		if ( isNaN( this.interval ) ) {
			this.interval = 4500;
		}
		this.originals = Array.prototype.slice.call(
			this.track.querySelectorAll( '.lrti-carousel-slide' )
		);
		this.count = this.originals.length;
		this.clones = [];
		this.index = 0;
		this.animating = false;
		this.timer = null;

		if ( this.count < 2 ) {
			return;
		}

		this.build();
		this.bind();
		this.start();
	}

	Carousel.prototype.build = function () {
		// Remove any previous clones.
		this.clones.forEach( function ( c ) {
			if ( c.parentNode ) {
				c.parentNode.removeChild( c );
			}
		} );
		this.clones = [];

		this.visible = visibleCount( this.root );
		var pct = 100 / this.visible;

		// Size the original slides.
		this.originals.forEach( function ( slide ) {
			slide.style.flex = '0 0 ' + pct + '%';
			slide.style.maxWidth = pct + '%';
		} );

		// Clone the first `visible` slides to the end for a seamless loop.
		var toClone = Math.min( this.visible, this.count );
		for ( var i = 0; i < toClone; i++ ) {
			var clone = this.originals[ i ].cloneNode( true );
			clone.setAttribute( 'aria-hidden', 'true' );
			clone.classList.add( 'lrti-carousel-clone' );
			clone.style.flex = '0 0 ' + pct + '%';
			clone.style.maxWidth = pct + '%';
			this.track.appendChild( clone );
			this.clones.push( clone );
		}

		if ( this.index > this.count ) {
			this.index = 0;
		}
		this.translate( false );
	};

	Carousel.prototype.translate = function ( animate ) {
		var pct = 100 / this.visible;
		this.track.style.transition = animate && ! reduceMotion
			? 'transform 0.5s ease'
			: 'none';
		this.track.style.transform =
			'translateX(-' + ( this.index * pct ) + '%)';
	};

	Carousel.prototype.next = function () {
		if ( this.animating ) {
			return;
		}
		this.animating = true;
		this.index++;
		this.translate( true );
	};

	Carousel.prototype.prev = function () {
		if ( this.animating ) {
			return;
		}
		this.animating = true;
		if ( this.index === 0 ) {
			// Jump to the mirrored position at the end, then step back.
			this.index = this.count;
			this.translate( false );
			// Force reflow so the next translate animates.
			void this.track.offsetWidth;
		}
		this.index--;
		this.translate( true );
	};

	Carousel.prototype.onTransitionEnd = function () {
		this.animating = false;
		// Seamless wrap: when we've scrolled onto the cloned first slides,
		// jump back to the real first slide with no animation.
		if ( this.index >= this.count ) {
			this.index = 0;
			this.translate( false );
		}
	};

	Carousel.prototype.start = function () {
		if ( reduceMotion || this.interval <= 0 ) {
			return;
		}
		this.stop();
		var self = this;
		this.timer = window.setInterval( function () {
			self.next();
		}, this.interval );
	};

	Carousel.prototype.stop = function () {
		if ( this.timer ) {
			window.clearInterval( this.timer );
			this.timer = null;
		}
	};

	Carousel.prototype.bind = function () {
		var self = this;

		this.track.addEventListener( 'transitionend', function ( e ) {
			if ( e.target === self.track || e.propertyName === 'transform' ) {
				self.onTransitionEnd();
			}
		} );

		if ( this.nextBtn ) {
			this.nextBtn.addEventListener( 'click', function () {
				self.next();
			} );
		}
		if ( this.prevBtn ) {
			this.prevBtn.addEventListener( 'click', function () {
				self.prev();
			} );
		}

		// Pause on hover / focus within.
		this.root.addEventListener( 'mouseenter', function () {
			self.stop();
		} );
		this.root.addEventListener( 'mouseleave', function () {
			self.start();
		} );
		this.root.addEventListener( 'focusin', function () {
			self.stop();
		} );
		this.root.addEventListener( 'focusout', function () {
			self.start();
		} );

		// Rebuild on resize (debounced) so the visible count stays responsive.
		var rt;
		window.addEventListener( 'resize', function () {
			window.clearTimeout( rt );
			rt = window.setTimeout( function () {
				var wasVisible = self.visible;
				if ( visibleCount( self.root ) !== wasVisible ) {
					self.animating = false;
					self.build();
				}
			}, 200 );
		} );
	};

	function init() {
		var nodes = document.querySelectorAll( '.lrti-carousel' );
		Array.prototype.forEach.call( nodes, function ( node ) {
			if ( ! node.getAttribute( 'data-lrti-carousel-ready' ) ) {
				node.setAttribute( 'data-lrti-carousel-ready', '1' );
				// eslint-disable-next-line no-new
				new Carousel( node );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
