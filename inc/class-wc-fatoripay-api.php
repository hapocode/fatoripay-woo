<?php

/**
 * WC FatoriPay API Class.
 */
class WC_FatoriPay_API {

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url;

	/**
	 * JS Library URL.
	 *
	 * @var string
	 */
	protected $js_url = 'https://stc.boacompra.com/payment.boacompra.min.js';

	/**
	 * Gateway class.
	 *
	 * @var WC_FatoriPay_Gateway
	 */
	protected $gateway;

	/**
	 * Payment method.
	 *
	 * @var string
	 */
	protected $method = '';

	/**
	 * Invoice reference prefix;
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * Constructor.
	 *
	 * @param WC_FatoriPay_Gateway $gateway
	 */
	public function __construct($gateway = null, $method = '', $sandbox = 'no', $prefix = 'wc') {

		if ($sandbox === 'yes') {

			$this->api_url = 'https://api.sandbox.boacompra.com';

		} else {

			$this->api_url = 'https://api.boacompra.com';

		} // end if;

		$this->gateway = $gateway;

		$this->method  = $method;

		$this->prefix  = $prefix;

	} // end __construct;

	/**
	 * Get API URL.
	 *
	 * @return string
	 */
	public function get_api_url() {

		return $this->api_url;

	} // end get_api_url;

	/**
	 * Get JS Library URL.
	 *
	 * @return string
	 */
	public function get_js_url() {

		return $this->js_url;

	} // end get_js_url;

	/**
	 * Get WooCommerce return URL.
	 *
	 * @return string
	 */
	protected function get_wc_request_url() {

		global $woocommerce;

		if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {

			return WC()->api_request_url(get_class($this->gateway));

		} else {

			return $woocommerce->api_request_url(get_class($this->gateway));

		} // end if;

	} // end get_wc_request_url;

	/**
	 * Get the settings URL.
	 *
	 * @return string
	 */
	public function get_settings_url() {

		if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {

			return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower(get_class($this->gateway)));

		} // end if;

