<?php
/**
 * Plugin Name: FatoriPay Gateway for WooCommerce
 * Description: FatoriPay Gateway for WooCommerce.
 * Plugin URI: https://wordpress.org/plugins/fatoripay-payment-for-woocommerce/
 * Text Domain: fatoripay-woo
 * Version: 1.0.0
 * Author: FatoriPay
 * Author URI: https://fatoripay.com.br/
 * Network: true
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /lang
 * WC requires at least: 3.0.0
 * WC tested up to: 7.1
*/

if (!defined('ABSPATH')) {

	exit;

}

if (!class_exists('FatoriPay_Woo')) {

	/**
	 * WooCommerce FatoriPay main class.
	 */
	class FatoriPay_Woo {

		/**
		 * API Client Name.
		 *
		 * @var string
		 */

		const CLIENT_NAME = 'fatoripay';

		/**
		 * CLient Version.
		 */
		const CLIENT_VERSION = '1.0.0';

		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Initialize the plugin actions.
		 */
		public function __construct() {

			if (!defined('FATORIPAY_WOO_PLUGIN_FILE')) {
				define('FATORIPAY_WOO_PLUGIN_FILE', __FILE__ );
			}

			/**
			 * Load plugin text domain.
			 */
			add_action('init', array( $this, 'load_plugin_textdomain'));

			include_once 'inc/class-fatoripay.php';

			new FatoriPay();

		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {

			/**
			 * If the single instance hasn't been set, set it now.
			 */
			if (null == self::$instance) {

				self::$instance = new self;

			}

			return self::$instance;

		}

		/**
		 * Get templates path.
		 *
		 * @return string
		 */
		public static function get_templates_path() {

			return plugin_dir_path( __FILE__ ) . 'templates/';

		}

		/**
		 * Get assets path.
		 *
		 * @param bool $url
		 * @return string
		 */
		public static function get_assets_path($url = false) {
			$path = plugin_dir_path( __FILE__ ) . 'assets/';
			if ($url) {
				$path = plugins_url( 'assets', __FILE__ );
			}
			return $path;
		}

		/**
		 * Load the plugin text domain for translation.
		 *
		 * @return void
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain('fatoripay-woo', false, dirname(plugin_basename(__FILE__)) . '/lang/');
		}

		/**
		 * Get log.
		 *
		 * @return string
		 */
		public static function get_log_view($gateway_id) {
			return '<code>woocommerce/logs/' . esc_attr( $gateway_id ) . '-' . sanitize_file_name(wp_hash($gateway_id)) . '.txt</code>';
		}

	}
	add_action('plugins_loaded', array('FatoriPay_Woo','get_instance'));
}
