<?php
/**
 * Ticket billing type class
 *
 * @package FatoriPayWoo
 */

namespace WC_FatoriPay\Billing_Type;

/**
 * Ticket billing type
 */
class Ticket extends Billing_Type {

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \WC_FatoriPay\Billing_Type\Billing_Type::get_id()
	 */
	public function get_id() {
		return 'BOLETO';
	}

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \WC_FatoriPay\Billing_Type\Billing_Type::get_name()
	 */
	public function get_slug() {
		return 'ticket';
	}

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \WC_FatoriPay\Billing_Type\Billing_Type::get_name()
	 */
	public function get_name() {
		return __( 'Ticket', 'fatoripay-woo' );
	}
}
