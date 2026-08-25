/**
 * Admin JavaScript for Little River Trailer Inventory.
 *
 * Runs only on the trailer add/edit screen (where it is enqueued). Powers:
 *   - the tabbed editor (instant switching, remembers last tab)
 *   - suggested trailer title auto-generation (never overwrites a custom title)
 *   - currency formatting for price fields + live Savings calculation
 *   - the custom Main Image control and Open Graph image picker (wp.media)
 *   - the photo gallery (wp.media, multiple)
 *   - submit-time validation (required Stock, Sale <= Regular, MSRP >= Sale)
 *   - live hints for VIN and Empty Weight vs GVWR
 *
 * Uses the built-in WordPress media library. No third-party libraries.
 */
( function ( $ ) {
	'use strict';

	function toNumber( raw ) {
		if ( ! raw ) {
			return 0;
		}
		var n = parseFloat( String( raw ).replace( /[^0-9.]/g, '' ) );
		return isNaN( n ) ? 0 : n;
	}

	function currency() {
		return ( window.lrtiAdmin && lrtiAdmin.currency ) || '$';
	}

	function formatMoney( num ) {
		return currency() + num.toLocaleString( undefined, {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		} );
	}

	function selectedText( selector ) {
		var $sel = $( selector );
		if ( ! $sel.length ) {
			return '';
		}
		var val = $sel.val();
		if ( ! val || '0' === String( val ) ) {
			return '';
		}
		return $.trim( $sel.find( 'option:selected' ).text() );
	}

	$( function () {
		var $editor = $( '.lrti-editor' );
		if ( ! $editor.length ) {
			return;
		}

		/* ---- Tabs --------------------------------------------------------- */

		var postId   = $editor.data( 'post' ) || 'new';
		var storeKey = 'lrtiActiveTab_' + postId;

		function activateTab( tabKey ) {
			$editor.find( '.lrti-nav-tab' ).removeClass( 'nav-tab-active' ).attr( 'aria-selected', 'false' );
			$editor.find( '.lrti-nav-tab[data-tab="' + tabKey + '"]' ).addClass( 'nav-tab-active' ).attr( 'aria-selected', 'true' );
			$editor.find( '.lrti-tab-panel' ).removeClass( 'is-active' );
			$editor.find( '.lrti-tab-panel[data-tab="' + tabKey + '"]' ).addClass( 'is-active' );
			try {
				window.sessionStorage.setItem( storeKey, tabKey );
			} catch ( e ) {}
		}

		$editor.on( 'click', '.lrti-nav-tab', function ( e ) {
			e.preventDefault();
			activateTab( $( this ).data( 'tab' ) );
		} );

		try {
			var saved = window.sessionStorage.getItem( storeKey );
			if ( saved && $editor.find( '.lrti-nav-tab[data-tab="' + saved + '"]' ).length ) {
				activateTab( saved );
			}
		} catch ( e ) {}

		/* ---- Auto title generation --------------------------------------- */

		var $title    = $( '#title' );           // Classic editor title field.
		var $autoFlag = $( '#lrti_title_auto' );
		var suppress  = false;

		function titleIsAuto() {
			return '1' === String( $autoFlag.val() );
		}

		function buildTitle() {
			var year  = $.trim( $( '#lrti_field_year' ).val() );
			var man   = selectedText( '#lrti_tax_trailer_manufacturer' ).replace( /\s*Trailers?$/i, '' );
			var model = $.trim( $( '#lrti_field_model' ).val() );
			var type  = selectedText( '#lrti_tax_trailer_type' ).replace( /s$/i, '' );
			var parts = [ year, man, model, type ].filter( function ( p ) {
				return p && p.length;
			} );
			return parts.join( ' ' ).replace( /\s+/g, ' ' ).trim();
		}

		function applyTitle() {
			if ( ! $title.length || ! titleIsAuto() ) {
				return;
			}
			var t = buildTitle();
			suppress = true;
			$title.val( t );
			// Toggle the placeholder label used by the classic editor.
			$( '#title-prompt-text' ).toggleClass( 'screen-reader-text', t.length > 0 );
			suppress = false;
		}

		// If the user types in the title themselves, stop auto-generating.
		$title.on( 'input', function () {
			if ( suppress ) {
				return;
			}
			$autoFlag.val( '0' );
		} );

		$( '#lrti_field_year, #lrti_field_model' ).on( 'input', applyTitle );
		$( '#lrti_tax_trailer_manufacturer, #lrti_tax_trailer_type' ).on( 'change', applyTitle );

		// Generate once on load for brand-new trailers.
		if ( titleIsAuto() && $title.length && '' === $.trim( $title.val() ) ) {
			applyTitle();
		}

		/* ---- Prices & savings -------------------------------------------- */

		var $regular = $( '#lrti_field_regular_price' );
		var $sale    = $( '#lrti_field_sale_price' );
		var $msrp    = $( '#lrti_field_msrp' );
		var $savings = $( '#lrti_savings_display' );

		function recalcSavings() {
			if ( ! $savings.length ) {
				return;
			}
			var regular = toNumber( $regular.val() );
			var sale    = toNumber( $sale.val() );
			var msrp    = toNumber( $msrp.val() );
			var selling = sale > 0 ? sale : regular;

			if ( msrp > 0 && selling > 0 && msrp > selling ) {
				var diff = msrp - selling;
				var pct  = Math.round( ( diff / msrp ) * 100 );
				$savings.val( formatMoney( diff ) + ' (' + pct + '%)' );
			} else {
				$savings.val( '' );
			}
		}

		$( '.lrti-price-input' ).on( 'focus', function () {
			var n = toNumber( $( this ).val() );
			$( this ).val( n > 0 ? String( n ) : '' );
		} ).on( 'blur', function () {
			var n = toNumber( $( this ).val() );
			$( this ).val( n > 0 ? formatMoney( n ) : '' );
			recalcSavings();
		} ).on( 'input', recalcSavings );

		recalcSavings();

		/* ---- Main (featured) image --------------------------------------- */

		var $featInput   = $( '#lrti_featured_image_id' );
		var $featPreview = $( '#lrti-featured-preview' );
		var $featRemove  = $( '#lrti-featured-remove' );
		var featFrame;

		$( '#lrti-featured-set' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( featFrame ) {
				featFrame.open();
				return;
			}
			featFrame = wp.media( {
				title: ( window.lrtiAdmin && lrtiAdmin.chooseMain ) || 'Select Main Image',
				button: { text: ( window.lrtiAdmin && lrtiAdmin.useMain ) || 'Use as Main Image' },
				library: { type: 'image' },
				multiple: false
			} );
			featFrame.on( 'select', function () {
				var att = featFrame.state().get( 'selection' ).first().toJSON();
				var url = att.url;
				if ( att.sizes && att.sizes.medium ) {
					url = att.sizes.medium.url;
				}
				$featInput.val( att.id );
				$featPreview.html( $( '<img />' ).attr( 'src', url ).attr( 'alt', '' ) );
				$featRemove.show();
				var $setBtn = $( '#lrti-featured-set' );
				$setBtn.text( $setBtn.data( 'label-replace' ) || $setBtn.text() );
			} );
			featFrame.open();
		} );

		$featRemove.on( 'click', function ( e ) {
			e.preventDefault();
			$featInput.val( '' );
			$featPreview.empty();
			$( this ).hide();
			var $setBtn = $( '#lrti-featured-set' );
			$setBtn.text( $setBtn.data( 'label-set' ) || $setBtn.text() );
		} );

		/* ---- Open Graph image (SEO tab) ---------------------------------- */

		var $ogInput   = $( '#lrti_field_seo_og_image' );
		var $ogPreview = $( '#lrti-og-preview' );
		var $ogClear   = $( '#lrti-og-clear' );
		var ogFrame;

		$( '#lrti-og-set' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( ogFrame ) {
				ogFrame.open();
				return;
			}
			ogFrame = wp.media( {
				title: ( window.lrtiAdmin && lrtiAdmin.chooseOg ) || 'Select Image',
				button: { text: ( window.lrtiAdmin && lrtiAdmin.useOg ) || 'Use this Image' },
				library: { type: 'image' },
				multiple: false
			} );
			ogFrame.on( 'select', function () {
				var att = ogFrame.state().get( 'selection' ).first().toJSON();
				var url = att.url;
				if ( att.sizes && att.sizes.large ) {
					url = att.sizes.large.url;
				}
				$ogInput.val( url );
				$ogPreview.html( $( '<img />' ).attr( 'src', url ).attr( 'alt', '' ) );
				$ogClear.show();
			} );
			ogFrame.open();
		} );

		$ogClear.on( 'click', function ( e ) {
			e.preventDefault();
			$ogInput.val( '' );
			$ogPreview.empty();
			$( this ).hide();
		} );

		/* ---- Gallery ------------------------------------------------------ */

		var $list   = $( '#lrti-gallery-list' );
		var $gInput = $( '#lrti_gallery_ids' );
		var gFrame;

		function syncGallery() {
			var ids = [];
			$list.find( '.lrti-gallery-item' ).each( function () {
				var id = $( this ).data( 'id' );
				if ( id ) {
					ids.push( id );
				}
			} );
			$gInput.val( ids.join( ',' ) );
		}

		$( '#lrti-gallery-add' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( gFrame ) {
				gFrame.open();
				return;
			}
			gFrame = wp.media( {
				title: ( window.lrtiAdmin && lrtiAdmin.chooseImages ) || 'Select Images',
				button: { text: ( window.lrtiAdmin && lrtiAdmin.useImages ) || 'Add to Gallery' },
				library: { type: 'image' },
				multiple: true
			} );
			gFrame.on( 'select', function () {
				gFrame.state().get( 'selection' ).each( function ( attachment ) {
					var data = attachment.toJSON();
					if ( $list.find( '.lrti-gallery-item[data-id="' + data.id + '"]' ).length ) {
						return;
					}
					var thumbUrl = data.url;
					if ( data.sizes && data.sizes.thumbnail ) {
						thumbUrl = data.sizes.thumbnail.url;
					}
					var $item = $( '<li class="lrti-gallery-item"></li>' ).attr( 'data-id', data.id );
					$( '<img />' ).attr( 'src', thumbUrl ).attr( 'alt', '' ).appendTo( $item );
					$( '<button type="button" class="lrti-gallery-remove" aria-label="Remove image">&times;</button>' ).appendTo( $item );
					$list.append( $item );
				} );
				syncGallery();
			} );
			gFrame.open();
		} );

		$list.on( 'click', '.lrti-gallery-remove', function ( e ) {
			e.preventDefault();
			$( this ).closest( '.lrti-gallery-item' ).remove();
			syncGallery();
		} );

		/* ---- Live hints: empty weight vs GVWR, VIN ----------------------- */

		function weightHint() {
			var gvwr  = toNumber( $( '#lrti_field_gvwr' ).val() );
			var empty = toNumber( $( '#lrti_field_empty_weight' ).val() );
			var $hint = $( '#lrti-weight-hint' );
			if ( gvwr > 0 && empty > 0 && empty > gvwr ) {
				if ( ! $hint.length ) {
					$( '#lrti_field_empty_weight' ).closest( 'td' ).append(
						$( '<p class="description lrti-warning" id="lrti-weight-hint"></p>' ).text(
							( window.lrtiAdmin && lrtiAdmin.emptyOverGvwr ) || 'Empty Weight is greater than GVWR.'
						)
					);
				}
			} else if ( $hint.length ) {
				$hint.remove();
			}
		}
		$( '#lrti_field_gvwr, #lrti_field_empty_weight' ).on( 'input blur', weightHint );

		$( '#lrti_field_vin' ).on( 'blur', function () {
			var v = String( $( this ).val() || '' ).toUpperCase().replace( /[^A-Z0-9]/g, '' );
			var $hint = $( '#lrti-vin-hint' );
			var bad = v.length > 0 && ! /^[A-HJ-NPR-Z0-9]{17}$/.test( v );
			if ( bad ) {
				if ( ! $hint.length ) {
					$( this ).closest( 'td' ).append(
						$( '<p class="description lrti-warning" id="lrti-vin-hint"></p>' ).text(
							( window.lrtiAdmin && lrtiAdmin.vinHint ) || 'A VIN is usually 17 characters.'
						)
					);
				}
			} else if ( $hint.length ) {
				$hint.remove();
			}
		} );

		/* ---- Character counters (SEO) ------------------------------------ */

		function attachCounter( selector, recommended ) {
			var $f = $( selector );
			if ( ! $f.length ) {
				return;
			}
			var $c = $( '<span class="lrti-charcount"></span>' );
			$f.after( $c );
			function update() {
				var len = String( $f.val() || '' ).length;
				$c.text( len + ' / ' + recommended );
				$c.toggleClass( 'lrti-over', len > recommended );
			}
			$f.on( 'input', update );
			update();
		}
		attachCounter( '#lrti_field_seo_meta_title', 60 );
		attachCounter( '#lrti_field_seo_meta_description', 155 );

		/* ---- Submit validation ------------------------------------------- */

		$( '#post' ).on( 'submit', function ( e ) {
			function stop( tab, $field, message ) {
				e.preventDefault();
				activateTab( tab );
				window.alert( message );
				if ( $field && $field.length ) {
					$field.trigger( 'focus' );
				}
				$( '#publish, #save-post' ).removeClass( 'disabled' ).attr( 'aria-disabled', 'false' );
				$( '.spinner' ).removeClass( 'is-active' );
				return false;
			}

			var $stock = $( '#lrti_field_stock_number' );
			if ( $stock.length && '' === $.trim( $stock.val() ) ) {
				return stop( 'general', $stock, ( window.lrtiAdmin && lrtiAdmin.stockRequired ) || 'Stock Number is required.' );
			}

			var regular = toNumber( $regular.val() );
			var sale    = toNumber( $sale.val() );
			var msrp    = toNumber( $msrp.val() );

			if ( regular > 0 && sale > 0 && sale > regular ) {
				return stop( 'pricing', $sale, ( window.lrtiAdmin && lrtiAdmin.saleTooHigh ) || 'Sale Price cannot be greater than Regular Price.' );
			}
			if ( msrp > 0 && sale > 0 && msrp < sale ) {
				return stop( 'pricing', $msrp, ( window.lrtiAdmin && lrtiAdmin.msrpTooLow ) || 'MSRP cannot be less than the Sale Price.' );
			}
		} );
	} );
} )( jQuery );
