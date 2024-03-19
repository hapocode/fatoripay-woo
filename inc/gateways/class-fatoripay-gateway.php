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
 * @version 1.0.0
 * @author  FatoriPay
 */
class WC_FatoriPay_Gateway extends WC_Payment_Gateway {

	/**
	 * API Client Name.
	 *
	 * @var string
	 */
	public $payable_with;
	public $installments_without_interest;
	public $boleto_overdue_days;
	public $client_id;
	public $client_secret;
	public $username;
	public $api;
	public $sandbox;
	public $debug;
	public $log;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		global $woocommerce;

		$this->id                   = 'fatoripay-gateway';
		$this->icon                 = apply_filters('fatoripay_woocommerce_icon', '');
		$this->method_title         = __('FatoriPay', 'fatoripay-woo' );
		$this->method_description   = __('Aceite pagamentos com Pix, Boleto e Cartão de Crédito.', 'fatoripay-woo' );
		$this->has_fields           = true;
		$this->view_transaction_url = 'https://fatoripay.com.br/';
		$this->supports             = array(
			'products',
		);

		/**
		 * Options.
		 */
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->payable_with = $this->get_option('payable_with');
		$this->installments_without_interest = $this->get_option('installments_without_interest');
		$this->boleto_overdue_days = $this->get_option('boleto_overdue_days');
		$this->client_id = $this->get_option('client_id');
		$this->client_secret = $this->get_option('client_secret');
		$this->username = $this->get_option('username');
		$this->sandbox = $this->get_option('sandbox', 'no');
		$this->debug = $this->get_option('debug');

		/**
		 * Active logs.
		 */
		if ($this->debug === 'yes') {
			if (class_exists('WC_Logger')) {
				$this->log = new WC_Logger();
			} else {
				$this->log = $woocommerce->logger();
			}

		}

		$this->api = new FatoriPay_API($this, 'redirect', $this->sandbox);

		/**
		 * Load the form fields.
		 */
		$this->init_form_fields();

		/**
		 * Load the settings.
		 */
		$this->init_settings();

		/**
		 * Actions
		 */
		add_action('woocommerce_api_wc_fatoripay_gateway', array($this, 'notification_handler'));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