		return admin_url('admin.php?page=woocommerce_settings&tab=payment_gateways&section=' . get_class( $this->gateway));

	} // end get_settings_url;

	/**
	 * Get order total.
	 *
	 * @return float
	 */
	public function get_order_total() {

		global $woocommerce;

		$order_total = 0;

		if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {

			$order_id = absint(get_query_var('order-pay'));

		} else {

			$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

		} // end if;

		/**
		 * Gets order total from "pay for order" page.
		 */
		if (0 < $order_id) {

			$order      = new WC_Order( $order_id );

			$order_total = (float) $order->get_total();

		/**
		 * // Gets order total from cart/checkout.
		 */
		} elseif (0 < $woocommerce->cart->total) {

			$order_total = (float) $woocommerce->cart->total;

		} // end if;

		return $order_total;

	} // end get_order_total;

	/**
	 * Check if order contains subscriptions.
	 *
	 * @param  int $order_id
	 * @return bool
	 */
	public function order_contains_subscription($order_id) {

		if (function_exists('wcs_order_contains_subscription')) {

			return wcs_order_contains_subscription($order_id) || wcs_order_contains_resubscribe( $order_id);

		} elseif ( class_exists('WC_Subscriptions_Order')) {

			return WC_Subscriptions_Order::order_contains_subscription($order_id) || WC_Subscriptions_Renewal_Order::is_renewal($order_id);

		} // end if;

		return false;

	} // end order_contains_subscription;

	/**
	 * Value in cents.
	 *
	 * @param  float $value
	 * @return int
	 */
	protected function get_cents($value) {

		return number_format( $value, 2, '', '' );

	} // end get_cents.

	/**
	 * Only numbers.
	 *
	 * @param  string|int $string
	 * @return string|int
	 */
	protected function only_numbers($string) {

		return preg_replace('([^0-9])', '', $string);

	} // end only_numbers;

	/**
	 * Add error message in checkout.
	 *
	 * @param  string $message Error message.
	 * @return string Displays the error message.
	 */
	public function add_error($message) {

		global $woocommerce;

		if (function_exists('wc_add_notice')) {

			wc_add_notice($message, 'error');

		} else {

			$woocommerce->add_error($message);

		} // end if;

	} // end add_error;

	/**
	 * Send email notification.
	 *
	 * @param string $subject Email subject.
	 * @param string $title   Email title.
	 * @param string $message Email message.
	 */
	public function send_email($subject, $title, $message) {

		global $woocommerce;

		if (defined( 'WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {

			$mailer = WC()->mailer();

		} else {

			$mailer = $woocommerce->mailer();

		} // end if;

		$mailer->send(get_option('admin_email'), $subject, $mailer->wrap_message($title, $message));

	} // end send_email;

	/**
	 * Empty card.
	 *
	 * @return void
	 */
	public function empty_card() {

		global $woocommerce;

		if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {

			WC()->cart->empty_cart();

		} else {

			$woocommerce->cart->empty_cart();

		} // end if;

	} // end empty_card;

	/**
	 * Process Payment
	 *
	 * @param string $order_id WC Order ID
	 * @return array API Response
	 */
	public function process_payment($order_id) {

		$order  = new WC_Order($order_id);

		$payload = $this->get_fatoripay_payload($order, $_POST);

		/**
		 * Processing redirect method.
		 */
		if ($this->method === 'redirect') {

			return array(
				'result'   => 'success',
				'redirect' => get_site_url().'/wc-api/wc_fatoripay_hosted_request?oid=' . $order_id,
			);

		} // end if;

		/**
		 * Processing redirect pix method.
		 */
		if ($this->method === 'pix') {

			return array(
				'result'   => 'success',
				'redirect' => get_site_url().'/wc-api/wc_fatoripay_hosted_pix_request?oid=' . $order_id,
			);

		} // end if;

		$charge = $this->do_request('/transactions', 'POST', $payload, 2);

		if ($charge && isset($charge['errors'])) {

			return array(
				'result'   => 'fail',
				'redirect' => '',
				'success' => false
			);

		} // end if;

		if (!isset($charge)) {

			return array(
				'result'   => 'fail',
				'redirect' => '',
				'success' => false
			);

		} // end if;

		$this->empty_card();

		if ($charge['transaction']) {


			update_post_meta($order->get_id(), '_fatoripay_wc_transaction_data', $charge['transaction']);

			update_post_meta($order->get_id(), '_fatoripay_wc_transaction_id', $charge['transaction']['code']);

			$status = $this->get_invoice_status($charge['transaction']['code']);

			$this->update_order_status($order->get_id(), $status);

			if ($this->method === 'e-wallet') {

				return array(
					'result'   => 'success',
					'redirect' => $charge['transaction']['payment-url'],
					'success'  => $charge['transaction']
				);

			} // end if;

			return array(
				'result'   => 'success',
				'redirect' => $this->gateway->get_return_url($order),
				'success'  => $charge['transaction']
			);

		} // end if;

		return array(
			'result'   => 'success',
			'redirect' => $this->gateway->get_return_url($order),
			'success'  => $charge['transaction']
		);

	} // process_payment

	/**
	 * Process order refund
	 *
	 * @param string $order_id WC Order ID.
	 * @param string $amount Amount to refund.
	 * @return array API Response
	 */
	public function process_refund($order_id, $amount) {

		$order = new WC_Order($order_id);

		$transaction_id = get_post_meta($order_id, '_fatoripay_wc_transaction_id');

		if (is_array($transaction_id) && isset($transaction_id['0'])) {

			$transaction_id = $transaction_id[0];

			if ($transaction_id) {

				$payload = array(
					'transaction-id' => $transaction_id,
					'amount'         => (float) $amount,
					'notify-url'     => WC()->api_request_url(get_class($this->gateway)),
					'reference'      => 'refund_' . $order_id,
					'test-mode'      => 0
				);

				if ($this->gateway->sandbox === 'yes') {

					$payload['test-mode'] = 1;

				} // end if;

				$charge = $this->do_request('/refunds', 'POST', $payload, 2);

				if (!isset($charge)) {

					return array(
						'result'   => 'fail',
						'redirect' => '',
						'success' => false
					);

				} // end if;

				if ($charge && isset($charge['errors'])) {

					return array(
						'result'   => 'fail',
						'redirect' => '',
						'success' => false
					);

				} // end if;

				if ($this->gateway->debug === 'yes' && isset($charge['refund-id'])) {

					$this->gateway->log->add($this->gateway->id, 'Order refunded successfully! Refund ID: ' . $charge['refund-id']);

				} // end if;

				return true;

			} // end if;

		} // end if;

	} // end process_refund;

	/**
	 * Update order status.
	 *
	 * @param int $order_id WC Order ID
	 * @param string $status FatoriPay Status
	 * @return bool
	 */
	protected function update_order_status($order_id, $status) {

		$order = new WC_Order($order_id);

		$check_status = $status;

		if (is_array($status)) {

			$check_status = $status['status'];

		} // end if

		switch ($check_status) {
			case 'CANCELLED':
				$message = __('Transaction was cancelled by FatoriPay', 'fatoripay-woo');

				$order->update_status('failed', $message);

				break;

			case 'COMPLETE':
				$message = __('Transaction was paid and approved.', 'fatoripay-woo');

				$order->payment_complete();

				$order->add_order_note($message);

			  break;

			case 'CHARGEBACK':
				$message = __('An approved transaction was cancelled by the End User. Please consult your Account Manager for costs.', 'fatoripay-woo');

				$order->update_status('refunded', $message);

				break;

			case 'EXPIRED':
				$message = __('Payment date of transaction expired', 'fatoripay-woo');

				$order->update_status('failed', $message);

				break;

			case 'NOT-PAID':
				$message = 'Payment confirmation of transaction was not received';

				break;
			case 'UNDER-REVIEW':
			case 'PENDING':

				if ($this->method === 'bank-slip') {

					$message = __('FatoriPay: The customer generated a bank slip. Awaiting payment confirmation.', 'fatoripay-woo');

					$order->update_status('on-hold', $message);

				} else {

					$message = __('FatoriPay: Invoice paid. Waiting for the acquirer confirmation.', 'fatoripay-woo');

					$order->update_status('on-hold', $message);

				} // end if;

				break;
			case 'REFUNDED':
				$message = __('A partial or full refund was requested and accepted for the transaction', 'fatoripay-woo');

				$this->refund_order($order_id, $order->get_total());

				$order->update_status('refunded', $message);

				$this->send_email(
					sprintf(__( 'Invoice for order %s was refunded', 'fatoripay-woo' ), $order->get_order_number()),
					__('Invoice refunded', 'fatoripay-woo'),
					sprintf(__('Order %s has been marked as refunded by FatoriPay.', 'fatoripay-woo'), $order->get_order_number())
				);

				break;

			default:
				$message = 'Erro ao obter o status';

				break;

		} // end switch;

		if ($this->gateway->debug === 'yes') {

			$this->gateway->log->add($this->gateway->id, 'FatoriPay payment status for order ' . $order->get_order_number() . ' is now: ' . $message);

		} // end if;

		/**
		 * Allow custom actions when update the order status.
		 */
		do_action( 'fatoripay_woocommerce_update_order_status', $order, $status, $message);

		return $message;

	} // end update_order_status;

	/**
	 * Notification Handler
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function notification_handler() {

		@ob_clean();

		if (isset($_REQUEST['notification-type']) && $_REQUEST['notification-type'] === 'transaction') {

			header( 'HTTP/1.1 200 OK' );

			$transaction_code = sanitize_text_field($_REQUEST['transaction-code']);

			$transaction = $this->get_invoice_status($transaction_code);

			if (isset($transaction['order_id'])) {

				$order_id = intval($transaction['order_id']);

				if ($order_id) {

					$status = $transaction['status'];

					if ($status) {

						$this->update_order_status($order_id, $status);

						exit();

					} // end if;

				} // end if;

			} // end if;

		} // end if;

		wp_die(__('The request failed!', 'fatoripay-woo' ), __('The request failed!', 'fatoripay-woo' ), array('response' => 200));

	} // end notification_handler

	/**
	 * Refund order.
	 *
	 * @param  WC_Order $order Order data.
	 * @param  string $payment_id.
	 */
	public function refund_order($order_id, $amount) {

		if(empty($order_id)) {

			return false;

		} // end if;

		$order = wc_get_order($order_id);
		$total = $order->get_total();
		if($total != $amount) throw new Exception( __( "Can't do partial refunds", 'fatoripay-woo' ) );

		$transaction_id = get_post_meta( $order_id, '_transaction_id', true);

		$response = $this->do_request( 'invoices/'.$transaction_id.'/refund', 'POST', array() );

		if (is_object($response) && is_wp_error($response)) {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'WP_Error while trying to refund order'.$order_id.': '.
																											$response->get_error_message() );
			}
			return $response;
		} else if ( isset( $response['body'] ) && ! empty( $response['body'] ) ) {
			if ( 'yes' == $this->gateway->debug && isset( $response['body']['status'] ) && $response['body']['status'] == "refunded" ) {
				$this->gateway->log->add( $this->gateway->id, 'Order refunded successfully!' );
			}
			return true;
		}
	}

	/**
	 * Get FatoriPay installments based on the card brand.
	 *
	 * @since 2.0.0
	 *
	 * @param string $payload Instalmments payload
	 * @return mixed.
	 */
	public function get_fatoripay_installments($payload) {

		if (is_array($payload)) {

			$endpoint = '/card?' . http_build_query($payload);

			$response = $this->do_request($endpoint, 'GET', array(), 1);

			if (is_array($response) && isset($response['errors'])) {

				wp_send_json_error(array(
					'errors'       => $response['errors'],
					'installments' => ''
				));

			} // end if;

			$max_installments = $this->gateway->get_option('max_installments', 12);

			$installments = array();

			foreach($response['installments'] as $key_installment => $value_installment) {

				if ($value_installment['quantity'] > $max_installments) {

					unset($response['installments'][$key_installment]);

				} else {

					$total_amount = wc_price($value_installment['totalAmount']);

					$installment_amount = wc_price($value_installment['installmentAmount']);

					$installments[$value_installment['quantity']] = '<option value="' . $value_installment['quantity'] . '">' . sprintf(__('%1s x %2s (Total: %3s)', 'fatoripay-woo'), $value_installment['quantity'], $installment_amount, $total_amount) . '</option>';

				} // end if;

			} // end foreach;

			wp_send_json_success(array(
				'errors'       => '',
				'installments' => $installments,
				'brand'        => $response['bin']['brand']
			));

		} // end ;

	} // end if;

	/**
		* Get the redirect payload from the order.
 		*
		* @since 2.0.0
		*
		* @param WC_Order $order Woocommerce Order
		* @param string   $method Payment Method
 		* @return array Redirect Payload.
		*/
	public function get_fatoripay_payload_redirect($order) {

			$secret = $this->gateway->secret_key;

			$sandbox = '';

			if ($this->gateway->sandbox === 'yes') {

				$sandbox = 1;

			} // end if;

			$order_id = $order->get_id();

			$store_id = $this->gateway->merchant_id;

			$return_url = $order->get_checkout_order_received_url();

			$notify_url = WC()->api_request_url(get_class($this->gateway));

			$total = (float) $order->get_total();

			$amount = (float) number_format($total, 2, '', '');

			$currency_code = $order->get_currency();

			/**
			 * Hash information
			 */
			$data = $store_id . $notify_url . $order_id . $amount . $currency_code;

			$hash_key = hash_hmac('sha256', $data, $secret);

			$document = $order->get_meta('_billing_cpf');

			if (!$document) {

				$document = $order->get_meta('_billing_cnpj');

			} // end if;

			$groups = array();

			$payment_id = '';

			if ($this->method !== 'pix') {

				$groups = $this->get_redirect_group_options();

				$groups = implode(',', $groups);

			} else {

				/**
				 * ID hardcoded for Pix payment.
				 */
				$payment_id = '229';

			} // end if;

			$description = '';

			$cart_items = $order->get_items();

			foreach ($cart_items as $item_key => $item_value) {

				if (!$description) {

					$description = preg_replace('/[<>\-&%\/]/', '', $item_value['name']);

				} // end if;

			} // end foreach;

			$country = $order->get_billing_country();

			$language = $this->get_country_language($country);

			$payload = array(
				'store_id'           => $store_id,
				'return'             => $return_url,
				'notify_url'         => $notify_url,
				'currency_code'      => $currency_code,
				'order_id'           => $order->get_id(),
				'order_description'  => $description,
				'amount'             => $amount,
				'hash_key'           => $hash_key,
				'client_email'       => $order->get_billing_email(),
				'client_name'        => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
				'client_zip_code'    => $order->get_billing_postcode(),
				'client_street'      => $order->get_shipping_address_1(),
				'client_suburb'      => $order->get_meta('_billing_neighborhood'),
				'client_number'      => $order->get_meta('_billing_number'),
				'client_city'        => $order->get_billing_city(),
				'client_state'       => $order->get_billing_state(),
				'client_country'     => $country,
				'client_telephone'   => $order->get_billing_phone(),
				'client_cpf'         => $document,
				'language'           => $language,
				'country_payment'    => $country,
				'test_mode'          => $sandbox,
				'mobile'             => wp_is_mobile() ? '1' : '0',
				'payment_group'      => $groups,
				'payment_id'         => $payment_id
			);

			return $payload;

	} // end get_fatoripay_payload_redirect;

	/**
	 * Get the redirect language based on the country.
	 *
	 * @param string $country
	 * @return string Country language.
	 */
	public function get_country_language($country) {

		$language = '';

		$currency = '';

		switch ($country) {
			case 'AR':
			case 'BO':
			case 'CL':
			case 'CO':
			case 'CR':
			case 'PT':
			case 'ES':
			case 'GT':
			case 'MX':
			case 'PE':
			case 'PY':
				$language = 'es_ES';

			break;
			case 'TR':
				$language = 'tr_TR';

			break;
			case 'US':
			case 'EC':
				$language = '';

			break;
			default:
				$language = 'pt_BR';
			break;

		} // end switch;

		return $language;

	} // end get_country_language;

 	/**
		* Get the API payload from the order.
 		*
		* @since 2.0.0
		*
		* @param WC_Order $order Woocommerce Order
		* @param string   $method Payment Method
 		* @return array Payload.
		*/
	public function get_fatoripay_payload($order) {

		$payload = array();

		$payload['transaction'] = $this->get_payload_transaction($order);

		$payload['charge'][] = $this->get_payload_charge($order);

		$payload['payer'] = $this->get_payload_payer($order);

		if ($order->get_shipping_postcode()) {

			$payload['shipping'] = $this->get_payload_shipping($order);

		} // end if;

		$payload['cart'] = $this->get_payload_cart($order);

		return $payload;

	} // end get_fatoripay_payload;

	/**
	 * Create the section Transaction in the FatoriPay_ payload.
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array Payload with the transaction section added.
	 */
	public function get_payload_transaction($order) {

		$transaction = array();

		$transaction['reference'] = $this->prefix . $order->get_id();

		$transaction['country'] = $order->get_billing_country();

		$transaction['currency'] = $order->get_currency();

		$transaction['checkout-type'] = 'direct';

		if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {

			$notification_url = WC()->api_request_url(get_class($this->gateway));

		} else {

			global $woocommerce;

			$notification_url = $woocommerce->api_request_url(get_class($this->gateway));

		} // end if;

		$transaction['notification-url'] = $notification_url;

		$transaction['language'] = 'pt-BR';

		if ($this->method == 'e-wallet') {

			$transaction['redirect-urls']['success'] = $order->get_checkout_order_received_url();

			$transaction['redirect-urls']['fail'] = $order->get_checkout_order_received_url();

		} // end if;

		return $transaction;

	} // end get_payload_transaction;

	/**
	 * Create the section Charge in the FatoriPay_ payload.
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array Payload with the charge section added.
	 */
	public function get_payload_charge($order) {

		$charge = array();

		$total = (float) $order->get_total();

		$charge['amount'] = (float) number_format($total, 2, '.', '');

		switch ($this->method) {
			case 'credit-card':

				$charge['payment-method']['type'] = 'credit-card';

				$charge['payment-method']['sub-type'] = wcbc_request('fatoripay_card_brand', '');;

				$charge['payment-info']['installments'] = (int) wcbc_request('fatoripay_card_installments', 1);

				$charge['payment-info']['token'] = wcbc_request('fatoripay_card_token', '');

				if (!$charge['payment-info']['token'] || !$charge['payment-method']['sub-type']) {

					wc_add_notice(__('Your credit card data is invalid. Try again!.', 'fatoripay-woo'), 'error');

					return;

				} // end if;

				break;
			case 'e-wallet':

				$charge['payment-method']['type'] = 'e-wallet';

				$charge['payment-method']['sub-type'] = wcbc_request('fatoripay_wallet', 'pagseguro');

				break;
			case 'bank-slip':

				$charge['payment-method']['type'] = 'postpay';

				$charge['payment-method']['sub-type'] = 'boleto';

				break;

		} // end switch;

		return $charge;

	} // end get_payload_charge;

	/**
	 * Create the section payer in the FatoriPay_ payload.
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array Payload with the payer section added.
	 */
	public function get_payload_payer($order) {

		$payer = array();

		$payer['name'] = $order->get_billing_first_name().' '.$order->get_billing_last_name();

		$payer['email'] = $order->get_billing_email();

		$birth_date = preg_replace('/^([\d]{2})\/([\d]{2})\/([\d]{4})$/', '$3-$2-$1', $order->get_meta('_billing_birthdate'));

		if ($birth_date) {

			$payer['birth-date'] = $birth_date;

		} // end if;

		$billing_phone = wcbc_clean_input_values($order->get_billing_phone());

		if ($billing_phone) {

			$payer['phone-number'] = '+55' . $billing_phone;

		} // end if;

		$cpf = $order->get_meta('_billing_cpf');

		if ($cpf) {

			$payer['document'] = array(
				'type'   => 'CPF',
				'number' => wcbc_clean_input_values($cpf)
			);

		} else {

			$payer['document'] = array(
				'type'   => 'CNPJ',
				'number' => wcbc_clean_input_values($order->get_meta('_billing_cnpj'))
			);

		} // end if;

		//$payer['ip'] = $this->get_client_ip(true);

		$payer['address'] = array();

		$payer['address']['street'] = $order->get_billing_address_1();

		$payer['address']['number'] = $order->get_meta('_billing_number');

		$payer['address']['complement'] = $order->get_shipping_address_2();

		$payer['address']['district'] = $order->get_meta('_billing_neighborhood');

		$payer['address']['zip-code'] = wcbc_clean_input_values($order->get_billing_postcode());

		$payer['address']['city'] = $order->get_billing_city();

		$payer['address']['state'] = $order->get_billing_state();

		$payer['address']['country'] = $order->get_billing_country();

		foreach ($payer['address'] as $key_address => $value_address) {

			if (!$value_address && $key_address !== 'complement' ) {

				$field = ucwords($key_address);

				if ($key_address === 'district') {

					$field = __('Neighborhood', 'fatoripay-woo');

				} // end if;

				$message = sprintf(__('%s invalid.', 'fatoripay-woo'), $field);

				wc_add_notice($message, 'error');

			} // end if;

		} // end foreach;

		return $payer;

	} // end get_payload_payer;

	/**
	 * Create the section shippíng in the FatoriPay_ payload.
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array Payload with the shipping section added.
	 */
	public function get_payload_shipping($order) {

		$shipping = array();

		$shipping['cost'] = 0;

		$shippingc['address'] = array();

		$shipping['address']['street'] = $order->get_shipping_address_1();

		$shipping['address']['number'] = $order->get_meta('_shipping_number');

		$shipping['address']['complement'] = $order->get_shipping_address_2();

		$shipping['address']['district'] = $order->get_meta('_shipping_neighborhood');

		$shipping['address']['zip-code'] = wcbc_clean_input_values($order->get_shipping_postcode());

		$shipping['address']['city'] = $order->get_shipping_city();

		$shipping['address']['state'] = $order->get_shipping_state();

		$shipping['address']['country'] = $order->get_shipping_country();

		foreach ($shipping['address'] as $key_address => $value_address) {

			if (!$value_address && $key_address !== 'complement' ) {

				$field = ucwords($key_address);

				if ($key_address === 'district') {

					$field = __('Neighborhood', 'fatoripay-woo');

				} // end if;

				$message = sprintf(__('%s invalid.', 'fatoripay-woo'), $field);

				wc_add_notice($message, 'error');

			} // end if;

		} // end foreach;

		return $shipping;

	} // end get_payload_shipping;

	/**
	 * Get API payload cart.
	 *
	 * @param WC_Order $order Woocommerce Order
	 * @return array Payload with cart included.
	 */
	public function get_payload_cart($order) {

		$cart_items = $order->get_items();

		$data = array();

		$counter = 0;

		foreach ($cart_items as $item_key => $item_value) {

			$data[$counter]['quantity'] = $item_value['qty'];

			$data[$counter]['description'] = preg_replace('/[<>\-&%\/]/', '', $item_value['name']);

			$data[$counter]['unit-price'] = (float) $item_value['line_subtotal'];

			if ($item_value['qty'] > 1) {

				$data[$counter]['unit-price'] = $data[$counter]['unit-price'] / $item_value['qty'];

			} // end if;

			$virtual = get_post_meta($item_value['product_id'], '_virtual', true);

			$data[$counter]['type'] = 'physical';

			if ($virtual) {

				$data[$counter]['type'] = 'digital';

			} // end if;

			$counter = $counter + 1;

		} // end foreach;

		return $data;

	} // end get_payload_cart;

	/**
	 * Do requests in the FatoriPay API.
	 *
	 * @param  string $endpoint API Endpoint.
	 * @param  string $method   Request method.
	 * @param  array  $data     Request data.
	 * @param  array  $headers  Request headers.
	 * @return array  Request response.
	 */
	protected function do_request($endpoint, $method = 'POST', $data = array(), $version = '2') {

		if (isset($data) && $data) {

			$data = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

			$data_hash = md5($data);

			$data_hash_hmac = hash_hmac(
				'sha256',
				$endpoint . $data_hash,
				$this->gateway->secret_key
			);

		} else {

			$data_hash_hmac = hash_hmac(
				'sha256',
				$endpoint,
				$this->gateway->secret_key
			);

		} // end if;

		$authorization_hash = $this->gateway->merchant_id . ':' . $data_hash_hmac;

		$params = array(
			'method'    => $method,
			'timeout'   => 60,
			'headers'    => array(
				'Accept'        => 'application/vnd.boacompra.com.v' . $version . '+json; charset=UTF-8',
				'Content-Type'  => 'application/json',
				'Authorization' => $authorization_hash,
			)
		);

		if ($data) {

			$params['headers']['Content-MD5'] = $data_hash;

			$params['body'] = $data;

		} // end ;

		$response = wp_remote_request($this->get_api_url() . $endpoint, $params);

		$body = json_decode(wp_remote_retrieve_body($response), true);

		return $body;

	} // end do_request;

	/**
	 * Redirect checkout.
	 *
	 * @param array $payload Payload params.
	 * @return void
	 */
	public function do_hosted_request($order) {

		$payload = $this->get_fatoripay_payload_redirect($order);

		$html = '<form method="POST" name="hostedForm" action="https://billing.boacompra.com/payment.php" >';

		foreach ($payload as $key => $value) {

			if (is_array($value)) {

				foreach($value as $key_value => $value_value) {

					if (is_array($value_value)) {

						foreach($value_value as $key_value2 => $value_value2) {

							$html .= '<input type="hidden" name="'.$key_value2.'" id="'.$key_value2.'" value="'.$value_value2.'">';

						} // end if;

					} else {

						$html .= '<input type="hidden" name="'.$key_value.'" id="'.$key_value.'" value="'.$value_value.'">';

					} // end ;

				} // end if;

			} else {

				$html .= '<input type="hidden" name="'.$key.'" id="'.$key.'" value="'.$value.'">';

			} // end ;

		} // end foreach.

		$html .= '</form>';

		$html .= '<script>';

		$html .= 'document.hostedForm.submit();';

		$html .= '</script>';

		return $html;

	} // end do_hosted_request;

	/**
	 * Request information about a specific transaction.
	 *
	 * @param string $transaction_id Transaction ID.
	 * @param boolean $is_refund If is a refund
	 * @return mixed Transaction response.
	 */
	public function get_invoice_status($transaction_id = '', $is_refund = false) {

		if (empty($transaction_id)) {

			return;

		} // end if;

		$endpoint = '/transactions/' . $transaction_id;

		$request = $this->do_request($endpoint, 'GET', array(), 1);

		if (isset($request) && isset($request['errors'])) {

			if ($this->gateway->debug == 'yes') {

				$this->gateway->log->add( $this->gateway->id, 'Error: Getting invoice status from FatoriPay. Invoice ID: ' . $transaction_id );

			} // end if;

			return;

		} // end if;

		$status = $request['transaction-result']['transactions'][0]['status'];

		if ($is_refund) {

			$refunds = $request['transaction-result']['transactions'][0]['refunds'];

			$last_refund = array_pop($refunds);

			return array(
				'order_id'    => str_replace($this->prefix, '', $request['transaction-result']['transactions'][0]['order-id']),
				'status'      => $status,
				'refunds'     => $refunds,
				'last_refund' => $last_refund
			);

		} else {

			return array(
				'order_id'    => str_replace($this->prefix, '', $request['transaction-result']['transactions'][0]['order-id']),
				'status'      => $status,
			);

		} // end if;

	} // end get_invoice_status;

	/**
	 * Build the API params from an array.
	 *
	 * @param  array  $data Data to build
	 * @param  string $prefix
	 * @return string API builded params.
	 */
	protected function build_api_params($data, $prefix = null) {

		if (!is_array($data)) {

			return $data;

		} // end if;

		$params = array();

		foreach ($data as $key => $value) {

			if (is_null($value)) {

				continue;

			} // end if;

			if ($prefix && $key && !is_int($key)) {

				$key = $prefix . '[' . $key . ']';

			} elseif ($prefix) {

				$key = $prefix . '[]';

			} // end if;

			if (is_array($value)) {

				$params[] = $this->build_api_params($value, $key);

			} else {

				$params[] = $key . '=' . urlencode($value);

			} // end if;

		} // end foreach;

		return implode( '&', $params );

	} // end build_api_params;

	/**
	 * Retrieve the group payment options for the redirect checkout;
	 *
	 * @return array Payment group.
	 */
	public function get_redirect_group_options() {

		$group_card = $this->gateway->get_option('card');

		if ($group_card !== 'no') {

			$group_card = 'card';

		} // end if;

		$group_cash = $this->gateway->get_option('cash');

		if ($group_cash !== 'no') {

			$group_cash = 'cash';

		} // end if;

		$group_wallet = $this->gateway->get_option('wallet');

		if ($group_wallet !== 'no') {

			$group_wallet = 'online wallet';

		} // end if;

		$group_transfer = $this->gateway->get_option('transfer');

		if ($group_transfer !== 'no') {

			$group_transfer = 'transfer';

		} // end if;

		return array(
			'card'          => $group_card,
			'cash'          => $group_cash,
			'online wallet' => $group_wallet,
			'transfer'      => $group_transfer,
		);

	} // end get_redirect_group_options;

	/**
	 * Convert errors into html strings.
	 *
	 * @param array $errors API Return errors.
	 * @return array Renderes errors.
	 */
	public function convert_errors($errors) {

		foreach ($errors as $error_key => $error_value) {


		} // end foreach;

	} // end convert_errors

} // end WC_FatoriPay_API;
