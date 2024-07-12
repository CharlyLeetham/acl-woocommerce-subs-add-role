<?php
/**
 * Plugin Name: ACL WooCommerce Subscription Role Assigner
 * Plugin URI:  https://askcharlyleetham.com
 * Description: Assigns user roles based on WooCommerce subscription purchases.
 * Version:     1.0.0
 * Author:      Charly Leetham
 * Author URI:  https://askcharlyleetham.com.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: acl-woocommerce-subscription-role-assigner
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Hook into 'woocommerce_subscription_payment_complete' action
add_action('woocommerce_subscription_payment_complete', 'acl_assign_user_role_on_subscription_payment_complete', 10, 1);

function acl_assign_user_role_on_subscription_payment_complete($subscription) {
    // Check if the subscription object is valid
    if (!$subscription instanceof WC_Subscription) {
        return;
    }

    // Get the user ID from the subscription
    $user_id = $subscription->get_user_id();

    // Get the subscription items
    $items = $subscription->get_items();

    // Define the mapping of subscription products to user roles
    $subscription_role_mapping = array(
        'product_id_1' => 'role_1', // Replace 'product_id_1' with your product ID and 'role_1' with your user role
        'product_id_2' => 'role_2', // Add as many as needed
    );

    // Initialize the user role variable
    $user_role = '';

    // Loop through the subscription items to find the purchased product
    foreach ($items as $item) {
        $product_id = $item->get_product_id();
        if (isset($subscription_role_mapping[$product_id])) {
            $user_role = $subscription_role_mapping[$product_id];
            break;
        }
    }

    // If a matching user role is found, assign it to the user
    if (!empty($user_role)) {
        $user = new WP_User($user_id);
        $user->set_role($user_role);
    }
}
