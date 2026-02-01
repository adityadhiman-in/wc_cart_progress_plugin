<?php

/**
 * Plugin Name: Cart Progress Discount by Aditya Dhiman
 * Plugin URI: https://adityadhiman.live
 * Description: Global cart incentive bar with automatic discounts to boost Average Order Value. Shows remaining amount needed to unlock discounts.
 * Version: 2.0.1
 * Author: Aditya Dhiman
 * Author URI: https://adityadhiman.live
 * Text Domain: wc-cart-progress-discount
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 5.0.0
 * WC tested up to: 9.0.0
 * Requires Plugins: woocommerce
 *
 * @package WCCartProgressDiscount
 * @author Aditya Dhiman <contact@adityadhiman.live>
 * @see https://adityadhiman.live
 */

if (! defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WC_CPD_VERSION', '2.0.1');
define('WC_CPD_PLUGIN_FILE', __FILE__);

/**
 * =====================================
 * CONFIGURATION - Edit Discount Rules
 * =====================================
 */
function wc_cpd_get_discount_rules(): array
{
    return [
        2000 => 5,   // ₹2000 → 5% discount
        3000 => 10,  // ₹3000 → 10% discount
    ];
}

/**
 * =====================================
 * APPLY DISCOUNT (BACKEND)
 * =====================================
 */
add_action('woocommerce_cart_calculate_fees', function ($cart): void {

    if (is_admin() && ! defined('DOING_AJAX')) {
        return;
    }

    if (! did_action('woocommerce_loaded')) {
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
            sprintf(
                __('Auto Discount (%s%%)', 'wc-cart-progress-discount'),
                $discount_percent
            ),
            - ($subtotal * $discount_percent / 100),
            false
        );
    }
}, 10, 1);

/**
 * =====================================
 * AJAX ENDPOINT - Cart Data
 * ===================================== */
add_action('wp_ajax_wc_cpd_get_cart', 'wc_cpd_ajax_get_cart');
add_action('wp_ajax_nopriv_wc_cpd_get_cart', 'wc_cpd_ajax_get_cart');

function wc_cpd_ajax_get_cart(): void
{
    header('Content-Type: application/json');

    // Check if WooCommerce is loaded
    if (! class_exists('WooCommerce')) {
        wp_send_json_error(['message' => 'WooCommerce not available']);
    }

    // Ensure WooCommerce is initialized
    if (! function_exists('WC')) {
        wp_send_json_error(['message' => 'WC function not available']);
    }

    // Initialize cart if needed
    if (! WC()->cart) {
        WC();
    }

    if (! WC()->cart) {
        wp_send_json_error(['message' => 'Cart not available']);
    }

    try {
        $rules = wc_cpd_get_discount_rules();
        ksort($rules);

        // Get subtotal
        $subtotal = WC()->cart->get_subtotal_ex_tax();
        if ($subtotal === 0) {
            $subtotal = WC()->cart->get_subtotal();
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

        wp_send_json_success([
            'subtotal' => (float) $subtotal,
            'current_discount' => (int) $current_discount,
            'next_goal' => $next_goal,
            'next_discount' => (int) $next_discount,
            'remaining' => (float) $remaining,
            'progress' => (float) $progress,
            'currency_symbol' => html_entity_decode(get_woocommerce_currency_symbol()),
            'currency' => get_woocommerce_currency(),
        ]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * =====================================
 * GLOBAL UI MOUNT - Footer
 * ===================================== */
add_action('wp_footer', function (): void {
    // Check if WooCommerce is available
    if (! class_exists('WooCommerce')) {
        return;
    }
?>
    <!-- Cart Progress Discount Bar by Aditya Dhiman - https://adityadhiman.live -->
    <div id="wc-cpd-global" role="status" aria-live="polite" aria-hidden="true">
        <div class="wc-cpd-card">
            <div class="wc-cpd-icon">🎁</div>
            <div class="wc-cpd-content">
                <div class="wc-cpd-text">Loading...</div>
                <div class="wc-cpd-track">
                    <div class="wc-cpd-fill" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>
<?php
}, 9999);

/**
 * =====================================
 * LOAD ASSETS - CSS & JavaScript
 * ===================================== */
add_action('wp_enqueue_scripts', function (): void {

    if (! class_exists('WooCommerce')) {
        return;
    }

    $asset_url = plugin_dir_url(__FILE__);

    wp_enqueue_style(
        'wc-cpd-style',
        $asset_url . 'assets/css/progress.css',
        [],
        WC_CPD_VERSION
    );

    wp_enqueue_script(
        'wc-cpd-script',
        $asset_url . 'assets/js/progress.js',
        ['jquery'],
        WC_CPD_VERSION,
        true
    );

    wp_localize_script('wc-cpd-script', 'wcCpdConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'action' => 'wc_cpd_get_cart',
        'i18n' => [
            'unlock_discount' => __('Add %1$s more to unlock %2$s OFF', 'wc-cart-progress-discount'),
            'unlocked' => __('🎉 You unlocked %s OFF', 'wc-cart-progress-discount'),
        ],
    ]);
}, 10);

/**
 * Plugin activation
 */
register_activation_hook(__FILE__, function (): void {
    if (! class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce to be installed and activated.', 'wc-cart-progress-discount'));
    }
});

/**
 * Plugin deactivation
 */
register_deactivation_hook(__FILE__, function (): void {
    // Cleanup if needed
});
