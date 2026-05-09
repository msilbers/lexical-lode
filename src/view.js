/**
 * Frontend script for Lexical Lode blocks.
 * Handles hover/focus attribution popovers.
 */
( function () {
	const items = document.querySelectorAll( '[data-post-title]' );
	if ( ! items.length ) return;

	let popover = null;
	let hideTimer = null;

	function hidePopover() {
		hideTimer = setTimeout( () => {
			if ( popover ) {
				popover.remove();
				popover = null;
			}
		}, 120 );
	}

	function cancelHide() {
		if ( hideTimer ) {
			clearTimeout( hideTimer );
			hideTimer = null;
		}
	}

	function showPopover( el ) {
		cancelHide();
		if ( popover ) {
			popover.remove();
			popover = null;
		}

		const title = el.dataset.postTitle;
		const url = el.dataset.postUrl;
		if ( ! title ) return;

		popover = document.createElement( 'div' );
		popover.className = 'lexical-lode-popover';
		popover.setAttribute( 'role', 'tooltip' );
		popover.id = 'lexical-lode-tooltip';

		const link = document.createElement( 'a' );
		link.href = url || '#';
		link.textContent = title;
		if ( url ) {
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			const hint = document.createElement( 'span' );
			hint.className = 'screen-reader-text';
			hint.textContent = ' (opens in a new tab)';
			link.appendChild( hint );
		}
		popover.appendChild( link );

		popover.addEventListener( 'mouseenter', cancelHide );
		popover.addEventListener( 'mouseleave', hidePopover );

		document.body.appendChild( popover );

		const rect = el.getBoundingClientRect();
		popover.style.left = ( rect.left + window.scrollX ) + 'px';
		popover.style.top = ( rect.top + window.scrollY - popover.offsetHeight - 4 ) + 'px';

		el.setAttribute( 'aria-describedby', 'lexical-lode-tooltip' );
	}

	function removeDescribedBy( el ) {
		el.removeAttribute( 'aria-describedby' );
	}

	items.forEach( ( el ) => {
		if ( ! el.getAttribute( 'tabindex' ) ) {
			el.setAttribute( 'tabindex', '0' );
		}

		el.addEventListener( 'mouseenter', () => showPopover( el ) );
		el.addEventListener( 'mouseleave', () => {
			removeDescribedBy( el );
			hidePopover();
		} );

		el.addEventListener( 'focusin', () => showPopover( el ) );
		el.addEventListener( 'focusout', () => {
			removeDescribedBy( el );
			hidePopover();
		} );

		el.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' && popover ) {
				removeDescribedBy( el );
				popover.remove();
				popover = null;
			}
		} );
	} );

	document.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' && popover ) {
			popover.remove();
			popover = null;
		}
	} );
} )();
