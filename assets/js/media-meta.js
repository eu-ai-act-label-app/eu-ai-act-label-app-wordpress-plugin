/**
 * EU AI Label — Media Library grid helper.
 *
 * The AI-label <select> is rendered as an attachment "compat" field via
 * `attachment_fields_to_edit`. In the classic edit screen the form submits
 * normally, but inside the Backbone-powered grid/modal the field must be
 * flushed back to the model so WordPress persists it through the
 * `save-attachment-compat` AJAX call. This listener triggers that save as
 * soon as the dropdown changes, giving instant feedback in grid view.
 */
( function ( $ ) {
	'use strict';

	var FIELD = 'eu_ai_label_status';

	$( document ).on( 'change', 'select[name$="[' + FIELD + ']"], .compat-field-' + FIELD + ' select', function () {
		var $select = $( this );
		var $form = $select.closest( '.compat-item, form' );

		// Prefer WordPress' own change handler if the field lives in the modal.
		if ( $form.length && typeof $form.data( 'setUserSetting' ) !== 'function' ) {
			$select.trigger( 'change.media-frame' );
		}
	} );
}( window.jQuery ) );
