/**
 * HAKKA Reservations – staff booking screen.
 */
( function () {
	'use strict';

	// Lucide icons (ISC licence), drawn inline so the screen needs no icon font.
	var ICONS = {
		check: '<path d="M20 6 9 17l-5-5"/>',
		x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		plus: '<path d="M5 12h14"/><path d="M12 5v14"/>',
		left: '<path d="m15 18-6-6 6-6"/>',
		right: '<path d="m9 18 6-6-6-6"/>',
		down: '<path d="m6 9 6 6 6-6"/>',
		search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
		pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
		bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
		bellOff: '<path d="M8.7 3A6 6 0 0 1 18 8a21.3 21.3 0 0 0 .6 5"/><path d="M17 17H3s3-2 3-9a4.67 4.67 0 0 1 .3-1.7"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><path d="m2 2 20 20"/>',
		logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
		grid: '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
		calendar: '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
		clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		gift: '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
		alert: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
		info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
		pencil: '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
		note: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		undo: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
		chair: '<path d="M19 9V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v3"/><path d="M3 16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v1.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V11a2 2 0 0 0-4 0z"/><path d="M5 18v2"/><path d="M19 18v2"/>',
		userX: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m17 8 5 5"/><path d="m22 8-5 5"/>',
		inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
		flag: '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>',
		lock: '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'
	};

	function icon( name ) {
		var span = document.createElement( 'span' );
		span.className = 'hkr-i';
		span.setAttribute( 'aria-hidden', 'true' );
		span.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ICONS[ name ] + '</svg>';
		return span;
	}

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( key ) {
				var v = attrs[ key ];
				if ( v === null || v === undefined || v === false ) {
					return;
				}
				if ( key === 'class' ) {
					node.className = v;
				} else if ( key === 'text' ) {
					node.textContent = v;
				} else if ( key.indexOf( 'on' ) === 0 && typeof v === 'function' ) {
					node.addEventListener( key.slice( 2 ), v );
				} else if ( v === true ) {
					node.setAttribute( key, '' );
				} else {
					node.setAttribute( key, v );
				}
			} );
		}
		( children || [] ).forEach( function ( c ) {
			if ( c === null || c === undefined || c === false ) {
				return;
			}
			node.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c );
		} );
		return node;
	}

	/** A button with an icon and a label. iconOnly keeps the label for screen readers and the tooltip. */
	function btn( label, iconName, cls, onClick, iconOnly ) {
		return el( 'button', {
			type: 'button',
			class: 'hkr-s-btn ' + ( cls || '' ) + ( iconOnly ? ' hkr-s-btn--icon' : '' ),
			'aria-label': iconOnly ? label : null,
			title: iconOnly ? label : null,
			onclick: onClick
		}, [ iconName ? icon( iconName ) : null, iconOnly ? null : el( 'span', { text: label } ) ] );
	}

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	function shiftDate( s, days ) {
		var p = s.split( '-' );
		var d = new Date( +p[ 0 ], +p[ 1 ] - 1, +p[ 2 ] + days );
		return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() );
	}

	function plural( n, one, many ) {
		return n + ' ' + ( n === 1 ? one : many );
	}

	/** "7:00pm" → [ "7:00", "pm" ] for the time column. */
	function splitTime( label ) {
		var m = String( label || '' ).match( /^(.*?)\s*([ap]\.?m\.?)$/i );
		return m ? [ m[ 1 ], m[ 2 ].toUpperCase().replace( /\./g, '' ) ] : [ label, '' ];
	}

	/** "HAKKA Port of Spain" → "Port of Spain" when every location shares the first word. */
	function shortNames( locs ) {
		var first = locs.map( function ( l ) {
			return l.name.split( ' ' )[ 0 ];
		} );
		var shared = locs.length > 1 && first.every( function ( w ) {
			return w === first[ 0 ];
		} ) && locs.every( function ( l ) {
			return l.name.indexOf( ' ' ) > 0;
		} );
		var out = {};
		locs.forEach( function ( l ) {
			out[ l.id ] = shared ? l.name.slice( l.name.indexOf( ' ' ) + 1 ) : l.name;
		} );
		return out;
	}

	var STATUS_ORDER = [ 'pending', 'confirmed', 'seated', 'completed', 'no_show', 'cancelled', 'declined' ];

	function Staff( root ) {
		this.root = root;
		this.cfg = JSON.parse( root.getAttribute( 'data-hkr-staff' ) );
		this.state = { view: this.cfg.openDate ? 'day' : 'upcoming', date: this.cfg.openDate || '', location: 0, status: '', search: '', latestId: 0, pending: 0, open: {} };
		if ( this.cfg.openBooking ) {
			this.state.open[ this.cfg.openBooking ] = true;
			this.highlight = this.cfg.openBooking;
		}
		this.locations = [];
		this.names = {};
		this.today = '';
		this.sound = false;
		try {
			this.sound = window.localStorage.getItem( 'hkr_sound' ) === '1';
		} catch ( e ) {}
		this.build();
		this.load();
		this.startPolling();
	}

	Staff.prototype.api = function ( path, opts ) {
		opts = opts || {};
		var self = this;
		return fetch( this.cfg.api.replace( /\/$/, '' ) + path, {
			method: opts.method || 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': this.cfg.nonce, 'Content-Type': 'application/json' },
			body: opts.body ? JSON.stringify( opts.body ) : undefined
		} ).then( function ( res ) {
			return res.json().catch( function () {
				return {};
			} ).then( function ( data ) {
				if ( ! res.ok ) {
					if ( res.status === 401 || res.status === 403 && data && data.code === 'rest_cookie_invalid_nonce' ) {
						self.toast( 'Your session has expired. Reloading…', true );
						setTimeout( function () {
							window.location.reload();
						}, 1500 );
					}
					throw new Error( ( data && data.message ) || 'Something went wrong.' );
				}
				return data;
			} );
		} );
	};

	Staff.prototype.build = function () {
		var self = this;
		this.root.innerHTML = '';

		/* Top bar */
		this.badge = el( 'span', { class: 'hkr-s-badge', hidden: true } );
		this.soundBtn = el( 'button', { type: 'button', class: 'hkr-s-btn hkr-s-btn--quiet hkr-s-sound', onclick: function () {
			self.sound = ! self.sound;
			try {
				window.localStorage.setItem( 'hkr_sound', self.sound ? '1' : '0' );
			} catch ( e ) {}
			if ( self.sound ) {
				self.beep();
			}
			self.updateSoundBtn();
		} } );
		this.updateSoundBtn();
		this.where = el( 'span', { class: 'hkr-s-where' } );
		var initials = ( this.cfg.user || '?' ).trim().charAt( 0 ).toUpperCase();
		var head = el( 'header', { class: 'hkr-s-head' }, [
			el( 'div', { class: 'hkr-s-head__in' }, [
				el( 'div', { class: 'hkr-s-brand' }, [
					this.cfg.logo ? el( 'img', { class: 'hkr-s-logo', src: this.cfg.logo, alt: this.cfg.business || '', onerror: function () {
						this.remove();
					} } ) : null,
					el( 'span', { class: 'hkr-s-brand__sep', 'aria-hidden': 'true' } ),
					el( 'div', { class: 'hkr-s-brand__text' }, [
						el( 'h1', { class: 'hkr-s-title' }, [ 'Bookings', this.badge ] ),
						this.where
					] )
				] ),
				el( 'div', { class: 'hkr-s-head__actions' }, [
					this.soundBtn,
					this.cfg.adminUrl ? el( 'a', { class: 'hkr-s-btn hkr-s-btn--quiet hkr-s-hide-sm', href: this.cfg.adminUrl }, [ icon( 'grid' ), el( 'span', { text: 'Admin' } ) ] ) : null,
					this.cfg.canEdit ? btn( 'Block times', 'lock', 'hkr-s-btn--ghost hkr-s-blockbtn', function () {
						self.blockDialog();
					} ) : null,
					this.cfg.canEdit ? btn( 'Add booking', 'plus', 'hkr-s-btn--primary', function () {
						self.editDialog( null );
					} ) : null,
					el( 'div', { class: 'hkr-s-user' }, [
						el( 'span', { class: 'hkr-s-avatar', 'aria-hidden': 'true', text: initials } ),
						el( 'span', { class: 'hkr-s-user__name hkr-s-hide-sm', text: this.cfg.user } ),
						el( 'a', { class: 'hkr-s-btn hkr-s-btn--quiet hkr-s-btn--icon', href: this.cfg.logoutUrl, title: 'Log out', 'aria-label': 'Log out' }, [ icon( 'logout' ) ] )
					] )
				] )
			] )
		] );

		/* Stat tiles – each one is also a shortcut. */
		this.tiles = {};
		var tile = function ( key, label, iconName, onClick ) {
			var value = el( 'strong', { class: 'hkr-s-tile__value', text: '–' } );
			var sub = el( 'span', { class: 'hkr-s-tile__sub', text: '' } );
			var node = el( 'button', { type: 'button', class: 'hkr-s-tile hkr-s-tile--' + key, onclick: onClick }, [
				el( 'span', { class: 'hkr-s-tile__icon' }, [ icon( iconName ) ] ),
				el( 'span', { class: 'hkr-s-tile__body' }, [ el( 'span', { class: 'hkr-s-tile__label', text: label } ), value, sub ] )
			] );
			self.tiles[ key ] = { node: node, value: value, sub: sub };
			return node;
		};
		var tiles = el( 'section', { class: 'hkr-s-tiles', 'aria-label': 'Overview' }, [
			tile( 'pending', 'Needs confirming', 'alert', function () {
				self.state.view = 'upcoming';
				self.setStatus( 'pending' );
			} ),
			tile( 'today', 'Today', 'clock', function () {
				self.state.status = '';
				self.syncChips();
				self.showDay( self.today );
			} ),
			tile( 'week', 'Next 7 days', 'calendar', function () {
				self.state.view = 'upcoming';
				self.setStatus( '' );
			} )
		] );

		/* Toolbar */
		this.locSeg = el( 'div', { class: 'hkr-s-seg hkr-s-seg--loc', role: 'group', 'aria-label': 'Location', hidden: true } );
		this.upcomingBtn = el( 'button', { type: 'button', class: 'hkr-s-seg__btn', onclick: function () {
			self.state.view = 'upcoming';
			self.load();
		} }, [ 'Upcoming' ] );
		this.todayBtn = el( 'button', { type: 'button', class: 'hkr-s-seg__btn', onclick: function () {
			self.showDay( self.today || '' );
		} }, [ 'Today' ] );
		this.dateInput = el( 'input', { type: 'date', class: 'hkr-s-date__input', 'aria-label': 'Pick a date', onchange: function () {
			if ( this.value ) {
				self.showDay( this.value );
			}
		} } );
		var dateNav = el( 'div', { class: 'hkr-s-date' }, [
			btn( 'Previous day', 'left', 'hkr-s-btn--quiet', function () {
				self.showDay( shiftDate( self.state.date || self.today, -1 ) );
			}, true ),
			el( 'label', { class: 'hkr-s-date__pick' }, [ icon( 'calendar' ), this.dateInput ] ),
			btn( 'Next day', 'right', 'hkr-s-btn--quiet', function () {
				self.showDay( shiftDate( self.state.date || self.today, 1 ) );
			}, true )
		] );
		var searchTimer;
		this.searchInput = el( 'input', { type: 'search', class: 'hkr-s-search__input', placeholder: 'Search name, phone or HK-ref', 'aria-label': 'Search bookings', oninput: function () {
			var v = this.value;
			clearTimeout( searchTimer );
			searchTimer = setTimeout( function () {
				self.state.search = v.trim();
				self.load();
			}, 350 );
		} } );
		this.statusChips = el( 'div', { class: 'hkr-s-chips', role: 'group', 'aria-label': 'Filter by status' } );
		[ [ '', 'All' ] ].concat( STATUS_ORDER.map( function ( k ) {
			return [ k, self.cfg.statuses[ k ] || k ];
		} ) ).forEach( function ( o ) {
			self.statusChips.appendChild( el( 'button', { type: 'button', class: 'hkr-s-chip' + ( o[ 0 ] ? ' hkr-s-chip--' + o[ 0 ] : '' ), 'data-status': o[ 0 ], 'aria-pressed': 'false', onclick: function () {
				self.setStatus( o[ 0 ] );
			} }, [ o[ 0 ] ? el( 'span', { class: 'hkr-s-dot', 'aria-hidden': 'true' } ) : null, o[ 1 ] ] ) );
		} );
		var toolbar = el( 'section', { class: 'hkr-s-toolbar', 'aria-label': 'Filters' }, [
			el( 'div', { class: 'hkr-s-toolbar__row' }, [
				el( 'div', { class: 'hkr-s-seg', role: 'group', 'aria-label': 'View' }, [ this.upcomingBtn, this.todayBtn ] ),
				dateNav,
				this.locSeg,
				el( 'label', { class: 'hkr-s-search' }, [ icon( 'search' ), this.searchInput ] )
			] ),
			this.statusChips
		] );

		this.blocksPanel = el( 'section', { class: 'hkr-s-blocks', 'aria-label': 'Closed to online bookings', hidden: true } );
		this.summary = el( 'div', { class: 'hkr-s-summary', 'aria-live': 'polite' } );
		this.list = el( 'div', { class: 'hkr-s-list' } );
		this.list.appendChild( this.skeleton() );
		this.toasts = el( 'div', { class: 'hkr-s-toasts', 'aria-live': 'assertive' } );
		this.dialog = el( 'dialog', { class: 'hkr-s-dialog' } );
		var main = el( 'main', { class: 'hkr-s-main' }, [ tiles, toolbar, this.blocksPanel, this.summary, this.list ] );
		[ head, main, this.toasts, this.dialog ].forEach( function ( n ) {
			self.root.appendChild( n );
		} );
		this.syncChips();
	};

	Staff.prototype.skeleton = function () {
		var wrap = el( 'div', { class: 'hkr-s-skeleton', 'aria-hidden': 'true' } );
		for ( var i = 0; i < 3; i++ ) {
			wrap.appendChild( el( 'div', { class: 'hkr-s-skeleton__row' } ) );
		}
		return wrap;
	};

	Staff.prototype.setStatus = function ( status ) {
		this.state.status = status;
		this.syncChips();
		return this.load();
	};

	Staff.prototype.syncChips = function () {
		var current = this.state.status;
		Array.prototype.forEach.call( this.statusChips.children, function ( c ) {
			var on = c.getAttribute( 'data-status' ) === current;
			c.classList.toggle( 'is-active', on );
			c.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );
	};

	Staff.prototype.showDay = function ( date ) {
		this.state.view = 'day';
		this.state.date = date;
		return this.load();
	};

	Staff.prototype.updateSoundBtn = function () {
		this.soundBtn.innerHTML = '';
		this.soundBtn.appendChild( icon( this.sound ? 'bell' : 'bellOff' ) );
		this.soundBtn.appendChild( el( 'span', { class: 'hkr-s-sound__label', text: this.sound ? 'Alerts on' : 'Alerts off' } ) );
		this.soundBtn.classList.toggle( 'is-on', this.sound );
		this.soundBtn.setAttribute( 'aria-pressed', this.sound ? 'true' : 'false' );
		this.soundBtn.title = this.sound ? 'Sound alerts are on. Click to mute.' : 'Turn on a sound when a new booking arrives.';
	};

	Staff.prototype.beep = function () {
		try {
			var Ctx = window.AudioContext || window.webkitAudioContext;
			var ctx = this.audio || ( this.audio = new Ctx() );
			[ 0, 0.25, 0.5 ].forEach( function ( delay ) {
				var o = ctx.createOscillator();
				var g = ctx.createGain();
				o.type = 'sine';
				o.frequency.value = 880;
				g.gain.setValueAtTime( 0.0001, ctx.currentTime + delay );
				g.gain.exponentialRampToValueAtTime( 0.4, ctx.currentTime + delay + 0.02 );
				g.gain.exponentialRampToValueAtTime( 0.0001, ctx.currentTime + delay + 0.2 );
				o.connect( g );
				g.connect( ctx.destination );
				o.start( ctx.currentTime + delay );
				o.stop( ctx.currentTime + delay + 0.22 );
			} );
		} catch ( e ) {}
	};

	Staff.prototype.toast = function ( text, warn, onClick ) {
		var t = el( 'div', { class: 'hkr-s-toast' + ( warn ? ' hkr-s-toast--warn' : '' ), role: 'status' }, [ icon( warn ? 'alert' : 'check' ), el( 'span', { text: text } ) ] );
		if ( onClick ) {
			t.classList.add( 'is-clickable' );
			t.addEventListener( 'click', onClick );
		}
		this.toasts.appendChild( t );
		setTimeout( function () {
			t.classList.add( 'is-leaving' );
			setTimeout( function () {
				t.remove();
			}, 300 );
		}, 8000 );
	};

	Staff.prototype.setPending = function ( n ) {
		this.state.pending = n;
		this.badge.hidden = ! n;
		this.badge.textContent = n + ' to confirm';
		document.title = ( n ? '(' + n + ') ' : '' ) + document.title.replace( /^\(\d+\)\s/, '' );
		if ( this.tiles ) {
			this.tiles.pending.value.textContent = String( n );
			this.tiles.pending.sub.textContent = n ? 'Tap to see them' : 'All caught up';
			this.tiles.pending.node.classList.toggle( 'is-alert', n > 0 );
		}
	};

	Staff.prototype.load = function () {
		var self = this;
		var s = this.state;
		var q = '?view=' + s.view + '&date=' + encodeURIComponent( s.date ) + '&location=' + s.location + '&status=' + encodeURIComponent( s.status ) + '&search=' + encodeURIComponent( s.search );
		this.list.setAttribute( 'aria-busy', 'true' );
		var ticket = ( this.ticket = ( this.ticket || 0 ) + 1 );
		return this.api( '/staff/bookings' + q ).then( function ( data ) {
			if ( ticket !== self.ticket ) {
				return; // A newer request is on its way.
			}
			self.list.setAttribute( 'aria-busy', 'false' );
			self.today = data.today;
			if ( s.view === 'day' ) {
				s.date = data.date;
				self.dateInput.value = data.date;
			} else {
				self.dateInput.value = '';
			}
			self.upcomingBtn.classList.toggle( 'is-active', s.view === 'upcoming' && ! s.search );
			self.todayBtn.classList.toggle( 'is-active', s.view === 'day' && s.date === data.today && ! s.search );
			s.latestId = Math.max( s.latestId, data.latestId || 0 );
			self.locations = data.locations || [];
			self.names = shortNames( self.locations );
			self.fillLocations();
			self.setPending( data.pending );
			if ( data.stats ) {
				self.tiles.today.value.textContent = plural( data.stats.today.bookings, 'booking', 'bookings' );
				self.tiles.today.sub.textContent = plural( data.stats.today.guests, 'guest', 'guests' );
				self.tiles.week.value.textContent = plural( data.stats.week.bookings, 'booking', 'bookings' );
				self.tiles.week.sub.textContent = plural( data.stats.week.guests, 'guest', 'guests' );
			}
			var title = s.search ? 'Search results' : ( s.view === 'upcoming' ? 'Upcoming' : data.dateLabel + ( data.date === data.today ? ' · Today' : '' ) );
			if ( s.status ) {
				title += ' · ' + ( self.cfg.statuses[ s.status ] || s.status );
			}
			self.summary.innerHTML = '';
			self.summary.appendChild( el( 'h2', { class: 'hkr-s-summary__title', text: title } ) );
			self.summary.appendChild( el( 'span', { class: 'hkr-s-summary__meta', text: plural( data.bookings.length, 'booking', 'bookings' ) + ' · ' + plural( data.covers, 'guest', 'guests' ) } ) );
			self.blocks = data.blocks || [];
			self.renderBlocks();
			self.renderList( data.bookings );
		} ).catch( function ( e ) {
			if ( ticket !== self.ticket ) {
				return;
			}
			self.list.setAttribute( 'aria-busy', 'false' );
			self.list.innerHTML = '';
			self.list.appendChild( el( 'p', { class: 'hkr-s-error', role: 'alert' }, [ icon( 'alert' ), el( 'span', { text: e.message } ) ] ) );
		} );
	};

	Staff.prototype.fillLocations = function () {
		var self = this;
		var many = this.locations.length > 1;
		this.locSeg.hidden = ! many;
		this.where.textContent = many ? ( this.state.location ? this.names[ this.state.location ] : 'All locations' ) : ( this.locations[ 0 ] ? this.locations[ 0 ].name : '' );
		if ( ! many ) {
			return;
		}
		this.locSeg.innerHTML = '';
		[ { id: 0, label: 'All' } ].concat( this.locations.map( function ( l ) {
			return { id: l.id, label: self.names[ l.id ] };
		} ) ).forEach( function ( o ) {
			var on = self.state.location === o.id;
			self.locSeg.appendChild( el( 'button', { type: 'button', class: 'hkr-s-seg__btn' + ( on ? ' is-active' : '' ), 'aria-pressed': on ? 'true' : 'false', onclick: function () {
				if ( self.state.location !== o.id ) {
					self.state.location = o.id;
					self.load();
				}
			} }, [ o.id ? el( 'span', { class: 'hkr-s-locdot hkr-s-locdot--' + self.locIndex( o.id ), 'aria-hidden': 'true' } ) : null, o.label ] ) );
		} );
	};

	Staff.prototype.locIndex = function ( id ) {
		for ( var i = 0; i < this.locations.length; i++ ) {
			if ( this.locations[ i ].id === id ) {
				return i % 4;
			}
		}
		return 0;
	};

	Staff.prototype.renderList = function ( bookings ) {
		var self = this;
		this.list.innerHTML = '';
		if ( ! bookings.length ) {
			var msg = this.state.search ? 'No bookings match your search.' : ( this.state.status ? 'Nothing here with this status.' : ( this.state.view === 'upcoming' ? 'No upcoming bookings yet.' : 'No bookings on this day.' ) );
			this.list.appendChild( el( 'div', { class: 'hkr-s-empty' }, [ icon( 'inbox' ), el( 'p', { text: msg } ) ] ) );
			return;
		}
		// Group by day, with a running count of bookings and guests for each day.
		var groups = [];
		bookings.forEach( function ( b ) {
			var g = groups[ groups.length - 1 ];
			if ( ! g || g.date !== b.date ) {
				g = { date: b.date, label: b.dateLabel, items: [], guests: 0 };
				groups.push( g );
			}
			g.items.push( b );
			if ( [ 'cancelled', 'declined', 'no_show' ].indexOf( b.status ) < 0 ) {
				g.guests += b.party;
			}
		} );
		groups.forEach( function ( g ) {
			var section = el( 'section', { class: 'hkr-s-day' } );
			section.appendChild( el( 'header', { class: 'hkr-s-day__head' }, [
				el( 'h3', { class: 'hkr-s-day__title' }, [ g.label, g.date === self.today ? el( 'span', { class: 'hkr-s-today', text: 'Today' } ) : null ] ),
				el( 'span', { class: 'hkr-s-day__meta', text: plural( g.items.length, 'booking', 'bookings' ) + ' · ' + plural( g.guests, 'guest', 'guests' ) } )
			] ) );
			var rows = el( 'div', { class: 'hkr-s-rows' } );
			g.items.forEach( function ( b ) {
				rows.appendChild( self.card( b ) );
			} );
			section.appendChild( rows );
			self.list.appendChild( section );
		} );
		if ( this.highlight ) {
			var target = this.list.querySelector( '[data-id="' + this.highlight + '"]' );
			if ( target ) {
				target.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				target.classList.add( 'is-highlight' );
			}
			this.highlight = 0;
		}
	};

	Staff.prototype.card = function ( b ) {
		var self = this;
		var open = !! this.state.open[ b.id ];
		var t = splitTime( b.timeLabel );

		var meta = el( 'div', { class: 'hkr-s-meta' }, [
			el( 'span', { class: 'hkr-s-meta__item hkr-s-meta__party' }, [ icon( 'users' ), plural( b.party, 'guest', 'guests' ) ] ),
			b.area ? el( 'span', { class: 'hkr-s-meta__item' }, [ icon( 'chair' ), b.area ] ) : null,
			this.locations.length > 1 ? el( 'span', { class: 'hkr-s-meta__item' }, [ el( 'span', { class: 'hkr-s-locdot hkr-s-locdot--' + this.locIndex( b.locationId ), 'aria-hidden': 'true' } ), this.names[ b.locationId ] || b.location ] ) : null,
			b.phone ? el( 'a', { class: 'hkr-s-meta__item hkr-s-phone', href: 'tel:' + b.phone }, [ icon( 'phone' ), b.phoneDisplay ] ) : null,
			el( 'span', { class: 'hkr-s-meta__item hkr-s-ref', text: b.ref } )
		] );

		var flags = [];
		if ( b.celebration ) {
			flags.push( el( 'span', { class: 'hkr-s-flag hkr-s-flag--party' }, [ icon( 'gift' ), b.celebration ] ) );
		}
		if ( b.dietary ) {
			flags.push( el( 'span', { class: 'hkr-s-flag hkr-s-flag--warn', title: b.dietary }, [ icon( 'alert' ), 'Allergy / dietary' ] ) );
		}
		if ( b.highChair ) {
			flags.push( el( 'span', { class: 'hkr-s-flag' }, [ icon( 'info' ), 'High chair' ] ) );
		}
		if ( b.largeParty ) {
			flags.push( el( 'span', { class: 'hkr-s-flag hkr-s-flag--warn' }, [ icon( 'users' ), 'Large party' ] ) );
		}
		if ( b.requests ) {
			flags.push( el( 'span', { class: 'hkr-s-flag', title: b.requests }, [ icon( 'note' ), 'Special request' ] ) );
		}

		// Main actions stay visible; the rest live in the details panel.
		var actions = el( 'div', { class: 'hkr-s-actions' } );
		var more = el( 'div', { class: 'hkr-s-actions hkr-s-actions--more' } );
		if ( this.cfg.canEdit ) {
			if ( b.status === 'pending' ) {
				actions.appendChild( btn( 'Confirm', 'check', 'hkr-s-btn--ok', function () {
					self.changeStatus( b, 'confirmed', '', true, this );
				} ) );
				actions.appendChild( btn( 'Decline', 'x', 'hkr-s-btn--ghost', function () {
					self.messageDialog( b, 'declined', 'Decline booking', 'Message to the guest (e.g. suggest another time)' );
				} ) );
			}
			if ( b.status === 'confirmed' ) {
				actions.appendChild( btn( 'Seated', 'chair', 'hkr-s-btn--info', function () {
					self.changeStatus( b, 'seated', '', false, this );
				} ) );
				actions.appendChild( btn( 'No-show', 'userX', 'hkr-s-btn--ghost', function () {
					self.changeStatus( b, 'no_show', '', false, this );
				} ) );
			}
			if ( b.status === 'seated' ) {
				actions.appendChild( btn( 'Completed', 'check', 'hkr-s-btn--ghost', function () {
					self.changeStatus( b, 'completed', '', false, this );
				} ) );
			}
			if ( b.status === 'cancelled' || b.status === 'declined' || b.status === 'no_show' ) {
				actions.appendChild( btn( 'Re-open', 'undo', 'hkr-s-btn--ghost', function () {
					self.changeStatus( b, 'pending', '', false, this );
				} ) );
			}
			more.appendChild( btn( 'Edit booking', 'pencil', 'hkr-s-btn--ghost', function () {
				self.editDialog( b );
			} ) );
			more.appendChild( btn( 'Add note', 'note', 'hkr-s-btn--ghost', function () {
				self.noteDialog( b );
			} ) );
			if ( b.status === 'pending' || b.status === 'confirmed' ) {
				more.appendChild( btn( 'Cancel booking', 'x', 'hkr-s-btn--danger', function () {
					self.messageDialog( b, 'cancelled', 'Cancel booking', 'Message to the guest (optional)' );
				} ) );
			}
		}

		var details = el( 'div', { class: 'hkr-s-details', id: 'hkr-d-' + b.id, hidden: ! open } );
		var dl = el( 'dl', { class: 'hkr-s-dl' } );
		[
			[ 'Time', b.timeLabel + ' – ' + b.endLabel ],
			[ 'Location', b.location ],
			[ 'Email', b.email ],
			[ 'Booked via', this.cfg.sources[ b.source ] || b.source ],
			[ 'Celebration', b.celebrationDetails ],
			[ 'Seating', b.seating ],
			[ 'Dietary / allergies', b.dietary ],
			[ 'Special requests', b.requests ],
			[ 'Staff notes', b.internalNotes ]
		].forEach( function ( r ) {
			if ( r[ 1 ] ) {
				dl.appendChild( el( 'div', { class: 'hkr-s-dl__row' + ( r[ 0 ] === 'Dietary / allergies' ? ' is-warn' : '' ) }, [ el( 'dt', { text: r[ 0 ] } ), el( 'dd', { text: r[ 1 ] } ) ] ) );
			}
		} );
		details.appendChild( dl );
		if ( more.children.length ) {
			details.appendChild( more );
		}

		var toggle = el( 'button', { type: 'button', class: 'hkr-s-toggle', 'aria-expanded': open ? 'true' : 'false', 'aria-controls': 'hkr-d-' + b.id, onclick: function () {
			var now = details.hidden;
			details.hidden = ! now;
			this.setAttribute( 'aria-expanded', now ? 'true' : 'false' );
			this.querySelector( '.hkr-s-toggle__label' ).textContent = now ? 'Less' : 'Details';
			self.state.open[ b.id ] = now;
		} }, [ el( 'span', { class: 'hkr-s-toggle__label', text: open ? 'Less' : 'Details' } ), icon( 'down' ) ] );

		return el( 'article', { class: 'hkr-s-card hkr-s-card--' + b.status, 'data-id': String( b.id ) }, [
			el( 'div', { class: 'hkr-s-card__time' }, [ el( 'strong', { text: t[ 0 ] } ), t[ 1 ] ? el( 'span', { text: t[ 1 ] } ) : null ] ),
			el( 'div', { class: 'hkr-s-card__body' }, [
				el( 'div', { class: 'hkr-s-card__top' }, [
					el( 'h4', { class: 'hkr-s-name', text: b.name } ),
					el( 'span', { class: 'hkr-s-status hkr-s-status--' + b.status }, [ el( 'span', { class: 'hkr-s-dot', 'aria-hidden': 'true' } ), b.statusLabel ] )
				] ),
				meta,
				flags.length ? el( 'div', { class: 'hkr-s-flags' }, flags ) : null,
				details
			] ),
			el( 'div', { class: 'hkr-s-card__side' }, [ actions, toggle ] )
		] );
	};

	/* ----- Online booking blocks ----- */

	/** Blocks that belong together (same day, location, type and note) are shown as one line. */
	Staff.prototype.groupBlocks = function () {
		var s = this.state;
		var groups = [];
		var index = {};
		( this.blocks || [] ).forEach( function ( b ) {
			if ( s.location && b.locationId !== s.location ) {
				return;
			}
			if ( s.view === 'day' && s.date && b.date !== s.date ) {
				return;
			}
			var key = [ b.date, b.locationId, b.areaId, b.note, b.allDay ? 'day' : 'times' ].join( '|' );
			if ( ! index[ key ] ) {
				index[ key ] = { date: b.date, dateLabel: b.dateLabel, location: b.location, locationId: b.locationId, area: b.area, note: b.note, by: b.by, allDay: b.allDay, times: [], ids: [] };
				groups.push( index[ key ] );
			}
			index[ key ].ids.push( b.id );
			if ( ! b.allDay ) {
				index[ key ].times.push( b.timeLabel );
			}
		} );
		return groups;
	};

	Staff.prototype.renderBlocks = function () {
		var self = this;
		var groups = this.groupBlocks();
		this.blocksPanel.innerHTML = '';
		this.blocksPanel.hidden = ! groups.length;
		if ( ! groups.length ) {
			return;
		}
		var shown = this.showAllBlocks ? groups : groups.slice( 0, 6 );
		this.blocksPanel.appendChild( el( 'div', { class: 'hkr-s-blocks__head' }, [
			el( 'span', { class: 'hkr-s-blocks__icon' }, [ icon( 'lock' ) ] ),
			el( 'div', {}, [
				el( 'h2', { class: 'hkr-s-blocks__title', text: 'Closed to online bookings' } ),
				el( 'p', { class: 'hkr-s-blocks__sub', text: 'Guests see these times as full. Staff can still add phone bookings and walk-ins.' } )
			] )
		] ) );
		var list = el( 'ul', { class: 'hkr-s-blocks__list' } );
		shown.forEach( function ( g ) {
			var bits = [ g.allDay ? 'All day' : g.times.join( ', ' ) ];
			if ( self.locations.length > 1 ) {
				bits.push( self.names[ g.locationId ] || g.location );
			}
			bits.push( g.area ? g.area + ' only' : 'All booking types' );
			var who = g.note ? '“' + g.note + '”' + ( g.by ? ' – ' + g.by : '' ) : ( g.by ? 'by ' + g.by : '' );
			list.appendChild( el( 'li', { class: 'hkr-s-block' }, [
				el( 'strong', { class: 'hkr-s-block__date', text: g.dateLabel } ),
				el( 'span', { class: 'hkr-s-block__what', text: bits.join( ' · ' ) } ),
				who ? el( 'span', { class: 'hkr-s-block__note', text: who } ) : null,
				self.cfg.canEdit ? btn( 'Open for online booking again', 'x', 'hkr-s-btn--quiet hkr-s-block__remove', function () {
					self.removeBlocks( g, this );
				}, true ) : null
			] ) );
		} );
		this.blocksPanel.appendChild( list );
		if ( groups.length > 6 ) {
			this.blocksPanel.appendChild( el( 'button', { type: 'button', class: 'hkr-s-toggle', onclick: function () {
				self.showAllBlocks = ! self.showAllBlocks;
				self.renderBlocks();
			} }, [ this.showAllBlocks ? 'Show fewer' : 'Show all ' + groups.length ] ) );
		}
	};

	Staff.prototype.removeBlocks = function ( g, button ) {
		var self = this;
		var label = g.dateLabel + ( g.allDay ? ' (all day)' : ' at ' + g.times.join( ', ' ) );
		if ( ! window.confirm( 'Open ' + label + ' for online bookings again?' ) ) {
			return;
		}
		button.disabled = true;
		g.ids.reduce( function ( chain, id ) {
			return chain.then( function () {
				return self.api( '/staff/blocks/' + id + '/delete', { method: 'POST', body: {} } );
			} );
		}, Promise.resolve() ).then( function () {
			self.toast( label + ' is open for online bookings again.' );
			self.load();
		} ).catch( function ( e ) {
			button.disabled = false;
			self.toast( e.message, true );
		} );
	};

	Staff.prototype.blockDialog = function () {
		var self = this;
		var locs = this.locations;
		if ( ! locs.length ) {
			return;
		}
		var start = this.state.view === 'day' && this.state.date && this.state.date >= this.today ? this.state.date : this.today;
		var locSel = el( 'select', { class: 'hkr-s-input' } );
		locs.forEach( function ( l ) {
			locSel.appendChild( el( 'option', { value: String( l.id ), text: l.name, selected: self.state.location === l.id } ) );
		} );
		var areaSel = el( 'select', { class: 'hkr-s-input' } );
		var from = el( 'input', { type: 'date', class: 'hkr-s-input', required: true, min: this.today, value: start } );
		var to = el( 'input', { type: 'date', class: 'hkr-s-input', min: this.today } );
		var mode = 'day';
		var modeSeg = el( 'div', { class: 'hkr-s-seg hkr-s-seg--wide', role: 'group', 'aria-label': 'What to block' } );
		var timesWrap = el( 'div', { class: 'hkr-s-field hkr-s-field--wide', hidden: true } );
		var timesGrid = el( 'div', { class: 'hkr-s-timegrid', role: 'group', 'aria-label': 'Times to block' } );
		var timesHelp = el( 'small', { class: 'hkr-s-help' } );
		var picked = {};
		var note = el( 'input', { type: 'text', class: 'hkr-s-input', maxlength: '190', placeholder: 'e.g. Mother’s Day – walk-ins and phone only' } );
		timesWrap.appendChild( el( 'span', { text: 'Tap the times to block' } ) );
		timesWrap.appendChild( timesGrid );
		timesWrap.appendChild( timesHelp );

		[ [ 'day', 'Whole day' ], [ 'times', 'Only some times' ] ].forEach( function ( m ) {
			modeSeg.appendChild( el( 'button', { type: 'button', class: 'hkr-s-seg__btn' + ( m[ 0 ] === mode ? ' is-active' : '' ), 'data-mode': m[ 0 ], 'aria-pressed': m[ 0 ] === mode ? 'true' : 'false', onclick: function () {
				mode = m[ 0 ];
				Array.prototype.forEach.call( modeSeg.children, function ( b ) {
					var on = b.getAttribute( 'data-mode' ) === mode;
					b.classList.toggle( 'is-active', on );
					b.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				} );
				timesWrap.hidden = mode !== 'times';
				fillTimes();
			} }, [ m[ 1 ] ] ) );
		} );

		function fillAreas() {
			var loc = locs.filter( function ( l ) {
				return String( l.id ) === locSel.value;
			} )[ 0 ];
			areaSel.innerHTML = '';
			areaSel.appendChild( el( 'option', { value: '0', text: 'All booking types' } ) );
			( loc ? loc.areas : [] ).filter( function ( a ) {
				return a.active;
			} ).forEach( function ( a ) {
				areaSel.appendChild( el( 'option', { value: String( a.id ), text: a.name + ' only' } ) );
			} );
		}
		function fillTimes() {
			if ( mode !== 'times' || ! from.value ) {
				return;
			}
			timesGrid.innerHTML = '';
			timesHelp.textContent = 'Loading times…';
			self.api( '/staff/slots?location=' + locSel.value + '&area=' + areaSel.value + '&date=' + from.value + '&party=1' ).then( function ( data ) {
				timesHelp.textContent = data.slots.length ? ( to.value && to.value !== from.value ? 'These times will be blocked on every day in the range.' : '' ) : 'The restaurant is closed on this day.';
				data.slots.forEach( function ( sl ) {
					var on = !! picked[ sl.time ];
					timesGrid.appendChild( el( 'button', {
						type: 'button',
						class: 'hkr-s-timechip' + ( on ? ' is-active' : '' ) + ( sl.blocked ? ' is-blocked' : '' ),
						'aria-pressed': on ? 'true' : 'false',
						disabled: sl.blocked,
						title: sl.blocked ? 'Already blocked' : null,
						onclick: function () {
							picked[ sl.time ] = ! picked[ sl.time ];
							this.classList.toggle( 'is-active', picked[ sl.time ] );
							this.setAttribute( 'aria-pressed', picked[ sl.time ] ? 'true' : 'false' );
						}
					}, [ el( 'span', { text: sl.label } ), sl.blocked ? el( 'small', { text: 'Blocked' } ) : null ] ) );
				} );
			} ).catch( function ( e ) {
				timesHelp.textContent = e.message;
			} );
		}
		locSel.addEventListener( 'change', function () {
			fillAreas();
			fillTimes();
		} );
		[ areaSel, from, to ].forEach( function ( n ) {
			n.addEventListener( 'change', fillTimes );
		} );
		fillAreas();

		var body = el( 'div', { class: 'hkr-s-grid' }, [
			el( 'p', { class: 'hkr-s-field--wide hkr-s-intro', text: 'Stop online bookings for a busy day or time (e.g. Mother’s Day). Guests will see it as full. You can still add phone bookings and walk-ins here.' } ),
			locs.length > 1 ? labelled( 'Location', locSel ) : null,
			labelled( 'Booking type', areaSel ),
			labelled( 'Date', from ),
			labelled( 'Until (optional, for several days)', to ),
			el( 'div', { class: 'hkr-s-field hkr-s-field--wide' }, [ el( 'span', { text: 'Block' } ), modeSeg ] ),
			timesWrap,
			el( 'label', { class: 'hkr-s-field hkr-s-field--wide' }, [ el( 'span', { text: 'Reason (staff only, optional)' } ), note ] )
		] );

		this.openDialog( 'Block online bookings', body, function () {
			var times = Object.keys( picked ).filter( function ( k ) {
				return picked[ k ];
			} );
			if ( mode === 'times' && ! times.length ) {
				throw new Error( 'Please tap the times you want to block.' );
			}
			if ( to.value && to.value < from.value ) {
				throw new Error( 'The “until” date must be on or after the first date.' );
			}
			return self.api( '/staff/blocks', { method: 'POST', body: {
				location: parseInt( locSel.value, 10 ),
				area: parseInt( areaSel.value, 10 ) || 0,
				from: from.value,
				to: to.value,
				times: mode === 'times' ? times : [],
				note: note.value
			} } ).then( function () {
				self.toast( 'Blocked for online bookings.' );
			} );
		}, 'Block' );
	};

	Staff.prototype.changeStatus = function ( b, status, message, notify, button ) {
		var self = this;
		if ( button ) {
			button.disabled = true;
			button.classList.add( 'is-busy' );
		}
		return this.api( '/staff/bookings/' + b.id + '/status', { method: 'POST', body: { status: status, message: message, notify: notify } } ).then( function () {
			self.toast( b.name + ': ' + ( self.cfg.statuses[ status ] || status ) + ( notify ? ' · guest emailed' : '' ) );
			return self.load();
		} ).catch( function ( e ) {
			if ( button ) {
				button.disabled = false;
				button.classList.remove( 'is-busy' );
			}
			self.toast( e.message, true );
		} );
	};

	Staff.prototype.openDialog = function ( title, body, onSave, saveLabel ) {
		var self = this;
		var d = this.dialog;
		d.innerHTML = '';
		var err = el( 'p', { class: 'hkr-s-error', role: 'alert', hidden: true } );
		var save = el( 'button', { type: 'submit', class: 'hkr-s-btn hkr-s-btn--primary' }, [ saveLabel || 'Save' ] );
		var form = el( 'form', { method: 'dialog', class: 'hkr-s-form' }, [
			el( 'div', { class: 'hkr-s-form__head' }, [
				el( 'h2', { text: title } ),
				btn( 'Close', 'x', 'hkr-s-btn--quiet', function () {
					d.close();
				}, true )
			] ),
			body,
			err,
			el( 'div', { class: 'hkr-s-actions hkr-s-form__foot' }, [
				save,
				el( 'button', { type: 'button', class: 'hkr-s-btn hkr-s-btn--ghost', onclick: function () {
					d.close();
				} }, [ 'Close' ] )
			] )
		] );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			save.disabled = true;
			err.hidden = true;
			Promise.resolve( onSave() ).then( function () {
				d.close();
				self.load();
			} ).catch( function ( ex ) {
				err.textContent = ex.message;
				err.hidden = false;
				save.disabled = false;
			} );
		} );
		d.appendChild( form );
		if ( typeof d.showModal === 'function' ) {
			d.showModal();
		} else {
			d.setAttribute( 'open', '' );
		}
		var first = d.querySelector( 'input, select, textarea' );
		if ( first ) {
			first.focus();
		}
	};

	function labelled( text, input ) {
		return el( 'label', { class: 'hkr-s-field' }, [ el( 'span', { text: text } ), input ] );
	}

	Staff.prototype.messageDialog = function ( b, status, title, prompt ) {
		var self = this;
		var msg = el( 'textarea', { class: 'hkr-s-input', rows: '3' } );
		var notify = el( 'input', { type: 'checkbox', checked: true } );
		this.openDialog( title + ' – ' + b.name, el( 'div', {}, [
			labelled( prompt, msg ),
			el( 'label', { class: 'hkr-s-check' }, [ notify, ' Email the guest' ] )
		] ), function () {
			return self.api( '/staff/bookings/' + b.id + '/status', { method: 'POST', body: { status: status, message: msg.value, notify: notify.checked } } );
		}, title );
	};

	Staff.prototype.noteDialog = function ( b ) {
		var self = this;
		var note = el( 'textarea', { class: 'hkr-s-input', rows: '3', required: true } );
		this.openDialog( 'Add a note – ' + b.name, labelled( 'Only staff see notes', note ), function () {
			if ( ! note.value.trim() ) {
				throw new Error( 'Please write a note.' );
			}
			return self.api( '/staff/bookings/' + b.id + '/note', { method: 'POST', body: { note: note.value } } );
		}, 'Add note' );
	};

	Staff.prototype.editDialog = function ( b ) {
		var self = this;
		var isNew = ! b;
		var locs = this.locations;
		if ( ! locs.length ) {
			return;
		}
		var locSel = el( 'select', { class: 'hkr-s-input', disabled: ! isNew } );
		locs.forEach( function ( l ) {
			var o = el( 'option', { value: String( l.id ), text: l.name } );
			if ( ( b && b.locationId === l.id ) || ( ! b && self.state.location === l.id ) ) {
				o.selected = true;
			}
			locSel.appendChild( o );
		} );
		var areaSel = el( 'select', { class: 'hkr-s-input' } );
		var date = el( 'input', { type: 'date', class: 'hkr-s-input', required: true, value: b ? b.date : ( this.state.view === 'day' && this.state.date ? this.state.date : this.today ) } );
		var party = el( 'input', { type: 'number', min: '1', max: '500', class: 'hkr-s-input', required: true, value: String( b ? b.party : 2 ) } );
		var time = el( 'select', { class: 'hkr-s-input', required: true } );
		var name = el( 'input', { type: 'text', class: 'hkr-s-input', required: true, value: b ? b.name : '' } );
		var phone = el( 'input', { type: 'tel', class: 'hkr-s-input', value: b ? b.phoneDisplay : '' } );
		var email = el( 'input', { type: 'email', class: 'hkr-s-input', value: b ? b.email : '' } );
		var celebration = el( 'select', { class: 'hkr-s-input' } );
		[ [ '', 'None' ] ].concat( this.cfg.celebrations.map( function ( c ) {
			return [ c, c ];
		} ) ).concat( [ [ 'Other', 'Other' ] ] ).forEach( function ( o ) {
			celebration.appendChild( el( 'option', { value: o[ 0 ], text: o[ 1 ] } ) );
		} );
		var celebrationText = el( 'input', { type: 'text', class: 'hkr-s-input', placeholder: 'What are they celebrating?' } );
		if ( b && b.celebration ) {
			if ( this.cfg.celebrations.indexOf( b.celebration ) >= 0 ) {
				celebration.value = b.celebration;
			} else {
				celebration.value = 'Other';
				celebrationText.value = b.celebration;
			}
		}
		var dietary = el( 'textarea', { class: 'hkr-s-input', rows: '2' } );
		dietary.value = b ? b.dietary : '';
		var requests = el( 'textarea', { class: 'hkr-s-input', rows: '2' } );
		requests.value = b ? b.requests : '';
		var status = el( 'select', { class: 'hkr-s-input' }, [ el( 'option', { value: 'confirmed', text: 'Confirmed' } ), el( 'option', { value: 'pending', text: 'Pending' } ), el( 'option', { value: 'seated', text: 'Seated (walk-in)' } ) ] );
		var source = el( 'select', { class: 'hkr-s-input' }, [ el( 'option', { value: 'phone', text: 'Phone' } ), el( 'option', { value: 'walkin', text: 'Walk-in' } ), el( 'option', { value: 'staff', text: 'Staff' } ) ] );
		var notify = el( 'input', { type: 'checkbox', checked: isNew } );
		var slotHelp = el( 'small', { class: 'hkr-s-help' } );

		function fillAreas() {
			var loc = locs.filter( function ( l ) {
				return String( l.id ) === locSel.value;
			} )[ 0 ];
			areaSel.innerHTML = '';
			var areas = loc ? loc.areas.filter( function ( a ) {
				return a.active || ( b && b.areaId === a.id );
			} ) : [];
			if ( ! areas.length ) {
				areaSel.appendChild( el( 'option', { value: '0', text: '—' } ) );
			}
			areas.forEach( function ( a ) {
				var o = el( 'option', { value: String( a.id ), text: a.name } );
				if ( b && b.areaId === a.id ) {
					o.selected = true;
				}
				areaSel.appendChild( o );
			} );
		}
		function fillTimes() {
			var current = time.value || ( b ? b.time : '' );
			time.innerHTML = '';
			slotHelp.textContent = '…';
			var q = '?location=' + locSel.value + '&area=' + areaSel.value + '&date=' + date.value + '&party=' + ( parseInt( party.value, 10 ) || 1 ) + ( b ? '&exclude=' + b.id : '' );
			self.api( '/staff/slots' + q ).then( function ( data ) {
				slotHelp.textContent = data.slots.length ? '' : 'Closed on this day.';
				data.slots.forEach( function ( s ) {
					var label = s.label + ( s.left !== null ? ' (' + s.left + ' left)' : '' ) + ( s.available ? '' : ' – full' ) + ( s.blocked ? ' – blocked online' : '' );
					var o = el( 'option', { value: s.time, text: label } );
					if ( s.time === current ) {
						o.selected = true;
					}
					time.appendChild( o );
				} );
			} ).catch( function ( e ) {
				slotHelp.textContent = e.message;
			} );
		}
		locSel.addEventListener( 'change', function () {
			fillAreas();
			fillTimes();
		} );
		[ areaSel, date, party ].forEach( function ( n ) {
			n.addEventListener( 'change', fillTimes );
		} );
		fillAreas();
		fillTimes();

		var body = el( 'div', { class: 'hkr-s-grid' }, [
			labelled( 'Location', locSel ),
			labelled( 'Booking type', areaSel ),
			labelled( 'Date', date ),
			labelled( 'Guests', party ),
			el( 'label', { class: 'hkr-s-field' }, [ el( 'span', { text: 'Time' } ), time, slotHelp ] ),
			isNew ? labelled( 'Status', status ) : null,
			isNew ? labelled( 'Source', source ) : null,
			labelled( 'Guest name', name ),
			labelled( 'Phone', phone ),
			labelled( 'Email', email ),
			labelled( 'Celebration', celebration ),
			labelled( 'Celebration (other)', celebrationText ),
			labelled( 'Dietary / allergies', dietary ),
			labelled( 'Special requests', requests ),
			el( 'label', { class: 'hkr-s-check hkr-s-field--wide' }, [ notify, isNew ? ' Email the guest a confirmation' : ' Email the guest about the change' ] )
		] );

		this.openDialog( isNew ? 'Add booking' : 'Edit ' + b.ref, body, function () {
			var payload = {
				area: parseInt( areaSel.value, 10 ) || 0,
				date: date.value,
				time: time.value,
				party: parseInt( party.value, 10 ) || 1,
				name: name.value,
				phone: phone.value,
				email: email.value,
				celebration: celebration.value,
				celebration_text: celebrationText.value,
				dietary: dietary.value,
				requests: requests.value,
				notify: notify.checked
			};
			if ( ! payload.time ) {
				throw new Error( 'Please choose a time.' );
			}
			if ( isNew ) {
				payload.location = parseInt( locSel.value, 10 );
				payload.status = status.value;
				payload.source = source.value;
				return self.api( '/staff/bookings', { method: 'POST', body: payload } );
			}
			return self.api( '/staff/bookings/' + b.id, { method: 'POST', body: payload } );
		}, isNew ? 'Add booking' : 'Save' );
	};

	Staff.prototype.startPolling = function () {
		var self = this;
		var every = Math.max( 10, this.cfg.pollSeconds || 30 ) * 1000;
		setInterval( function () {
			if ( document.hidden && ! self.sound ) {
				return;
			}
			self.api( '/staff/poll?since=' + self.state.latestId + '&location=' + self.state.location ).then( function ( data ) {
				self.setPending( data.pending );
				if ( data.new && data.new.length ) {
					self.state.latestId = data.latestId;
					var fresh = data.new.filter( function ( b ) {
						return b.source === 'web';
					} );
					if ( fresh.length ) {
						if ( self.sound ) {
							self.beep();
						}
						fresh.forEach( function ( b ) {
							self.toast( 'New booking: ' + b.name + ' · ' + b.party + ' guests · ' + b.dateLabel + ', ' + b.timeLabel + ' (tap to open)', false, function () {
								self.state.view = 'day';
								self.state.date = b.date;
								self.state.status = '';
								self.syncChips();
								self.highlight = b.id;
								self.state.open[ b.id ] = true;
								self.load();
							} );
						} );
					}
					self.load();
				} else {
					self.state.latestId = Math.max( self.state.latestId, data.latestId || 0 );
				}
			} ).catch( function () {} );
		}, every );
	};

	function boot() {
		var nodes = document.querySelectorAll( '.hkr-staff[data-hkr-staff]' );
		Array.prototype.forEach.call( nodes, function ( node ) {
			if ( ! node.__hkr ) {
				node.__hkr = new Staff( node );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
