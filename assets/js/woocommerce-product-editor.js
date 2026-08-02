( function ( $ ) {
	'use strict';

	var badgeStatuses = [ 'ai_generated', 'ai_edited', 'ai_undisclosed' ];

	function updateImageControl( card ) {
		var select = card.querySelector( '.eu-ai-label-product-image__select' );
		var status = card.querySelector( '.eu-ai-label-product-image__status' );
		var details = card.querySelector( '.eu-ai-label-product-image__details' );

		if ( ! select || ! status ) {
			return;
		}

		var option = select.options[ select.selectedIndex ];
		var value = select.value;
		var chipClass = value ? value : 'none';

		status.innerHTML = '';
		var chip = document.createElement( 'span' );
		chip.className = 'eu-ai-label-chip eu-ai-label-chip--' + chipClass;
		chip.textContent = option ? option.textContent : '';
		status.appendChild( chip );

		if ( details ) {
			details.disabled = badgeStatuses.indexOf( value ) === -1;
		}
	}

	function initialize() {
		var cards = document.querySelectorAll( '.eu-ai-label-product-image' );

		Array.prototype.forEach.call( cards, function ( card ) {
			updateImageControl( card );
			var select = card.querySelector( '.eu-ai-label-product-image__select' );
			if ( select ) {
				$( select ).on( 'change', function () {
					updateImageControl( card );
				} );
			}
		} );
	}

	$( initialize );
}( jQuery ) );
