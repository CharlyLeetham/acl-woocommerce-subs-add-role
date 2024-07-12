<?php
/**
 * Plugin Name: ACL WooCommerce Subscription Role Assigner
 * Plugin URI:  https://askcharlyleetham.com
 * Description: Assigns user roles based on WooCommerce subscription purchases and updates roles based on subscription status.
 * Version:     1.0.0
 * Author:      Charly Leetham
 * Author URI:  https://askcharlyleetham.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: acl-woocommerce-subscription-role-assigner
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

// Hook into 'woocommerce_subscription_status_active' action
add_action( 'woocommerce_subscription_status_active', 'acl_add_user_role_on_subscription_status_active', 10, 1 );

// Hook into 'woocommerce_subscription_status_cancelled' action
add_action( 'woocommerce_subscription_status_cancelled', 'acl_remove_user_role_on_subscription_status_changed', 10, 1 );

// Hook into 'woocommerce_subscription_status_expired' action
add_action( 'woocommerce_subscription_status_expired', 'acl_remove_user_role_on_subscription_status_changed', 10, 1 );

// Hook into 'woocommerce_subscription_status_on-hold' action
add_action( 'woocommerce_subscription_status_on-hold', 'acl_remove_user_role_on_subscription_status_changed', 10, 1 );

function acl_add_user_role_on_subscription_status_active( $subscription ) {
	
    // Log entry into the function
    acl_log_info( 'Entering acl_add_user_role_on_subscription_status_active function. Subscription' );

    // Check if the subscription object is valid
    if ( !$subscription instanceof WC_Subscription ) {
        return;
    }

    // Get the user ID from the subscription
    $user_id = $subscription->get_user_id();
	acl_log_info( 'User ID - '.$user_id );

    // Define the mapping of subscription IDs to user roles
    $subscription_role_mapping = array(
        '4904' => 'directory_member', // Directory Annual
        '4847' => 'directory_member', // Directory Monthly
        '4902' => 'full_member', // Full Member Annual
        '4901' => 'full_member', // Full Member Six Monthly
        '4846' => 'full_member', // Full Member Monthly
    );

    // Loop through the subscription items to find the purchased product
    $items = $subscription->get_items();
    foreach ( $items as $item ) {
        $product_id = $item->get_product_id();
        if ( isset(  $subscription_role_mapping[$product_id] ) ) {
            $user_role =  $subscription_role_mapping[$product_id];
            $user = new WP_User( $user_id );
            // Add the role to the user
            $user->add_role( $user_role );
            acl_log_role_assignment( $user_id, $product_id, 'active', $user_role, 'added' );
        }
    }
}

function acl_remove_user_role_on_subscription_status_changed( $subscription ) {

    // Log entry into the function
    acl_log_info( 'Entering acl_remove_user_role_on_subscription_status_changed function.' );

    // Check if the subscription object is valid
    if ( !$subscription instanceof WC_Subscription ) {
        return;
    }

    // Get the user ID from the subscription
    $user_id = $subscription->get_user_id();

    // Define the mapping of subscription IDs to user roles
    $subscription_role_mapping = array(
        '4904' => 'directory_member', // Directory Annual
        '4847' => 'directory_member', // Directory Monthly
        '4902' => 'full_member', // Full Member Annual
        '4901' => 'full_member', // Full Member Six Monthly
        '4846' => 'full_member', // Full Member Monthly
    );

    // Loop through the subscription items to find the purchased product
    $items = $subscription->get_items();
    foreach ( $items as $item ) {
        $product_id = $item->get_product_id();
        if ( isset(  $subscription_role_mapping[$product_id] ) ) {
            $user_role =  $subscription_role_mapping[$product_id];
            $user = new WP_User( $user_id );

            // Remove the role from the user
            $user->remove_role( $user_role );
            acl_log_role_assignment( $user_id, $product_id, $subscription->get_status(), $user_role, 'removed' );
        }
    }
	
    // Set the status of gd_place posts to pending
    acl_set_gd_place_posts_to_pending( $user_id );	
}


function acl_set_gd_place_posts_to_pending( $user_id ) {
    // Get all gd_place posts owned by the user
    $args = array(
        'post_type'      => 'gd_place',
        'post_status'    => 'publish',
        'author'         => $user_id,
        'posts_per_page' => -1,
    );

    $posts = get_posts( $args );

    // Update the status of each post to pending
    foreach ( $posts as $post ) {
        $post->post_status = 'pending';
        wp_update_post( $post );
        acl_log_info( 'Setting post ID ' . $post->ID . ' status to pending.' );
    }
}

function acl_log_role_assignment( $user_id, $subscription_id, $status, $role, $action ) {
    if ( !function_exists( 'wc_get_logger' ) ) {
        return;
    }
	
    $logger = wc_get_logger();
    $context = array( 'source' => 'acl_subscription_role_assigner' );
    $message = sprintf( 'User ID: %d, Subscription ID: %d, Status: %s, Role: %s, Action: %s', $user_id, $subscription_id, $status, $role, $action );
    $logger->info( $message, $context );
}

function acl_log_info( $message ) {
    if ( !function_exists( 'wc_get_logger' ) ) {
        return;
    }
	
    $logger = wc_get_logger();
    $context = array( 'source' => 'acl_subscription_role_assigner' );
    $logger->info( $message, $context );
}


// Hook into 'user_register' action to auto-login user after account creation
add_action( 'user_register', 'acl_auto_login_new_user' );

function acl_auto_login_new_user( $user_id ) {
    // Log entry into the function
    acl_log_info( 'Entering acl_auto_login_new_user function - '.$user_id );

    define('COOKIEPATH', '/');
    // Set the cookie to expire in 1 hour
    $cookie_value = $user_id;
    $cookie_expiration = time() + 3600; // 1 hour = 3600 seconds
        
     // Get the domain dynamically
    $domain = parse_url( home_url(), PHP_URL_HOST );

    // Use PHP's setcookie() function to set the cookie
    setcookie( 'acl_userid', $cookie_value, $cookie_expiration, COOKIEPATH, $domain );

    // Write to the log
    acl_log_info('Custom cookie has been set!');
}


// Hook into 'woocommerce_before_checkout_form' action to check for the cookie and log in the user
add_action( 'woocommerce_checkout_process', 'acl_check_cookie_and_login_user' , 1);

function acl_check_cookie_and_login_user() {
    if ( isset( $_COOKIE['acl_userid'] ) ) {
         acl_log_info('Custom cookie has been found! '. $_COOKIE['acl_userid']);
        // Get the user ID from the cookie value or another method
        
        $user_id = $_COOKIE['acl_userid']; // Assuming the cookie value is the user ID

        // Verify that the user ID is valid and get user data
        $user = get_userdata( $user_id );
        if ( $user ) {
            acl_log_info('Logging in?!');
            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id );
        }
    }
}


//After the order has processed, delete the cookie
add_action( 'woocommerce_checkout_order_processed', 'delete_acl_userid_cookie', 10, 1 );

function delete_acl_userid_cookie( $order_id ) {
    
    define('COOKIEPATH', '/');
    $domain = parse_url( home_url(), PHP_URL_HOST );    

    // Check if the cookie is set
    if ( isset( $_COOKIE['acl_userid'] ) ) {
        // Unset the cookie in PHP
        unset( $_COOKIE['acl_userid'] );

        // Use PHP's setcookie function to delete the cookie
        setcookie( 'acl_userid', '', time() - 3600, COOKIEPATH, $domain );

        // Log or output a message to verify that the function runs
        acl_log_info( 'acl_userid cookie has been deleted after order processed.' );
    }
}