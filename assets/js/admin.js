/**
 * CaseLens Before and After Gallery — case editor.
 *
 * Adds, removes and reorders before/after rows, and opens the media library
 * for each slot.
 */
( function ( $ ) {
	'use strict';

	var settings = window.caseLensAdmin || {};
	var wrap = document.getElementById( 'caselens-pairs' );
	var addButton = document.getElementById( 'caselens-add-pair' );
	var template = document.getElementById( 'tmpl-caselens-pair' );

	if ( ! wrap || ! addButton || ! template ) {
		return;
	}

	var nextIndex = wrap.querySelectorAll( '.caselens-pair' ).length;

	addButton.addEventListener( 'click', function () {
		var html = template.innerHTML.replace( /__index__/g, 'new' + nextIndex );
		nextIndex += 1;

		var holder = document.createElement( 'div' );
		holder.innerHTML = html.trim();

		var row = holder.firstElementChild;
		if ( row ) {
			wrap.appendChild( row );
			row.scrollIntoView( { block: 'nearest' } );
		}
	} );

	wrap.addEventListener( 'click', function ( event ) {
		var pick = event.target.closest( '.caselens-pick' );
		if ( pick ) {
			event.preventDefault();
			chooseImage( pick.closest( '.caselens-slot' ) );
			return;
		}

		var clear = event.target.closest( '.caselens-clear' );
		if ( clear ) {
			event.preventDefault();
			clearSlot( clear.closest( '.caselens-slot' ) );
			return;
		}

		var remove = event.target.closest( '.caselens-remove-pair' );
		if ( remove ) {
			event.preventDefault();
			remove.closest( '.caselens-pair' ).remove();
		}
	} );

	if ( $ && $.fn && $.fn.sortable ) {
		$( wrap ).sortable( {
			handle: '.caselens-pair-handle',
			items: '> .caselens-pair',
			axis: 'y',
			cursor: 'move',
			opacity: 0.7
		} );
	}

	/**
	 * Open the media library and store the chosen attachment.
	 *
	 * @param {HTMLElement} slot The before or after slot.
	 */
	function chooseImage( slot ) {
		if ( ! slot || ! window.wp || ! window.wp.media ) {
			return;
		}

		var frame = window.wp.media( {
			title: settings.frameTitle || 'Select a photo',
			button: { text: settings.useImage || 'Use this image' },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var input = slot.querySelector( '.caselens-slot-id' );
			var button = slot.querySelector( '.caselens-pick' );
			var clear = slot.querySelector( '.caselens-clear' );

			var url = attachment.url;
			if ( attachment.sizes && attachment.sizes.thumbnail ) {
				url = attachment.sizes.thumbnail.url;
			}

			input.value = attachment.id;
			button.classList.add( 'has-image' );
			button.innerHTML = '';

			var img = document.createElement( 'img' );
			img.src = url;
			img.alt = '';
			button.appendChild( img );

			if ( clear ) {
				clear.hidden = false;
			}
		} );

		frame.open();
	}

	/**
	 * Empty one slot.
	 *
	 * @param {HTMLElement} slot The before or after slot.
	 */
	function clearSlot( slot ) {
		var input = slot.querySelector( '.caselens-slot-id' );
		var button = slot.querySelector( '.caselens-pick' );
		var clear = slot.querySelector( '.caselens-clear' );

		input.value = '';
		button.classList.remove( 'has-image' );
		button.innerHTML = '';

		var text = document.createElement( 'span' );
		text.className = 'caselens-pick-text';
		text.textContent = settings.chooseImage || 'Choose image';
		button.appendChild( text );

		if ( clear ) {
			clear.hidden = true;
		}
	}
} )( window.jQuery );
