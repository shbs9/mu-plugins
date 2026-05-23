add_action('woocommerce_after_checkout_validation', function($data, $errors) {
    if (!isset($_POST['woocommerce-process-checkout-nonce']) ||
        !wp_verify_nonce($_POST['woocommerce-process-checkout-nonce'], 'woocommerce-process_checkout')) {
        $errors->add('validation', 'Session expired, please refresh and try again.');
    }
}, 5, 2);
