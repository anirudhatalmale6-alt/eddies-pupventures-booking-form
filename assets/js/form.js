/**
 * Eddie's Pupventures — booking form.
 *
 * Progressive enhancement: the markup is a plain, complete form. This turns it
 * into a one-question-at-a-time flow, draws the signature pad and works out a
 * price estimate. If it never runs, the form still submits.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) { fn(); }
		else { document.addEventListener( 'DOMContentLoaded', fn ); }
	}

	ready( function () {
		var forms = document.querySelectorAll( '.pupbf-form' );
		Array.prototype.forEach.call( forms, setup );
	} );

	function setup( form ) {
		var steps = Array.prototype.slice.call( form.querySelectorAll( '.pupbf-step' ) );
		if ( ! steps.length ) { return; }

		form.classList.add( 'pupbf-stepped' );

		var fill    = form.querySelector( '.pupbf-progress-fill' );
		var nowText = form.querySelector( '.pupbf-step-now' );
		var current = 0;

		/* ---- anchor so we can scroll back to the top of the form ---- */
		var top = document.createElement( 'div' );
		top.id = 'pupbf-top';
		top.style.scrollMarginTop = '90px'; // clears the sticky header
		form.parentNode.insertBefore( top, form );

		/* ---- if the server sent us back with errors, open that step ---- */
		var firstError = form.querySelector( '.pupbf-has-error' );
		if ( firstError ) {
			var errStep = firstError.closest( '.pupbf-step' );
			if ( errStep ) { current = steps.indexOf( errStep ); }
		}

		function show( index, scroll ) {
			current = Math.max( 0, Math.min( steps.length - 1, index ) );
			steps.forEach( function ( s, i ) {
				s.classList.toggle( 'is-active', i === current );
			} );
			if ( fill ) { fill.style.width = ( ( current + 1 ) / steps.length * 100 ) + '%'; }
			if ( nowText ) { nowText.textContent = String( current + 1 ); }
			if ( scroll ) {
				top.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
			// A canvas sized while hidden comes out at zero width.
			resizeSignature( form );
		}

		form.addEventListener( 'click', function ( e ) {
			var next = e.target.closest( '.pupbf-next' );
			var back = e.target.closest( '.pupbf-back' );
			if ( next ) {
				e.preventDefault();
				if ( validateStep( steps[ current ] ) ) { show( current + 1, true ); }
				return;
			}
			if ( back ) {
				e.preventDefault();
				show( current - 1, true );
			}
		} );

		/* ---- Enter shouldn't submit halfway through ---- */
		form.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Enter' ) { return; }
			var t = e.target;
			if ( t.tagName === 'TEXTAREA' || t.type === 'submit' || t.tagName === 'BUTTON' ) { return; }
			e.preventDefault();
			if ( current < steps.length - 1 ) {
				if ( validateStep( steps[ current ] ) ) { show( current + 1, true ); }
			}
		} );

		form.addEventListener( 'submit', function ( e ) {
			// Validate everything, not just the last screen.
			for ( var i = 0; i < steps.length; i++ ) {
				if ( ! validateStep( steps[ i ] ) ) {
					e.preventDefault();
					show( i, true );
					return;
				}
			}
			var btn = form.querySelector( '.pupbf-submit' );
			if ( btn ) {
				btn.disabled = true;
				btn.textContent = 'Sending…';
			}
		} );

		conditionals( form );
		dayToggles( form );
		signature( form );
		estimate( form );

		show( current, false );
	}

	/* ---------------------------------------------------------------- *
	 * Validation
	 * ---------------------------------------------------------------- */
	function fieldWrap( el ) { return el.closest( '.pupbf-field' ); }

	function setError( wrap, message ) {
		if ( ! wrap ) { return; }
		var existing = wrap.querySelector( '.pupbf-error' );
		if ( message ) {
			wrap.classList.add( 'pupbf-has-error' );
			if ( ! existing ) {
				existing = document.createElement( 'p' );
				existing.className = 'pupbf-error';
				existing.setAttribute( 'role', 'alert' );
				wrap.appendChild( existing );
			}
			existing.textContent = message;
		} else {
			wrap.classList.remove( 'pupbf-has-error' );
			if ( existing ) { existing.remove(); }
		}
	}

	function validateStep( step ) {
		var ok = true;
		var firstBad = null;

		// text / textarea / number
		Array.prototype.forEach.call( step.querySelectorAll( '[data-required="1"]' ), function ( el ) {
			var wrap = fieldWrap( el );
			if ( ! wrap || wrap.hasAttribute( 'hidden' ) ) { return; }

			var bad = false;
			var msg = 'This one\'s needed before I can take the booking.';

			if ( el.type === 'checkbox' ) {
				bad = ! el.checked;
				msg = 'Please tick this to carry on.';
			} else if ( el.tagName === 'FIELDSET' ) {
				bad = ! el.querySelector( 'input:checked' );
				msg = 'Please pick one.';
			} else {
				bad = el.value.trim() === '';
				if ( ! bad && el.type === 'email' && ! /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( el.value.trim() ) ) {
					bad = true;
					msg = 'That email address doesn\'t look quite right.';
				}
				if ( ! bad && el.type === 'tel' && el.value.replace( /[^0-9]/g, '' ).length < 5 ) {
					bad = true;
					msg = 'Please give a phone number I can reach you on.';
				}
			}

			setError( wrap, bad ? msg : '' );
			if ( bad ) {
				ok = false;
				if ( ! firstBad ) { firstBad = wrap; }
			}
		} );

		// "regular walks" means at least one day
		var daysWrap = step.querySelector( '.pupbf-type-days' );
		if ( daysWrap && ! daysWrap.hasAttribute( 'hidden' ) ) {
			var anyDay = daysWrap.querySelector( '.pupbf-day-check input:checked' );
			setError( daysWrap, anyDay ? '' : 'Please tick at least one day, or choose "Ad hoc / as needed".' );
			if ( ! anyDay ) {
				ok = false;
				if ( ! firstBad ) { firstBad = daysWrap; }
			}
		}

		// conditional textarea that became required by its answer
		var recWrap = step.querySelector( '[data-field="recording_details"]' );
		if ( recWrap && ! recWrap.hasAttribute( 'hidden' ) ) {
			var ta = recWrap.querySelector( 'textarea' );
			var empty = ta && ta.value.trim() === '';
			setError( recWrap, empty ? 'Just a quick note on where they are, please.' : '' );
			if ( empty ) {
				ok = false;
				if ( ! firstBad ) { firstBad = recWrap; }
			}
		}

		// signature
		var sigWrap = step.querySelector( '[data-field="signature"]' );
		if ( sigWrap ) {
			var data = sigWrap.parentNode.querySelector( '.pupbf-sig-data' ) || sigWrap.querySelector( '.pupbf-sig-data' );
			var signed = data && data.value !== '';
			setError( sigWrap, signed ? '' : 'Please add your signature — a finger on the box is all it takes.' );
			if ( ! signed ) {
				ok = false;
				if ( ! firstBad ) { firstBad = sigWrap; }
			}
		}

		if ( firstBad ) {
			firstBad.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			var focusable = firstBad.querySelector( 'input:not([type=hidden]), textarea' );
			if ( focusable && focusable.type !== 'checkbox' && focusable.type !== 'radio' ) {
				try { focusable.focus( { preventScroll: true } ); } catch ( err ) { /* older browsers */ }
			}
		}

		return ok;
	}

	/* ---------------------------------------------------------------- *
	 * Show/hide dependent questions
	 * ---------------------------------------------------------------- */
	function conditionals( form ) {
		var wraps = form.querySelectorAll( '.pupbf-conditional' );
		if ( ! wraps.length ) { return; }

		function apply() {
			Array.prototype.forEach.call( wraps, function ( wrap ) {
				var key  = wrap.getAttribute( 'data-showif-field' );
				var want = wrap.getAttribute( 'data-showif-value' );
				var checked = form.querySelector( 'input[name="pupbf[' + key + ']"]:checked' );
				var match = checked && checked.value === want;
				if ( match ) {
					wrap.removeAttribute( 'hidden' );
				} else {
					wrap.setAttribute( 'hidden', 'hidden' );
					setError( wrap, '' );
				}
			} );
		}

		form.addEventListener( 'change', apply );
		apply();
	}

	/* ---------------------------------------------------------------- *
	 * Day picker — the time box wakes up when the day is ticked
	 * ---------------------------------------------------------------- */
	function dayToggles( form ) {
		Array.prototype.forEach.call( form.querySelectorAll( '.pupbf-day' ), function ( row ) {
			var box  = row.querySelector( 'input[type="checkbox"]' );
			var time = row.querySelector( '.pupbf-day-time' );
			if ( ! box ) { return; }
			function sync() {
				row.classList.toggle( 'is-on', box.checked );
				if ( ! box.checked && time ) { time.value = ''; }
			}
			box.addEventListener( 'change', sync );
			sync();
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Signature pad
	 * ---------------------------------------------------------------- */
	var pads = [];

	function signature( form ) {
		var wrap = form.querySelector( '.pupbf-sig' );
		var canvas = form.querySelector( '.pupbf-sig-pad' );
		var data = form.querySelector( '.pupbf-sig-data' );
		var clear = form.querySelector( '.pupbf-sig-clear' );
		if ( ! canvas || ! data ) { return; }

		var ctx = canvas.getContext( '2d' );
		var drawing = false;
		var dirty = false;
		var last = null;

		function size() {
			var rect = canvas.getBoundingClientRect();
			if ( ! rect.width ) { return; } // hidden step — try again when shown
			var dpr = Math.min( window.devicePixelRatio || 1, 2 );
			var w = Math.round( rect.width );
			var h = Math.round( rect.height );
			var changed = ( canvas.width !== w * dpr || canvas.height !== h * dpr );

			if ( changed ) {
				// Redrawing would need the stroke history; the honest thing is
				// to clear and ask again, which only happens on rotate/resize.
				canvas.width = w * dpr;
				canvas.height = h * dpr;
			}

			// Set every time: resizing a canvas resets its drawing state.
			ctx.setTransform( dpr, 0, 0, dpr, 0, 0 );
			ctx.lineWidth = 2.4;
			ctx.lineCap = 'round';
			ctx.lineJoin = 'round';
			ctx.strokeStyle = '#2C2724';

			if ( changed && dirty ) { reset(); }
		}

		function reset() {
			ctx.clearRect( 0, 0, canvas.width, canvas.height );
			dirty = false;
			data.value = '';
			if ( wrap ) { wrap.classList.remove( 'is-signed' ); }
		}

		function point( e ) {
			var rect = canvas.getBoundingClientRect();
			var t = e.touches && e.touches[ 0 ] ? e.touches[ 0 ] : e;
			return { x: t.clientX - rect.left, y: t.clientY - rect.top };
		}

		function start( e ) {
			e.preventDefault();
			drawing = true;
			// Keep receiving moves even if a finger strays outside the box —
			// without this the stroke stops at the edge and resumes with a
			// straight line back, which looks like a glitch mid-signature.
			if ( canvas.setPointerCapture && e.pointerId !== undefined ) {
				try { canvas.setPointerCapture( e.pointerId ); } catch ( err ) { /* not fatal */ }
			}
			last = point( e );
			// A tap with no movement should still leave a mark.
			ctx.beginPath();
			ctx.arc( last.x, last.y, 1.2, 0, Math.PI * 2 );
			ctx.fillStyle = '#2C2724';
			ctx.fill();
			mark();
		}

		function move( e ) {
			if ( ! drawing ) { return; }
			e.preventDefault();
			var p = point( e );
			ctx.beginPath();
			ctx.moveTo( last.x, last.y );
			ctx.lineTo( p.x, p.y );
			ctx.stroke();
			last = p;
			mark();
		}

		function end( e ) {
			if ( ! drawing ) { return; }
			drawing = false;
			if ( e && canvas.releasePointerCapture && e.pointerId !== undefined ) {
				try { canvas.releasePointerCapture( e.pointerId ); } catch ( err ) { /* not fatal */ }
			}
			commit();
		}

		function mark() {
			if ( dirty ) { return; }
			dirty = true;
			if ( wrap ) { wrap.classList.add( 'is-signed' ); }
			setError( canvas.closest( '.pupbf-field' ), '' );
		}

		function commit() {
			if ( ! dirty ) { data.value = ''; return; }
			try {
				data.value = canvas.toDataURL( 'image/png' );
			} catch ( err ) {
				data.value = '';
			}
		}

		canvas.addEventListener( 'pointerdown', start );
		canvas.addEventListener( 'pointermove', move );
		window.addEventListener( 'pointerup', end );
		canvas.addEventListener( 'pointercancel', end );

		// Older iOS Safari, before pointer events.
		if ( ! window.PointerEvent ) {
			canvas.addEventListener( 'touchstart', start, { passive: false } );
			canvas.addEventListener( 'touchmove', move, { passive: false } );
			window.addEventListener( 'touchend', end );
			canvas.addEventListener( 'mousedown', start );
			canvas.addEventListener( 'mousemove', move );
			window.addEventListener( 'mouseup', end );
		}

		if ( clear ) {
			clear.addEventListener( 'click', function () { reset(); } );
		}

		window.addEventListener( 'resize', size );
		window.addEventListener( 'orientationchange', size );

		pads.push( size );
		size();
	}

	function resizeSignature() {
		pads.forEach( function ( fn ) { fn(); } );
	}

	/* ---------------------------------------------------------------- *
	 * Live price estimate
	 * ---------------------------------------------------------------- */
	function money( n ) {
		return '£' + ( Math.round( n * 100 ) / 100 ).toFixed( 2 ).replace( /\.00$/, '' );
	}

	function estimate( form ) {
		var box = form.querySelector( '.pupbf-estimate' );
		if ( ! box || typeof PUPBF === 'undefined' || ! PUPBF.prices ) { return; }
		var line = box.querySelector( '.pupbf-estimate-line' );
		var prices = PUPBF.prices;

		function update() {
			var walk = form.querySelector( 'input[name="pupbf[walk_type]"]:checked' );
			if ( ! walk ) { box.hidden = true; return; }

			var len   = walk.value === '60' ? '60' : '30';
			var base  = parseFloat( prices[ 'weekday_' + len ] );
			var extra = parseFloat( prices[ 'extra_dog_' + len ] );
			var dogsEl = form.querySelector( 'input[name="pupbf[dog_count]"]' );
			var dogs  = Math.max( 1, parseInt( dogsEl && dogsEl.value, 10 ) || 1 );
			var perWalk = base + ( dogs - 1 ) * extra;

			var type = form.querySelector( 'input[name="pupbf[schedule_type]"]:checked' );
			var days = form.querySelectorAll( '.pupbf-day-check input:checked' ).length;

			var label = len === '60' ? '1-hour walk' : '30-minute walk';
			var dogText = dogs > 1 ? ' for ' + dogs + ' dogs' : '';

			if ( type && type.value === 'regular' && days > 0 ) {
				line.textContent = money( perWalk ) + ' per walk' + dogText +
					' · about ' + money( perWalk * days ) + ' a week (' + days + ' walk' + ( days === 1 ? '' : 's' ) + ')';
			} else {
				line.textContent = money( perWalk ) + ' per ' + label + dogText;
			}
			box.hidden = false;
		}

		form.addEventListener( 'change', update );
		form.addEventListener( 'input', update );
		update();
	}
} )();
