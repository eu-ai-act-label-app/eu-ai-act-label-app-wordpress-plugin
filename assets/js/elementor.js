/**
 * EU AI Label — Elementor dynamic/background compatibility.
 */
( function () {
	'use strict';

	var SELECTOR = '[data-eu-ai-label-background]';

	function candidates( root ) {
		var elements = [];
		if ( root && root.matches && root.matches( SELECTOR ) ) {
			elements.push( root );
		}
		if ( root && root.querySelectorAll ) {
			elements = elements.concat( Array.prototype.slice.call( root.querySelectorAll( SELECTOR ) ) );
		}
		return elements;
	}

	function enhance( root ) {
		candidates( root ).forEach( function ( element ) {
			if ( '1' === element.getAttribute( 'data-eu-ai-label-ready' ) ) {
				return;
			}

			var markup;
			try {
				markup = JSON.parse( element.getAttribute( 'data-eu-ai-label-background' ) );
			} catch ( error ) {
				return;
			}

			var template = document.createElement( 'template' );
			template.innerHTML = String( markup ).trim();
			if ( ! template.content.querySelector( '.eu-ai-label-badge' ) ) {
				return;
			}

			if ( 'static' === window.getComputedStyle( element ).position ) {
				element.classList.add( 'eu-ai-label-background-wrap--relative' );
			}
			element.appendChild( template.content.cloneNode( true ) );
			element.setAttribute( 'data-eu-ai-label-ready', '1' );
		} );
	}

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

	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', function ( scope ) {
				enhance( scope && scope[ 0 ] ? scope[ 0 ] : scope );
			} );
		}
	} );
}() );
