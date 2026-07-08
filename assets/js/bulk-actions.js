/**
 * EU AI Label — grid-view bulk labeling.
 *
 * The Media Library list view uses native WordPress bulk actions. The default
 * grid view has no bulk-action API, so this injects a small control into the
 * grid's "Bulk select" toolbar and applies the chosen label to the selected
 * attachments over admin-ajax.
 *
 * Injection is DOM-based (reads the `.attachment.selected` nodes rendered by
 * the media grid) rather than hooking Backbone internals, so it degrades
 * gracefully if WordPress changes its media views: worst case the control just
 * does not appear and the list view still works.
 */
( function () {
	'use strict';

	var cfg = window.euAiLabelBulk;
	if ( ! cfg || ! window.fetch ) {
		return;
	}

	var CONTROL_ID = 'eu-ai-label-bulk-control';

	/**
	 * The media grid frame, if this screen is in grid mode.
	 *
	 * @return {Element|null}
	 */
	function gridFrame() {
		return document.querySelector( '.media-frame' );
	}

	/**
	 * IDs of the currently selected grid attachments.
	 *
	 * @return {number[]}
	 */
	function selectedIds() {
		var ids = [];
		document.querySelectorAll( '.attachment.selected' ).forEach( function ( el ) {
			var id = parseInt( el.getAttribute( 'data-id' ), 10 );
			if ( id > 0 && ids.indexOf( id ) === -1 ) {
				ids.push( id );
			}
		} );
		return ids;
	}

	/**
	 * Build the select + apply control.
	 *
	 * @return {Element}
	 */
	function buildControl() {
		var wrap = document.createElement( 'span' );
		wrap.id = CONTROL_ID;
		wrap.style.display = 'inline-flex';
		wrap.style.alignItems = 'center';
		wrap.style.gap = '6px';
		wrap.style.marginLeft = '8px';

		var select = document.createElement( 'select' );
		var placeholder = document.createElement( 'option' );
		placeholder.value = '__none__';
		placeholder.textContent = cfg.i18n.choose;
		select.appendChild( placeholder );

		cfg.choices.forEach( function ( choice ) {
			var option = document.createElement( 'option' );
			option.value = choice.value;
			option.textContent = choice.label;
			select.appendChild( option );
		} );

		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'button media-button';
		button.textContent = cfg.i18n.apply;

		var status = document.createElement( 'span' );
		status.setAttribute( 'aria-live', 'polite' );
		status.style.marginLeft = '4px';

		button.addEventListener( 'click', function () {
			var ids = selectedIds();
			if ( ! ids.length ) {
				status.textContent = cfg.i18n.noSelection;
				return;
			}
			if ( '__none__' === select.value ) {
				status.textContent = cfg.i18n.noneChosen;
				return;
			}

			button.disabled = true;
			status.textContent = '';

			var body = new URLSearchParams();
			body.append( 'action', cfg.action );
			body.append( 'nonce', cfg.nonce );
			body.append( 'status', select.value );
			ids.forEach( function ( id ) {
				body.append( 'ids[]', id );
			} );

			window.fetch( cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( result ) {
				button.disabled = false;
				if ( result && result.success ) {
					status.textContent = cfg.i18n.done.replace( '%d', result.data.updated );
				} else {
					status.textContent = cfg.i18n.error;
				}
			} ).catch( function () {
				button.disabled = false;
				status.textContent = cfg.i18n.error;
			} );
		} );

		wrap.appendChild( select );
		wrap.appendChild( button );
		wrap.appendChild( status );
		return wrap;
	}

	/**
	 * Inject the control into the "Bulk select" toolbar when it is active.
	 *
	 * @return {void}
	 */
	function inject() {
		var frame = gridFrame();
		if ( ! frame || ! frame.classList.contains( 'mode-select' ) ) {
			return;
		}
		if ( document.getElementById( CONTROL_ID ) ) {
			return;
		}
		var toolbar = frame.querySelector( '.media-toolbar-secondary' );
		if ( toolbar ) {
			toolbar.appendChild( buildControl() );
		}
	}

	function start() {
		if ( ! gridFrame() ) {
			return;
		}
		inject();
		// The grid toolbar re-renders on entering/leaving select mode, so watch
		// for it and (re)inject. Guards inside inject() keep this idempotent.
		new MutationObserver( inject ).observe( document.body, {
			childList: true,
			subtree: true
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
