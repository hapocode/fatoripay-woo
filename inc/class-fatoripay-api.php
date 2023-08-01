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
			//$this->api_url = 'https://sandbox-api.fatoripay.com.br/api/v1/';
			$this->api_url = 'http://localhost:8001/api/v1/';
		} else {
			$this->api_url = 'https://api.fatoripay.com.br/api/v1/';
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

		$payload = $this->getPayload($order, $_POST);

		$charge = $this->doRequest('invoices/create', 'POST', $payload);

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

		if ($charge['link']) {

			update_post_meta($order->get_id(), '_fatoripay_wc_transaction_data', $charge);
			update_post_meta($order->get_id(), '_fatoripay_wc_transaction_id', $charge['id']);

			return array(
				'result'   => 'success',
				'redirect' => $charge['link'],
				'success'  => $charge
			);

		} else {

			return array(
				'result'   => 'fail',
				'redirect' => '',
				'success' => false
			);

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

		switch ($status) {
			case 'canceled':
				$message = __('Transaction was cancelled by FatoriPay', 'fatoripay-woo');
				$order->update_status('failed', $message);
				break;

			case 'paid':
				$message = __('Transação foi paga e aprovada.', 'fatoripay-woo');
				$order->payment_complete();
				$order->add_order_note($message);

			  break;

			case 'chargeback':
				$message = __('Transação foi estornada.', 'fatoripay-woo');
				$order->update_status('refunded', $message);

				break;

			case 'overdue':
				$message = __('A fatura expirou.', 'fatoripay-woo');
				$order->update_status('failed', $message);

				break;

			case 'unpaid':
				$message = __('Transação não foi paga.', 'fatoripay-woo');
				$order->update_status('on-hold', $message);
				break;

		}

		if ($this->gateway->debug === 'yes') {
			$this->gateway->log->add($this->gateway->id, 'FatoriPay Status ' . $order->get_order_number() . ' e agora: ' . $message);
		}

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

		if (isset($_REQUEST['id']) && isset($_REQUEST['status'])) {

			header( 'HTTP/1.1 200 OK' );
			$order_id = intval(str_replace($_REQUEST['ref'], 'WC-', ''));

			if ($order_id) {

				$this->update_order_status($order_id, $_REQUEST['status']);
				exit();

			}
		}

		wp_die(__('The request failed!', 'fatoripay-woo' ), __('The request failed!', 'fatoripay-woo' ), array('response' => 200));

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
	public function getPayload($order) {

		global $woocommerce;

		$payload = [
			'ref' => 'WC-' . $order->get_id(),
			'due_date' => date('d-m-Y', strtotime('+1 day')),
			'description' => 'Pedido #' . $order->get_id(),
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
			'redirect_url' => $order->get_checkout_order_received_url(),
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
	protected function doRequest($endpoint, $method = 'POST', $data = array()) {

		$bearToken = $this->getBearerToken();

		$params = array(
			'method'    => $method,
			'headers'    => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $bearToken['access_token'],
			)
		);

		if ($data) {
			$params['body'] = json_encode($data);
		}

		$response = wp_remote_request($this->get_api_url() . $endpoint, $params);
		$body = json_decode(wp_remote_retrieve_body($response), true);
		error_log(print_r($body, true));
		return $body;

	}

	protected function getBearerToken() {

		$params = array(
			'method'    => 'POST',
			'headers'    => array(
				'Content-Type'  => 'application/json',
				'client-id' => $this->gateway->client_id,
				'client-secret' => $this->gateway->client_secret,
			),
			'body' => json_encode([
				'username' => $this->gateway->username,
			])
		);

		$response = wp_remote_request($this->get_api_url() . 'auth/login', $params);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		return $body;

	}

}
