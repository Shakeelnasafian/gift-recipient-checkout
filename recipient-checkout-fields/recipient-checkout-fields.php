<?php
/**
 * Plugin Name: Recipient Checkout Fields
 * Plugin URI:  https://github.com/shakeelnasafian/gift-recipient-checkout
 * Description: Adds "This order is for someone else" functionality to WooCommerce checkout, with recipient name/email fields, order meta storage, and webhook payload injection.
 * Version:     1.0.0
 * Author:      Shakeel Nasafian
 * License:     GPL-2.0+
 * Text Domain: recipient-checkout-fields
 * Requires at least: 6.0
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants for use throughout the plugin.
define( 'RCF_VERSION', '1.0.0' );
define( 'RCF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RCF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Boot the plugin only after WooCommerce has loaded.
 * Using `woocommerce_loaded` ensures all WC classes and hooks are available.
 */
add_action( 'woocommerce_loaded', function () {
    require_once RCF_PLUGIN_DIR . 'includes/class-recipient-fields.php';
    Recipient_Fields::init();
} );
