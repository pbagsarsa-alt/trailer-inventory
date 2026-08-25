/**
 * Little River Trailer Inventory — front-end archive script (Sprint 4.1).
 *
 * Progressive enhancement only: auto-submits the sort form when the dropdown
 * changes. Without JavaScript the visible "Sort" button still works, so the
 * archive remains fully usable.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var select = document.getElementById( 'lrti-sort-select' );
		if ( ! select ) {
			return;
		}

		select.addEventListener( 'change', function () {
			var form = select.closest( 'form' );
			if ( form ) {
				form.submit();
			}
		} );
	} );
}() );
