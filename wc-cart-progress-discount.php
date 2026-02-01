<?php

/**
 * Plugin Name: Cart Progress Discount by Aditya Dhiman
 * Plugin URI: https://adityadhiman.live
 * Description: Global cart incentive bar with automatic discounts to boost Average Order Value.
 * Version: 2.1.0
 * Author: Aditya Dhiman
 * Author URI: https://adityadhiman.live
 * Text Domain: wc-cart-progress-discount
 * License: GPL v2 or later
 * Requires Plugins: woocommerce
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WC_CPD_VERSION', '2.1.0');

/**
 * Discount rules configuration
 */
function wc_cpd_get_discount_rules()
{
    return array(
        2000 => 5,
        3000 => 10,
    );
}

/**
 * Apply discount at checkout
 */
add_action('woocommerce_cart_calculate_fees', 'wc_cpd_apply_discount');

function wc_cpd_apply_discount($cart)
{
    if (is_admin() && ! defined('DOING_AJAX')) {
        return;
    }

    if (! WC()->cart) {
        return;
    }

    $rules = wc_cpd_get_discount_rules();
    ksort($rules);

    $subtotal = $cart->get_subtotal();
    $discount_percent = 0;

    foreach ($rules as $amount => $percent) {
        if ($subtotal >= $amount) {
            $discount_percent = $percent;
        }
    }

    if ($discount_percent > 0) {
        $cart->add_fee(
            sprintf(__('Auto Discount (%s%%)', 'wc-cart-progress-discount'), $discount_percent),
            - ($subtotal * $discount_percent / 100),
            false
        );
    }
}

/**
 * AJAX handler - get cart data
 */
add_action('wp_ajax_wc_cpd_get_cart', 'wc_cpd_ajax_get_cart');
add_action('wp_ajax_nopriv_wc_cpd_get_cart', 'wc_cpd_ajax_get_cart');

function wc_cpd_ajax_get_cart()
{
    header('Content-Type: application/json');

    $response = array(
        'success' => false,
        'subtotal' => 0,
        'current_discount' => 0,
        'next_goal' => 2000,
        'next_discount' => 5,
        'remaining' => 2000,
        'progress' => 0,
        'currency_symbol' => '₹',
    );

    if (function_exists('WC') && WC()->cart) {
        try {
            $rules = wc_cpd_get_discount_rules();
            ksort($rules);

            $subtotal = WC()->cart->get_subtotal();
            if ($subtotal === 0) {
                $subtotal = WC()->cart->get_subtotal_ex_tax();
            }

            $current_discount = 0;
            $next_goal = null;
            $next_discount = 0;

            foreach ($rules as $amount => $percent) {
                if ($subtotal >= $amount) {
                    $current_discount = $percent;
                } elseif ($next_goal === null) {
                    $next_goal = $amount;
                    $next_discount = $percent;
                }
            }

            $remaining = 0;
            $progress = 0;

            if ($next_goal !== null && $subtotal > 0) {
                $remaining = max(0, $next_goal - $subtotal);
                $progress = min(100, ($subtotal / $next_goal) * 100);
            } elseif ($next_goal !== null) {
                $remaining = $next_goal;
                $progress = 0;
            } else {
                $progress = 100;
            }

            $response = array(
                'success' => true,
                'subtotal' => (float) $subtotal,
                'current_discount' => (int) $current_discount,
                'next_goal' => $next_goal,
                'next_discount' => (int) $next_discount,
                'remaining' => (float) $remaining,
                'progress' => (float) $progress,
                'currency_symbol' => html_entity_decode(get_woocommerce_currency_symbol()),
            );
        } catch (Exception $e) {
        }
    }

    echo json_encode($response);
    wp_die();
}

/**
 * Output the incentive bar in footer
 */
add_action('wp_footer', 'wc_cpd_output_bar', 9999);

function wc_cpd_output_bar()
{
    if (! class_exists('WooCommerce')) {
        return;
    }
?>
    <!-- Cart Progress Discount by Aditya Dhiman - https://adityadhiman.live -->
    <div id="wc-cpd-global" role="status" aria-live="polite" aria-hidden="true" class="wc-cpd-collapsed" style="display:none;">
        <div class="wc-cpd-card">
            <button class="wc-cpd-close" aria-label="Close">×</button>
            <div class="wc-cpd-icon">🎁</div>
            <div class="wc-cpd-content">
                <div class="wc-cpd-text"></div>
                <div class="wc-cpd-track">
                    <div class="wc-cpd-fill"></div>
                </div>
            </div>
        </div>
    </div>
<?php
}

/**
 * Enqueue scripts and styles
 */
add_action('wp_enqueue_scripts', 'wc_cpd_enqueue_assets', 10);

function wc_cpd_enqueue_assets()
{
    if (! class_exists('WooCommerce')) {
        return;
    }

    $plugin_url = plugin_dir_url(__FILE__);

    wp_enqueue_style(
        'wc-cpd-style',
        $plugin_url . 'assets/css/progress.css',
        array(),
        WC_CPD_VERSION
    );

    wp_enqueue_script(
        'wc-cpd-script',
        $plugin_url . 'assets/js/progress.js',
        array('jquery'),
        WC_CPD_VERSION,
        true
    );

    wp_localize_script('wc-cpd-script', 'wcCpdConfig', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'action' => 'wc_cpd_get_cart',
        'collapsed' => true,
        'i18n' => array(
            'unlock_discount' => __('Add %1$s more to unlock %2$s OFF', 'wc-cart-progress-discount'),
            'unlocked' => __('🎉 You unlocked %s OFF', 'wc-cart-progress-discount'),
            'collapsed' => __('Show cart progress', 'wc-cart-progress-discount'),
        ),
    ));
}

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function () {
    if (! class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce.', 'wc-cart-progress-discount'));
    }
});
