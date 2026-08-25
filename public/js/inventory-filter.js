/**
 * Little River Trailer Inventory — AJAX filtering (Sprint 4.3).
 *
 * Progressive enhancement: every control works without JavaScript via standard
 * GET submission. When JS is available this intercepts the forms to update
 * results in place, sync the URL (pushState/popstate), manage active-filter
 * chips, reset, and a keyboard-accessible mobile filter toggle. Each inventory
 * instance is scoped independently so multiple grids never collide.
 */
( function () {
	'use strict';

	if ( typeof window.lrtiFilter === 'undefined' ) {
		return;
	}

	var CFG = window.lrtiFilter;

	// Filter field names that live in the URL / request.
	var BASE_FIELDS = [
		'keyword', 'manufacturer', 'type', 'condition', 'availability',
		'min_price', 'max_price', 'min_year', 'max_year', 'min_gvwr', 'max_gvwr',
		'axles', 'pull_type', 'featured', 'sale', 'sort', 'paged'
	];
	// Spec accordion fields are appended dynamically (deduped).
	var SPEC_FIELDS = ( CFG && CFG.specFields ) ? CFG.specFields : [];
	var FIELDS = BASE_FIELDS.slice();
	SPEC_FIELDS.forEach( function ( n ) {
		if ( FIELDS.indexOf( n ) === -1 ) {
			FIELDS.push( n );
		}
	} );

	function debounce( fn, wait ) {
		var t;
		return function () {
			var ctx = this, args = arguments;
			clearTimeout( t );
			t = setTimeout( function () {
				fn.apply( ctx, args );
			}, wait );
		};
	}

	function InventoryApp( root ) {
		this.root = root;
		this.instance = root.getAttribute( 'data-instance' ) || 'archive';
		this.isArchive = ( this.instance === 'archive' );
		try {
			this.base = JSON.parse( root.getAttribute( 'data-atts' ) || '{}' );
		} catch ( e ) {
			this.base = {};
		}
		this.controller = null;
		this.lastKey = '';
		this.init();
	}

	InventoryApp.prototype.results = function () {
		return this.root.querySelector( '.lrti-results' );
	};

	InventoryApp.prototype.form = function () {
		// The sidebar form may live inside this root, or in a standalone
		// [trailer_filters] element that targets this instance.
		var inside = this.root.querySelector( '.lrti-filter-form' );
		if ( inside ) {
			return inside;
		}
		var external = document.querySelector( '.lrti-filters-standalone[data-target="' + this.instance + '"] .lrti-filter-form' );
		return external || null;
	};

	InventoryApp.prototype.init = function () {
		var self = this;

		// Mobile filter toggle.
		var toggle = this.root.querySelector( '.lrti-filter-toggle' );
		var form = this.form();
		if ( toggle && form ) {
			toggle.addEventListener( 'click', function () {
				var open = form.classList.toggle( 'is-open' );
				toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				toggle.textContent = open ? CFG.i18n.hideFilters : CFG.i18n.showFilters;
			} );
		}

		// Collapsible filter groups on mobile (tap a legend to toggle).
		if ( form ) {
			form.querySelectorAll( '.lrti-filter-legend' ).forEach( function ( legend ) {
				legend.setAttribute( 'tabindex', '0' );
				legend.setAttribute( 'role', 'button' );
				var toggleSection = function () {
					var section = legend.closest( '.lrti-filter-section' );
					if ( section ) {
						section.classList.toggle( 'is-collapsed' );
					}
				};
				legend.addEventListener( 'click', toggleSection );
				legend.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' || e.key === ' ' ) {
						e.preventDefault();
						toggleSection();
					}
				} );
			} );
		}

		// Intercept the filter form (Apply + implicit submit).
		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				self.collectAndGo( 1 );
			} );

			// Live updates: debounce keyword, react to selects/checkboxes.
			var kw = form.querySelector( 'input[name="keyword"]' );
			if ( kw ) {
				kw.addEventListener( 'input', debounce( function () {
					self.collectAndGo( 1 );
				}, 450 ) );
			}
			form.querySelectorAll( 'select, input[type="checkbox"]' ).forEach( function ( el ) {
				el.addEventListener( 'change', function () {
					self.collectAndGo( 1 );
				} );
			} );
			form.querySelectorAll( 'input[type="number"]' ).forEach( function ( el ) {
				el.addEventListener( 'change', debounce( function () {
					self.collectAndGo( 1 );
				}, 350 ) );
			} );

			// Specifications accordion: toggle group panels (keyboard accessible).
			form.addEventListener( 'click', function ( e ) {
				var t = e.target.closest ? e.target.closest( '.lrti-spec-filter-toggle' ) : null;
				if ( ! t ) {
					return;
				}
				e.preventDefault();
				var expanded = t.getAttribute( 'aria-expanded' ) === 'true';
				var panelId = t.getAttribute( 'aria-controls' );
				var panel = panelId ? document.getElementById( panelId ) : null;
				var group = t.closest ? t.closest( '.lrti-spec-filter-group' ) : null;
				t.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
				if ( group ) {
					group.classList.toggle( 'is-open', ! expanded );
				}
				if ( panel ) {
					if ( expanded ) {
						panel.setAttribute( 'hidden', '' );
					} else {
						panel.removeAttribute( 'hidden' );
					}
				}
			} );
		}

		// Delegated handlers on the results region (sort, pagination, chips, reset).
		this.root.addEventListener( 'click', function ( e ) {
			var chip = e.target.closest( '.lrti-chip-remove' );
			if ( chip && self.root.contains( chip ) ) {
				e.preventDefault();
				self.removeFilter( chip.getAttribute( 'data-filter' ) );
				return;
			}
			var reset = e.target.closest( '.lrti-reset' );
			if ( reset ) {
				e.preventDefault();
				self.reset();
				return;
			}
			var page = e.target.closest( '.lrti-pagination a' );
			if ( page ) {
				e.preventDefault();
				self.goToPage( page.getAttribute( 'href' ) );
				return;
			}
		} );

		this.root.addEventListener( 'change', function ( e ) {
			var sortSel = e.target.closest( '.lrti-sort-select' );
			if ( sortSel ) {
				e.preventDefault();
				self.collectAndGo( 1 );
			}
		} );

		this.root.addEventListener( 'submit', function ( e ) {
			if ( e.target.closest( '.lrti-sort' ) ) {
				e.preventDefault();
				self.collectAndGo( 1 );
			}
		} );

		// Back/forward navigation (archive owns the URL).
		if ( this.isArchive ) {
			window.addEventListener( 'popstate', function () {
				self.applyFromUrl();
			} );
		}
	};

	// Gather current filter values from the form + sort select.
	InventoryApp.prototype.collect = function ( paged ) {
		var data = {};
		var form = this.form();
		if ( form ) {
			FIELDS.forEach( function ( name ) {
				var el = form.querySelector( '[name="' + name + '"]' );
				if ( ! el ) {
					return;
				}
				if ( el.type === 'checkbox' ) {
					if ( el.checked ) {
						data[ name ] = '1';
					}
				} else if ( el.value !== '' ) {
					data[ name ] = el.value;
				}
			} );
		}
		// Sort select lives in the results header.
		var sortSel = this.root.querySelector( '.lrti-sort-select' );
		if ( sortSel && sortSel.value ) {
			data.sort = sortSel.value;
		}
		if ( paged && paged > 1 ) {
			data.paged = String( paged );
		}
		return data;
	};

	InventoryApp.prototype.collectAndGo = function ( paged ) {
		this.request( this.collect( paged ) );
	};

	InventoryApp.prototype.removeFilter = function ( key ) {
		var form = this.form();
		if ( form ) {
			if ( key === 'price' ) {
				this.clearField( form, 'min_price' );
				this.clearField( form, 'max_price' );
			} else if ( key === 'year' ) {
				this.clearField( form, 'min_year' );
				this.clearField( form, 'max_year' );
			} else if ( form.querySelector( '[name="' + key + '"]' ) ) {
				// Direct field (select / checkbox / text).
				this.clearField( form, key );
			} else {
				// Range chip whose key is the base name (min_<key>/max_<key>).
				this.clearField( form, 'min_' + key );
				this.clearField( form, 'max_' + key );
			}
		}
		this.collectAndGo( 1 );
	};

	InventoryApp.prototype.clearField = function ( form, name ) {
		var el = form.querySelector( '[name="' + name + '"]' );
		if ( ! el ) {
			return;
		}
		if ( el.type === 'checkbox' ) {
			el.checked = false;
		} else {
			el.value = '';
		}
	};

	InventoryApp.prototype.reset = function () {
		var form = this.form();
		if ( form ) {
			form.reset();
			form.querySelectorAll( 'input, select' ).forEach( function ( el ) {
				if ( el.type === 'checkbox' ) {
					el.checked = false;
				} else if ( el.name !== 'sort' ) {
					el.value = '';
				}
			} );
		}
		var sortSel = this.root.querySelector( '.lrti-sort-select' );
		if ( sortSel ) {
			sortSel.value = 'newest';
		}
		this.request( {} );
	};

	InventoryApp.prototype.goToPage = function ( href ) {
		var paged = 1;
		var m = href.match( /[?&]paged=(\d+)/ );
		if ( m ) {
			paged = parseInt( m[ 1 ], 10 );
		} else {
			var m2 = href.match( /\/page\/(\d+)/ );
			if ( m2 ) {
				paged = parseInt( m2[ 1 ], 10 );
			}
		}
		var data = this.collect( paged );
		this.request( data, true );
	};

	// Parse the current URL into the form + sort, then refresh (popstate).
	InventoryApp.prototype.applyFromUrl = function () {
		var params = new URLSearchParams( window.location.search );
		var form = this.form();
		if ( form ) {
			FIELDS.forEach( function ( name ) {
				var el = form.querySelector( '[name="' + name + '"]' );
				if ( ! el ) {
					return;
				}
				var val = params.get( name );
				if ( el.type === 'checkbox' ) {
					el.checked = ( val === '1' );
				} else if ( name !== 'sort' && name !== 'paged' ) {
					el.value = val || '';
				}
			} );
		}
		var sortSel = this.root.querySelector( '.lrti-sort-select' );
		if ( sortSel ) {
			sortSel.value = params.get( 'sort' ) || 'newest';
		}
		var data = {};
		params.forEach( function ( v, k ) {
			if ( FIELDS.indexOf( k ) !== -1 && v !== '' ) {
				data[ k ] = v;
			}
		} );
		this.request( data, false, true );
	};

	// Perform the AJAX request for this instance.
	InventoryApp.prototype.request = function ( data, scroll, skipPush ) {
		var self = this;
		var results = this.results();
		if ( ! results ) {
			return;
		}

		// Prevent duplicate identical requests.
		var key = JSON.stringify( data );
		if ( key === this.lastKey && ! scroll ) {
			return;
		}
		this.lastKey = key;

		// Cancel any in-flight request.
		if ( this.controller ) {
			this.controller.abort();
		}
		this.controller = ( typeof AbortController !== 'undefined' ) ? new AbortController() : null;

		results.classList.add( 'is-loading' );

		var body = new URLSearchParams();
		body.append( 'action', CFG.action );
		body.append( 'nonce', CFG.nonce );
		body.append( 'instance', this.instance );
		body.append( 'base', JSON.stringify( this.base.base || {} ) );
		body.append( 'columns', String( this.base.columns || 3 ) );
		body.append( 'pagination', this.base.pagination === false ? '0' : '1' );
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );

		// Update the URL (archive only) before fetching so shares/back work.
		if ( this.isArchive && ! skipPush ) {
			this.pushUrl( data );
		}

		var opts = { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() };
		if ( this.controller ) {
			opts.signal = this.controller.signal;
		}

		fetch( CFG.ajaxUrl, opts )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( json ) {
				results.classList.remove( 'is-loading' );
				if ( json && json.success && json.data && typeof json.data.html === 'string' ) {
					results.innerHTML = json.data.html;
					if ( scroll ) {
						self.scrollIntoView();
					}
				} else {
					self.announce( CFG.i18n.error );
				}
			} )
			.catch( function ( err ) {
				if ( err && err.name === 'AbortError' ) {
					return;
				}
				results.classList.remove( 'is-loading' );
				self.announce( CFG.i18n.error );
			} );
	};

	InventoryApp.prototype.pushUrl = function ( data ) {
		var params = new URLSearchParams();
		FIELDS.forEach( function ( name ) {
			if ( name === 'paged' ) {
				return;
			}
			if ( data[ name ] && ! ( name === 'sort' && data[ name ] === 'newest' ) ) {
				params.set( name, data[ name ] );
			}
		} );
		if ( data.paged && parseInt( data.paged, 10 ) > 1 ) {
			params.set( 'paged', data.paged );
		}
		var qs = params.toString();
		var url = window.location.pathname + ( qs ? '?' + qs : '' );
		window.history.pushState( { lrti: true }, '', url );
	};

	InventoryApp.prototype.scrollIntoView = function () {
		var top = this.root.getBoundingClientRect().top + window.pageYOffset - 20;
		window.scrollTo( { top: top, behavior: 'smooth' } );
		var heading = this.root.querySelector( '.lrti-results-count' );
		if ( heading ) {
			heading.setAttribute( 'tabindex', '-1' );
			heading.focus( { preventScroll: true } );
		}
	};

	InventoryApp.prototype.announce = function ( msg ) {
		var count = this.root.querySelector( '.lrti-results-count' );
		if ( count ) {
			count.textContent = msg;
		}
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.lrti-inventory[data-instance]' ).forEach( function ( root ) {
			// Skip standalone filter wrappers; they are driven by their target.
			if ( root.classList.contains( 'lrti-filters-standalone' ) ) {
				return;
			}
			new InventoryApp( root );
		} );
	} );
}() );
