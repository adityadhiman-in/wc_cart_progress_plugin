<?php
/**
 * Plugin Name: WC Cart Discount Progress
 * Plugin URI: https://example.com/
 * Description: Adds automatic WooCommerce cart discounts based on subtotal thresholds and shows a checkout progress bar to unlock the next discount.
 * Version: 1.0.0
 * Author: Codex
 * Requires Plugins: woocommerce
 * Text Domain: wc-cart-discount-progress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Cart_Discount_Progress' ) ) {
	final class WC_Cart_Discount_Progress {
		/**
		 * Discount rules. Update these values to change thresholds or percentages.
		 *
		 * @var array<int, float>
		 */
		private $discount_rules = array(
			2000 => 2.5,
			3000 => 5.0,
		);

		/**
		 * Boot the plugin.
		 *
		 * @return void
		 */
		public function init() {
			add_action( 'plugins_loaded', array( $this, 'load' ) );
		}

		/**
		 * Load WooCommerce hooks only when WooCommerce is available.
		 *
		 * @return void
		 */
		public function load() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}

			add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_discount_fee' ) );
			add_action( 'woocommerce_before_checkout_form', array( $this, 'render_checkout_banner' ), 5 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'wp_ajax_wc_cart_discount_progress_banner', array( $this, 'ajax_render_banner' ) );
			add_action( 'wp_ajax_nopriv_wc_cart_discount_progress_banner', array( $this, 'ajax_render_banner' ) );
		}

		/**
		 * Return normalized discount rules sorted by threshold.
		 *
		 * @return array<int, float>
		 */
		private function get_discount_rules() {
			$rules = apply_filters( 'wc_cart_discount_progress_rules', $this->discount_rules );

			if ( ! is_array( $rules ) || empty( $rules ) ) {
				$rules = $this->discount_rules;
			}

			$normalized_rules = array();

			foreach ( $rules as $threshold => $percentage ) {
				$threshold  = (float) $threshold;
				$percentage = (float) $percentage;

				if ( $threshold <= 0 || $percentage <= 0 ) {
					continue;
				}

				$normalized_rules[ (int) round( $threshold ) ] = $percentage;
			}

			ksort( $normalized_rules, SORT_NUMERIC );

			return $normalized_rules;
		}

		/**
		 * Get the subtotal used for progress and discount calculation.
		 *
		 * @return float
		 */
		private function get_cart_subtotal() {
			if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
				return 0.0;
			}

			return (float) WC()->cart->get_subtotal();
		}

		/**
		 * Find the currently unlocked discount rule for the subtotal.
		 *
		 * @param float $subtotal Current cart subtotal.
		 * @return array{threshold:int, percentage:float}|null
		 */
		private function get_active_rule( $subtotal ) {
			$active_rule = null;

			foreach ( $this->get_discount_rules() as $threshold => $percentage ) {
				if ( $subtotal >= $threshold ) {
					$active_rule = array(
						'threshold'  => (int) $threshold,
						'percentage' => (float) $percentage,
					);
				}
			}

			return $active_rule;
		}

		/**
		 * Find the next available discount rule for the subtotal.
		 *
		 * @param float $subtotal Current cart subtotal.
		 * @return array{threshold:int, percentage:float}|null
		 */
		private function get_next_rule( $subtotal ) {
			foreach ( $this->get_discount_rules() as $threshold => $percentage ) {
				if ( $subtotal < $threshold ) {
					return array(
						'threshold'  => (int) $threshold,
						'percentage' => (float) $percentage,
					);
				}
			}

			return null;
		}

		/**
		 * Apply the automatic discount as a negative fee.
		 *
		 * @param WC_Cart $cart WooCommerce cart instance.
		 * @return void
		 */
		public function apply_discount_fee( $cart ) {
			if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
				return;
			}

			if ( ! $cart instanceof WC_Cart || $cart->is_empty() ) {
				return;
			}

			$subtotal    = (float) $cart->get_subtotal();
			$active_rule = $this->get_active_rule( $subtotal );

			if ( ! $active_rule ) {
				return;
			}

			$discount_amount = round( $subtotal * ( $active_rule['percentage'] / 100 ), wc_get_price_decimals() );

			if ( $discount_amount <= 0 ) {
				return;
			}

			$label = sprintf(
				/* translators: %s: discount percentage. */
				__( 'Cart discount (%s%% off)', 'wc-cart-discount-progress' ),
				wc_format_localized_decimal( $active_rule['percentage'] )
			);

			$cart->add_fee( $label, -$discount_amount, false );
		}

		/**
		 * Enqueue frontend assets on the checkout page.
		 *
		 * @return void
		 */
		public function enqueue_assets() {
			if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
				return;
			}

			wp_enqueue_style(
				'wc-cart-discount-progress',
				plugin_dir_url( __FILE__ ) . 'assets/css/frontend.css',
				array(),
				'1.0.0'
			);

			wp_enqueue_script(
				'wc-cart-discount-progress',
				plugin_dir_url( __FILE__ ) . 'assets/js/frontend.js',
				array( 'jquery' ),
				'1.0.0',
				true
			);

			wp_localize_script(
				'wc-cart-discount-progress',
				'WCCartDiscountProgress',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wc_cart_discount_progress_banner' ),
				)
			);
		}

		/**
		 * Render the checkout banner.
		 *
		 * @return void
		 */
		public function render_checkout_banner() {
			if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
				return;
			}

			echo wp_kses_post( $this->get_banner_markup() );
		}

		/**
		 * AJAX handler used to refresh the banner after checkout updates.
		 *
		 * @return void
		 */
		public function ajax_render_banner() {
			check_ajax_referer( 'wc_cart_discount_progress_banner', 'nonce' );

			wp_send_json_success(
				array(
					'markup' => $this->get_banner_markup(),
				)
			);
		}

		/**
		 * Build the banner markup.
		 *
		 * @return string
		 */
		private function get_banner_markup() {
			$rules = $this->get_discount_rules();

			if ( empty( $rules ) ) {
				return '';
			}

			$subtotal    = $this->get_cart_subtotal();
			$active_rule = $this->get_active_rule( $subtotal );
			$next_rule   = $this->get_next_rule( $subtotal );
			$max_goal    = (int) max( array_keys( $rules ) );
			$progress    = $max_goal > 0 ? min( 100, max( 0, ( $subtotal / $max_goal ) * 100 ) ) : 0;
			$savings     = $active_rule ? round( $subtotal * ( $active_rule['percentage'] / 100 ), wc_get_price_decimals() ) : 0;

			if ( $next_rule ) {
				$remaining_amount = max( 0, $next_rule['threshold'] - $subtotal );
				$title            = sprintf(
					/* translators: 1: amount remaining, 2: discount percentage. */
					__( 'Add %1$s more to unlock %2$s%% off', 'wc-cart-discount-progress' ),
					wc_price( $remaining_amount ),
					wc_format_localized_decimal( $next_rule['percentage'] )
				);
				$subtitle         = sprintf(
					/* translators: 1: current subtotal, 2: target threshold. */
					__( 'Current eligible subtotal: %1$s of %2$s', 'wc-cart-discount-progress' ),
					wc_price( $subtotal ),
					wc_price( $next_rule['threshold'] )
				);
				$status_badge     = $active_rule
					? sprintf(
						/* translators: %s: discount percentage. */
						__( '%s%% off unlocked', 'wc-cart-discount-progress' ),
						wc_format_localized_decimal( $active_rule['percentage'] )
					)
					: __( 'No discount unlocked yet', 'wc-cart-discount-progress' );
				$caption          = $active_rule
					? sprintf(
						/* translators: %s: savings amount. */
						__( 'You are already saving %s on this order.', 'wc-cart-discount-progress' ),
						wc_price( $savings )
					)
					: __( 'The discount will apply automatically when the target is reached.', 'wc-cart-discount-progress' );
			} else {
				$title        = sprintf(
					/* translators: %s: discount percentage. */
					__( 'Nice! Your cart has unlocked the maximum %s%% discount.', 'wc-cart-discount-progress' ),
					$active_rule ? wc_format_localized_decimal( $active_rule['percentage'] ) : '0'
				);
				$subtitle     = sprintf(
					/* translators: %s: current subtotal. */
					__( 'Discount is being applied automatically on %s.', 'wc-cart-discount-progress' ),
					wc_price( $subtotal )
				);
				$status_badge = __( 'Best discount active', 'wc-cart-discount-progress' );
				$caption      = sprintf(
					/* translators: %s: savings amount. */
					__( 'Automatic savings applied: %s', 'wc-cart-discount-progress' ),
					wc_price( $savings )
				);
				$progress     = 100;
			}

			ob_start();
			?>
			<div class="wc-cart-discount-progress" data-wc-cart-discount-progress>
				<div class="wc-cart-discount-progress__glow" aria-hidden="true"></div>
				<div class="wc-cart-discount-progress__content">
					<div class="wc-cart-discount-progress__text">
						<span class="wc-cart-discount-progress__badge"><?php echo esc_html( $status_badge ); ?></span>
						<h3 class="wc-cart-discount-progress__title"><?php echo wp_kses_post( $title ); ?></h3>
						<p class="wc-cart-discount-progress__subtitle"><?php echo wp_kses_post( $subtitle ); ?></p>
						<p class="wc-cart-discount-progress__caption"><?php echo wp_kses_post( $caption ); ?></p>
					</div>
					<div class="wc-cart-discount-progress__meter">
						<div class="wc-cart-discount-progress__meter-head">
							<span><?php esc_html_e( 'Progress to best offer', 'wc-cart-discount-progress' ); ?></span>
							<strong><?php echo esc_html( round( $progress ) . '%' ); ?></strong>
						</div>
						<div class="wc-cart-discount-progress__track">
							<span class="wc-cart-discount-progress__fill" style="width: <?php echo esc_attr( round( $progress, 2 ) ); ?>%;"></span>
						</div>
						<div class="wc-cart-discount-progress__steps">
							<?php foreach ( $rules as $threshold => $percentage ) : ?>
								<?php $is_reached = $subtotal >= $threshold; ?>
								<div class="wc-cart-discount-progress__step<?php echo $is_reached ? ' is-active' : ''; ?>">
									<strong><?php echo wp_kses_post( wc_price( $threshold ) ); ?></strong>
									<span><?php echo esc_html( wc_format_localized_decimal( $percentage ) . '% off' ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
			<?php

			return (string) ob_get_clean();
		}
	}
}

$wc_cart_discount_progress = new WC_Cart_Discount_Progress();
$wc_cart_discount_progress->init();
