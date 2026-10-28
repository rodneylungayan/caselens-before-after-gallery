/**
 * CaseLens Before and After Gallery — front-end behaviour.
 *
 * The first page of cards is rendered by PHP, so the gallery works without
 * JavaScript. This script adds procedure switching without a reload, the
 * filter dialog, the number search and "Load more".
 */
( function () {
	'use strict';

	var data = window.caseLensData || {};
	var strings = data.strings || {};

	/**
	 * One gallery instance.
	 *
	 * @param {HTMLElement} app The gallery wrapper.
	 */
	function Gallery( app ) {
		this.app = app;
		this.grid = app.querySelector( '.caselens-grid' );
		this.msg = app.querySelector( '.caselens-msg' );
		this.more = app.querySelector( '.caselens-more' );
		this.heading = app.querySelector( '.caselens-heading' );
		this.intro = app.querySelector( '.caselens-intro' );
		this.nav = app.querySelector( '.caselens-nav' );
		this.chips = app.querySelector( '.caselens-chips' );
		this.filterBtn = app.querySelector( '.caselens-filter-btn' );
		this.filterCount = app.querySelector( '.caselens-filter-count' );
		this.modal = app.querySelector( '.caselens-modal' );
		this.form = app.querySelector( '.caselens-modal-body' );
		this.numberForm = app.querySelector( '.caselens-number' );

		this.base = app.getAttribute( 'data-base' ) || '/';
		this.perPage = parseInt( app.getAttribute( 'data-per-page' ), 10 ) || 12;

		this.state = {
			procedure: app.getAttribute( 'data-procedure' ) || '',
			page: 1,
			pages: parseInt( app.getAttribute( 'data-pages' ), 10 ) || 1,
			number: '',
			filters: {},
			ranges: {}
		};

		this.request = 0;
		this.optionCache = {};
		this.lastFocus = null;

		this.bindEvents();
		this.syncNav();
	}

	Gallery.prototype.bindEvents = function () {
		var self = this;

		if ( this.nav ) {
			this.nav.addEventListener( 'click', function ( event ) {
				var link = event.target.closest( '.caselens-term' );
				if ( ! link || ! self.nav.contains( link ) ) {
					return;
				}

				var parent = link.parentNode;
				var isParent = parent && parent.classList.contains( 'caselens-parent' );

				// A group that is not the current one opens instead of navigating.
				if ( isParent && ! parent.classList.contains( 'is-open' ) ) {
					parent.classList.add( 'is-open' );
				}

				event.preventDefault();
				self.select( link.getAttribute( 'data-slug' ) || '', link.getAttribute( 'href' ) );
			} );
		}

		if ( this.more ) {
			this.more.addEventListener( 'click', function () {
				self.state.page += 1;
				self.load( true );
			} );
		}

		if ( this.numberForm ) {
			this.numberForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				var input = self.numberForm.querySelector( 'input' );
				self.state.number = input ? input.value.trim() : '';
				self.state.page = 1;
				self.load( false );
			} );
		}

		if ( this.filterBtn ) {
			this.filterBtn.hidden = false;
			this.filterBtn.addEventListener( 'click', function () {
				self.openModal();
			} );
		}

		if ( this.modal ) {
			this.modal.addEventListener( 'click', function ( event ) {
				if ( event.target === self.modal || event.target.closest( '.caselens-x' ) ) {
					self.closeModal();
				}
				if ( event.target.closest( '.caselens-apply' ) ) {
					self.applyModal();
				}
				if ( event.target.closest( '.caselens-clear' ) ) {
					self.clearFilters();
				}
			} );

			document.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key && ! self.modal.hidden ) {
					self.closeModal();
				}
			} );
		}

		if ( this.chips ) {
			this.chips.addEventListener( 'click', function ( event ) {
				var chip = event.target.closest( '.caselens-chip' );
				if ( ! chip ) {
					return;
				}
				self.removeFilter( chip.getAttribute( 'data-key' ), chip.getAttribute( 'data-value' ) );
			} );
		}

		window.addEventListener( 'popstate', function () {
			self.state.procedure = self.slugFromUrl();
			self.state.page = 1;
			self.syncNav();
			self.load( false );
		} );
	};

	/** Read the procedure slug out of the current URL. */
	Gallery.prototype.slugFromUrl = function () {
		var path = window.location.pathname;
		if ( 0 === path.indexOf( this.base ) ) {
			return path.slice( this.base.length ).replace( /\/+$/, '' ).split( '/' )[ 0 ] || '';
		}
		return '';
	};

	/** Switch to a procedure. */
	Gallery.prototype.select = function ( slug, url ) {
		if ( slug === this.state.procedure ) {
			return;
		}

		this.state.procedure = slug;
		this.state.page = 1;
		this.state.number = '';
		this.state.filters = {};
		this.state.ranges = {};
		this.optionCache = {};

		if ( url && window.history && window.history.pushState ) {
			window.history.pushState( {}, '', url );
		}

		this.syncNav();
		this.renderChips();
		this.load( false );
	};

	/** Mark the current procedure in the menu and open its branch. */
	Gallery.prototype.syncNav = function () {
		if ( ! this.nav ) {
			return;
		}

		var current = this.state.procedure;
		var links = this.nav.querySelectorAll( '.caselens-term' );

		links.forEach( function ( link ) {
			var isCurrent = ( link.getAttribute( 'data-slug' ) || '' ) === current;
			link.classList.toggle( 'is-current', isCurrent );
			if ( isCurrent ) {
				link.setAttribute( 'aria-current', 'page' );
				var node = link.parentNode;
				while ( node && node !== this.nav ) {
					if ( node.classList && node.classList.contains( 'caselens-parent' ) ) {
						node.classList.add( 'is-open' );
					}
					node = node.parentNode;
				}
			} else {
				link.removeAttribute( 'aria-current' );
			}
		}, this );

		var node = this.findNode( this.procedures(), current );
		if ( this.heading ) {
			this.heading.textContent = node ? node.name : ( this.app.getAttribute( 'data-heading' ) || this.heading.textContent );
		}
		if ( this.intro ) {
			this.intro.textContent = node && node.desc ? node.desc : '';
			this.intro.hidden = ! ( node && node.desc );
		}
	};

	/** The procedure tree. */
	Gallery.prototype.procedures = function () {
		return data.procedures || [];
	};

	/** Find one node in the procedure tree. */
	Gallery.prototype.findNode = function ( nodes, slug ) {
		if ( ! slug ) {
			return null;
		}
		for ( var i = 0; i < nodes.length; i++ ) {
			if ( nodes[ i ].slug === slug ) {
				return nodes[ i ];
			}
			var found = this.findNode( nodes[ i ].children || [], slug );
			if ( found ) {
				return found;
			}
		}
		return null;
	};

	/** Build the query string for the cases endpoint. */
	Gallery.prototype.query = function () {
		var params = new URLSearchParams();
		params.set( 'page', this.state.page );
		params.set( 'per_page', this.perPage );

		if ( this.state.procedure ) {
			params.set( 'procedure', this.state.procedure );
		}
		if ( this.state.number ) {
			params.set( 'number', this.state.number );
		}

		Object.keys( this.state.filters ).forEach( function ( key ) {
			this.state.filters[ key ].forEach( function ( value ) {
				params.append( 'f[' + key + '][]', value );
			} );
		}, this );

		Object.keys( this.state.ranges ).forEach( function ( key ) {
			var range = this.state.ranges[ key ];
			[ 'min', 'max' ].forEach( function ( end ) {
				if ( '' !== range[ end ] && undefined !== range[ end ] && null !== range[ end ] ) {
					params.set( 'r[' + key + '][' + end + ']', range[ end ] );
				}
			} );
		}, this );

		return params.toString();
	};

	/** Fetch and render a page of cases. */
	Gallery.prototype.load = function ( append ) {
		var self = this;
		var token = ++this.request;

		if ( ! append ) {
			this.setMessage( strings.loading || 'Loading…' );
			this.grid.innerHTML = '';
		}
		if ( this.more ) {
			this.more.disabled = true;
		}

		fetch( data.rest + 'cases?' + this.query(), {
			headers: { 'X-WP-Nonce': data.nonce || '' },
			credentials: 'same-origin'
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( function ( payload ) {
				if ( token !== self.request ) {
					return;
				}

				self.state.pages = payload.pages || 1;
				self.render( payload.cases || [], append );

				if ( ! payload.cases || ! payload.cases.length ) {
					self.setMessage( self.state.number ? ( strings.notFound || '' ) : ( strings.none || '' ) );
				} else {
					self.setMessage( '' );
				}

				if ( self.more ) {
					self.more.hidden = self.state.page >= self.state.pages;
					self.more.disabled = false;
				}
			} )
			.catch( function () {
				if ( token !== self.request ) {
					return;
				}
				self.setMessage( strings.error || 'Something went wrong.' );
				if ( self.more ) {
					self.more.disabled = false;
				}
			} );
	};

	/** Put cards in the grid. */
	Gallery.prototype.render = function ( cases, append ) {
		if ( ! append ) {
			this.grid.innerHTML = '';
		}

		var fragment = document.createDocumentFragment();

		cases.forEach( function ( item ) {
			var card = document.createElement( 'a' );
			card.className = 'caselens-card';
			card.href = item.url;

			var pair = document.createElement( 'span' );
			pair.className = 'caselens-pair';

			( item.images || [] ).forEach( function ( image ) {
				var img = document.createElement( 'img' );
				img.src = image.url;
				img.alt = image.alt || '';
				img.loading = 'lazy';
				img.decoding = 'async';
				pair.appendChild( img );
			} );

			var cap = document.createElement( 'span' );
			cap.className = 'caselens-cap';
			cap.textContent = item.title;

			card.appendChild( pair );
			card.appendChild( cap );
			fragment.appendChild( card );
		} );

		this.grid.appendChild( fragment );
	};

	/** Status line under the heading. */
	Gallery.prototype.setMessage = function ( text ) {
		if ( this.msg ) {
			this.msg.textContent = text || '';
		}
	};

	/* ------------------------------------------------------------ Filters */

	Gallery.prototype.openModal = function () {
		var self = this;
		if ( ! this.modal ) {
			return;
		}

		this.lastFocus = document.activeElement;
		this.modal.hidden = false;
		document.body.classList.add( 'caselens-lock' );
		this.filterBtn.setAttribute( 'aria-expanded', 'true' );

		this.form.innerHTML = '<p>' + escapeHtml( strings.loading || 'Loading…' ) + '</p>';

		this.fetchOptions().then( function ( groups ) {
			self.renderForm( groups );
			var first = self.form.querySelector( 'input' );
			if ( first ) {
				first.focus();
			} else {
				self.modal.querySelector( '.caselens-x' ).focus();
			}
		} );
	};

	Gallery.prototype.closeModal = function () {
		if ( ! this.modal ) {
			return;
		}
		this.modal.hidden = true;
		document.body.classList.remove( 'caselens-lock' );
		this.filterBtn.setAttribute( 'aria-expanded', 'false' );
		if ( this.lastFocus ) {
			this.lastFocus.focus();
		}
	};

	/** Load the filter options for the current procedure, once. */
	Gallery.prototype.fetchOptions = function () {
		var self = this;
		var key = this.state.procedure || '*';

		if ( this.optionCache[ key ] ) {
			return Promise.resolve( this.optionCache[ key ] );
		}

		var url = data.rest + 'filters' + ( this.state.procedure ? '?procedure=' + encodeURIComponent( this.state.procedure ) : '' );

		return fetch( url, {
			headers: { 'X-WP-Nonce': data.nonce || '' },
			credentials: 'same-origin'
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				self.optionCache[ key ] = payload.groups || [];
				return self.optionCache[ key ];
			} )
			.catch( function () {
				return [];
			} );
	};

	/** Draw the filter form. */
	Gallery.prototype.renderForm = function ( groups ) {
		this.form.innerHTML = '';

		if ( ! groups.length ) {
			this.form.innerHTML = '<p>' + escapeHtml( strings.none || '' ) + '</p>';
			return;
		}

		groups.forEach( function ( group ) {
			var title = document.createElement( 'h4' );
			title.textContent = group.title;
			this.form.appendChild( title );

			group.fields.forEach( function ( field ) {
				var set = document.createElement( 'fieldset' );
				set.className = 'caselens-fs';

				var legend = document.createElement( 'legend' );
				legend.textContent = field.label;
				set.appendChild( legend );

				if ( 'range' === field.type ) {
					set.appendChild( this.rangeRow( field ) );
				} else {
					set.appendChild( this.choiceRows( field ) );
				}

				this.form.appendChild( set );
			}, this );
		}, this );
	};

	Gallery.prototype.choiceRows = function ( field ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'caselens-opts';
		var chosen = this.state.filters[ field.key ] || [];

		field.options.forEach( function ( option ) {
			var label = document.createElement( 'label' );
			label.className = 'caselens-opt';

			var input = document.createElement( 'input' );
			input.type = 'checkbox';
			input.value = option.values.join( '|' );
			input.setAttribute( 'data-key', field.key );
			input.checked = option.values.some( function ( value ) {
				return chosen.indexOf( value ) !== -1;
			} );

			var text = document.createElement( 'span' );
			text.textContent = option.label;

			var count = document.createElement( 'small' );
			count.textContent = '(' + option.count + ')';

			label.appendChild( input );
			label.appendChild( text );
			label.appendChild( count );
			wrap.appendChild( label );
		} );

		return wrap;
	};

	Gallery.prototype.rangeRow = function ( field ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'caselens-rng';
		var range = this.state.ranges[ field.key ] || {};

		[ 'min', 'max' ].forEach( function ( end, index ) {
			var input = document.createElement( 'input' );
			input.type = 'number';
			input.step = 'any';
			input.min = field.min;
			input.max = field.max;
			input.placeholder = ( 'min' === end ? strings.min : strings.max ) + ' ' + ( 'min' === end ? field.min : field.max );
			input.setAttribute( 'data-key', field.key );
			input.setAttribute( 'data-end', end );
			input.setAttribute( 'aria-label', field.label + ' ' + ( 'min' === end ? strings.min : strings.max ) );
			if ( undefined !== range[ end ] ) {
				input.value = range[ end ];
			}

			if ( 1 === index ) {
				var to = document.createElement( 'span' );
				to.textContent = strings.to || '–';
				wrap.appendChild( to );
			}

			wrap.appendChild( input );
		} );

		return wrap;
	};

	/** Read the form, store the filters and reload. */
	Gallery.prototype.applyModal = function () {
		var filters = {};
		var ranges = {};

		this.form.querySelectorAll( 'input[type="checkbox"]:checked' ).forEach( function ( input ) {
			var key = input.getAttribute( 'data-key' );
			filters[ key ] = ( filters[ key ] || [] ).concat( input.value.split( '|' ) );
		} );

		this.form.querySelectorAll( 'input[type="number"]' ).forEach( function ( input ) {
			var value = input.value.trim();
			if ( '' === value ) {
				return;
			}
			var key = input.getAttribute( 'data-key' );
			ranges[ key ] = ranges[ key ] || {};
			ranges[ key ][ input.getAttribute( 'data-end' ) ] = value;
		} );

		this.state.filters = filters;
		this.state.ranges = ranges;
		this.state.page = 1;

		this.renderChips();
		this.closeModal();
		this.load( false );
	};

	Gallery.prototype.clearFilters = function () {
		this.state.filters = {};
		this.state.ranges = {};
		this.state.page = 1;
		this.form.querySelectorAll( 'input' ).forEach( function ( input ) {
			if ( 'checkbox' === input.type ) {
				input.checked = false;
			} else {
				input.value = '';
			}
		} );
		this.renderChips();
		this.load( false );
	};

	Gallery.prototype.removeFilter = function ( key, value ) {
		if ( this.state.ranges[ key ] ) {
			delete this.state.ranges[ key ];
		} else if ( this.state.filters[ key ] ) {
			this.state.filters[ key ] = this.state.filters[ key ].filter( function ( item ) {
				return value.split( '|' ).indexOf( item ) === -1;
			} );
			if ( ! this.state.filters[ key ].length ) {
				delete this.state.filters[ key ];
			}
		}

		this.state.page = 1;
		this.renderChips();
		this.load( false );
	};

	/** Show one chip per chosen filter. */
	Gallery.prototype.renderChips = function () {
		if ( ! this.chips ) {
			return;
		}

		this.chips.innerHTML = '';
		var total = 0;
		var groups = this.optionCache[ this.state.procedure || '*' ] || [];
		var labels = {};

		groups.forEach( function ( group ) {
			group.fields.forEach( function ( field ) {
				labels[ field.key ] = field;
			} );
		} );

		Object.keys( this.state.filters ).forEach( function ( key ) {
			var field = labels[ key ];
			var values = this.state.filters[ key ];
			var options = field && field.options ? field.options : [];

			options.forEach( function ( option ) {
				var matched = option.values.filter( function ( value ) {
					return values.indexOf( value ) !== -1;
				} );
				if ( ! matched.length ) {
					return;
				}
				total += 1;
				this.chips.appendChild( this.chip( key, option.values.join( '|' ), field.label + ': ' + option.label ) );
			}, this );
		}, this );

		Object.keys( this.state.ranges ).forEach( function ( key ) {
			var field = labels[ key ];
			var range = this.state.ranges[ key ];
			var text = ( field ? field.label : key ) + ': ' +
				( undefined !== range.min ? range.min : '' ) + ' – ' + ( undefined !== range.max ? range.max : '' );
			total += 1;
			this.chips.appendChild( this.chip( key, '', text ) );
		}, this );

		if ( this.filterCount ) {
			this.filterCount.textContent = total;
			this.filterCount.hidden = 0 === total;
		}
	};

	Gallery.prototype.chip = function ( key, value, text ) {
		var chip = document.createElement( 'button' );
		chip.type = 'button';
		chip.className = 'caselens-chip';
		chip.setAttribute( 'data-key', key );
		chip.setAttribute( 'data-value', value );
		chip.setAttribute( 'aria-label', ( strings.remove || 'Remove' ) + ': ' + text );

		var label = document.createElement( 'span' );
		label.textContent = text;

		var cross = document.createElement( 'span' );
		cross.className = 'caselens-chip-x';
		cross.textContent = '×';
		cross.setAttribute( 'aria-hidden', 'true' );

		chip.appendChild( label );
		chip.appendChild( cross );

		return chip;
	};

	/** Escape a string for use in innerHTML. */
	function escapeHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	// Start every gallery on the page, once the methods above are defined.
	document.querySelectorAll( '.caselens-app' ).forEach( function ( app ) {
		new Gallery( app );
	} );
} )();
