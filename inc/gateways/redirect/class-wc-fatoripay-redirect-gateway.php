<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FatoriPay Payment Redirect Gateway class.
 *
 * Extended by individual payment gateways to handle payments.
 *
 * @class   WC_FatoriPay_Redirect_Gateway
 * @extends WC_Payment_Gateway
 * @version 2.0.0
 * @author  FatoriPay
 */
class WC_FatoriPay_Redirect_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		global $woocommerce;

		$this->id                   = 'fatoripay-redirect';
		$this->icon                 = apply_filters('fatoripay_woocommerce_redirect_icon', '');
		$this->method_title         = __('FatoriPay - Redirect – Hosted Checkout ', 'fatoripay-woo' );
		$this->method_description   = __('Accept over 140 payments on FatoriPay’s payment page.', 'fatoripay-woo' );
		$this->has_fields           = true;
		$this->view_transaction_url = 'https://billing-partner.boacompra.com/transactions_test.php/%s';
		$this->supports             = array(
			'subscriptions',
			'products',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'subscription_date_changes',
			'refunds'
		);

		/**
		 * Load the form fields.
		 */
		$this->init_form_fields();

		/**
		 * Load the settings.
		 */
		$this->init_settings();

		/**
		 * Options.
		 */
		$this->title            = $this->get_option('title');
		$this->description      = $this->get_option('description');
		$this->merchant_id      = $this->get_option('merchantid');
		$this->secret_key       = $this->get_option('secretkey');
		$this->ignore_due_email = $this->get_option('ignore_due_email');
		$this->deadline         = $this->get_option('deadline');
		$this->send_only_total  = $this->get_option('send_only_total', 'no');
		$this->prefix           = $this->get_option('invoice_prefix', 'wc');
		$this->sandbox          = $this->get_option('environment', 'no');
		$this->debug            = $this->get_option('debug');

		/**
		 * Active logs.
		 */
		if ($this->debug === 'yes') {

			if (class_exists('WC_Logger')) {

				$this->log = new WC_Logger();

			} else {

				$this->log = $woocommerce->logger();

			} // end if;

		} // end if;

		$this->api = new WC_FatoriPay_API($this, 'redirect', $this->sandbox, $this->prefix);

		/**
		 * Actions
		 */
		add_action('woocommerce_api_wc_fatoripay_redirect_gateway', array($this, 'notification_handler'));

		add_action('woocommerce_api_wc_fatoripay_hosted_request', array($this, 'redirect_checkout'));

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

		if ($this->settings['enabled'] === 'yes') {

			add_action('admin_notices', array($this, 'dependencies_notices'));

		} // end if;

	} // end __construct;

	/**
	 * Returns a value indicating the the Gateway is available or not.
	 *
	 * @return bool
	 */
	public function is_available() {

		// Test if is valid for use.
		$api = !empty($this->merchant_id) && !empty( $this->secret_key);

		$available = parent::is_available() && $api;

		return $available;

	} // end is_available;

	/**
	 * Dependecie notice.
	 *
	 * @return mixed.
	 */
	public function dependencies_notices() {

		if (!class_exists('Extra_Checkout_Fields_For_Brazil')) {

			require_once dirname(WC_PGI_PLUGIN_FILE) . '/views/html-notice-ecfb-missing.php';

		} // end if;

	} // end if;

	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @return void.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __('Enable/Disable', 'fatoripay-woo'),
				'type'    => 'checkbox',
				'label'   => __('Enable redirect payments with FatoriPay', 'fatoripay-woo'),
				'default' => 'no'
			),
			'title'           => array(
				'title'       => __('Title', 'fatoripay-woo'),
				'type'        => 'text',
				'description' => __('Payment method title seen on the checkout page.', 'fatoripay-woo'),
				'default'     => __( 'FatoriPay', 'fatoripay-woo')
			),
			'description'     => array(
				'title'       => __('Description', 'fatoripay-woo' ),
				'type'        => 'textarea',
				'description' => __('Payment method description seen on the checkout page.', 'fatoripay-woo'),
				'default'     => __('Confirm your order to be redirected to the payment page.', 'fatoripay-woo')
			),
			'integration'    => array(
				'title'       => __('Integration settings', 'fatoripay-woo'),
				'type'        => 'title',
				'description' => ''
			),
			'merchantid'      => array(
				'title'             => __('Client ID', 'fatoripay-woo'),
				'type'              => 'text',
				'description'       => sprintf(__( 'Your FatoriPay account\'s unique store ID, found in %s.', 'fatoripay-woo' ), '<a href="https://myaccount.boacompra.com/" target="_blank">' . __( 'My Account', 'fatoripay-woo' ) . '</a>'),
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required'
				)
			),
			'secretkey'      => array(
				'title'             => __('SecretKey', 'fatoripay-woo'),
				'type'              => 'text',
				'description'       => sprintf(__('SecretKey can be found/created in %s.', 'fatoripay-woo' ), '<a href="https://myaccount.boacompra.com/" target="_blank">' . __( 'My Account', 'fatoripay-woo' ) . '</a>'),
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required'
				)
			),
			'hosted_options' => array(
				'title'       => __('Payment options available in the redirected checkout.', 'fatoripay-woo'),
				'type'        => 'title',
				'description' => '',
			),
			'card'           => array(
				'title'   => __('Credit Card', 'fatoripay-woo'),
				'type'    => 'checkbox',
				'label'   => __('Enable Credit Card for Hosted Checkout', 'fatoripay-woo'),
				'default' => 'yes',
		  ),
		  'cash'           => array(
				'title'   => __('Cash', 'fatoripay-woo'),
				'type'    => 'checkbox',
				'label'   => __('Enable Cash for Hosted Checkout', 'fatoripay-woo'),
				'default' => 'yes',
		  ),
		  'wallet'         => array(
				'title'   => __('E-Wallet', 'fatoripay-woo'),
				'type'    => 'checkbox',
				'label'   => __('Enable E-Wallet for Hosted Checkout', 'fatoripay-woo'),
				'default' => 'yes',
		  ),
		  'transfer'       => array(
				'title'   => __('Transfer', 'fatoripay-woo'),
				'type'    => 'checkbox',
				'label'   => __('Enable Transfer for Hosted Checkout', 'fatoripay-woo'),
				'default' => 'yes',
		  ),
			'behavior'       => array(
				'title'       => __( 'Integration behavior', 'fatoripay-woo' ),
				'type'        => 'title',
				'description' => ''
			),
			'invoice_prefix' => array(
				'title'       => __('Invoice Prefix', 'fatoripay-woo'),
				'type'        => 'text',
				'description' => __('Please enter a prefix for your invoice code.', 'fatoripay-woo'),
				'placeholder' => '',
				'default'     => 'WC',
				'placeholder' => 'WC',
			),
			'environment'    => array(
				'title'       => __( 'FatoriPay sandbox', 'fatoripay-woo' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable FatoriPay sandbox', 'fatoripay-woo' ),
				'default'     => 'no',
				'description' => __( 'Used to test payments.', 'fatoripay-woo' )
			),
			'debug'         => array(
				'title'       => __( 'Debugging', 'fatoripay-woo' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'fatoripay-woo' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log FatoriPay events, such as API requests, for debugging purposes. The log can be found in %s.', 'fatoripay-woo' ), FatoriPay_Woo::get_log_view($this->id))
			)
		);

	} // end init_form_fields;

	/**
	 * Payment fields.
	 */
	public function payment_fields() {

		if ($description = $this->get_description()) {

			echo esc_html($description);

		} // end if;

		wc_get_template(
			'redirect/checkout-instructions.php',
			array(),
			'woocommerce/boacompra/',
			FatoriPay_Woo::get_templates_path()
		);

	} // end payment_fields;

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id Order ID.
	 * @return array Redirect.
	 */
	public function process_payment($order_id) {

		return $this->api->process_payment($order_id);

	} // end process_payment;

	/**
	 * Thank You page message.
	 *
	 * @param  int $order_id Order ID.
	 * @return void.
	 */
	public function thankyou_page($order_id) {

		wc_get_template(
			'redirect/payment-instructions.php',
			array(),
			'woocommerce/boacompra/',
			FatoriPay_Woo::get_templates_path()
		);

	} // end thankyou_page;

	/**
	 * Handles the notification posts from FatoriPay Gateway.
	 *
	 * @return void
	 */
	public function notification_handler() {

		$this->api->notification_handler();

	} // end notification_handler;

	/**
	 * Process refund.
	 *
	 * @param  int        $order_id Order ID.
	 * @param  float|null $amount Refund amount.
	 * @param  string     $reason Refund reason.
	 * @return boolean True or false based on success, or a WP_Error object.
	 */
	public function process_refund($order_id, $amount = null, $reason = '') {

		return new \WP_Error('error', __('Refunds for this payment method are not allowed.', 'fatoripay-woo'));

	} // end process_refund;

	/**
	 * Auto redirect to the FatoriPay_ checkout page.
	 *
	 * @return void
	 */
	public function redirect_checkout() {

		$order_id = wcbc_request('oid', '');

		$order = wc_get_order($order_id);

		if ($order) {

			$return = $this->api->do_hosted_request($order);

			echo esc_url($return);

			die();

		} // end if;

	} // end redirect_checkout;

} // end WC_FatoriPay_Redirect_Gateway;
