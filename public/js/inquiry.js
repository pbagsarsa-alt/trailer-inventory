/**
 * Little River Trailer Inventory — inquiry form submission (Sprint 5.0).
 *
 * Progressive enhancement: each form works as a normal POST without JS. With JS
 * this submits via AJAX, shows a loading state, prevents double submits, renders
 * accessible inline errors + an error summary, and announces success via an
 * aria-live region. Multiple forms on a page are handled independently. The
 * primary "Check Availability" / "Request Information" buttons select the form
 * mode and move focus to the form heading.
 */
( function () {
	'use strict';

	if ( typeof window.lrtiInquiry === 'undefined' ) {
		return;
	}
	var CFG = window.lrtiInquiry;

	function setFormType( wrap, type, heading ) {
		var input = wrap.querySelector( '.lrti-form-type' );
		if ( input ) {
			input.value = type;
		}
		if ( heading ) {
			var h = wrap.querySelector( '.lrti-inquiry-heading' );
			if ( h ) {
				h.textContent = heading;
			}
		}
	}

	function focusForm( wrap, focusField ) {
		var target = null;
		if ( focusField ) {
			target = wrap.querySelector( '[name="' + focusField + '"]' );
		}
		if ( ! target ) {
			target = wrap.querySelector( '.lrti-inquiry-heading' );
		}
		if ( target ) {
			target.focus();
		}
	}

	function clearErrors( form ) {
		form.querySelectorAll( '.lrti-field-error' ).forEach( function ( el ) {
			el.textContent = '';
		} );
		form.querySelectorAll( '[aria-invalid="true"]' ).forEach( function ( el ) {
			el.removeAttribute( 'aria-invalid' );
		} );
		var summary = form.parentNode.querySelector( '.lrti-inquiry-errorsummary' );
		if ( summary ) {
			summary.textContent = '';
			summary.style.display = 'none';
		}
	}

	function showErrors( wrap, form, message, fields ) {
		var summary = wrap.querySelector( '.lrti-inquiry-errorsummary' );
		if ( ! summary ) {
			summary = document.createElement( 'div' );
			summary.className = 'lrti-inquiry-errorsummary';
			summary.setAttribute( 'role', 'alert' );
			form.parentNode.insertBefore( summary, form );
		}
		summary.textContent = message || CFG.i18n.error;
		summary.style.display = 'block';

		if ( fields ) {
			Object.keys( fields ).forEach( function ( name ) {
				var input = form.querySelector( '[name="' + name + '"]' );
				var errEl = form.querySelector( '#lrti-' + name + '-err-' + wrap.getAttribute( 'data-instance' ) );
				if ( input ) {
					input.setAttribute( 'aria-invalid', 'true' );
				}
				if ( errEl ) {
					errEl.textContent = fields[ name ];
				}
			} );
			var firstKey = Object.keys( fields )[ 0 ];
			var firstInput = form.querySelector( '[name="' + firstKey + '"]' );
			if ( firstInput ) {
				firstInput.focus();
			}
		}
	}

	function showSuccess( wrap, form, message ) {
		var status = wrap.querySelector( '.lrti-inquiry-status' );
		if ( status ) {
			var box = document.createElement( 'div' );
			box.className = 'lrti-inquiry-success';
			box.setAttribute( 'tabindex', '-1' );
			box.textContent = message || CFG.i18n.success;
			status.innerHTML = '';
			status.appendChild( box );
			wrap.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			setTimeout( function () { box.focus(); }, 300 );
		}
		form.style.display = 'none';
	}

	function initForm( wrap ) {
		var form = wrap.querySelector( '.lrti-inquiry-form' );
		if ( ! form ) {
			return;
		}

		// Track whether the visitor edited the message (so we never overwrite it).
		var message = form.querySelector( 'textarea[name="message"]' );
		if ( message ) {
			message.addEventListener( 'input', function () {
				message.setAttribute( 'data-touched', '1' );
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			if ( wrap.classList.contains( 'is-submitting' ) ) {
				return;
			}
			clearErrors( form );
			wrap.classList.add( 'is-submitting' );

			// Disable the button and show a sending state (prevents double-clicks
			// and slow-network re-submits).
			var submitBtn = form.querySelector( '.lrti-inquiry-submit' );
			var originalLabel = '';
			if ( submitBtn ) {
				originalLabel = submitBtn.textContent;
				submitBtn.disabled = true;
				submitBtn.setAttribute( 'aria-busy', 'true' );
				submitBtn.textContent = ( CFG.i18n && CFG.i18n.loading ) ? CFG.i18n.loading : 'Sending…';
			}

			var restoreButton = function () {
				wrap.classList.remove( 'is-submitting' );
				if ( submitBtn ) {
					submitBtn.disabled = false;
					submitBtn.removeAttribute( 'aria-busy' );
					submitBtn.textContent = originalLabel;
				}
			};

			var body = new URLSearchParams( new FormData( form ) );
			body.set( 'action', CFG.action );

			fetch( CFG.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( json ) {
					if ( json && json.success ) {
						// Leave the button disabled; the form is replaced by the
						// success message.
						wrap.classList.remove( 'is-submitting' );
						showSuccess( wrap, form, json.data && json.data.message );
					} else {
						restoreButton();
						var d = ( json && json.data ) || {};
						showErrors( wrap, form, d.message, d.fields );
					}
				} )
				.catch( function () {
					restoreButton();
					showErrors( wrap, form, CFG.i18n.error, null );
				} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.lrti-inquiry-form-wrap' ).forEach( initForm );

		// Wire the price-area CTA buttons to select the mode + focus the form.
		document.querySelectorAll( '[data-lrti-inquiry-mode]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				var targetSel = btn.getAttribute( 'data-lrti-target' ) || '#lrti-inquiry';
				var target = document.querySelector( targetSel );
				if ( ! target ) {
					return;
				}
				var wrap = target.querySelector( '.lrti-inquiry-form-wrap' ) || target.closest( '.lrti-inquiry-form-wrap' );
				if ( wrap ) {
					e.preventDefault();
					setFormType( wrap, btn.getAttribute( 'data-lrti-inquiry-mode' ), btn.getAttribute( 'data-lrti-heading' ) );
					target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
					var focusField = btn.getAttribute( 'data-lrti-focus' );
					setTimeout( function () { focusForm( wrap, focusField ); }, 300 );
				}
			} );
		} );
	} );
}() );
