<?php
/**
 * Subscriptions helper class
 *
 * @package FatoriPayWoo
 */

namespace WC_FatoriPay\Helper;

/**
 * Subscriptions helper functions
 */
class Subscriptions_Helper {

	/**
	 * Allowed period combinations.
	 *
	 * @var array
	 */
	private $allowed_period_combinations = array();

	/**
	 * Allowed discount coupon types.
	 * The plugin doesn't supports the following types: recurring_fee, recurring_percent
	 *
	 * @var array
	 */
	private $allowed_discount_coupon_types = array();

	/**
	 * Subscription product types.
	 *
	 * @var array
	 */
	public $subscription_product_types = array( 'variable-subscription', 'subscription', 'subscription_variation' );

	/**
	 * Init the Subscription Helper class
	 */
	public function __construct() {
		$this->allowed_period_combinations = array(
			'1 week'  => array(
				'period'      => 'WEEKLY',
				'description' => __( 'WEEKLY', 'fatoripay-woo' ),
			),
			'2 week'  => array(
				'period'      => 'BIWEEKLY',
				'description' => __( 'BIWEEKLY', 'fatoripay-woo' ),
			),
			'1 month' => array(
				'period'      => 'MONTHLY',
				'description' => __( 'MONTHLY', 'fatoripay-woo' ),
			),
			'4 week'  => array(
				'period'      => 'MONTHLY',
				'description' => __( 'MONTHLY', 'fatoripay-woo' ),
			),
			'2 month' => array(
				'period'      => 'BIMONTHLY',
				'description' => __( 'BIMONTHLY', 'fatoripay-woo' ),
			),
			'3 month' => array(
				'period'      => 'QUARTERLY',
				'description' => __( 'QUARTERLY', 'fatoripay-woo' ),
			),
			'6 month' => array(
				'period'      => 'SEMIANNUALLY',
				'description' => __( 'SEMIANNUALLY', 'fatoripay-woo' ),
			),
			'1 year'  => array(
				'period'      => 'YEARLY',
				'description' => __( 'YEARLY', 'fatoripay-woo' ),
			),
		);

		$this->allowed_discount_coupon_types = array(
			'percent'             => __( 'Percentage discount', 'fatoripay-woo' ),
			'fixed_cart'          => __( 'Fixed cart discount', 'fatoripay-woo' ),
			'fixed_product'       => __( 'Fixed product discount', 'fatoripay-woo' ),
			'sign_up_fee'         => __( 'Sign Up Fee Discount', 'fatoripay-woo' ),
			'sign_up_fee_percent' => __( 'Sign Up Fee % Discount', 'fatoripay-woo' ),
		);
	}

	/**
	 * Return supported billing period string
	 *
	 * @return string.
	 */
	public function get_supported_billing_periods_string() {
		$periods = [];
		foreach ( $this->allowed_period_combinations as $key => $period ) {
			if ( false === in_array( $period['description'], $periods, true ) ) {
				$periods[] = $period['description'];
			}
		}

		return implode( ', ', $periods );
	}

	/**
	 * Convert combined period to allowed billing cycle
	 *
	 * @link https://fatoripayv3.docs.apiary.io/#reference/0/assinaturas/criar-nova-assinatura
	 *
	 * @param string $interval The subscription product billing interval.
	 * @param string $period The subscription product billing period.
	 * @return string|false The billing cycle or false if fails.
	 */
	public function convert_period( $interval = '', $period = '' ) {
		$combined_period = $interval . ' ' . $period;
		if ( array_key_exists( $combined_period, $this->allowed_period_combinations ) ) {
			return $this->allowed_period_combinations[ $combined_period ]['period'];
		}

		return false;
	}

	/**
	 * Checks if discount coupon is supported
	 *
	 * @param \WC_Coupon $coupon The discount coupon.
	 * @return bool True if coupon is supported.
	 */
	public function discount_coupon_supported( $coupon ) {
		if ( array_key_exists( $coupon->get_discount_type(), $this->allowed_discount_coupon_types ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return supported coupon types string
	 *
	 * @return string
	 */
	public function get_supported_coupon_types_string() {
		$coupon_types = [];
		foreach ( $this->allowed_discount_coupon_types as $key => $coupon_type ) {
			if ( false === in_array( $coupon_type, $coupon_types, true ) ) {
				$coupon_types[] = $coupon_type;
			}
		}

		return implode( ', ', $coupon_types );
	}

	/**
	 * Gets Subscription object by FatoriPay subscription id
	 *
	 * @param  string $subscription_id The FatoriPay subscription id.
	 * @return WC_Subscription|bool WC_Subscription object if it found. Otherwise, false.
	 */
	public function get_subscription_by_id( $subscription_id ) {
		/* @var wpdb $wpdb WordPress database access abstraction object */
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT ID FROM {$wpdb->posts} as P
			INNER JOIN {$wpdb->postmeta} as PM
			WHERE P.ID = PM.post_id
			AND P.post_type   = %s
			AND PM.meta_key   = %s
			AND PM.meta_value = %s",
				array(
					'shop_subscription',
					'_fatoripay_subscription_id',
					$subscription_id,
				)
			)
		);

		if ( count( $results ) > 0 && empty( $wpdb->last_error ) && function_exists( '\wcs_get_subscription' ) ) {
			return \wcs_get_subscription( $results[0]->ID );
		}

		return false;
	}

	/**
	 * Gets order by FatoriPay payment id
	 *
	 * @param string $payment_id The FatoriPay payment id.
	 * @return WC_Order|bool WC_Order object if it found. Otherwise, false.
	 */
	public function get_order_by_payment_id( $payment_id ) {
		/* @var wpdb $wpdb WordPress database access abstraction object */
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key = %s
			AND meta_value = %s",
				array(
					'_fatoripay_id',
					$payment_id,
				)
			)
		);

		if ( count( $results ) > 0 && empty( $wpdb->last_error ) ) {
			return wc_get_order( $results[0]->post_id );
		}

		return false;
	}
}
