/**
 * TWC Trailer Inventory — Trailer Type "Category Card Image" picker.
 *
 * Uses the native WordPress Media Library. Loaded only on the Trailer Types
 * add/edit screens. Requires jQuery (a WordPress-bundled dependency) and
 * wp.media (enqueued via wp_enqueue_media()).
 *
 * @package LRTI
 */
( function ( $ ) {
	'use strict';

	var frame = null;

	$( document ).on( 'click', '.twc-home-image-select', function ( e ) {
		e.preventDefault();
		var $wrap = $( this ).closest( '[data-twc-home-image]' );

		if ( frame ) {
			frame.off( 'select' );
		}
		frame = wp.media( {
			title: ( window.twcHomeImage && window.twcHomeImage.title ) || 'Select image',
			button: { text: ( window.twcHomeImage && window.twcHomeImage.button ) || 'Use this image' },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
			$wrap.find( '.twc-home-image-id' ).val( att.id );
			$wrap.find( '.twc-home-image-preview' ).html(
				$( '<img>' ).attr( { src: url, alt: '' } )
			);
			$wrap.find( '.twc-home-image-remove' ).prop( 'disabled', false );
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.twc-home-image-remove', function ( e ) {
		e.preventDefault();
		var $wrap = $( this ).closest( '[data-twc-home-image]' );
		$wrap.find( '.twc-home-image-id' ).val( '' );
		$wrap.find( '.twc-home-image-preview' ).html(
			$( '<span>' ).attr( 'aria-hidden', 'true' ).addClass( 'twc-home-image-placeholder dashicons dashicons-format-image' )
		);
		$( this ).prop( 'disabled', true );
	} );
}( jQuery ) );
