<?php
/**
 * Plugin Name: Recipient Checkout Fields
 * Plugin URI:  https://github.com/shakeelnasafian/gift-recipient-checkout
 * Description: Adds "This order is for someone else" functionality to WooCommerce checkout. Includes admin settings under WooCommerce → Settings → Recipient Fields.
 * Version:     2.0.0
 * Author:      Shakeel Nasafian
 * License:     GPL-2.0+
 * Text Domain: recipient-checkout-fields
 * Requires at least: 6.0
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

// Plugin-wide constants.
define( 'RCF_VERSION',    '2.0.0' );
define( 'RCF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RCF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Bootstrap both classes once WooCommerce — and its WC_Settings_Page base
 * class — is fully loaded. Using `woocommerce_loaded` guarantees all WC
 * classes and hooks are available before we register anything.
 */
add_action( 'woocommerce_loaded', function () {

    require_once RCF_PLUGIN_DIR . 'includes/class-recipient-settings.php';
    require_once RCF_PLUGIN_DIR . 'includes/class-recipient-fields.php';

    // Register the settings tab under WooCommerce → Settings.
    // `woocommerce_get_settings_pages` fires during WC settings init and
    // expects an array of WC_Settings_Page instances to be returned.
    add_filter( 'woocommerce_get_settings_pages', function ( array $pages ): array {
        $pages[] = new Recipient_Settings();
        return $pages;
    } );

    // Boot the checkout / order hooks.
    Recipient_Fields::init();
} );
