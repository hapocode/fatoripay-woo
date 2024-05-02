<?php
/**
 * FatoriPay My Account actions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;

class WC_FatoriPay_My_Account {

	/**
	 * Legacy - Add bank slip link/button in My Orders section on My Accout page.
	 *
	 * @deprecated 1.1.0
	 */
	public function legacy_my_orders_bank_slip_link( $actions, $order ) {
		if ( 'fatoripay-bank-slip' !== $order->get_payment_method() ) {
			return $actions;
		}

		if ( ! in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
			return $actions;
		}

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$data = $order->get_meta('_fatoripay_wc_transaction_data', true );
		} else {
			$data = get_post_meta( $order->get_id(), '_fatoripay_wc_transaction_data', true );
		}

		if ( ! empty( $data['pdf'] ) ) {
			$actions[] = array(
				'url'  => $data['pdf'],
				'name' => __( 'Pay the Bank Slip', 'fatoripay-woo' ),
			);
		}

		return $actions;
	}

	/**
	 * Add bank slip link/button in My Orders section on My Accout page.
	 */
	public function my_orders_bank_slip_link( $actions, $order ) {
		if ( 'fatoripay-bank-slip' !== $order->get_payment_method() ) {
			return $actions;
		}

		if ( ! $order->has_status( array( 'pending', 'on-hold' ) ) ) {
			return $actions;
		}

		$data = $order->get_meta( '_fatoripay_wc_transaction_data' );
		if ( ! empty( $data['payment-url'] ) ) {
			$actions[] = array(
				'url'  => $data['payment-url'],
				'name' => __( 'Pay the Bank Slip', 'fatoripay-woo' ),
			);
		}

		return $actions;
	}
}

new WC_FatoriPay_My_Account();
