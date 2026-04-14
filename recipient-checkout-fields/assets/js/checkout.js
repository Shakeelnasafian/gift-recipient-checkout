/**
 * checkout.js — Recipient Checkout Fields
 *
 * Handles the show/hide toggle for the recipient name/email fields.
 * Relies on CSS transitions defined in checkout.css for smooth animation.
 *
 * No jQuery slide — uses CSS class toggling for better performance and
 * respects the user's prefers-reduced-motion preference.
 */
( function () {
    'use strict';

    var checkbox = document.getElementById( 'recipient_is_gift' );
    var fieldsWrap = document.getElementById( 'rcf-recipient-fields' );

    if ( ! checkbox || ! fieldsWrap ) {
        return; // Fields not present on this page load — nothing to do.
    }

    var inputs = fieldsWrap.querySelectorAll( 'input' );

    /**
     * Sync the visible / accessible state of the recipient fields to the
     * current checked value of the toggle checkbox.
     *
     * @param {boolean} animate - If false, skip CSS transitions on first paint.
     */
    function syncFields( animate ) {
        var checked = checkbox.checked;

        if ( ! animate ) {
            // Disable transitions for the initial paint so there is no
            // flash of animation on page load.
            fieldsWrap.style.transition = 'none';
        }

        if ( checked ) {
            fieldsWrap.classList.add( 'rcf-visible' );

            // Require the hidden inputs only when they are actually visible,
            // so the browser's native validation does not fire on hidden fields.
            inputs.forEach( function ( input ) {
                input.setAttribute( 'required', 'required' );
            } );
        } else {
            fieldsWrap.classList.remove( 'rcf-visible' );

            inputs.forEach( function ( input ) {
                input.removeAttribute( 'required' );
            } );
        }

        // Update ARIA so screen readers announce the expanded/collapsed state.
        fieldsWrap.setAttribute( 'aria-hidden', checked ? 'false' : 'true' );

        if ( ! animate ) {
            // Re-enable transitions after a single paint cycle so subsequent
            // toggles are animated.
            // eslint-disable-next-line no-unused-expressions
            fieldsWrap.offsetHeight; // force reflow
            fieldsWrap.style.transition = '';
        }
    }

    // Set initial state without animation.
    syncFields( false );

    // Animate on every subsequent toggle.
    checkbox.addEventListener( 'change', function () {
        syncFields( true );
    } );

} )();
