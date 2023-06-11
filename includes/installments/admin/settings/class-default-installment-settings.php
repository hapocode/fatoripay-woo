<?php
/**
 * Default Installment Settings class
 *
 * @package FatoriPayWoo
 */

namespace WC_FatoriPay\Installments\Admin\Settings;

use WC_FatoriPay\Api\Api_Limit;
use WC_FatoriPay\Gateway\Gateway;

/**
 * Credit card settings values
 */
class Default_Installment_Settings {

	/**
	 * The prefix gateway
	 *
	 * @var string
	 */
	private $gateway_prefix;

	/**
	 * The Api Limit object
	 *
	 * @var Api_Limit
	 */
	private $api_limit;

	/**
	 * Constructor
	 *
	 * @param Gateway   $gateway The gateway object.
	 * @param Api_Limit $api_limit The api limit object.
	 */
	public function __construct( Gateway $gateway, Api_Limit $api_limit ) {
		$this->gateway_prefix = $gateway->prefix();
		$this->api_limit      = $api_limit;
	}

	/**
	 * The maximum installments allowed in a order
	 *
	 * @return int The max installments
	 */
	public function get_max_installments() {
		$max_installments_limit = $this->api_limit->max_installments( $this->gateway_prefix );

		return apply_filters( "woocommerce_fatoripay_{$this->gateway_prefix}_max_installments", $max_installments_limit );
	}

	/**
	 * The minimum value for each installment
	 *
	 * @return number The installment value
	 */
	public function get_min_installment_value() {
		$min_installment_value_limit = $this->api_limit->min_installment_value();

		return apply_filters( "woocommerce_fatoripay_{$this->gateway_prefix}_min_installment_value", $min_installment_value_limit );
	}
}
