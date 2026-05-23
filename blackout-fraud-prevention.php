<?php
/**
 * Blackout Dynamics - Fraud Prevention & Checkout Validation
 * MU Plugin (Must-Use)
 * This runs automatically, can't be deactivated
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_after_checkout_validation', function($data, $errors) {
    
    // 1. Nonce verification
    if (!isset($_POST['woocommerce-process-checkout-nonce']) ||
        !wp_verify_nonce($_POST['woocommerce-process-checkout-nonce'], 'woocommerce-process_checkout')) {
        $errors->add('validation', 'Security verification failed. Please refresh and try again.');
        error_log('Blackout Fraud: Failed nonce - IP: ' . $_SERVER['REMOTE_ADDR']);
        return;
    }

    // 2. Age verification (18+ for accessories, 21+ for receivers)
    if (isset($_POST['billing_age_verified']) && $_POST['billing_age_verified'] !== '1') {
        $errors->add('validation', 'Age verification is required to proceed.');
    }

    // 3. Restricted states check
    $billing_state = isset($_POST['billing_state']) ? sanitize_text_field($_POST['billing_state']) : '';
    $restricted_states = ['CA', 'NY', 'NJ', 'MA']; // Add your restricted states
    
    if (in_array(strtoupper($billing_state), $restricted_states)) {
        $errors->add('validation', 'We cannot ship receivers to ' . $billing_state . '. Please check local laws.');
        error_log('Blackout Fraud: Restricted state attempt - State: ' . $billing_state);
    }

    // 4. High-value order flag
    $cart_total = WC()->cart->get_total(null);
    if (floatval($cart_total) > 5000) {
        error_log('Blackout Alert: High-value order - Amount: $' . $cart_total . ' - User: ' . (get_current_user_id() ?: 'Guest') . ' - IP: ' . $_SERVER['REMOTE_ADDR']);
    }

    // 5. Rapid order detection
    $user_id = get_current_user_id();
    if ($user_id) {
        $recent_orders = wc_get_orders([
            'customer_id' => $user_id,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'limit'       => 1,
            'status'      => ['completed', 'processing', 'on-hold'],
        ]);

        if (!empty($recent_orders)) {
            $last_order_time = strtotime($recent_orders[0]->get_date_created());
            $time_diff = time() - $last_order_time;

            if ($time_diff < 60) { // Less than 60 seconds
                $errors->add('validation', 'Please wait before placing another order.');
                error_log('Blackout Fraud: Rapid order - User ID: ' . $user_id . ' - Time gap: ' . $time_diff . 's');
            }
        }
    }

}, 10, 2);

/**
 * Log all checkout attempts (optional - for compliance)
 */
add_action('woocommerce_checkout_process', function() {
    $user_id = get_current_user_id();
    $email = isset($_POST['billing_email']) ? sanitize_email($_POST['billing_email']) : 'unknown';
    error_log('Blackout Checkout Attempt - User: ' . ($user_id ?: 'Guest') . ' - Email: ' . $email . ' - IP: ' . $_SERVER['REMOTE_ADDR']);
});
?>
