<?php
/**
 * API response handler class
 *
 * @package FatoriPayWoo
 */

namespace WC_FatoriPay\Api\Response;

/**
 * API response handler class
 */
class Error_Response extends Response {

	/**
	 * Get response errors, if is error.
	 *
	 * @return \WP_Error|bool The error object, if has error. False, otherwise.
	 */
	public function get_errors() {
		$error = new \WP_Error();

		if ( empty( $this->data ) ) {
			$log_message = sprintf( 'The FatoriPay API return a %d code.', $this->code );
			$this->client->get_gateway()->get_logger()->log( $log_message, 'emergency' );

			$customer_message = __( 'An error processing your order. Contact us.', 'fatoripay-woo' );
			$error->add( 'internal-error', apply_filters( 'woocommerce_fatoripay_internal_api_error', $customer_message ) );
		} else {
			foreach ( $this->get_json()->errors as $response_error ) {
				$error->add( $response_error->code, $response_error->description );
			}
		}

		return $error;
	}
}
