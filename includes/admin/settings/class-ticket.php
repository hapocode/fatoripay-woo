<?php
/**
 * Ticket settings class
 *
 * @package FatoriPayWoo
 */

namespace WC_FatoriPay\Admin\Settings;

/**
 * Ticket settings
 */
class Ticket extends Settings {

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \WC_FatoriPay\Admin\Settings\Settings::get_fields()
	 */
	public function get_fields() {
		$fields                           = parent::get_fields( $this->gateway->get_type() );
		$fields['description']['default'] = __( 'Pay your purchase with ticket.', 'fatoripay-woo' );

		return array_merge(
			apply_filters( 'woocommerce_fatoripay_ticket_settings_fields', $fields, $this ),
			array()
		);
	}

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \WC_FatoriPay\Admin\Settings\Settings::get_sections()
	 */
	public function get_sections() {
		$sections                     = parent::get_sections();
		$sections['gateway']['title'] = __( 'Todos os Pagamentos', 'fatoripay-woo' );
		return apply_filters( 'woocommerce_fatoripay_ticket_settings_sections', $sections );
	}
}
