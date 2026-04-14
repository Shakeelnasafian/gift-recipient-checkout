<?php
/**
 * Class Recipient_Fields
 *
 * Encapsulates all logic for the "This order is for someone else" feature:
 *   - Renders checkout fields (toggle switch + animated reveal)
 *   - Validates on submission
 *   - Saves data to order meta
 *   - Injects data into WooCommerce webhook payloads
 *   - Displays recipient data in the WooCommerce admin order detail page
 *
 * Labels and feature flags are read from the settings managed by
 * Recipient_Settings (WooCommerce → Settings → Recipient Fields).
 */

defined( 'ABSPATH' ) || exit;

class Recipient_Fields {

    /**
     * Register all WordPress/WooCommerce hooks.
     * Called once from the main plugin file after WooCommerce has loaded.
     */
    public static function init(): void {
        $instance = new self();

        // Enqueue frontend assets (CSS + JS) on the checkout page only.
        add_action( 'wp_enqueue_scripts', [ $instance, 'enqueue_scripts' ] );

        // Render the toggle + recipient fields after the order-notes textarea.
        add_action( 'woocommerce_after_order_notes', [ $instance, 'render_fields' ] );

        // Validate the recipient fields when the customer submits the checkout form.
        add_action( 'woocommerce_checkout_process', [ $instance, 'validate_fields' ] );

        // Persist the recipient data as order meta once the order is created.
        add_action( 'woocommerce_checkout_update_order_meta', [ $instance, 'save_order_meta' ] );

        // Inject recipient data into every outgoing WooCommerce webhook payload.
        add_filter( 'woocommerce_webhook_payload', [ $instance, 'inject_webhook_payload' ], 10, 4 );

        // Display recipient details in the WooCommerce admin order detail view.
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $instance, 'render_admin_order_fields' ] );
    }

    // -------------------------------------------------------------------------
    // Settings helpers
    // -------------------------------------------------------------------------

    /**
     * Read a plugin option with a fallback default.
     * Uses wc_get_option() so WooCommerce applies the registered default value
     * before the option has ever been saved.
     *
     * @param string $key     Option key without the `rcf_` prefix.
     * @param string $default Fallback if the option is empty.
     * @return string
     */
    private function setting( string $key, string $default = '' ): string {
        return (string) get_option( 'rcf_' . $key, $default );
    }

    /**
     * Whether the feature is enabled in settings (default: yes).
     */
    private function is_enabled(): bool {
        return 'yes' === $this->setting( 'enabled', 'yes' );
    }

    // -------------------------------------------------------------------------
    // Asset enqueue
    // -------------------------------------------------------------------------

    /**
     * Enqueue checkout.css and checkout.js on the checkout page.
     * Skipped entirely when the feature is disabled in settings.
     */
    public function enqueue_scripts(): void {
        if ( ! is_checkout() || ! $this->is_enabled() ) {
            return;
        }

        wp_enqueue_style(
            'rcf-checkout',
            RCF_PLUGIN_URL . 'assets/css/checkout.css',
            [],
            RCF_VERSION
        );

        // No jQuery dependency — checkout.js uses vanilla JS.
        wp_enqueue_script(
            'rcf-checkout',
            RCF_PLUGIN_URL . 'assets/js/checkout.js',
            [],
            RCF_VERSION,
            true // Load in footer so the DOM elements already exist.
        );
    }

    // -------------------------------------------------------------------------
    // Checkout field rendering
    // -------------------------------------------------------------------------

    /**
     * Output the toggle switch and the recipient name/email fields
     * directly after the Order Notes textarea.
     *
     * Markup is intentionally minimal so active WooCommerce themes can
     * style it; core field styling is handled by checkout.css.
     * woocommerce_form_field() is used for the inputs so they inherit the
     * theme's checkout field styles automatically.
     *
     * @param WC_Checkout $checkout The WooCommerce checkout object.
     */
    public function render_fields( WC_Checkout $checkout ): void {
        if ( ! $this->is_enabled() ) {
            return;
        }

        // Resolve dynamic labels from settings (fall back to defaults).
        $checkbox_label = $this->setting( 'checkbox_label', __( 'This order is for someone else', 'recipient-checkout-fields' ) );
        $name_label     = $this->setting( 'name_label',     __( 'Recipient Full Name',             'recipient-checkout-fields' ) );
        $email_label    = $this->setting( 'email_label',    __( 'Recipient Email Address',         'recipient-checkout-fields' ) );

        // Restore previously posted values so the form re-populates correctly
        // after a failed validation attempt.
        // Nonce verification is handled upstream by WooCommerce's checkout handler.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $is_gift     = ! empty( $_POST['recipient_is_gift'] );
        $saved_name  = isset( $_POST['recipient_name'] )  ? sanitize_text_field( wp_unslash( $_POST['recipient_name'] ) )  : '';
        $saved_email = isset( $_POST['recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email'] ) )       : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        ?>

        <div class="rcf-gift-section">

            <!-- ── Toggle switch row ─────────────────────────────────── -->
            <div class="rcf-toggle-row">
                <label class="rcf-toggle-label" for="recipient_is_gift">

                    <!--
                        Hidden real checkbox. The visible toggle track below is purely
                        decorative; clicking the <label> activates this real input,
                        keeping full keyboard and screen-reader accessibility.
                    -->
                    <input
                        type="checkbox"
                        id="recipient_is_gift"
                        name="recipient_is_gift"
                        value="yes"
                        class="rcf-toggle-input"
                        <?php checked( $is_gift ); ?>
                        aria-controls="rcf-recipient-fields"
                        aria-expanded="<?php echo $is_gift ? 'true' : 'false'; ?>"
                    />

                    <!-- Visual track + thumb (CSS-driven) -->
                    <span class="rcf-toggle-track" aria-hidden="true"></span>

                    <!-- Gift icon -->
                    <span class="rcf-toggle-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 12 20 22 4 22 4 12"></polyline>
                            <rect x="2" y="7" width="20" height="5"></rect>
                            <line x1="12" y1="22" x2="12" y2="7"></line>
                            <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path>
                            <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>
                        </svg>
                    </span>

                    <!-- Configurable label text -->
                    <span class="rcf-toggle-text"><?php echo esc_html( $checkbox_label ); ?></span>

                </label>
            </div><!-- .rcf-toggle-row -->

            <!--
                Animated reveal wrapper.
                checkout.js toggles the `rcf-visible` class;
                checkout.css handles the max-height / opacity transition.
                aria-hidden is kept in sync by checkout.js.
            -->
            <div
                id="rcf-recipient-fields"
                class="rcf-fields-wrap<?php echo $is_gift ? ' rcf-visible' : ''; ?>"
                aria-hidden="<?php echo $is_gift ? 'false' : 'true'; ?>"
            >
                <?php
                // woocommerce_form_field() outputs a fully theme-styled field
                // row, including label, input, and any validation classes.

                woocommerce_form_field(
                    'recipient_name',
                    [
                        'type'        => 'text',
                        'label'       => $name_label,
                        'placeholder' => __( "e.g. Jane Smith", 'recipient-checkout-fields' ),
                        'required'    => false, // Enforced conditionally in validate_fields().
                        'class'       => [ 'form-row-wide' ],
                        'clear'       => true,
                    ],
                    $saved_name
                );

                woocommerce_form_field(
                    'recipient_email',
                    [
                        'type'        => 'email',
                        'label'       => $email_label,
                        'placeholder' => __( "e.g. jane@example.com", 'recipient-checkout-fields' ),
                        'required'    => false, // Enforced conditionally in validate_fields().
                        'class'       => [ 'form-row-wide' ],
                        'clear'       => true,
                    ],
                    $saved_email
                );
                ?>
            </div><!-- #rcf-recipient-fields -->

        </div><!-- .rcf-gift-section -->
        <?php
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Validate recipient fields when the checkout form is submitted.
     *
     * Called by WooCommerce before the order is created. Adding a notice
     * with type 'error' prevents the order from being placed.
     *
     * Nonce verification is handled upstream by WooCommerce's checkout handler.
     */
    public function validate_fields(): void {
        if ( ! $this->is_enabled() ) {
            return;
        }

        // Only validate when the gift toggle is on.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if ( empty( $_POST['recipient_is_gift'] ) ) {
            return;
        }

        $name  = isset( $_POST['recipient_name'] )  ? sanitize_text_field( wp_unslash( $_POST['recipient_name'] ) )  : '';
        $email = isset( $_POST['recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email'] ) )       : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( '' === $name ) {
            wc_add_notice(
                __( 'Please enter the recipient\'s full name.', 'recipient-checkout-fields' ),
                'error'
            );
        }

        $email_optional = 'yes' === $this->setting( 'email_optional', 'no' );

        if ( ! $email_optional ) {
            if ( '' === $email ) {
                wc_add_notice(
                    __( 'Please enter the recipient\'s email address.', 'recipient-checkout-fields' ),
                    'error'
                );
            } elseif ( ! is_email( $email ) ) {
                wc_add_notice(
                    __( 'The recipient email address is not valid.', 'recipient-checkout-fields' ),
                    'error'
                );
            }
        } elseif ( '' !== $email && ! is_email( $email ) ) {
            // Email is optional but was filled in with an invalid value.
            wc_add_notice(
                __( 'The recipient email address is not valid.', 'recipient-checkout-fields' ),
                'error'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Order meta persistence
    // -------------------------------------------------------------------------

    /**
     * Save recipient fields to order meta after the order record is created.
     *
     * Nonce verification is handled upstream by WooCommerce's checkout handler.
     *
     * @param int $order_id The newly created order ID.
     */
    public function save_order_meta( int $order_id ): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $is_gift = ! empty( $_POST['recipient_is_gift'] ) ? 'yes' : 'no';
        $name    = isset( $_POST['recipient_name'] )  ? sanitize_text_field( wp_unslash( $_POST['recipient_name'] ) )  : '';
        $email   = isset( $_POST['recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email'] ) )       : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        update_post_meta( $order_id, '_recipient_is_gift', $is_gift );

        if ( 'yes' === $is_gift ) {
            update_post_meta( $order_id, '_recipient_name',  $name );
            update_post_meta( $order_id, '_recipient_email', $email );
        } else {
            // Clear stale values if the customer unchecked after a previous attempt.
            delete_post_meta( $order_id, '_recipient_name' );
            delete_post_meta( $order_id, '_recipient_email' );
        }
    }

    // -------------------------------------------------------------------------
    // Webhook payload
    // -------------------------------------------------------------------------

    /**
     * Append recipient data to every outgoing WooCommerce webhook payload.
     *
     * Fired for all webhook topics; we guard against non-order topics that
     * would not have meaningful order meta.
     *
     * @param array  $payload     The current webhook payload array.
     * @param string $resource    The webhook resource type (e.g. 'order').
     * @param int    $resource_id The resource object ID.
     * @param int    $webhook_id  The webhook definition ID.
     *
     * @return array Modified payload with recipient fields appended.
     */
    public function inject_webhook_payload( array $payload, string $resource, int $resource_id, int $webhook_id ): array {
        // Only enrich order-related webhooks.
        if ( 'order' !== $resource ) {
            return $payload;
        }

        $is_gift = get_post_meta( $resource_id, '_recipient_is_gift', true );

        $payload['recipient_is_gift'] = ( 'yes' === $is_gift );
        $payload['recipient_name']    = (string) get_post_meta( $resource_id, '_recipient_name',  true );
        $payload['recipient_email']   = (string) get_post_meta( $resource_id, '_recipient_email', true );

        return $payload;
    }

    // -------------------------------------------------------------------------
    // Admin order view
    // -------------------------------------------------------------------------

    /**
     * Display a "Recipient Details" section inside the WooCommerce admin
     * order detail page, below the billing address block.
     *
     * Uses the `.address` wrapper class so the section inherits WooCommerce's
     * native admin styling for address blocks, keeping it visually consistent.
     *
     * @param WC_Order $order The order being viewed.
     */
    public function render_admin_order_fields( WC_Order $order ): void {
        $order_id = $order->get_id();
        $is_gift  = get_post_meta( $order_id, '_recipient_is_gift', true );

        // Only render the section when the order was flagged as a gift.
        if ( 'yes' !== $is_gift ) {
            return;
        }

        $name  = (string) get_post_meta( $order_id, '_recipient_name',  true );
        $email = (string) get_post_meta( $order_id, '_recipient_email', true );
        ?>

        <div class="rcf-admin-recipient-details" style="margin-top:1.5em;">
            <h3 style="margin-bottom:0.5em;">
                <?php esc_html_e( 'Recipient Details', 'recipient-checkout-fields' ); ?>
            </h3>
            <div class="address">
                <p>
                    <strong><?php esc_html_e( 'Full Name:', 'recipient-checkout-fields' ); ?></strong>
                    <?php echo esc_html( $name ?: '—' ); ?>
                </p>
                <p>
                    <strong><?php esc_html_e( 'Email:', 'recipient-checkout-fields' ); ?></strong>
                    <?php if ( $email ) : ?>
                        <a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a>
                    <?php else : ?>
                        <?php esc_html_e( '—', 'recipient-checkout-fields' ); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php
    }
}
