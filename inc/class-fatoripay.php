<?php

use Automattic\WooCommerce\Utilities\FeaturesUtil as AutomatticWooCommerceUtilitiesFeaturesUtil;

/**
 * WooCommerce FatoriPay main class.
 */
class FatoriPay {

	/**
	 * Initialize the plugin actions.
	 */
	public function __construct() {

		if (class_exists('WC_Payment_Gateway')) {
			$this->includes();
			add_filter('woocommerce_payment_gateways', array( $this, 'add_gateway'));
		} else {
			add_action('admin_notices', array( $this, 'dependencies_notices'));
		}

		add_action( 'before_woocommerce_init', function() {
			if ( class_exists( AutomatticWooCommerceUtilitiesFeaturesUtil::class ) ) {
				AutomatticWooCommerceUtilitiesFeaturesUtil::declare_compatibility( 'custom_order_tables', FATORIPAY_WOO_PLUGIN_FILE, true );
			}
		} );

	}

	/**
	 * Include the main files.
	 *
	 * @return void
	 */
	private function includes() {
		include_once 'functions/helpers.php';
		include_once 'class-fatoripay-api.php';
		include_once 'gateways/class-fatoripay-gateway.php';
		include_once 'admin-pages/class-fatoripay-woo-my-account.php';
	}

	/**
	 * Add the gateway to WooCommerce.
	 *
	 * @param  array $methods WooCommerce payment methods.
	 * @return array Payment methods with FatoriPay.
	 */
	public function add_gateway($methods) {
		$methods[] = 'WC_FatoriPay_Gateway';
		return $methods;
	}

	/**
	 * Dependencies notices.
	 */
	public function dependencies_notices() {
		if (!class_exists( 'WC_Payment_Gateway')) {
			require_once dirname(FATORIPAY_WOO_PLUGIN_FILE) . '/views/html-notice-woocommerce-missing.php';
		}
	}

}
