<?php
/**
 * Api Limit class
 *
 * @package FatoriPayWoo
 */

namespace WC_FatoriPay\Api;

/**
 * Define the limit existent on FatoriPay API
 */
class Api_Limit {

	/**
	 * Max installments allowed on API
	 *
	 * @param string $prefix Prefix payment method name.
	 *
	 * @return number Max installments number
	 */
	public function max_installments( $prefix ) {
		if ( 'ticket' === $prefix ) {
			return 60;
		}

		return 12;
	}

	/**
	 * The minimum value defined on API
	 *
	 * @return number The minimum installment value
	 */
	public function min_installment_value() {
		return 5;
	}
}
