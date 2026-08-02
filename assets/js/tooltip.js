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
	var LINKED = 'eu-ai-label-link--has-tip';

	/**
	 * Move tooltip semantics to an enclosing image link. This keeps a linked
	 * thumbnail as one focus target and prevents the badge from swallowing the
	 * click while preserving the same hover/focus description.
	 */
	function enhance( root ) {
		var badges = [];
		if ( root && root.matches && root.matches( '.eu-ai-label--has-tip' ) ) {
			badges.push( root );
		}
		if ( root && root.querySelectorAll ) {
			badges = badges.concat( Array.prototype.slice.call( root.querySelectorAll( '.eu-ai-label--has-tip' ) ) );
		}

		badges.forEach( function ( badge ) {
			var link = badge.closest( 'a' );
			var tipId = badge.getAttribute( 'aria-describedby' );
			if ( ! link || ! tipId ) {
				return;
			}

			var describedBy = ( link.getAttribute( 'aria-describedby' ) || '' ).split( /\s+/ ).filter( Boolean );
			if ( -1 === describedBy.indexOf( tipId ) ) {
				describedBy.push( tipId );
			}
			link.setAttribute( 'aria-describedby', describedBy.join( ' ' ) );
			link.classList.add( LINKED );
			badge.removeAttribute( 'tabindex' );
			badge.removeAttribute( 'aria-describedby' );
		} );
	}

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
		var owner = event.target.closest( '.' + LINKED ) || event.target.closest( '.eu-ai-label--has-tip' );
		if ( owner && ( ! event.relatedTarget || ! owner.contains( event.relatedTarget ) ) ) {
			if ( owner.classList.contains( DISMISSED ) ) {
				owner.classList.remove( DISMISSED );
			}
			owner.querySelectorAll( '.' + DISMISSED ).forEach( function ( badge ) {
				badge.classList.remove( DISMISSED );
			} );
		}
	}

	document.addEventListener( 'pointerout', maybeReset );
	document.addEventListener( 'focusout', maybeReset );

	function start() {
		enhance( document );
		new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				mutation.addedNodes.forEach( function ( node ) {
					if ( 1 === node.nodeType ) {
						enhance( node );
					}
				} );
			} );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
