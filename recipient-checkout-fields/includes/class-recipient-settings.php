<?php
/**
 * Class Recipient_Settings
 *
 * Adds a "Recipient Fields" tab to WooCommerce → Settings, giving store
 * admins control over the checkout feature without touching code.
 *
 * Settings are stored as individual wp_options entries and read back
 * via WooCommerce's wc_get_option() wrapper (which applies defaults).
 *
 * Extends WC_Settings_Page — the standard WooCommerce pattern for adding
 * first-class settings tabs to the WC admin settings screen.
 */

defined( 'ABSPATH' ) || exit;

class Recipient_Settings extends WC_Settings_Page {

    public function __construct() {
        $this->id    = 'recipient_fields';
        $this->label = __( 'Recipient Fields', 'recipient-checkout-fields' );

        // WC_Settings_Page::__construct() registers the tab and its save handler.
        parent::__construct();
    }

    /**
     * Return the settings array for this tab.
     *
     * WooCommerce uses this array to render the settings form, handle saves,
     * and provide default values via wc_get_option().
     *
     * @param string $current_section Unused (no sub-sections in this tab).
     * @return array
     */
    public function get_settings( $current_section = '' ): array {
        return apply_filters(
            'woocommerce_get_settings_' . $this->id,
            [
                // ── Section: General ──────────────────────────────────────
                [
                    'title' => __( 'General', 'recipient-checkout-fields' ),
                    'desc'  => __( 'Controls whether the "This order is for someone else" feature is shown on the checkout page.', 'recipient-checkout-fields' ),
                    'type'  => 'title',
                    'id'    => 'rcf_general_section',
                ],
                [
                    'title'   => __( 'Enable Feature', 'recipient-checkout-fields' ),
                    'desc'    => __( 'Show the recipient fields toggle on the checkout page', 'recipient-checkout-fields' ),
                    'type'    => 'checkbox',
                    'id'      => 'rcf_enabled',
                    'default' => 'yes',
                ],
                [
                    'type' => 'sectionend',
                    'id'   => 'rcf_general_section',
                ],

                // ── Section: Labels ───────────────────────────────────────
                [
                    'title' => __( 'Field Labels', 'recipient-checkout-fields' ),
                    'desc'  => __( 'Customise the text shown to customers on the checkout page.', 'recipient-checkout-fields' ),
                    'type'  => 'title',
                    'id'    => 'rcf_labels_section',
                ],
                [
                    'title'    => __( 'Toggle Label', 'recipient-checkout-fields' ),
                    'desc'     => __( 'Label shown next to the toggle switch.', 'recipient-checkout-fields' ),
                    'type'     => 'text',
                    'id'       => 'rcf_checkbox_label',
                    'default'  => __( 'This order is for someone else', 'recipient-checkout-fields' ),
                    'css'      => 'min-width:350px;',
                    'desc_tip' => true,
                ],
                [
                    'title'    => __( 'Recipient Name Label', 'recipient-checkout-fields' ),
                    'desc'     => __( 'Label for the recipient\'s full name field.', 'recipient-checkout-fields' ),
                    'type'     => 'text',
                    'id'       => 'rcf_name_label',
                    'default'  => __( 'Recipient Full Name', 'recipient-checkout-fields' ),
                    'css'      => 'min-width:350px;',
                    'desc_tip' => true,
                ],
                [
                    'title'    => __( 'Recipient Email Label', 'recipient-checkout-fields' ),
                    'desc'     => __( 'Label for the recipient\'s email address field.', 'recipient-checkout-fields' ),
                    'type'     => 'text',
                    'id'       => 'rcf_email_label',
                    'default'  => __( 'Recipient Email Address', 'recipient-checkout-fields' ),
                    'css'      => 'min-width:350px;',
                    'desc_tip' => true,
                ],
                [
                    'type' => 'sectionend',
                    'id'   => 'rcf_labels_section',
                ],

                // ── Section: Validation ───────────────────────────────────
                [
                    'title' => __( 'Validation', 'recipient-checkout-fields' ),
                    'type'  => 'title',
                    'id'    => 'rcf_validation_section',
                ],
                [
                    'title'   => __( 'Email Optional', 'recipient-checkout-fields' ),
                    'desc'    => __( 'Allow customers to leave the recipient email address blank', 'recipient-checkout-fields' ),
                    'type'    => 'checkbox',
                    'id'      => 'rcf_email_optional',
                    'default' => 'no',
                ],
                [
                    'type' => 'sectionend',
                    'id'   => 'rcf_validation_section',
                ],
            ],
            $current_section
        );
    }
}
