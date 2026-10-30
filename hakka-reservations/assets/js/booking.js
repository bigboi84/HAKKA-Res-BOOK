/**
 * HAKKA Reservations – guest booking form.
 * Flow: choose a location (picker only) → photo + location switcher appear →
 * booking type → guests → date → time → details → review.
 */
( function () {
	'use strict';

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

	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( str ).replace( /%(\d+\$)?[sd]/g, function ( m, pos ) {
			var idx = pos ? parseInt( pos, 10 ) - 1 : i++;
			return args[ idx ] !== undefined ? args[ idx ] : '';
		} );
	}

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	function ymd( d ) {
		return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() );
	}

	function parseYmd( s ) {
		var p = s.split( '-' );
		return new Date( +p[ 0 ], +p[ 1 ] - 1, +p[ 2 ] );
	}

	function prettyDate( s ) {
		try {
			return parseYmd( s ).toLocaleDateString( undefined, { weekday: 'long', day: 'numeric', month: 'long' } );
		} catch ( e ) {
			return s;
		}
	}

	var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

	// Server error codes that belong to a field on the details step.
	var SERVER_FIELD = {
		hkr_name: 'name',
		hkr_phone: 'phone',
		hkr_email: 'email',
		hkr_celebration: 'celebration_text',
		hkr_seating: 'seating',
		hkr_dietary_notes: 'dietary',
		hkr_special_requests: 'requests'
	};

	function Booking( root ) {
		this.root = root;
		this.cfg = JSON.parse( root.getAttribute( 'data-hkr-config' ) );
		this.t = this.cfg.i18n;
		this.state = { loc: null, area: null, party: 2, date: '', time: '', timeLabel: '', details: {} };
		this.calendar = {};
		this.month = null;
		this.step = 'type';
		this.serverErrors = null;
		this.render();
	}

	Booking.prototype.api = function ( path, opts ) {
		opts = opts || {};
		var url = this.cfg.api.replace( /\/$/, '' ) + path;
		return fetch( url, {
			method: opts.method || 'GET',
			headers: opts.body ? { 'Content-Type': 'application/json' } : {},
			body: opts.body ? JSON.stringify( opts.body ) : undefined,
			credentials: 'same-origin'
		} ).then( function ( res ) {
			return res.json().catch( function () {
				return {};
			} ).then( function ( data ) {
				if ( ! res.ok ) {
					var err = new Error( ( data && data.message ) || 'Error' );
					err.status = res.status;
					err.code = data && data.code;
					throw err;
				}
				return data;
			} );
		} );
	};

	/** A location is only pre-selected when the link or shortcode names one (?loc=brentwood). */
	Booking.prototype.initialLocation = function () {
		var locs = this.cfg.locations;
		if ( locs.length === 1 ) {
			return locs[ 0 ];
		}
		var params = new URLSearchParams( window.location.search );
		var wanted = this.cfg.preselect || params.get( 'loc' );
		var found = null;
		locs.forEach( function ( l ) {
			if ( wanted && ( l.slug === wanted || String( l.id ) === wanted ) ) {
				found = l;
			}
		} );
		return found;
	};

	Booking.prototype.render = function () {
		var self = this;
		this.root.innerHTML = '';

		this.heroImgs = [ el( 'img', { class: 'hkr-hero__img is-active', alt: '', loading: 'lazy', decoding: 'async' } ), el( 'img', { class: 'hkr-hero__img', alt: '', loading: 'lazy', decoding: 'async' } ) ];
		this.heroInfo = el( 'div', { class: 'hkr-hero__info', 'aria-live': 'polite' } );
		// The photo stays hidden until the guest has chosen a location.
		this.hero = el( 'div', { class: 'hkr-hero', hidden: true }, [
			el( 'div', { class: 'hkr-hero__media' }, this.heroImgs ),
			el( 'div', { class: 'hkr-hero__shade' } ),
			el( 'div', { class: 'hkr-hero__content' }, [ this.heroInfo ] )
		] );
		this.chips = el( 'div', { class: 'hkr-chips', 'aria-live': 'polite' } );
		this.body = el( 'div', { class: 'hkr-body' } );
		this.root.appendChild( this.hero );
		this.root.appendChild( el( 'div', { class: 'hkr-card' }, [ this.chips, this.body ] ) );

		var initial = this.initialLocation();
		if ( initial ) {
			this.selectLocation( initial, true );
		} else {
			this.root.classList.add( 'hkr-booking--picking' );
			this.view_location();
		}
	};

	/** Back from the first step: hide the photo and show the location choice again. */
	Booking.prototype.backToLocations = function () {
		this.state.loc = null;
		this.state.area = null;
		this.state.date = '';
		this.state.time = '';
		this.step = 'location';
		this.hero.hidden = true;
		this.root.classList.add( 'hkr-booking--picking' );
		this.view_location();
		var h = this.body.querySelector( '.hkr-title' );
		if ( h ) {
			h.focus( { preventScroll: true } );
		}
		if ( this.root.getBoundingClientRect().top < 0 ) {
			window.scrollTo( { top: this.root.getBoundingClientRect().top + window.pageYOffset - 80, behavior: 'smooth' } );
		}
	};

	/** First screen: just the choice of location. */
	Booking.prototype.view_location = function () {
		var self = this;
		this.chips.innerHTML = '';
		this.body.innerHTML = '';
		this.body.appendChild( el( 'div', { class: 'hkr-step' }, [
			el( 'h2', { class: 'hkr-title', text: this.t.chooseLocation, tabindex: '-1' } ),
			el( 'div', { class: 'hkr-options hkr-options--locations' }, this.cfg.locations.map( function ( l ) {
				return el( 'button', { type: 'button', class: 'hkr-option', onclick: function () {
					self.selectLocation( l );
				} }, [
					el( 'strong', { text: l.name } ),
					l.address ? el( 'span', { text: l.address } ) : null,
					l.phone ? el( 'span', { text: l.phone } ) : null
				] );
			} ) )
		] ) );
	};

	/** The header photo to show: the chosen booking type's photo, else the location's (phone version on small screens). */
	Booking.prototype.heroMedia = function ( loc, area ) {
		var phone = this.phoneQuery && this.phoneQuery.matches;
		var pick = function ( src, alt, frame, phoneSrc ) {
			frame = frame || {};
			return {
				src: ( phone && phoneSrc ) || src,
				alt: alt || '',
				pos: ( phone ? frame.posM : frame.pos ) || '50% 50%',
				zoom: ( phone ? frame.zoomM : frame.zoom ) || 1
			};
		};
		if ( area && area.image ) {
			return pick( area.image, area.imageAlt || area.name, area.frame );
		}
		if ( loc && loc.image ) {
			return pick( loc.image, loc.imageAlt, loc.frame, loc.imageMobile );
		}
		return pick( this.cfg.defaultImage || '', '', null );
	};

	function frameImg( img, m ) {
		img.style.objectPosition = m.pos;
		img.style.transformOrigin = m.pos;
		img.style.transform = m.zoom > 1 ? 'scale(' + m.zoom + ')' : '';
	}

	/** Cross-fade the header photo to match the current location and booking type. */
	Booking.prototype.paintHero = function () {
		var m = this.heroMedia( this.state.loc, this.state.area );
		var current = this.heroImgs[ 0 ].classList.contains( 'is-active' ) ? 0 : 1;
		var nextImg = this.heroImgs[ 1 - current ];
		var curImg = this.heroImgs[ current ];
		this.hero.classList.toggle( 'hkr-hero--noimage', ! m.src );
		if ( ! m.src ) {
			return;
		}
		if ( curImg.getAttribute( 'src' ) === m.src ) {
			frameImg( curImg, m );
			curImg.alt = m.alt;
			return;
		}
		frameImg( nextImg, m );
		nextImg.onload = function () {
			nextImg.onload = null;
			nextImg.classList.add( 'is-active' );
			curImg.classList.remove( 'is-active' );
		};
		nextImg.alt = m.alt;
		nextImg.src = m.src;
		if ( nextImg.complete && nextImg.naturalWidth ) {
			nextImg.onload();
		}
	};

	Booking.prototype.showHero = function ( loc ) {
		var self = this;
		if ( ! this.phoneQuery && window.matchMedia ) {
			// The header is shaped differently on phones, so it has its own photo position.
			this.phoneQuery = window.matchMedia( '(max-width: 600px)' );
			var refresh = function () {
				if ( self.state.loc && ! self.hero.hidden ) {
					self.paintHero();
				}
			};
			if ( this.phoneQuery.addEventListener ) {
				this.phoneQuery.addEventListener( 'change', refresh );
			} else if ( this.phoneQuery.addListener ) {
				this.phoneQuery.addListener( refresh );
			}
		}
		this.paintHero();
		this.heroInfo.innerHTML = '';
		if ( loc ) {
			var lines = [ el( 'h2', { class: 'hkr-hero__name', text: loc.name } ) ];
			if ( loc.address ) {
				lines.push( el( 'p', { class: 'hkr-hero__line', text: loc.address } ) );
			}
			var links = el( 'p', { class: 'hkr-hero__links' } );
			if ( loc.phone ) {
				links.appendChild( el( 'a', { href: 'tel:' + loc.phoneRaw, text: loc.phone } ) );
			}
			if ( loc.mapUrl ) {
				links.appendChild( el( 'a', { href: loc.mapUrl, target: '_blank', rel: 'noopener', text: this.t.directions } ) );
			}
			lines.push( links );
			if ( loc.hours && loc.hours.length ) {
				lines.push( el( 'p', { class: 'hkr-hero__hours', text: loc.hours.join( ' · ' ) } ) );
			}
			lines.forEach( function ( n ) {
				this.heroInfo.appendChild( n );
			}, this );
		}
	};

	Booking.prototype.selectLocation = function ( loc, initial ) {
		var self = this;
		this.state.loc = loc;
		this.state.area = null;
		this.state.date = '';
		this.state.time = '';
		this.calendar = {};
		this.month = null;
		this.state.party = Math.max( loc.minParty, Math.min( this.state.party, loc.maxParty ) );
		if ( this.hero.hidden ) {
			this.hero.classList.add( 'is-entering' );
			this.hero.hidden = false;
			this.root.classList.remove( 'hkr-booking--picking' );
			window.requestAnimationFrame( function () {
				window.requestAnimationFrame( function () {
					self.hero.classList.remove( 'is-entering' );
				} );
			} );
		}
		this.showHero( loc );
		if ( loc.areas.length === 1 ) {
			this.state.area = loc.areas[ 0 ];
			this.paintHero();
		}
		this.go( loc.areas.length > 1 ? 'type' : 'party', initial );
	};

	Booking.prototype.limits = function () {
		var loc = this.state.loc;
		var area = this.state.area;
		var min = loc.minParty;
		var max = loc.maxParty;
		if ( area ) {
			min = Math.max( min, area.minParty || 1 );
			if ( area.maxParty ) {
				max = area.maxParty;
			}
		}
		return [ min, Math.max( min, max ) ];
	};

	Booking.prototype.renderChips = function () {
		var self = this;
		var s = this.state;
		this.chips.innerHTML = '';
		var items = [];
		if ( s.area && s.loc.areas.length > 1 ) {
			items.push( [ 'type', s.area.name ] );
		}
		if ( this.order().indexOf( this.step ) > this.order().indexOf( 'party' ) ) {
			items.push( [ 'party', s.party + ' ' + ( s.party === 1 ? this.t.guest : this.t.guests ) ] );
		}
		if ( s.date ) {
			items.push( [ 'date', prettyDate( s.date ) ] );
		}
		if ( s.time ) {
			items.push( [ 'time', s.timeLabel ] );
		}
		items.forEach( function ( item ) {
			self.chips.appendChild( el( 'button', { type: 'button', class: 'hkr-chip', title: self.t.change, onclick: function () {
				self.go( item[ 0 ] );
			} }, [ item[ 1 ] ] ) );
		} );
	};

	Booking.prototype.order = function () {
		var steps = [];
		if ( this.state.loc && this.state.loc.areas.length > 1 ) {
			steps.push( 'type' );
		}
		return steps.concat( [ 'party', 'date', 'time', 'details', 'review' ] );
	};

	Booking.prototype.go = function ( step, noFocus ) {
		this.step = step;
		this.body.innerHTML = '';
		this.renderChips();
		var view = this[ 'view_' + step ]();
		var order = this.order();
		var progress = el( 'p', { class: 'hkr-progress', text: fmt( this.t.step, order.indexOf( step ) + 1, order.length ) } );
		this.body.appendChild( progress );
		this.body.appendChild( view );
		if ( ! noFocus ) {
			var h = view.querySelector( '.hkr-title' );
			if ( h ) {
				h.focus( { preventScroll: true } );
				if ( this.root.getBoundingClientRect().top < 0 ) {
					window.scrollTo( { top: this.root.getBoundingClientRect().top + window.pageYOffset - 80, behavior: 'smooth' } );
				}
			}
		}
	};

	Booking.prototype.nav = function ( prev, next ) {
		var bar = el( 'div', { class: 'hkr-actions' } );
		if ( prev ) {
			bar.appendChild( el( 'button', { type: 'button', class: 'hkr-btn hkr-btn--ghost', onclick: prev }, [ this.t.back ] ) );
		}
		if ( next ) {
			bar.appendChild( next );
		}
		return bar;
	};

	Booking.prototype.prevOf = function ( step ) {
		var order = this.order();
		var i = order.indexOf( step );
		var self = this;
		if ( i > 0 ) {
			return function () {
				self.go( order[ i - 1 ] );
			};
		}
		// First step: back to the choice of location (when there is more than one).
		return this.cfg.locations.length > 1 ? function () {
			self.backToLocations();
		} : null;
	};

	Booking.prototype.view_type = function () {
		var self = this;
		var loc = this.state.loc;
		// When some types have photos, give the others a matching strip so the cards line up.
		var anyPhoto = loc.areas.some( function ( a ) {
			return !! a.thumb;
		} );
		return el( 'div', { class: 'hkr-step' }, [
			el( 'h2', { class: 'hkr-title', tabindex: '-1', text: this.t.chooseType } ),
			el( 'div', { class: 'hkr-options' }, loc.areas.map( function ( a ) {
				var on = self.state.area && self.state.area.id === a.id;
				return el( 'button', {
					type: 'button',
					class: 'hkr-option' + ( on ? ' is-active' : '' ),
					'aria-pressed': on ? 'true' : 'false',
					onclick: function () {
						if ( ! self.state.area || self.state.area.id !== a.id ) {
							self.state.area = a;
							self.paintHero();
							self.state.date = '';
							self.state.time = '';
							self.calendar = {};
							var lim = self.limits();
							self.state.party = Math.max( lim[ 0 ], Math.min( self.state.party, lim[ 1 ] ) );
						}
						self.go( 'party' );
					}
				}, [
					a.thumb ? el( 'span', { class: 'hkr-option__media', 'aria-hidden': 'true' }, [ el( 'img', { src: a.thumb, alt: '', loading: 'lazy', decoding: 'async', style: 'object-position:' + ( a.frame && a.frame.pos ? a.frame.pos : '50% 50%' ) } ) ] ) : ( anyPhoto ? el( 'span', { class: 'hkr-option__media hkr-option__media--empty', 'aria-hidden': 'true' }, [ a.name.charAt( 0 ) ] ) : null ),
					el( 'strong', { text: a.name } ),
					a.description ? el( 'span', { text: a.description } ) : null
				] );
			} ) ),
			this.nav( this.prevOf( 'type' ), null )
		] );
	};

	Booking.prototype.view_party = function () {
		var self = this;
		var lim = this.limits();
		var value = el( 'output', { class: 'hkr-stepper__value', 'aria-live': 'polite' } );
		var minus = el( 'button', { type: 'button', class: 'hkr-stepper__btn', 'aria-label': '−' }, [ '−' ] );
		var plus = el( 'button', { type: 'button', class: 'hkr-stepper__btn', 'aria-label': '+' }, [ '+' ] );
		function update() {
			value.textContent = self.state.party + ' ' + ( self.state.party === 1 ? self.t.guest : self.t.guests );
			minus.disabled = self.state.party <= lim[ 0 ];
			plus.disabled = self.state.party >= lim[ 1 ];
		}
		minus.addEventListener( 'click', function () {
			self.state.party = Math.max( lim[ 0 ], self.state.party - 1 );
			self.state.time = '';
			update();
		} );
		plus.addEventListener( 'click', function () {
			self.state.party = Math.min( lim[ 1 ], self.state.party + 1 );
			self.state.time = '';
			update();
		} );
		this.state.party = Math.max( lim[ 0 ], Math.min( this.state.party, lim[ 1 ] ) );
		update();
		var loc = this.state.loc;
		var groupText = String( this.t.largeGroup ).split( '%2$s' );
		return el( 'div', { class: 'hkr-step' }, [
			el( 'h2', { class: 'hkr-title', tabindex: '-1', text: this.t.partySize } ),
			el( 'div', { class: 'hkr-stepper' }, [ minus, value, plus ] ),
			loc.phone ? el( 'p', { class: 'hkr-help' }, [
				fmt( groupText[ 0 ], lim[ 1 ] ),
				el( 'a', { href: 'tel:' + loc.phoneRaw, text: loc.phone } ),
				groupText[ 1 ] || ''
			] ) : null,
			this.nav( this.prevOf( 'party' ), el( 'button', { type: 'button', class: 'hkr-btn', onclick: function () {
				self.go( 'date' );
			} }, [ this.t.next ] ) )
		] );
	};

	Booking.prototype.loadCalendar = function () {
		var key = this.state.loc.id + ':' + ( this.state.area ? this.state.area.id : 0 );
		if ( this.calendar[ key ] ) {
			return Promise.resolve( this.calendar[ key ] );
		}
		var self = this;
		var q = '?location=' + this.state.loc.id + ( this.state.area ? '&area=' + this.state.area.id : '' );
		return this.api( '/calendar' + q ).then( function ( data ) {
			self.calendar[ key ] = data.dates || {};
			return self.calendar[ key ];
		} );
	};

	Booking.prototype.view_date = function () {
		var self = this;
		var wrap = el( 'div', { class: 'hkr-step' }, [ el( 'h2', { class: 'hkr-title', tabindex: '-1', text: this.t.chooseDate } ) ] );
		var cal = el( 'div', { class: 'hkr-calendar', 'aria-busy': 'true' }, [ el( 'div', { class: 'hkr-loading', text: '…' } ) ] );
		wrap.appendChild( cal );
		wrap.appendChild( this.nav( this.prevOf( 'date' ), null ) );
		this.loadCalendar().then( function ( dates ) {
			self.drawMonth( cal, dates );
		} ).catch( function ( e ) {
			cal.innerHTML = '';
			cal.appendChild( el( 'p', { class: 'hkr-error', text: e.message || self.t.error } ) );
		} );
		return wrap;
	};

	Booking.prototype.drawMonth = function ( cal, dates ) {
		var self = this;
		var keys = Object.keys( dates ).sort();
		if ( ! keys.length ) {
			cal.innerHTML = '';
			cal.appendChild( el( 'p', { class: 'hkr-error', text: this.t.noSlots } ) );
			return;
		}
		var first = parseYmd( keys[ 0 ] );
		var last = parseYmd( keys[ keys.length - 1 ] );
		if ( ! this.month ) {
			var firstOpen = keys.filter( function ( k ) {
				return dates[ k ];
			} )[ 0 ];
			var start = this.state.date ? parseYmd( this.state.date ) : ( firstOpen ? parseYmd( firstOpen ) : first );
			this.month = new Date( start.getFullYear(), start.getMonth(), 1 );
		}
		var m = this.month;
		cal.innerHTML = '';
		cal.setAttribute( 'aria-busy', 'false' );
		var monthLabel = m.toLocaleDateString( undefined, { month: 'long', year: 'numeric' } );
		var canPrev = new Date( m.getFullYear(), m.getMonth(), 1 ) > new Date( first.getFullYear(), first.getMonth(), 1 );
		var canNext = new Date( m.getFullYear(), m.getMonth() + 1, 1 ) <= last;
		cal.appendChild( el( 'div', { class: 'hkr-cal__head' }, [
			el( 'button', { type: 'button', class: 'hkr-cal__nav', 'aria-label': this.t.prevMonth, disabled: ! canPrev, onclick: function () {
				self.month = new Date( m.getFullYear(), m.getMonth() - 1, 1 );
				self.drawMonth( cal, dates );
			} }, [ '‹' ] ),
			el( 'strong', { class: 'hkr-cal__month', text: monthLabel, 'aria-live': 'polite' } ),
			el( 'button', { type: 'button', class: 'hkr-cal__nav', 'aria-label': this.t.nextMonth, disabled: ! canNext, onclick: function () {
				self.month = new Date( m.getFullYear(), m.getMonth() + 1, 1 );
				self.drawMonth( cal, dates );
			} }, [ '›' ] )
		] ) );
		var grid = el( 'div', { class: 'hkr-cal__grid', role: 'grid' } );
		this.t.weekdays.forEach( function ( w ) {
			grid.appendChild( el( 'span', { class: 'hkr-cal__dow', 'aria-hidden': 'true', text: w } ) );
		} );
		var offset = ( m.getDay() + 6 ) % 7; // Monday first.
		for ( var i = 0; i < offset; i++ ) {
			grid.appendChild( el( 'span', { class: 'hkr-cal__blank' } ) );
		}
		var daysInMonth = new Date( m.getFullYear(), m.getMonth() + 1, 0 ).getDate();
		for ( var d = 1; d <= daysInMonth; d++ ) {
			( function ( day ) {
				var key = ymd( new Date( m.getFullYear(), m.getMonth(), day ) );
				var open = dates[ key ] === true;
				var outside = ! ( key in dates ); // Before today or past the booking window.
				var selected = self.state.date === key;
				grid.appendChild( el( 'button', {
					type: 'button',
					class: 'hkr-cal__day' + ( selected ? ' is-active' : '' ) + ( open ? '' : ( outside ? ' is-outside' : ' is-closed' ) ),
					disabled: ! open,
					'aria-pressed': selected ? 'true' : 'false',
					'aria-label': prettyDate( key ) + ( open ? '' : ' – ' + self.t.closed ),
					onclick: function () {
						self.state.date = key;
						self.state.time = '';
						self.go( 'time' );
					}
				}, [ String( day ) ] ) );
			} )( d );
		}
		cal.appendChild( grid );
	};

	Booking.prototype.view_time = function () {
		var self = this;
		var s = this.state;
		var wrap = el( 'div', { class: 'hkr-step' }, [ el( 'h2', { class: 'hkr-title', tabindex: '-1', text: this.t.chooseTime } ) ] );
		var list = el( 'div', { class: 'hkr-slots', 'aria-busy': 'true' }, [ el( 'div', { class: 'hkr-loading', text: '…' } ) ] );
		var msg = el( 'p', { class: 'hkr-error', role: 'alert', hidden: true } );
		wrap.appendChild( msg );
		wrap.appendChild( list );
		wrap.appendChild( this.nav( this.prevOf( 'time' ), null ) );
		if ( this.pendingError ) {
			msg.textContent = this.pendingError;
			msg.hidden = false;
			this.pendingError = '';
		}
		var q = '?location=' + s.loc.id + '&date=' + s.date + '&party=' + s.party + ( s.area ? '&area=' + s.area.id : '' );
		this.api( '/slots' + q ).then( function ( data ) {
			list.innerHTML = '';
			list.setAttribute( 'aria-busy', 'false' );
			if ( data.note ) {
				wrap.insertBefore( el( 'p', { class: 'hkr-help', text: data.note } ), list );
			}
			var any = false;
			( data.slots || [] ).forEach( function ( slot ) {
				any = any || slot.available;
				var extra = '';
				if ( ! slot.available ) {
					extra = self.t.full;
				} else if ( slot.left !== null && slot.left !== undefined && slot.left <= 10 ) {
					extra = fmt( self.t.spotsLeft, slot.left );
				}
				list.appendChild( el( 'button', {
					type: 'button',
					class: 'hkr-slot' + ( s.time === slot.time ? ' is-active' : '' ),
					disabled: ! slot.available,
					'aria-pressed': s.time === slot.time ? 'true' : 'false',
					onclick: function () {
						s.time = slot.time;
						s.timeLabel = slot.label;
						self.go( 'details' );
					}
				}, [ el( 'span', { class: 'hkr-slot__time', text: slot.label } ), extra ? el( 'span', { class: 'hkr-slot__left', text: extra } ) : null ] ) );
			} );
			if ( ! any ) {
				list.appendChild( el( 'p', { class: 'hkr-help', text: self.t.noSlots } ) );
			}
		} ).catch( function ( e ) {
			list.innerHTML = '';
			msg.textContent = e.message || self.t.error;
			msg.hidden = false;
		} );
		return wrap;
	};

	/* ---------- Details form ---------- */

	/**
	 * One labelled field. Required fields get a red asterisk and aria-required; each field has its own
	 * error line under it.
	 */
	Booking.prototype.field = function ( opts ) {
		var d = this.state.details;
		var id = 'hkr-f-' + opts.name + '-' + Math.random().toString( 36 ).slice( 2, 7 );
		var star = el( 'span', { class: 'hkr-req', 'aria-hidden': 'true', text: '*' } );
		star.hidden = ! opts.required;
		var label = el( 'label', { class: 'hkr-label', for: id }, [ opts.label, ' ', star, opts.optional ? el( 'span', { class: 'hkr-optional', text: ' (' + this.t.optional + ')' } ) : null ] );
		var input;
		if ( opts.type === 'textarea' ) {
			input = el( 'textarea', { id: id, name: opts.name, class: 'hkr-input', rows: '3', maxlength: opts.max || '1000' } );
			input.value = d[ opts.name ] || '';
		} else if ( opts.type === 'select' ) {
			input = el( 'select', { id: id, name: opts.name, class: 'hkr-input' } );
			opts.options.forEach( function ( o ) {
				var opt = el( 'option', { value: o[ 0 ], text: o[ 1 ] } );
				if ( ( d[ opts.name ] || '' ) === o[ 0 ] ) {
					opt.selected = true;
				}
				input.appendChild( opt );
			} );
		} else {
			input = el( 'input', { id: id, name: opts.name, type: opts.type || 'text', class: 'hkr-input', autocomplete: opts.autocomplete || null, maxlength: opts.max || null, inputmode: opts.inputmode || null } );
			input.value = d[ opts.name ] || '';
		}
		input.required = !! opts.required;
		if ( opts.required ) {
			input.setAttribute( 'aria-required', 'true' );
		}
		var help = opts.help ? el( 'p', { class: 'hkr-help', id: id + '-help', text: opts.help } ) : null;
		var err = el( 'p', { class: 'hkr-field-error', id: id + '-err', hidden: true } );
		input.setAttribute( 'aria-describedby', ( help ? id + '-help ' : '' ) + id + '-err' );
		var field = {
			key: opts.name,
			label: opts.label,
			needMsg: opts.needMsg || this.t.required,
			input: input,
			star: star,
			err: err,
			wrap: el( 'div', { class: 'hkr-field' + ( opts.wide ? ' hkr-field--wide' : '' ) }, [ label, input, help, err ] )
		};
		var self = this;
		var clear = function () {
			self.clearFieldError( field );
		};
		input.addEventListener( 'input', clear );
		input.addEventListener( 'change', clear );
		return field;
	};

	Booking.prototype.setRequired = function ( field, required ) {
		field.input.required = required;
		field.star.hidden = ! required;
		if ( required ) {
			field.input.setAttribute( 'aria-required', 'true' );
		} else {
			field.input.removeAttribute( 'aria-required' );
		}
	};

	Booking.prototype.showFieldError = function ( field, message ) {
		field.err.textContent = message;
		field.err.hidden = false;
		field.input.setAttribute( 'aria-invalid', 'true' );
	};

	Booking.prototype.clearFieldError = function ( field ) {
		field.err.hidden = true;
		field.err.textContent = '';
		field.input.removeAttribute( 'aria-invalid' );
	};

	Booking.prototype.askCelebration = function () {
		var s = this.state;
		return s.area ? !! s.area.askCelebration : true;
	};

	Booking.prototype.view_details = function () {
		var self = this;
		var t = this.t;
		var cfg = this.cfg;
		var d = this.state.details;
		var form = el( 'form', { class: 'hkr-step hkr-form', novalidate: true } );
		form.appendChild( el( 'h2', { class: 'hkr-title', tabindex: '-1', text: t.yourDetails } ) );
		form.appendChild( el( 'p', { class: 'hkr-required-note' }, [ el( 'span', { class: 'hkr-req', 'aria-hidden': 'true', text: '*' } ), ' ' + t.requiredNote ] ) );
		var alertBox = el( 'div', { class: 'hkr-alert', role: 'alert', tabindex: '-1', hidden: true } );
		form.appendChild( alertBox );

		var grid = el( 'div', { class: 'hkr-grid' } );
		var fields = {};
		fields.name = this.field( { name: 'name', label: t.name, needMsg: t.needName, required: true, autocomplete: 'name', max: 120 } );
		fields.phone = this.field( { name: 'phone', label: t.phone, needMsg: t.needPhone, type: 'tel', required: true, autocomplete: 'tel', inputmode: 'tel', help: t.phoneHelp, max: 30 } );
		fields.email = this.field( { name: 'email', label: t.email, needMsg: t.needEmail, type: 'email', required: true, autocomplete: 'email', max: 190, wide: true } );
		[ 'name', 'phone', 'email' ].forEach( function ( k ) {
			grid.appendChild( fields[ k ].wrap );
		} );

		if ( this.askCelebration() ) {
			var opts = [ [ '', t.none ] ].concat( cfg.celebrations.map( function ( c ) {
				return [ c, c ];
			} ) ).concat( [ [ 'Other', t.other ] ] );
			fields.celebration = this.field( { name: 'celebration', label: t.celebrating, type: 'select', options: opts, wide: true } );
			fields.celebration_text = this.field( { name: 'celebration_text', label: t.celebratingWhat, needMsg: t.needCelebration, max: 120, wide: true } );
			fields.celebration_details = this.field( { name: 'celebration_details', label: t.celebrationNote, type: 'textarea', max: 500, wide: true } );
			grid.appendChild( fields.celebration.wrap );
			grid.appendChild( fields.celebration_text.wrap );
			grid.appendChild( fields.celebration_details.wrap );
			var toggle = function () {
				var v = fields.celebration.input.value;
				fields.celebration_text.wrap.hidden = v !== 'Other';
				self.setRequired( fields.celebration_text, v === 'Other' );
				fields.celebration_details.wrap.hidden = v === '';
			};
			fields.celebration.input.addEventListener( 'change', toggle );
			toggle();
		}

		if ( cfg.seating ) {
			fields.seating = this.field( { name: 'seating', label: t.seating, needMsg: t.needSeating, type: 'select', required: cfg.seating.required, optional: ! cfg.seating.required, options: [ [ '', '—' ] ].concat( cfg.seating.options.map( function ( o ) {
				return [ o, o ];
			} ) ) } );
			grid.appendChild( fields.seating.wrap );
		}
		[ [ 'dietary', t.dietary, cfg.fields.dietary ], [ 'requests', t.requests, cfg.fields.requests ] ].forEach( function ( f ) {
			if ( f[ 2 ] === 'off' ) {
				return;
			}
			fields[ f[ 0 ] ] = self.field( { name: f[ 0 ], label: f[ 1 ], type: 'textarea', required: f[ 2 ] === 'required', optional: f[ 2 ] !== 'required', wide: true } );
			grid.appendChild( fields[ f[ 0 ] ].wrap );
		} );
		if ( cfg.fields.highChair !== 'off' ) {
			var hc = el( 'input', { type: 'checkbox', name: 'high_chair', id: 'hkr-hc' } );
			hc.checked = !! d.high_chair;
			fields.high_chair = { key: 'high_chair', input: hc };
			grid.appendChild( el( 'div', { class: 'hkr-field hkr-field--wide hkr-check' }, [ hc, el( 'label', { for: 'hkr-hc', text: t.highChair } ) ] ) );
		}
		form.appendChild( grid );

		// Honeypot: hidden from people, bots fill it in.
		var hp = el( 'input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off' } );
		form.appendChild( el( 'div', { class: 'hkr-hp', 'aria-hidden': 'true' }, [ el( 'label', { text: 'Website' } ), hp ] ) );

		form.appendChild( this.nav( this.prevOf( 'details' ), el( 'button', { type: 'submit', class: 'hkr-btn' }, [ t.next ] ) ) );

		// Show a list of everything that needs fixing, and a message under each field.
		var showProblems = function ( problems ) {
			alertBox.innerHTML = '';
			alertBox.appendChild( el( 'strong', { text: t.fixFields } ) );
			var list = el( 'ul' );
			problems.forEach( function ( p ) {
				var link = el( 'a', { href: '#' + p.f.input.id, text: p.f.label.replace( /\s*\*$/, '' ), onclick: function ( e ) {
					e.preventDefault();
					p.f.input.focus();
				} } );
				list.appendChild( el( 'li' , {}, [ link, ' – ' + p.short ] ) );
				self.showFieldError( p.f, p.msg );
			} );
			alertBox.appendChild( list );
			alertBox.hidden = false;
			alertBox.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			problems[ 0 ].f.input.focus( { preventScroll: true } );
		};

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var problems = [];
			alertBox.hidden = true;
			Object.keys( fields ).forEach( function ( k ) {
				var f = fields[ k ];
				var input = f.input;
				var value = input.type === 'checkbox' ? input.checked : input.value.trim();
				d[ k ] = value;
				if ( ! f.err ) {
					return;
				}
				self.clearFieldError( f );
				if ( f.wrap.hidden ) {
					return;
				}
				if ( input.required && ! value ) {
					problems.push( { f: f, short: t.isRequired, msg: f.needMsg } );
				} else if ( k === 'email' && value && ! EMAIL_RE.test( value ) ) {
					problems.push( { f: f, short: t.invalidEmailShort, msg: t.invalidEmail } );
				} else if ( k === 'phone' && value && value.replace( /\D/g, '' ).length < 7 ) {
					problems.push( { f: f, short: t.invalidPhoneShort, msg: t.invalidPhone } );
				}
			} );
			d.website = hp.value;
			if ( problems.length ) {
				showProblems( problems );
				return;
			}
			self.go( 'review' );
		} );

		// Problems the server found when the booking was sent (e.g. a phone number it could not use).
		if ( this.serverErrors ) {
			var server = this.serverErrors;
			this.serverErrors = null;
			var list = [];
			Object.keys( server ).forEach( function ( k ) {
				if ( fields[ k ] && fields[ k ].err ) {
					list.push( { f: fields[ k ], short: server[ k ], msg: server[ k ] } );
				}
			} );
			if ( list.length ) {
				window.setTimeout( function () {
					showProblems( list );
				}, 0 );
			}
		}
		return form;
	};

	Booking.prototype.view_review = function () {
		var self = this;
		var t = this.t;
		var s = this.state;
		var d = s.details;
		var rows = [ [ t.location, s.loc.name ] ];
		if ( s.area ) {
			rows.push( [ t.type, s.area.name ] );
		}
		rows.push( [ t.date, prettyDate( s.date ) ], [ t.time, s.timeLabel ], [ t.party, s.party + ' ' + ( s.party === 1 ? t.guest : t.guests ) ], [ t.name, d.name ], [ t.phone, d.phone ], [ t.email, d.email ] );
		var celebration = d.celebration === 'Other' ? d.celebration_text : d.celebration;
		if ( celebration ) {
			rows.push( [ t.celebration, celebration ] );
		}
		if ( d.dietary ) {
			rows.push( [ t.dietary, d.dietary ] );
		}
		if ( d.requests ) {
			rows.push( [ t.requests, d.requests ] );
		}
		if ( d.high_chair ) {
			rows.push( [ t.highChair, '✓' ] );
		}
		var dl = el( 'dl', { class: 'hkr-summary' } );
		rows.forEach( function ( r ) {
			dl.appendChild( el( 'dt', { text: r[ 0 ] } ) );
			dl.appendChild( el( 'dd', { text: r[ 1 ] } ) );
		} );

		var terms = el( 'input', { type: 'checkbox', id: 'hkr-terms', 'aria-required': 'true', 'aria-describedby': 'hkr-terms-err' } );
		var termsErr = el( 'p', { class: 'hkr-field-error', id: 'hkr-terms-err', hidden: true } );
		terms.addEventListener( 'change', function () {
			termsErr.hidden = true;
			terms.removeAttribute( 'aria-invalid' );
		} );
		var termsBox = el( 'div', { class: 'hkr-terms' }, [
			this.cfg.terms ? el( 'p', { class: 'hkr-help', text: this.cfg.terms } ) : null,
			el( 'div', { class: 'hkr-check' }, [ terms, el( 'label', { for: 'hkr-terms' }, [ t.terms + ' ', el( 'span', { class: 'hkr-req', 'aria-hidden': 'true', text: '*' } ), this.cfg.termsUrl ? ' · ' : '', this.cfg.termsUrl ? el( 'a', { href: this.cfg.termsUrl, target: '_blank', rel: 'noopener', text: t.readTerms } ) : null ] ) ] ),
			termsErr
		] );
		var turnstile = null;
		if ( this.cfg.turnstileSite ) {
			turnstile = el( 'div', { class: 'hkr-turnstile' } );
			setTimeout( function () {
				if ( window.turnstile ) {
					self.turnstileId = window.turnstile.render( turnstile, { sitekey: self.cfg.turnstileSite } );
				}
			}, 50 );
		}
		var err = el( 'p', { class: 'hkr-error', role: 'alert', hidden: true } );
		var submit = el( 'button', { type: 'button', class: 'hkr-btn' }, [ t.submit ] );
		submit.addEventListener( 'click', function () {
			if ( ! terms.checked ) {
				termsErr.textContent = t.termsRequired;
				termsErr.hidden = false;
				terms.setAttribute( 'aria-invalid', 'true' );
				terms.focus();
				return;
			}
			submit.disabled = true;
			submit.textContent = t.sending;
			err.hidden = true;
			var body = {
				location: s.loc.id,
				area: s.area ? s.area.id : 0,
				date: s.date,
				time: s.time,
				party: s.party,
				name: d.name,
				phone: d.phone,
				email: d.email,
				celebration: d.celebration || '',
				celebration_text: d.celebration_text || '',
				celebration_details: d.celebration ? ( d.celebration_details || '' ) : '',
				seating: d.seating || '',
				high_chair: !! d.high_chair,
				dietary: d.dietary || '',
				requests: d.requests || '',
				terms: true,
				website: d.website || ''
			};
			if ( self.cfg.turnstileSite && window.turnstile && self.turnstileId !== undefined ) {
				body.turnstile = window.turnstile.getResponse( self.turnstileId ) || '';
			}
			self.api( '/bookings', { method: 'POST', body: body } ).then( function ( res ) {
				self.done( res );
			} ).catch( function ( e ) {
				submit.disabled = false;
				submit.textContent = t.submit;
				if ( e.code === 'hkr_full' || e.code === 'hkr_time' || e.code === 'hkr_window' ) {
					self.pendingError = e.message;
					s.time = '';
					self.go( 'time' );
					return;
				}
				if ( SERVER_FIELD[ e.code ] ) {
					self.serverErrors = {};
					self.serverErrors[ SERVER_FIELD[ e.code ] ] = e.message;
					self.go( 'details' );
					return;
				}
				err.textContent = e.message || t.error;
				err.hidden = false;
				if ( window.turnstile && self.turnstileId !== undefined ) {
					window.turnstile.reset( self.turnstileId );
				}
			} );
		} );
		return el( 'div', { class: 'hkr-step' }, [
			el( 'h2', { class: 'hkr-title', tabindex: '-1', text: t.review } ),
			dl,
			el( 'p', { class: 'hkr-confirm-note', text: s.loc.confirmNote } ),
			termsBox,
			turnstile,
			err,
			this.nav( this.prevOf( 'review' ), submit )
		] );
	};

	Booking.prototype.summaryOf = function ( res ) {
		var t = this.t;
		return el( 'dl', { class: 'hkr-summary' }, [
			el( 'dt', { text: t.reference } ), el( 'dd', { text: res.ref } ),
			el( 'dt', { text: t.location } ), el( 'dd', { text: res.location } ),
			res.area ? el( 'dt', { text: t.type } ) : null, res.area ? el( 'dd', { text: res.area } ) : null,
			el( 'dt', { text: t.date } ), el( 'dd', { text: res.date } ),
			el( 'dt', { text: t.time } ), el( 'dd', { text: res.time } ),
			el( 'dt', { text: t.party } ), el( 'dd', { text: res.party + ' ' + ( res.party === 1 ? t.guest : t.guests ) } )
		] );
	};

	Booking.prototype.done = function ( res ) {
		var self = this;
		var t = this.t;
		this.chips.innerHTML = '';
		this.body.innerHTML = '';
		var title = el( 'h2', { class: 'hkr-title', tabindex: '-1', text: t.thanksTitle } );
		this.body.appendChild( el( 'div', { class: 'hkr-step hkr-done', role: 'status' }, [
			el( 'div', { class: 'hkr-done__icon', 'aria-hidden': 'true', text: '✓' } ),
			title,
			el( 'p', { class: 'hkr-done__note', text: res.confirmNote } ),
			this.summaryOf( res ),
			el( 'p', { class: 'hkr-help', text: fmt( t.thanksEmail, res.email ) } ),
			el( 'div', { class: 'hkr-actions' }, [ el( 'button', { type: 'button', class: 'hkr-btn hkr-btn--ghost', onclick: function () {
				self.state.details = {};
				self.state.date = '';
				self.state.time = '';
				self.go( self.order()[ 0 ] );
			} }, [ t.bookAnother ] ) ] )
		] ) );
		this.popup( res, title );
	};

	/**
	 * Pop-up over the page as soon as the booking is received. Closing it leaves the same details on the form.
	 */
	Booking.prototype.popup = function ( res, returnFocus ) {
		var t = this.t;
		var dialog = el( 'dialog', { class: 'hkr-booking hkr-modal', 'aria-labelledby': 'hkr-modal-title', style: this.root.getAttribute( 'style' ) || null } );
		var close = function () {
			if ( typeof dialog.close === 'function' && dialog.open ) {
				dialog.close();
			}
			dialog.remove();
			if ( returnFocus ) {
				returnFocus.focus();
			}
		};
		var closeBtn = el( 'button', { type: 'button', class: 'hkr-modal__x', 'aria-label': t.close, onclick: close }, [ '×' ] );
		dialog.appendChild( el( 'div', { class: 'hkr-card hkr-modal__card' }, [
			closeBtn,
			el( 'div', { class: 'hkr-done__icon', 'aria-hidden': 'true', text: '✓' } ),
			el( 'h2', { class: 'hkr-title', id: 'hkr-modal-title', text: t.thanksTitle } ),
			el( 'p', { class: 'hkr-done__note', text: res.confirmNote } ),
			this.summaryOf( res ),
			el( 'p', { class: 'hkr-help', text: fmt( t.thanksEmail, res.email ) } ),
			el( 'div', { class: 'hkr-actions hkr-actions--center' }, [ el( 'button', { type: 'button', class: 'hkr-btn', onclick: close }, [ t.gotIt ] ) ] )
		] ) );
		dialog.addEventListener( 'click', function ( e ) {
			if ( e.target === dialog ) {
				close(); // Click on the dark backdrop.
			}
		} );
		dialog.addEventListener( 'cancel', function ( e ) {
			e.preventDefault();
			close(); // Escape key.
		} );
		document.body.appendChild( dialog );
		if ( typeof dialog.showModal === 'function' ) {
			dialog.showModal();
		} else {
			dialog.setAttribute( 'open', '' );
		}
		closeBtn.focus();
	};

	function boot() {
		var nodes = document.querySelectorAll( '.hkr-booking[data-hkr-config]' );
		Array.prototype.forEach.call( nodes, function ( node ) {
			if ( ! node.__hkr ) {
				node.__hkr = new Booking( node );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
