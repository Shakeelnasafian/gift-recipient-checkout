# gift-recipient-checkout

A WordPress plugin that adds **"This order is for someone else"** functionality to WooCommerce checkout. When a customer checks the option, recipient name and email fields appear. The data is saved to the order and included in outgoing webhook payloads.

---

## Plugin: `recipient-checkout-fields`

### Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+

### Installation

1. Copy the `recipient-checkout-fields/` folder into `wp-content/plugins/`.
2. In the WordPress admin go to **Plugins → Installed Plugins** and activate **Recipient Checkout Fields**.
3. No configuration is required — the fields appear on the checkout page automatically.

Alternatively, zip the `recipient-checkout-fields/` directory and upload it via **Plugins → Add New → Upload Plugin**.

---

## File Structure

```
recipient-checkout-fields/
├── recipient-checkout-fields.php   # Main plugin file — bootstraps on woocommerce_loaded
├── includes/
│   └── class-recipient-fields.php  # Single OOP class containing all plugin logic
└── assets/
    └── js/
        └── checkout.js             # jQuery toggle with smooth slide animation
```

---

## Features

### Checkout Fields
- Adds a **"This order is for someone else"** checkbox after the Order Notes field.
- When checked, two fields slide into view:
  - **Recipient Full Name** (required)
  - **Recipient Email Address** (required)
- Fields animate smoothly using jQuery `slideDown`/`slideUp`.
- If validation fails, the form re-renders with previous values preserved.

### Validation
- If the checkbox is checked and either field is empty, checkout is blocked and a WooCommerce error notice is displayed.
- Email addresses are validated with `is_email()`.

### Order Meta
The following keys are saved to every order:

| Meta key | Value |
|---|---|
| `_recipient_is_gift` | `"yes"` or `"no"` |
| `_recipient_name` | Recipient's full name |
| `_recipient_email` | Recipient's email address |

### Webhook Payload
The plugin hooks into `woocommerce_webhook_payload` and appends these fields to **order** webhooks sent to your Laravel app:

```json
{
  "recipient_is_gift": true,
  "recipient_name": "Jane Doe",
  "recipient_email": "jane@example.com"
}
```

### Admin Order View
A **Recipient Details** section is displayed beneath the billing address on the WooCommerce admin order detail page — visible only when the order was flagged as a gift.

---

## Hooks Reference

| WordPress/WooCommerce Hook | Method | Purpose |
|---|---|---|
| `wp_enqueue_scripts` | `enqueue_scripts()` | Loads `checkout.js` on the checkout page only |
| `woocommerce_after_order_notes` | `render_fields()` | Renders the checkbox and recipient fields |
| `woocommerce_checkout_process` | `validate_fields()` | Blocks order on missing/invalid recipient data |
| `woocommerce_checkout_update_order_meta` | `save_order_meta()` | Persists recipient data to order meta |
| `woocommerce_webhook_payload` | `inject_webhook_payload()` | Appends recipient fields to order webhook payloads |
| `woocommerce_admin_order_data_after_billing_address` | `render_admin_order_fields()` | Shows recipient details in the admin order view |

---

## Code Standards

- OOP structure — single class (`Recipient_Fields`)
- All outputs escaped (`esc_html`, `esc_url`, `esc_attr`)
- All inputs sanitized (`sanitize_text_field`, `sanitize_email`)
- No external dependencies
- Compatible with WooCommerce 7+ and WordPress 6+
