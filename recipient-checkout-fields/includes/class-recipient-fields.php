<?php
/**
 * Class Recipient_Fields
 *
 * Encapsulates all logic for the "This order is for someone else" feature:
 *   - Renders checkout fields
 *   - Validates on submission
 *   - Saves data to order meta
 *   - Injects data into WooCommerce webhook payloads
 *   - Displays recipient data in the admin order screen
 */

defined( 'ABSPATH' ) || exit;

class Recipient_Fields {

    /**
     * Register all WordPress/WooCommerce hooks.
     * Called once from the main plugin file after WooCommerce has loaded.
     */
    public static function init(): void {
        $instance = new self();

        // Enqueue the checkout toggle script on the checkout page only.
        add_action( 'wp_enqueue_scripts', [ $instance, 'enqueue_scripts' ] );

        // Render the checkbox + recipient fields after the order-notes textarea.
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
    // Script enqueue
    // -------------------------------------------------------------------------

    /**
     * Enqueue checkout.js on the checkout page.
     * The script handles showing/hiding the recipient fields via a checkbox toggle.
     */
    public function enqueue_scripts(): void {
        if ( ! is_checkout() ) {
            return;
        }

        wp_enqueue_script(
            'rcf-checkout',
            RCF_PLUGIN_URL . 'assets/js/checkout.js',
            [ 'jquery' ],   // jQuery is already loaded by WooCommerce on checkout.
            RCF_VERSION,
            true            // Load in the footer so the DOM is ready.
        );
    }

    // -------------------------------------------------------------------------
    // Checkout field rendering
    // -------------------------------------------------------------------------

    /**
     * Output the "This order is for someone else" checkbox and the
     * recipient name/email fields directly after the Order Notes textarea.
     *
     * The recipient fields wrapper is hidden by default via inline style;
     * checkout.js toggles visibility when the checkbox changes.
     *
     * @param WC_Checkout $checkout The WooCommerce checkout object.
     */
    public function render_fields( WC_Checkout $checkout ): void {
        // Determine the current checked state so the form re-renders correctly
        // after a failed validation attempt (POST data is preserved).
        $is_gift     = ! empty( $_POST['recipient_is_gift'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $saved_name  = isset( $_POST['recipient_name'] )  ? sanitize_text_field( wp_unslash( $_POST['recipient_name'] ) )  : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $saved_email = isset( $_POST['recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email'] ) )       : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        ?>

        <div class="rcf-gift-section">

            <!-- Checkbox: "This order is for someone else" -->
            <p class="form-row form-row-wide">
                <label for="recipient_is_gift">
                    <input
                        type="checkbox"
                        id="recipient_is_gift"
                        name="recipient_is_gift"
                        value="yes"
                        <?php checked( $is_gift ); ?>
                    />
                    <?php esc_html_e( 'This order is for someone else', 'recipient-checkout-fields' ); ?>
                </label>
            </p>

            <!-- Recipient fields wrapper — hidden/shown by checkout.js -->
            <div
                id="rcf-recipient-fields"
                style="display:<?php echo $is_gift ? 'block' : 'none'; ?>; overflow:hidden;"
            >
                <?php
                // Use WooCommerce's built-in woocommerce_form_field() helper so that
                // the fields inherit the active theme's checkout styling automatically.

                woocommerce_form_field(
                    'recipient_name',
                    [
                        'type'        => 'text',
                        'label'       => __( 'Recipient Full Name', 'recipient-checkout-fields' ),
                        'placeholder' => __( 'Enter the recipient\'s full name', 'recipient-checkout-fields' ),
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
                        'label'       => __( 'Recipient Email Address', 'recipient-checkout-fields' ),
                        'placeholder' => __( 'Enter the recipient\'s email address', 'recipient-checkout-fields' ),
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
     * WooCommerce calls every callback hooked to `woocommerce_checkout_process`
     * before creating the order. Calling wc_add_notice() with type 'error'
     * prevents the order from being placed and displays the message to the customer.
     *
     * Nonce verification is handled upstream by WooCommerce's checkout handler.
     */
    public function validate_fields(): void {
        // Only validate when the gift checkbox is checked.
        if ( empty( $_POST['recipient_is_gift'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        $name  = isset( $_POST['recipient_name'] )  ? sanitize_text_field( wp_unslash( $_POST['recipient_name'] ) )  : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $email = isset( $_POST['recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email'] ) )       : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ( '' === $name ) {
            wc_add_notice(
                __( 'Please enter the recipient\'s full name.', 'recipient-checkout-fields' ),
                'error'
            );
        }

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
    }

    // -------------------------------------------------------------------------
    // Order meta persistence
    // -------------------------------------------------------------------------

    /**
     * Save recipient fields to order meta after the order record is created.
     *
     * `woocommerce_checkout_update_order_meta` fires with the new order ID
     * and the raw POST data. We sanitize everything before storing.
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

        // Only store name/email when this is actually a gift order.
        if ( 'yes' === $is_gift ) {
            update_post_meta( $order_id, '_recipient_name',  $name );
            update_post_meta( $order_id, '_recipient_email', $email );
        } else {
            // Clear any stale values if the customer previously had the box checked.
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
     * `woocommerce_webhook_payload` fires for every webhook delivery regardless
     * of topic, so we guard against topics that don't carry an order ID.
     *
     * @param array  $payload    The current webhook payload array.
     * @param string $resource   The webhook resource type (e.g. 'order').
     * @param int    $resource_id The resource object ID.
     * @param int    $webhook_id  The webhook definition ID.
     *
     * @return array Modified payload with recipient fields appended.
     */
    public function inject_webhook_payload( array $payload, string $resource, int $resource_id, int $webhook_id ): array {
        // Only enrich order-related webhooks; other topics won't have order meta.
        if ( 'order' !== $resource ) {
            return $payload;
        }

        $is_gift = get_post_meta( $resource_id, '_recipient_is_gift', true );

        $payload['recipient_is_gift'] = ( 'yes' === $is_gift ) ? true : false;
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
     * `woocommerce_admin_order_data_after_billing_address` passes the WC_Order
     * object, which we use to fetch the stored meta values.
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

        <div class="rcf-admin-recipient-details" style="margin-top:20px;">
            <h3><?php esc_html_e( 'Recipient Details', 'recipient-checkout-fields' ); ?></h3>
            <p>
                <strong><?php esc_html_e( 'Recipient Name:', 'recipient-checkout-fields' ); ?></strong>
                <?php echo esc_html( $name ); ?>
            </p>
            <p>
                <strong><?php esc_html_e( 'Recipient Email:', 'recipient-checkout-fields' ); ?></strong>
                <a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a>
            </p>
        </div>

        <?php
    }
}
