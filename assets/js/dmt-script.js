/**
 * Simple Dark Mode Toggle - button behaviour.
 * The initial mode is applied by a tiny inline script in <head>; this file only
 * handles clicks, accessibility labels, persistence and cross-tab sync.
 */
( function () {
	'use strict';

	var KEY = 'dmt_mode';
	var root = document.documentElement;
	var button = document.getElementById( 'dmt-toggle' );

	if ( ! button ) {
		return;
	}

	function current() {
		return 'dark' === root.getAttribute( 'data-dmt' ) ? 'dark' : 'light';
	}

	function paint( mode ) {
		root.setAttribute( 'data-dmt', mode );
		button.setAttribute( 'aria-pressed', 'dark' === mode ? 'true' : 'false' );
		button.setAttribute(
			'aria-label',
			'dark' === mode ? button.getAttribute( 'data-label-light' ) : button.getAttribute( 'data-label-dark' )
		);
	}

	function save( mode ) {
		try {
			window.localStorage.setItem( KEY, mode );
		} catch ( e ) {
			// Storage blocked: the choice simply lasts for this page view.
		}
	}

	function hasSavedChoice() {
		try {
			var saved = window.localStorage.getItem( KEY );
			return 'dark' === saved || 'light' === saved;
		} catch ( e ) {
			return false;
		}
	}

	paint( current() );

	// Enable the transition only after the first paint, so nothing animates on load.
	window.requestAnimationFrame( function () {
		window.requestAnimationFrame( function () {
			root.classList.add( 'dmt-ready' );
		} );
	} );

	button.addEventListener( 'click', function () {
		var next = 'dark' === current() ? 'light' : 'dark';
		paint( next );
		save( next );
	} );

	// Keep other open tabs of the same site in sync.
	window.addEventListener( 'storage', function ( event ) {
		if ( event.key === KEY && ( 'dark' === event.newValue || 'light' === event.newValue ) ) {
			paint( event.newValue );
		}
	} );

	// "Follow device setting": react to OS changes until the visitor chooses.
	if ( 'auto' === button.getAttribute( 'data-default' ) && window.matchMedia ) {
		var query = window.matchMedia( '(prefers-color-scheme: dark)' );
		var onChange = function ( event ) {
			if ( ! hasSavedChoice() ) {
				paint( event.matches ? 'dark' : 'light' );
			}
		};

		if ( query.addEventListener ) {
			query.addEventListener( 'change', onChange );
		} else if ( query.addListener ) {
			query.addListener( onChange );
		}
	}
}() );
