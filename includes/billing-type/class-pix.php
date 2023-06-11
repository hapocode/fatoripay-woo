<?php
/**
 * Pix billing type class
 *
 * @package FatoriPayWoo
 */

namespace WC_FatoriPay\Billing_Type;

/**
 * Pix billing type
 */
class Pix extends Billing_Type {

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \WC_FatoriPay\Billing_Type\Billing_Type::get_id()
	 */
	public function get_id() {
		return 'PIX';
	}

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \WC_FatoriPay\Billing_Type\Billing_Type::get_name()
	 */
	public function get_slug() {
		return 'pix';
	}

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \WC_FatoriPay\Billing_Type\Billing_Type::get_name()
	 */
	public function get_name() {
		return __( 'Pix', 'fatoripay-woo' );
	}
}
