/**
 * TWC Trailer Inventory — Leads list interactions.
 *
 * Vanilla JS, loaded only on edit.php?post_type=lrti_lead. Adds:
 *  - a horizontal scroll wrapper around the list table (so the wide table
 *    scrolls instead of squeezing the action column);
 *  - an accessible "More" action menu positioned as fixed so it is never
 *    clipped by the scroll wrapper;
 *  - visual integration of the search box into the toolbar card.
 *
 * @package LRTI
 */
( function () {
	'use strict';

	/* ---- Wrap the list table for horizontal scrolling. ---- */
	function wrapTable() {
		var table = document.querySelector( '.wp-list-table' );
		if ( ! table || table.parentNode.classList.contains( 'lrti-table-scroll' ) ) {
			return;
		}
		var wrap = document.createElement( 'div' );
		wrap.className = 'lrti-table-scroll';
		table.parentNode.insertBefore( wrap, table );
		wrap.appendChild( table );
	}

	/* ---- Move the search box into the top toolbar card. ---- */
	function integrateSearch() {
		var search = document.querySelector( '.search-box' );
		var toolbar = document.querySelector( '.tablenav.top' );
		if ( search && toolbar && ! toolbar.contains( search ) ) {
			toolbar.appendChild( search );
		}
	}

	/* ---- Accessible More menu. ---- */
	var openMenu = null;
	var openToggle = null;

	function menuItems( menu ) {
		return Array.prototype.slice.call(
			menu.querySelectorAll( '.lrti-menu-item' )
		);
	}

	function positionMenu( toggle, menu ) {
		// Fixed positioning escapes the scroll wrapper's clipping.
		menu.classList.add( 'lrti-more-menu--fixed' );
		menu.style.position = 'fixed';
		menu.style.top = 'auto';
		menu.style.left = 'auto';

		var r = toggle.getBoundingClientRect();
		var mw = menu.offsetWidth;
		var mh = menu.offsetHeight;

		var left = r.left;
		if ( left + mw > window.innerWidth - 8 ) {
			left = Math.max( 8, r.right - mw );
		}
		var top = r.bottom + 4;
		if ( top + mh > window.innerHeight - 8 ) {
			top = Math.max( 8, r.top - mh - 4 );
		}
		menu.style.left = left + 'px';
		menu.style.top = top + 'px';
	}

	function close( returnFocus ) {
		if ( ! openMenu ) {
			return;
		}
		openMenu.classList.remove( 'is-open', 'lrti-more-menu--fixed', 'lrti-more-menu--left' );
		openMenu.style.position = '';
		openMenu.style.top = '';
		openMenu.style.left = '';
		if ( openToggle ) {
			openToggle.setAttribute( 'aria-expanded', 'false' );
			if ( returnFocus ) {
				openToggle.focus();
			}
		}
		openMenu = null;
		openToggle = null;
	}

	function open( toggle ) {
		var menu = document.getElementById( toggle.getAttribute( 'aria-controls' ) );
		if ( ! menu ) {
			return;
		}
		close( false );
		menu.classList.add( 'is-open' );
		toggle.setAttribute( 'aria-expanded', 'true' );
		openMenu = menu;
		openToggle = toggle;
		positionMenu( toggle, menu );

		var first = menuItems( menu )[ 0 ];
		if ( first ) {
			first.focus();
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var toggle = e.target.closest ? e.target.closest( '.lrti-more-toggle' ) : null;
		if ( toggle ) {
			e.preventDefault();
			if ( 'true' === toggle.getAttribute( 'aria-expanded' ) ) {
				close( false );
			} else {
				open( toggle );
			}
			return;
		}
		if ( openMenu && ! e.target.closest( '.lrti-more-menu' ) ) {
			close( false );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( ! openMenu ) {
			return;
		}
		var list = menuItems( openMenu );
		var idx = list.indexOf( document.activeElement );

		switch ( e.key ) {
			case 'Escape':
				e.preventDefault();
				close( true );
				break;
			case 'ArrowDown':
				e.preventDefault();
				( list[ idx + 1 ] || list[ 0 ] ).focus();
				break;
			case 'ArrowUp':
				e.preventDefault();
				( list[ idx - 1 ] || list[ list.length - 1 ] ).focus();
				break;
			case 'Home':
				e.preventDefault();
				if ( list[ 0 ] ) {
					list[ 0 ].focus();
				}
				break;
			case 'End':
				e.preventDefault();
				if ( list.length ) {
					list[ list.length - 1 ].focus();
				}
				break;
			case 'Tab':
				close( false );
				break;
			default:
				break;
		}
	} );

	// Fixed menus do not follow scroll/resize, so close them.
	window.addEventListener( 'scroll', function () { close( false ); }, true );
	window.addEventListener( 'resize', function () { close( false ); } );

	function init() {
		wrapTable();
		integrateSearch();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