		if ($this->settings['enabled'] === 'yes') {
			add_action('admin_notices', array($this, 'dependencies_notices'));
		}

	}

	/**
	 * Returns a value indicating the the Gateway is available or not.
	 *
	 * @return bool
	 */
	public function is_available() {

		// Test if is valid for use.
		$api = !empty($this->client_id);
		$available = parent::is_available() && $api;
		return $available;

	}

	/**
	 * Dependecie notice.
	 *
	 * @return mixed.
	 */
	public function dependencies_notices() {
		if (!class_exists('Extra_Checkout_Fields_For_Brazil')) {
			require_once dirname(FATORIPAY_WOO_PLUGIN_FILE) . '/views/html-notice-ecfb-missing.php';
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @return void.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __('Habilitar/Desabilitar', 'fatoripay-woo'),
				'type'    => 'checkbox',
				'label'   => __('Habilitar FatoriPay', 'fatoripay-woo'),
				'default' => 'no'
			),
			'title'           => array(
				'title'       => __('Título', 'fatoripay-woo'),
				'type'        => 'text',
				'description' => __('o titulo do método de pagamento que o cliente vê durante o checkout.', 'fatoripay-woo'),
				'default'     => __( 'FatoriPay', 'fatoripay-woo')
			),
			'description'     => array(
				'title'       => __('Descrição', 'fatoripay-woo' ),
				'type'        => 'textarea',
				'description' => __('A descrição do método de pagamento que o cliente vê durante o checkout.', 'fatoripay-woo'),
				'default'     => __('Aceite pagamentos com Pix, Boleto e Cartão de Crédito.', 'fatoripay-woo')
			),
			'payments_options' => array(
				'title' => __('Configurações dos Pagamentos', 'fatoripay-woo'),
				'type' => 'title',
				'description' => ''
			),
			'payable_with' => array(
				'title'       => __('Pagamentos aceitos', 'fatoripay-woo'),
				'type'        => 'multiselect',
				'description' => __('Selecione os métodos de pagamento que deseja aceitar.', 'fatoripay-woo'),
				'default'     => array('pix', 'boleto', 'credit_card'),
				'options'     => array(
					'pix'         => __('Pix', 'fatoripay-woo'),
					'boleto'      => __('Boleto', 'fatoripay-woo'),
					'credit_card' => __('Cartão de Crédito', 'fatoripay-woo'),
					'pixboleto'   => __('Pix e Boleto', 'fatoripay-woo'),
				)
			),
			'boleto_options' => array(
				'title' => __('Configurações do Boleto', 'fatoripay-woo'),
				'type' => 'title',
				'description' => ''
			),
			'boleto_overdue_days'      => array(
				'title'             => 'Dias para vencimento',
				'label'				=> '',
				'type'              => 'number',
				'description'       => __( 'Número de dias para vencimento do boleto.', 'fatoripay-woo' ),
				'custom_attributes' => array(),
				'default'           => 1
			),
			'credit_card_options' => array(
				'title' => __('Configurações do Cartão de Crédito', 'fatoripay-woo'),
				'type' => 'title',
				'description' => ''
			),
			'installments_without_interest'      => array(
				'title'             => 'Parcelas sem juros',
				'label'				=> '',
				'type'              => 'number',
				'description'       => __( 'Número máximo de parcelas sem juros.', 'fatoripay-woo' ),
				'custom_attributes' => array(),
				'default'           => 0
			),
			'integration'    => array(
				'title'       => __('Configurações de Integração', 'fatoripay-woo'),
				'type'        => 'title',
				'description' => ''
			),
			'client_id'      => array(
				'title'             => 'Client ID',
				'label'				=> __('O seu client ID.', 'fatoripay-woo'),
				'type'              => 'text',
				'description'       => sprintf( __( 'Obtenha o seu client ID de acesso em <a href="%s" target="_blank">FatoriPay</a>.', 'fatoripay-woo' ), 'https://app.fatoripay.com.br' ),
				'custom_attributes' => array()
			),
			'client_secret'      => array(
				'title'             => 'Client Secret',
				'label'				=> __('O seu client Secret.', 'fatoripay-woo'),
				'type'              => 'text',
				'description'       => sprintf( __( 'Obtenha o seu client ID de acesso em <a href="%s" target="_blank">FatoriPay</a>.', 'fatoripay-woo' ), 'https://app.fatoripay.com.br' ),
				'custom_attributes' => array()
			),
			'username'      => array(
				'title'             => 'Username',
				'label'				=> __('O seu username (CPF).', 'fatoripay-woo'),
				'type'              => 'text',
				'description'       => __( 'O seu username (CPF).', 'fatoripay-woo' ),
				'custom_attributes' => array()
			),
			'sandbox'    => array(
				'title'       => __( 'Sandbox', 'fatoripay-woo' ),
				'type'        => 'checkbox',
				'label'       => __( 'Habilite o uso da API Sandbox', 'fatoripay-woo' ),
				'default'     => 'no',
				'description' => __( 'Habilite o uso da API Sandbox.', 'fatoripay-woo' )
			),
			'debug'         => array(
				'title'       => __( 'Debugging', 'fatoripay-woo' ),
				'type'        => 'checkbox',
				'label'       => __( 'Habilitar log', 'fatoripay-woo' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log as requisições da API FatoriPay', 'fatoripay-woo' ), FatoriPay_Woo::get_log_view($this->id))
			)
		);
	}

	/**
	 * Payment fields.
	 */
	public function payment_fields() {
		if ($description = $this->get_description()) {
			echo esc_html($description);
		}

		wc_get_template(
			'redirect/checkout-instructions.php',
			array(),
			'woocommerce/fatoripay/',
			FatoriPay_Woo::get_templates_path()
		);

	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id Order ID.
	 * @return array Redirect.
	 */
	public function process_payment($order_id) {
		return $this->api->process_payment($order_id);
	}

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
			'woocommerce/fatoripay/',
			FatoriPay_Woo::get_templates_path()
		);

	}

	/**
	 * Handles the notification posts from FatoriPay Gateway.
	 *
	 * @return void
	 */
	public function notification_handler() {
		@ob_clean(); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		$this->api->notification_handler();
	}

	/**
	 * Process refund.
	 *
	 * @param  int        $order_id Order ID.
	 * @param  float|null $amount Refund amount.
	 * @param  string     $reason Refund reason.
	 * @return boolean True or false based on success, or a WP_Error object.
	 */
	public function process_refund($order_id, $amount = null, $reason = '') {
		return new \WP_Error('error', __('Estorno não suportado.', 'fatoripay-woo'));
	}

}
