/**
 * checkout.js — Recipient Checkout Fields
 *
 * Toggles the recipient name/email fields when the
 * "This order is for someone else" checkbox changes.
 *
 * Depends on: jQuery (loaded by WooCommerce on the checkout page).
 */
( function ( $ ) {
    'use strict';

    var $checkbox = $( '#recipient_is_gift' );
    var $fields   = $( '#rcf-recipient-fields' );

    /**
     * Show or hide the recipient fields with a smooth slide animation.
     * Also toggle the HTML `required` attribute so browser-native validation
     * only fires when the fields are actually visible.
     *
     * @param {boolean} animate - Whether to use slideDown/Up (false on page load).
     */
    function toggleFields( animate ) {
        if ( $checkbox.is( ':checked' ) ) {
            if ( animate ) {
                $fields.slideDown( 300 );
            } else {
                $fields.show();
            }
            // Mark inputs as required so WooCommerce's inline validation notices
            // are consistent with server-side validation.
            $fields.find( 'input' ).prop( 'required', true );
        } else {
            if ( animate ) {
                $fields.slideUp( 300 );
            } else {
                $fields.hide();
            }
            // Remove required so the browser doesn't block submit when hidden.
            $fields.find( 'input' ).prop( 'required', false );
        }
    }

    // On page load: set initial state without animation.
    toggleFields( false );

    // On checkbox change: animate the transition.
    $checkbox.on( 'change', function () {
        toggleFields( true );
    } );

} )( jQuery );
