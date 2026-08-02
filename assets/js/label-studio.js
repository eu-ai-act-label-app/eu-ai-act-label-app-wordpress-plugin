/**
 * EU AI Label — Pro Label Studio live preview.
 */
( function () {
	'use strict';

	var background = document.getElementById( 'eu-ai-label-bg-color' );
	var text = document.getElementById( 'eu-ai-label-text-color' );
	var radius = document.getElementById( 'eu-ai-label-radius' );
	var preview = document.querySelector( '.eu-ai-label-studio__preview-badge' );
	var previewStage = document.querySelector( '.eu-ai-label-studio__preview' );
	var contrast = document.querySelector( '.eu-ai-label-studio__contrast' );
	var sizes = {
		s: { fontSize: 11, paddingY: 3, paddingX: 8 },
		m: { fontSize: 13, paddingY: 4, paddingX: 11 },
		l: { fontSize: 15, paddingY: 6, paddingX: 14 }
	};

	if ( ! background || ! text || ! radius || ! preview || ! previewStage || ! contrast ) {
		return;
	}

	function channel( value ) {
		value /= 255;
		return value <= 0.04045 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 );
	}

	function luminance( hex ) {
		var value = hex.replace( '#', '' );
		var red = parseInt( value.slice( 0, 2 ), 16 );
		var green = parseInt( value.slice( 2, 4 ), 16 );
		var blue = parseInt( value.slice( 4, 6 ), 16 );
		return ( 0.2126 * channel( red ) ) + ( 0.7152 * channel( green ) ) + ( 0.0722 * channel( blue ) );
	}

	function format( template, value ) {
		return template.replace( '%s', value );
	}

	function update() {
		preview.style.backgroundColor = background.value;
		preview.style.color = text.value;
		preview.style.borderRadius = Math.max( 0, Math.min( 999, parseInt( radius.value, 10 ) || 0 ) ) + 'px';

		document.querySelector( '[data-color-value="background"]' ).textContent = background.value;
		document.querySelector( '[data-color-value="text"]' ).textContent = text.value;

		var selected = document.querySelector( 'input[name="eu_ai_label_studio[icon]"]:checked' );
		var glyph = selected ? selected.parentNode.querySelector( '.eu-ai-label-studio__icon-glyph' ).textContent : '';
		preview.querySelector( '[data-preview-icon]' ).textContent = glyph;

		var selectedSize = document.querySelector( 'input[name="eu_ai_label_studio[size]"]:checked' );
		var size = sizes[ selectedSize ? selectedSize.value : 'm' ] || sizes.m;
		preview.style.fontSize = size.fontSize + 'px';
		preview.style.padding = size.paddingY + 'px ' + size.paddingX + 'px';

		var selectedPosition = document.querySelector( 'input[name="eu_ai_label_studio[position]"]:checked' );
		previewStage.setAttribute( 'data-preview-position', selectedPosition ? selectedPosition.value : 'bottom-left' );

		var first = luminance( background.value );
		var second = luminance( text.value );
		var ratio = ( ( Math.max( first, second ) + 0.05 ) / ( Math.min( first, second ) + 0.05 ) ).toFixed( 2 );
		var passes = parseFloat( ratio ) >= 4.5;
		contrast.textContent = format( contrast.getAttribute( passes ? 'data-pass' : 'data-warning' ), ratio );
		contrast.classList.toggle( 'eu-ai-label-studio__contrast--warning', ! passes );
	}

	document.querySelectorAll( '.eu-ai-label-studio input' ).forEach( function ( input ) {
		input.addEventListener( 'input', update );
		input.addEventListener( 'change', update );
	} );

	document.querySelectorAll( '[data-studio-preset]' ).forEach( function ( preset ) {
		preset.addEventListener( 'click', function () {
			background.value = preset.getAttribute( 'data-bg' );
			text.value = preset.getAttribute( 'data-text' );
			radius.value = preset.getAttribute( 'data-radius' );

			var sizeInput = document.querySelector( 'input[name="eu_ai_label_studio[size]"][value="' + preset.getAttribute( 'data-size' ) + '"]' );
			var iconInput = document.querySelector( 'input[name="eu_ai_label_studio[icon]"][value="' + preset.getAttribute( 'data-icon' ) + '"]' );
			if ( sizeInput ) {
				sizeInput.checked = true;
			}
			if ( iconInput ) {
				iconInput.checked = true;
			}
			update();
		} );
	} );
	update();
}() );
