/**
 * CaseLens Before and After Gallery — single case behaviour.
 *
 * Turns the stack of before/after pairs into a slider and, when the setting is
 * on, opens a photo full size. Without JavaScript every pair is simply listed.
 */
( function () {
	'use strict';

	var cases = document.querySelectorAll( '.caselens-case' );
	if ( ! cases.length ) {
		return;
	}

	cases.forEach( function ( root ) {
		setUpSlider( root );
		if ( '1' === root.getAttribute( 'data-lightbox' ) ) {
			setUpLightbox( root );
		}
	} );

	/**
	 * Show one pair at a time, with arrows and a counter.
	 *
	 * @param {HTMLElement} root The case wrapper.
	 */
	function setUpSlider( root ) {
		var slider = root.querySelector( '.caselens-slider' );
		if ( ! slider ) {
			return;
		}

		var slides = Array.prototype.slice.call( slider.querySelectorAll( '.caselens-slide' ) );
		var prev = slider.querySelector( '.caselens-prev' );
		var next = slider.querySelector( '.caselens-next' );
		var counter = slider.querySelector( '.caselens-slider-count' );

		if ( slides.length < 2 || ! prev || ! next ) {
			return;
		}

		var index = 0;

		prev.hidden = false;
		next.hidden = false;
		if ( counter ) {
			counter.hidden = false;
		}

		function show( target ) {
			index = ( target + slides.length ) % slides.length;
			slides.forEach( function ( slide, position ) {
				slide.hidden = position !== index;
			} );
			if ( counter ) {
				counter.textContent = ( index + 1 ) + ' / ' + slides.length;
			}
		}

		prev.addEventListener( 'click', function () {
			show( index - 1 );
		} );

		next.addEventListener( 'click', function () {
			show( index + 1 );
		} );

		slider.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowLeft' === event.key ) {
				show( index - 1 );
			}
			if ( 'ArrowRight' === event.key ) {
				show( index + 1 );
			}
		} );

		show( 0 );
	}

	/**
	 * Open a photo at full size in an overlay.
	 *
	 * @param {HTMLElement} root The case wrapper.
	 */
	function setUpLightbox( root ) {
		root.addEventListener( 'click', function ( event ) {
			var image = event.target.closest( '.caselens-photo img' );
			if ( ! image ) {
				return;
			}

			var full = image.getAttribute( 'data-full' ) || image.src;
			if ( ! full ) {
				return;
			}

			event.preventDefault();
			open( full, image.alt );
		} );

		function open( src, alt ) {
			var box = document.createElement( 'div' );
			box.className = 'caselens-lightbox';

			var img = document.createElement( 'img' );
			img.src = src;
			img.alt = alt || '';

			var close = document.createElement( 'button' );
			close.type = 'button';
			close.textContent = '×';
			close.setAttribute( 'aria-label', 'Close' );

			box.appendChild( img );
			box.appendChild( close );
			document.body.appendChild( box );
			document.body.classList.add( 'caselens-lock' );
			close.focus();

			function dismiss() {
				box.remove();
				document.body.classList.remove( 'caselens-lock' );
				document.removeEventListener( 'keydown', onKey );
			}

			function onKey( event ) {
				if ( 'Escape' === event.key ) {
					dismiss();
				}
			}

			box.addEventListener( 'click', function ( event ) {
				if ( event.target === box || event.target === close ) {
					dismiss();
				}
			} );

			document.addEventListener( 'keydown', onKey );
		}
	}
} )();
