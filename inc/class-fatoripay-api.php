<?php

/**
 * WC FatoriPay API Class.
 */
class FatoriPay_API {

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url;

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
			$this->api_url = 'https://api-sandbox.fatoripay.com.br';
		} else {
			$this->api_url = 'https://api.fatoripay.com.br';
		}

		$this->gateway = $gateway;
		$this->method  = $method;
		$this->prefix  = $prefix;
	}

	/**
	 * Get API URL.
	 *
	 * @return string
	 */
	public function get_api_url() {
		return $this->api_url;
	}

	/**
	 * Get WooCommerce return URL.
	 *
	 * @return string
	 */
	protected function get_wc_request_url() {
		global $woocommerce;
		return $woocommerce->api_request_url(get_class($this->gateway));
	}

	/**
	 * Get the settings URL.
	 *
	 * @return string
	 */
	public function get_settings_url() {
		return admin_url('admin.php?page=woocommerce_settings&tab=payment_gateways&section=' . get_class( $this->gateway));
	}

	/**
	 * Get order total.
	 *
	 * @return float
	 */
	public function get_order_total() {

		global $woocommerce;

		$order_total = 0;

		$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

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
		}

		return $order_total;

	}

	/**
	 * Value in cents.
	 *
	 * @param  float $value
	 * @return int
	 */
	protected function get_cents($value) {
		return number_format( $value, 2, '', '' );
	}

	/**
	 * Only numbers.
	 *
	 * @param  string|int $string
	 * @return string|int
	 */
	protected function only_numbers($string) {
		return preg_replace('([^0-9])', '', $string);
	}

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
		}

	}

	/**
	 * Send email notification.
	 *
	 * @param string $subject Email subject.
	 * @param string $title   Email title.
	 * @param string $message Email message.
	 */
	public function send_email($subject, $title, $message) {

		global $woocommerce;
		$mailer = $woocommerce->mailer();
		$mailer->send(get_option('admin_email'), $subject, $mailer->wrap_message($title, $message));

	}

	/**
	 * Process Payment
	 *
	 * @param string $order_id WC Order ID
	 * @return array API Response
	 */
	public function process_payment($order_id) {

		$order  = new WC_Order($order_id);

		$payload = $this->get_fatoripay_payload($order, $_POST);

		$charge = $this->do_request('/transactions', 'POST', $payload, 2);

		if ($charge && isset($charge['errors'])) {

			return array(
				'result'   => 'fail',
				'redirect' => '',
				'success' => false
			);

		}

		if (!isset($charge)) {

			return array(
				'result'   => 'fail',
				'redirect' => '',
				'success' => false
			);

		}

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

			}

			return array(
				'result'   => 'success',
				'redirect' => $this->gateway->get_return_url($order),
				'success'  => $charge['transaction']
			);

		}

		return array(
			'result'   => 'success',
			'redirect' => $this->gateway->get_return_url($order),
			'success'  => $charge['transaction']
		);

	}

	/**
	 * Process order refund
	 *
	 * @param string $order_id WC Order ID.
	 * @param string $amount Amount to refund.
	 * @return array API Response
	 */
	public function process_refund($order_id, $amount) {

		global $woocommerce;

		$order = new WC_Order($order_id);

		$transaction_id = get_post_meta($order_id, '_fatoripay_wc_transaction_id');

		if (is_array($transaction_id) && isset($transaction_id['0'])) {

			$transaction_id = $transaction_id[0];

			if ($transaction_id) {

				$payload = array(
					'transaction-id' => $transaction_id,
					'amount'         => (float) $amount,
					'notify-url'     => $woocommerce->api_request_url(get_class($this->gateway)),
					'reference'      => 'refund_' . $order_id,
					'test-mode'      => 0
				);

				if ($this->gateway->sandbox === 'yes') {

					$payload['test-mode'] = 1;

				}

				$charge = $this->do_request('/refunds', 'POST', $payload, 2);

				if (!isset($charge)) {

					return array(
						'result'   => 'fail',
						'redirect' => '',
						'success' => false
					);

				}

				if ($charge && isset($charge['errors'])) {

					return array(
						'result'   => 'fail',
						'redirect' => '',
						'success' => false
					);

				}

				if ($this->gateway->debug === 'yes' && isset($charge['refund-id'])) {

					$this->gateway->log->add($this->gateway->id, 'Order refunded successfully! Refund ID: ' . $charge['refund-id']);

				}

				return true;

			}
		}
	}

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

		}

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

				}

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

		}

		if ($this->gateway->debug === 'yes') {

			$this->gateway->log->add($this->gateway->id, 'FatoriPay payment status for order ' . $order->get_order_number() . ' is now: ' . $message);

		}

		/**
		 * Allow custom actions when update the order status.
		 */
		do_action( 'fatoripay_woocommerce_update_order_status', $order, $status, $message);

		return $message;

	}

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

					}

				}

			}

		}

		wp_die(__('The request failed!', 'fatoripay-woo' ), __('The request failed!', 'fatoripay-woo' ), array('response' => 200));

	}

	/**
	 * Refund order.
	 *
	 * @param  WC_Order $order Order data.
	 * @param  string $payment_id.
	 */
	public function refund_order($order_id, $amount) {

		if(empty($order_id)) {

			return false;

		}

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
		* Get the API payload from the order.
 		*
		* @since 2.0.0
		*
		* @param WC_Order $order Woocommerce Order
		* @param string   $method Payment Method
 		* @return array Payload.
		*/
	public function get_fatoripay_payload($order) {

		global $woocommerce;

		$payload = [
			'ref' => '',
			'due_date' => '',
			'description' => '',
			'discount_amount' => 0,
			'tax_amount' => 0,
			'customer' => $this->getCustomerPayload($order),
			'items' => $this->getItemsPayload($order),
			'payable_with' => [
				'boleto' => true,
				'pix' => true,
				'credit_card' => true,
			],
			'notification_url' => $woocommerce->api_request_url(get_class($this->gateway)),
			'return_url' => $order->get_checkout_order_received_url(),
		];

		return $payload;

	}

	/**
	 * Get customer payload.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array Payload with the payer section added.
	 */
	public function getCustomerPayload($order) {

		$cpfOrCnpj = $order->get_meta('_billing_cpf');
		if (empty($cpfOrCnpj)) {
			$cpfOrCnpj = $order->get_meta('_billing_cnpj');
		}

		$customer = [
			'name' => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
			'email' => $order->get_billing_email(),
			'cellphone' => wcbc_clean_input_values($order->get_billing_phone()),
			'cpfcnpj' => wcbc_clean_input_values($cpfOrCnpj),
			'address' => [
				'street' => $order->get_billing_address_1(),
				'number' => $order->get_meta('_billing_number'),
				'complement' => $order->get_shipping_address_2(),
				'neighborhood' => $order->get_meta('_billing_neighborhood'),
				'city' => $order->get_billing_city(),
				'state' => $order->get_billing_state(),
				'zipcode' => wcbc_clean_input_values($order->get_billing_postcode()),
			]
		];

		return $customer;

	}

	/**
	 * Get items payload.
	 *
	 * @param WC_Order $order Woocommerce Order
	 * @return array Payload with cart included.
	 */
	public function getItemsPayload($order) {

		$cart_items = $order->get_items();

		$items = [];

		$counter = 0;

		foreach ($cart_items as $item_key => $item_value) {
			$items[$counter] = [
				'name' => $item_value['name'],
				'description' => $item_value['name'],
				'quantity' => $item_value['qty'],
				'price' => $item_value['line_subtotal'],
			];

			$counter = $counter + 1;
		}

		return $items;

	}

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

		}

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

		}
		$response = wp_remote_request($this->get_api_url() . $endpoint, $params);

		$body = json_decode(wp_remote_retrieve_body($response), true);

		return $body;

	}

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

		}

		$endpoint = '/transactions/' . $transaction_id;

		$request = $this->do_request($endpoint, 'GET', array(), 1);

		if (isset($request) && isset($request['errors'])) {

			if ($this->gateway->debug == 'yes') {

				$this->gateway->log->add( $this->gateway->id, 'Error: Getting invoice status from FatoriPay. Invoice ID: ' . $transaction_id );

			}

			return;

		}

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

		}

	}

}
