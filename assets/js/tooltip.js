/**
 * EU AI Label — badge tooltip dismissal (Pro).
 *
 * The tooltip itself is pure CSS (shown on badge hover/focus). This adds the
 * WCAG 1.4.13 "dismissible" behaviour: pressing Escape hides any visible
 * tooltip without moving the pointer or focus. The dismissal is cleared once
 * the pointer/focus leaves the badge, so the next hover shows the tip again.
 */
( function () {
	'use strict';

	var DISMISSED = 'eu-ai-label--tip-dismissed';

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' !== event.key ) {
			return;
		}
		document.querySelectorAll( '.eu-ai-label--has-tip' ).forEach( function ( badge ) {
			badge.classList.add( DISMISSED );
		} );
	} );

	function maybeReset( event ) {
		if ( ! event.target || ! event.target.closest ) {
			return;
		}
		var badge = event.target.closest( '.' + DISMISSED );
		if ( badge && ( ! event.relatedTarget || ! badge.contains( event.relatedTarget ) ) ) {
			badge.classList.remove( DISMISSED );
		}
	}

	document.addEventListener( 'pointerout', maybeReset );
	document.addEventListener( 'focusout', maybeReset );
}() );
