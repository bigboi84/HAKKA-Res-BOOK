/* HAKKA Reservations – admin screens. */
( function ( $ ) {
	'use strict';

	var uid = Date.now();

	// Image pickers.
	$( document ).on( 'click', '.hkr-image-choose', function ( e ) {
		e.preventDefault();
		var wrap = $( this ).closest( '.hkr-image-field' );
		var frame = wp.media( { title: hkrAdmin.chooseImage, button: { text: hkrAdmin.useImage }, library: { type: 'image' }, multiple: false } );
		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
			var large = att.sizes && att.sizes.large ? att.sizes.large.url : att.url;
			wrap.find( '.hkr-image-id' ).val( att.id );
			wrap.find( '.hkr-image-preview' ).html( $( '<img alt="">' ).attr( 'src', url ) );
			wrap.find( '.hkr-image-remove' ).prop( 'hidden', false );
			wrap.attr( 'data-large', large ).trigger( 'hkr:image' );
		} );
		frame.open();
	} );
	$( document ).on( 'click', '.hkr-image-remove', function ( e ) {
		e.preventDefault();
		var wrap = $( this ).closest( '.hkr-image-field' );
		wrap.find( '.hkr-image-id' ).val( 0 );
		wrap.find( '.hkr-image-preview' ).empty();
		$( this ).prop( 'hidden', true );
		wrap.attr( 'data-large', '' ).trigger( 'hkr:image' );
	} );

	/*
	 * Photo position editor. Uses the same CSS as the booking form header (object-position plus a zoom
	 * around that point), so what you see here is what guests see.
	 */
	function sourceUrl( id ) {
		var field = id ? document.getElementById( id ) : null;
		return field ? field.getAttribute( 'data-large' ) || '' : '';
	}
	function framerViews( framer ) {
		return $( framer ).find( '.hkr-framer__view' ).map( function () {
			var view = $( this );
			return {
				el: view,
				mode: view.data( 'mode' ),
				frame: view.find( '.hkr-framer__frame' )[ 0 ],
				img: view.find( 'img' )[ 0 ],
				x: view.find( '.hkr-framer__x' ),
				y: view.find( '.hkr-framer__y' ),
				z: view.find( '.hkr-framer__z' ),
				zoom: view.find( '.hkr-framer__zoom' )
			};
		} ).get();
	}
	function paint( v ) {
		var pos = v.x.val() + '% ' + v.y.val() + '%';
		v.img.style.objectPosition = pos;
		v.img.style.transformOrigin = pos;
		v.img.style.transform = 'scale(' + ( v.z.val() / 100 ) + ')';
	}
	function loadFramer( framer ) {
		var main = sourceUrl( framer.getAttribute( 'data-source' ) );
		var phone = sourceUrl( framer.getAttribute( 'data-mob-source' ) ) || main;
		$( framer ).toggleClass( 'has-image', !! main );
		framerViews( framer ).forEach( function ( v ) {
			var src = v.mode === 'm' ? phone : main;
			if ( src && v.img.getAttribute( 'src' ) !== src ) {
				v.img.src = src;
			}
			paint( v );
		} );
	}
	/** How far (in px) the photo can travel inside the frame for a given zoom, on each axis. */
	function travel( v ) {
		var fw = v.frame.clientWidth;
		var fh = v.frame.clientHeight;
		var nw = v.img.naturalWidth || fw;
		var nh = v.img.naturalHeight || fh;
		var cover = Math.max( fw / nw, fh / nh );
		var z = v.z.val() / 100;
		return [ z * nw * cover - fw, z * nh * cover - fh ];
	}
	function nudge( v, dxPct, dyPct ) {
		v.x.val( Math.round( Math.min( 100, Math.max( 0, parseFloat( v.x.val() ) + dxPct ) ) * 10 ) / 10 );
		v.y.val( Math.round( Math.min( 100, Math.max( 0, parseFloat( v.y.val() ) + dyPct ) ) * 10 ) / 10 );
		paint( v );
	}
	function initFramer( framer ) {
		if ( framer.__hkr ) {
			return;
		}
		framer.__hkr = true;
		framerViews( framer ).forEach( function ( v ) {
			var start = null;
			v.frame.addEventListener( 'pointerdown', function ( e ) {
				if ( ! v.img.getAttribute( 'src' ) ) {
					return;
				}
				e.preventDefault();
				v.frame.setPointerCapture( e.pointerId );
				start = { px: e.clientX, py: e.clientY, x: parseFloat( v.x.val() ), y: parseFloat( v.y.val() ), t: travel( v ) };
				v.frame.classList.add( 'is-dragging' );
			} );
			v.frame.addEventListener( 'pointermove', function ( e ) {
				if ( ! start ) {
					return;
				}
				// Dragging the photo right shows more of its left side, so the focus point moves left.
				var nx = start.t[ 0 ] > 0.5 ? start.x - ( e.clientX - start.px ) / start.t[ 0 ] * 100 : start.x;
				var ny = start.t[ 1 ] > 0.5 ? start.y - ( e.clientY - start.py ) / start.t[ 1 ] * 100 : start.y;
				v.x.val( Math.round( Math.min( 100, Math.max( 0, nx ) ) * 10 ) / 10 );
				v.y.val( Math.round( Math.min( 100, Math.max( 0, ny ) ) * 10 ) / 10 );
				paint( v );
			} );
			var stop = function () {
				start = null;
				v.frame.classList.remove( 'is-dragging' );
			};
			v.frame.addEventListener( 'pointerup', stop );
			v.frame.addEventListener( 'pointercancel', stop );
			v.frame.addEventListener( 'keydown', function ( e ) {
				var step = e.shiftKey ? 10 : 2;
				var map = { ArrowLeft: [ step, 0 ], ArrowRight: [ -step, 0 ], ArrowUp: [ 0, step ], ArrowDown: [ 0, -step ] };
				if ( map[ e.key ] ) {
					e.preventDefault();
					nudge( v, map[ e.key ][ 0 ], map[ e.key ][ 1 ] );
				}
			} );
			v.zoom.on( 'input', function () {
				v.z.val( this.value );
				paint( v );
			} );
			v.el.find( '.hkr-framer__reset' ).on( 'click', function () {
				v.x.val( 50 );
				v.y.val( 50 );
				v.z.val( 100 );
				v.zoom.val( 100 );
				paint( v );
			} );
		} );
		loadFramer( framer );
	}
	function initFramers( scope ) {
		$( scope || document ).find( '.hkr-framer' ).each( function () {
			initFramer( this );
		} );
	}
	initFramers();
	$( document ).on( 'hkr:image', '.hkr-image-field', function () {
		var id = this.id;
		$( '.hkr-framer' ).each( function () {
			if ( id && ( this.getAttribute( 'data-source' ) === id || this.getAttribute( 'data-mob-source' ) === id ) ) {
				loadFramer( this );
			}
		} );
		// Keep the small thumbnail next to a booking type's "Photo" heading in step.
		var thumb = $( this ).closest( '.hkr-area-photo' ).find( '.hkr-area-thumb' );
		var preview = $( this ).find( '.hkr-image-preview img' ).attr( 'src' );
		thumb.html( preview ? $( '<img alt="">' ).attr( 'src', preview ) : '' );
	} );

	// Opening hours.
	function syncClosed( row ) {
		var closed = row.find( '.hkr-closed' ).is( ':checked' );
		row.toggleClass( 'is-closed', closed );
		row.find( '.hkr-ranges input' ).prop( 'disabled', closed );
	}
	$( '.hkr-hours tr' ).each( function () {
		syncClosed( $( this ) );
	} );
	$( document ).on( 'change', '.hkr-closed', function () {
		syncClosed( $( this ).closest( 'tr' ) );
	} );
	$( document ).on( 'click', '.hkr-add-range', function ( e ) {
		e.preventDefault();
		var name = $( this ).data( 'name' ) + '[' + ( uid++ ) + ']';
		var range = $( this ).siblings( '.hkr-range' ).last().clone();
		range.find( 'input' ).each( function ( i ) {
			$( this ).attr( 'name', name + '[' + i + ']' ).val( '' ).prop( 'disabled', false );
		} );
		$( this ).before( range );
	} );
	$( document ).on( 'click', '.hkr-remove-range', function ( e ) {
		e.preventDefault();
		var cell = $( this ).closest( '.hkr-ranges' );
		if ( cell.find( '.hkr-range' ).length > 1 ) {
			$( this ).closest( '.hkr-range' ).remove();
		} else {
			cell.closest( 'tr' ).find( '.hkr-closed' ).prop( 'checked', true ).trigger( 'change' );
		}
	} );
	function copyDay( from, toDays ) {
		var src = $( '.hkr-hours tr[data-day="' + from + '"]' );
		toDays.forEach( function ( d ) {
			var row = $( '.hkr-hours tr[data-day="' + d + '"]' );
			var cell = row.find( '.hkr-ranges' );
			cell.find( '.hkr-range' ).remove();
			src.find( '.hkr-range' ).each( function ( i ) {
				var clone = $( this ).clone();
				var srcInputs = $( this ).find( 'input' );
				clone.find( 'input' ).each( function ( j ) {
					$( this ).attr( 'name', 'hours[' + d + '][' + i + '][' + j + ']' ).val( srcInputs.eq( j ).val() );
				} );
				cell.find( '.hkr-add-range' ).before( clone );
			} );
			row.find( '.hkr-closed' ).prop( 'checked', src.find( '.hkr-closed' ).is( ':checked' ) );
			syncClosed( row );
		} );
	}
	$( '.hkr-copy-monday' ).on( 'click', function () {
		copyDay( 1, [ 2, 3, 4, 5 ] );
	} );
	$( '.hkr-copy-all' ).on( 'click', function () {
		copyDay( 1, [ 2, 3, 4, 5, 6, 7 ] );
	} );

	// Repeating rows (special dates, booking types).
	function addRow( templateId, target ) {
		var html = document.getElementById( templateId ).innerHTML.replace( /__i__/g, 'n' + ( uid++ ) );
		var added = $( html ).appendTo( target );
		initFramers( added.parent() );
	}
	$( '.hkr-add-special' ).on( 'click', function () {
		addRow( 'hkr-special-template', '.hkr-special tbody' );
	} );
	$( '.hkr-add-area' ).on( 'click', function () {
		addRow( 'hkr-area-template', '.hkr-areas' );
	} );
	$( document ).on( 'click', '.hkr-remove-row', function ( e ) {
		e.preventDefault();
		if ( window.confirm( hkrAdmin.removeConfirm ) ) {
			var area = $( this ).closest( 'tbody.hkr-area' );
			( area.length ? area : $( this ).closest( 'tr' ) ).remove();
		}
	} );

	// Booking add/edit screen: booking types and available times.
	var form = $( '.hkr-booking-form' );
	if ( form.length ) {
		var areas = form.data( 'areas' ) || {};
		var bookingId = parseInt( form.data( 'booking' ), 10 ) || 0;
		var loc = $( '#hkr-location' );
		var area = $( '#hkr-area' );
		var date = $( '#hkr-date' );
		var party = $( '#hkr-party' );
		var time = $( '#hkr-time' );
		var help = $( '.hkr-slot-help' );

		var fillAreas = function () {
			var current = String( area.val() || area.data( 'current' ) || '' );
			var list = areas[ loc.val() ] || [];
			area.empty();
			if ( ! list.length ) {
				area.append( $( '<option value="0">—</option>' ) );
			}
			list.forEach( function ( a ) {
				area.append( $( '<option>' ).val( a.id ).text( a.name ).prop( 'selected', String( a.id ) === current ) );
			} );
		};
		var fillTimes = function () {
			var current = time.val() || time.data( 'current' ) || '';
			help.text( '…' );
			$.ajax( {
				url: hkrAdmin.api + '/staff/slots',
				data: { location: loc.val(), area: area.val(), date: date.val(), party: party.val(), exclude: bookingId },
				beforeSend: function ( xhr ) {
					xhr.setRequestHeader( 'X-WP-Nonce', hkrAdmin.nonce );
				}
			} ).done( function ( data ) {
				time.empty();
				var slots = data.slots || [];
				help.text( slots.length ? '' : hkrAdmin.noSlots );
				if ( current && ! slots.some( function ( s ) {
					return s.time === current;
				} ) ) {
					time.append( $( '<option>' ).val( current ).text( current ) );
				}
				slots.forEach( function ( s ) {
					var label = s.label + ( s.left !== null ? ' (' + s.left + ' left)' : '' ) + ( s.available ? '' : ' – full' );
					time.append( $( '<option>' ).val( s.time ).text( label ).prop( 'selected', s.time === current ) );
				} );
			} ).fail( function () {
				help.text( '' );
			} );
		};
		loc.on( 'change', function () {
			fillAreas();
			fillTimes();
		} );
		area.add( date ).add( party ).on( 'change', fillTimes );
		fillAreas();
		fillTimes();
	}
} )( jQuery );
