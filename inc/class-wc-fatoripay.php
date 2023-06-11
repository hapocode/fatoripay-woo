<?php
/**
 * WooCommerce FatoriPay main class.
 */
class WC_FatoriPay_ {

	/**
	 * Initialize the plugin actions.
	 */
	public function __construct() {

		/**
		 * Checks with WooCommerce and WooCommerce Extra Checkout Fields for Brazil is installed.
		 */
		if (class_exists('WC_Payment_Gateway')) {

			$this->includes();

			add_filter('woocommerce_payment_gateways', array( $this, 'add_gateway'));

			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'plugin_action_links'));

		} else {

			add_action('admin_notices', array( $this, 'dependencies_notices'));

		} // end if;

	} // end __construct;


	/**
	 * Include the main files.
	 *
	 * @return void
	 */
	private function includes() {

		include_once 'functions/helpers.php';

		include_once 'class-fatoripay-woo-api.php';

		include_once 'gateways/redirect/class-fatoripay-woo-redirect-gateway.php';

		include_once 'admin-pages/class-fatoripay-woo-my-account.php';

	} // end includes;

	/**
	 * Add the gateway to WooCommerce.
	 *
	 * @param  array $methods WooCommerce payment methods.
	 * @return array Payment methods with FatoriPay.
	 */
	public function add_gateway($methods) {

		$methods[] = 'WC_FatoriPay_Redirect_Gateway';

		return $methods;

	} // end add_gateway;

	/**
	 * Dependencies notices.
	 */
	public function dependencies_notices() {

		if (!class_exists( 'WC_Payment_Gateway')) {

			require_once dirname(WC_PGI_PLUGIN_FILE) . '/views/html-notice-woocommerce-missing.php';

		} // end if;

	} // end if;

	/**
	 * Action links.
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	public function plugin_action_links($links) {

			$plugin_links = array();

			if (defined('WC_VERSION') && version_compare(WC_VERSION, '2.1', '>=')) {

				$settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=');

			} else {

				$settings_url = admin_url('admin.php?page=woocommerce_settings&tab=payment_gateways&section=');

			} // end if;

			if (class_exists('WC_Subscriptions_Order') || class_exists('WC_Pre_Orders_Order')) {

        $credit_card = 'wc_fatoripay_credit_card_addons_gateway';

        $bank_slip   = 'wc_fatoripay_bank_slip_addons_gateway';


			} else  {

				$credit_card = 'wc_fatoripay_credit_card_Gateway';

				$bank_slip   = 'wc_fatoripay_bank_slip_gateway';

			} // end if.

			$plugin_links[] = '<a href="' . esc_url( $settings_url . $credit_card ) . '">' . __( 'Credit card settings', 'fatoripay-woo' ) . '</a>';

			$plugin_links[] = '<a href="' . esc_url( $settings_url . $bank_slip ) . '">' . __( 'Bank slip settings', 'fatoripay-woo' ) . '</a>';

			return array_merge( $plugin_links, $links );

		} // end if;

} // end WC_FatoriPay_;
