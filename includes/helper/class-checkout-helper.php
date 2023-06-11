<?php
/**
 * Validation helper class
 *
 * @package FatoriPayWoo
 */

namespace WC_FatoriPay\Helper;

/**
 * Validation helper functions
 */
class Checkout_Helper {

	/**
	 * Convert payment status to natural language
	 *
	 * @link https://fatoripayv3.docs.apiary.io/#reference/0/cobrancas
	 *
	 * @param string $status The payment status.
	 * @return string The natural language payment status.
	 */
	public function convert_status( $status ) {
		if ( 'PENDING' === $status ) {
			return __( 'Pending', 'fatoripay-woo' );
		}

		if ( true === in_array( $status, array( 'RECEIVED', 'CONFIRMED' ), true ) ) {
			return __( 'Confirmed', 'fatoripay-woo' );
		}

		if ( 'OVERDUE' === $status ) {
			return __( 'Overdue', 'fatoripay-woo' );
		}

		if ( 'REFUNDED' === $status ) {
			return __( 'Refunded', 'fatoripay-woo' );
		}

		if ( 'RECEIVED_IN_CASH' === $status ) {
			return __( 'Received in cash', 'fatoripay-woo' );
		}

		if ( 'REFUND_REQUESTED' === $status ) {
			return __( 'Refund requested', 'fatoripay-woo' );
		}
	}
}
