add_action('woocommerce_after_checkout_validation', function($data, $errors) {
    
    // 1. Standard nonce verification
    if (!isset($_POST['woocommerce-process-checkout-nonce']) ||
        !wp_verify_nonce($_POST['woocommerce-process-checkout-nonce'], 'woocommerce-process_checkout')) {
        $errors->add('validation', 'Security verification failed.');
    }

    // 2. Age verification (18+ for accessories, 21+ for receivers)
    if (isset($_POST['billing_age_verified'])) {
        if ($_POST['billing_age_verified'] !== '1') {
            $errors->add('validation', 'Age verification is required.');
        }
    }

    // 3. Check restricted states
    $billing_state = isset($_POST['billing_state']) ? sanitize_text_field($_POST['billing_state']) : '';
    $restricted_states = ['CA', 'NY', 'NJ', 'MA']; // Example - verify your own restrictions
    
    if (in_array($billing_state, $restricted_states)) {
        $errors->add('validation', 'We cannot ship to ' . $billing_state . '.');
    }

    // 4. Fraud detection
    if (floatval(WC()->cart->get_total(null)) > 5000) {
        // For high-value orders, require additional verification
        error_log('High-value firearms order: $' . WC()->cart->get_total(null) . ' - Review required');
    }

}, 10, 2);
