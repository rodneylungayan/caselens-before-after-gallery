/**
 * CaseLens Before and After Gallery — the Case Fields screen.
 *
 * Adds and removes field rows, and hides the choices box for field types that
 * do not use it.
 */
( function () {
	'use strict';

	var rows = document.getElementById( 'caselens-field-rows' );
	var addButton = document.getElementById( 'caselens-add-field' );
	var template = document.getElementById( 'tmpl-caselens-field' );

	if ( ! rows || ! addButton || ! template ) {
		return;
	}

	var nextIndex = rows.querySelectorAll( '.caselens-field-row' ).length;

	addButton.addEventListener( 'click', function () {
		var html = template.innerHTML.replace( /__index__/g, 'new' + nextIndex );
		nextIndex += 1;

		var holder = document.createElement( 'div' );
		holder.innerHTML = html.trim();

		var row = holder.firstElementChild;
		if ( row ) {
			rows.appendChild( row );
			syncChoices( row );
			var label = row.querySelector( 'input[type="text"]' );
			if ( label ) {
				label.focus();
			}
		}
	} );

	rows.addEventListener( 'click', function ( event ) {
		var remove = event.target.closest( '.caselens-remove-field' );
		if ( ! remove ) {
			return;
		}
		event.preventDefault();
		remove.closest( '.caselens-field-row' ).remove();
	} );

	rows.addEventListener( 'change', function ( event ) {
		if ( event.target.classList.contains( 'caselens-field-type' ) ) {
			syncChoices( event.target.closest( '.caselens-field-row' ) );
		}
	} );

	rows.querySelectorAll( '.caselens-field-row' ).forEach( syncChoices );

	/**
	 * Only choice fields need the choices box.
	 *
	 * @param {HTMLElement} row One field row.
	 */
	function syncChoices( row ) {
		if ( ! row ) {
			return;
		}
		var type = row.querySelector( '.caselens-field-type' );
		var choices = row.querySelector( '.caselens-field-choices' );
		if ( ! type || ! choices ) {
			return;
		}
		var usesChoices = 'select' === type.value || 'multiselect' === type.value;
		choices.hidden = ! usesChoices;
	}
} )();
