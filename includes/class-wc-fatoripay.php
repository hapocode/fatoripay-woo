<?php
/**
 * Plugin main class
 *
 * @package FatoriPayWoo
 */

namespace WC_FatoriPay;

use WC_FatoriPay\Admin\Plugin_Dependency;
use WC_FatoriPay\Webhook\Endpoint;
use WC_FatoriPay\Gateway\Gateway;
use WC_FatoriPay\Checkout\Form_Field\Card_Expiration;
use WC_FatoriPay\Checkout\Form_Field\Label;
use WC_FatoriPay\Checkout\Form_Field\Card;
use WC_FatoriPay\Checkout\Form_Field\One_Click_Options;
use WC_FatoriPay\Checkout\Form_Field\Card_Number;
use WC_FatoriPay\Checkout\Form_Field\Card_Security_Code;
use WC_FatoriPay\Billing_Type\Billing_Type_Exception;
use WC_FatoriPay\Cron\Expired_Pix_Cron;
use WC_FatoriPay\Cron\Expired_Ticket_Cron;
use WC_FatoriPay\Installments\Admin\Settings\Installments_Fields;
use WC_FatoriPay\Installments\Gateway\Checkout_Installments;
use WC_FatoriPay\Installments\Gateway\Payment_Installments;
use WC_FatoriPay\Product\Admin\Settings\Product_Settings;
use WC_FatoriPay\Cart\Cart;
use WC_FatoriPay\Coupon\Coupon;
use WC_FatoriPay\Subscription\Subscription;
use WC_FatoriPay\Subscription\Admin\Settings\WooCommerce_Subscriptions_Settings;
use WC_FatoriPay\Subscription\Admin\Subscription_Admin;
use WC_FatoriPay\My_Account\WooCommerce_My_Account;
use WC_FatoriPay\Checkout\Checkout;

/**
 * FatoriPay Gateway for WooCommerce main class
 */
class WC_FatoriPay {

	/**
	 * WooCommerce version.
	 *
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * Instance of this class
	 *
	 * @var self
	 */
	protected static $instance = null;

	/**
	 * Gateway list with id
	 *
	 * @var array
	 */
	protected $gateways = array();

	/**
	 * Initialize the plugin public actions
	 *
	 * Block external object instantiation.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		$dependency = Plugin_Dependency::get_instance();
		add_action( 'admin_init', array( $dependency, 'check_dependencies' ) );

		if ( ! $dependency->check_woocommerce() ) {
			return;
		}

		add_action( 'init', array( $this, 'init_gateways' ) );

		add_action( 'init', array( $this, 'init_form_fields' ) );
		add_action( 'init', array( Expired_Ticket_Cron::get_instance(), 'schedule_remove_expired_ticket' ) );
		add_action( 'remove_expired_ticket', array( Expired_Ticket_Cron::get_instance(), 'remove_expired_ticket' ) );
		add_action( 'remove_expired_pix_fatoripay', array( Expired_Pix_Cron::get_instance(), 'remove_expired_pix' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_files' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateways' ) );
		add_filter( 'woocommerce_fatoripay_payment_data', array( Payment_Installments::get_instance(), 'installment_payment_data' ), 10, 3 );
		add_filter( 'woocommerce_fatoripay_ticket_payment_fields', array( Checkout_Installments::get_instance(), 'add_ticket_installment_field' ), 10, 2 );
		add_filter( 'woocommerce_fatoripay_cc_payment_fields', array( Checkout_Installments::get_instance(), 'add_cc_installment_field' ), 10, 2 );

		add_filter( 'woocommerce_fatoripay_cc_settings_fields', array( Installments_Fields::get_instance(), 'add_installments_fields' ), 10, 2 );

		add_filter( 'wcs_is_early_renewal_enabled', array( WooCommerce_Subscriptions_Settings::get_instance(), 'disable_early_renewal' ), 10 );
		add_filter( 'wcs_is_early_renewal_via_modal_enabled', array( WooCommerce_Subscriptions_Settings::get_instance(), 'disable_early_renewal' ), 10 );
		add_filter( 'woocommerce_subscription_settings', array( WooCommerce_Subscriptions_Settings::get_instance(), 'show_notice_unsupport_early_renewal' ), 20 );
		add_filter( 'woocommerce_subscription_settings', array( WooCommerce_Subscriptions_Settings::get_instance(), 'show_notice_unsupport_manual_renewal' ), 20, 1 );
		add_filter( 'woocommerce_fatoripay_settings_sections', array( WooCommerce_Subscriptions_Settings::get_instance(), 'show_notice_unsupport_manual_renewal' ), 10, 2 );
		add_filter( 'woocommerce_subscription_settings', array( WooCommerce_Subscriptions_Settings::get_instance(), 'show_notice_unsupport_auto_renewal_toggle' ), 20, 1 );
		add_filter( 'woocommerce_fatoripay_settings_sections', array( WooCommerce_Subscriptions_Settings::get_instance(), 'show_notice_unsupport_auto_renewal_toggle' ), 10, 2 );

		add_action( 'pre_post_update', array( Subscription_Admin::get_instance(), 'validate_subscription_changes' ), 10, 2 );


		add_action( 'woocommerce_product_options_general_product_data', array( Product_Settings::get_instance(), 'show_tip_supported_billing_cycles' ), 5 );
		add_filter( 'woocommerce_available_payment_gateways', array( Cart::get_instance(), 'check_available_payment_gateways' ), 10, 1 );
		add_action( 'woocommerce_coupon_options', array( Coupon::get_instance(), 'show_notice_about_supported_coupon_types' ), 10 );

		add_filter( 'woocommerce_can_subscription_be_updated_to_active', array( Subscription::get_instance(), 'can_subscription_be_updated_to_active' ), 10, 2 );
		add_action( 'woocommerce_subscription_status_changed', array( Subscription::get_instance(), 'sync_status' ), 10, 4 );

		add_action( 'woocommerce_checkout_order_created', array( Checkout::get_instance(), 'handle_woocommerce_subscriptions_checkout_usage' ), 10, 1 );
		add_filter( 'woocommerce_subscriptions_synced_first_payment_date_string', array( Checkout::get_instance(), 'hide_first_payment_date_string' ), 10, 2 );
		remove_filter( 'wcs_cart_totals_order_total_html', 'wcs_add_cart_first_renewal_payment_date', 10 );

		add_filter( 'woocommerce_my_account_my_orders_actions', array( WooCommerce_My_Account::get_instance(), 'my_orders_actions' ), 10, 2 );

		add_action( 'admin_notices', array( $this, 'check_checkout_settings' ) );
	}

	/**
	 * Return an instance of this class
	 *
	 * @return self A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get the plugin absolute path
	 *
	 * @return string The plugin absolute path.
	 */
	public function get_plugin_path() {
		return plugin_dir_path( dirname( __FILE__ ) );
	}

	/**
	 * Get the plugin URL
	 *
	 * @return string The plugin URL.
	 */
	public function get_plugin_url() {
		return plugin_dir_url( dirname( __FILE__ ) );
	}

	/**
	 * Get templates path
	 */
	public function get_templates_path() {
		return $this->get_plugin_path() . 'templates/';
	}

	/**
	 * Get a template file
	 *
	 * A template can be overwritten by the theme. Add the templates file in `woocommerce/fatoripay` in your theme.
	 *
	 * @param string  $template_name The template file name.
	 * @param array   $args The template variables.
	 * @param boolean $return Return the template HTML if is true. Otherwise, print the template.
	 * @return string
	 */
	public function get_template_file( $template_name, $args = array(), $return = false ) {
		if ( $return ) {
			return wc_get_template_html(
				$template_name,
				$args,
				'woocommerce/fatoripay/',
				self::get_instance()->get_templates_path()
			);
		}

		wc_get_template(
			$template_name,
			$args,
			'woocommerce/fatoripay/',
			self::get_instance()->get_templates_path()
		);
	}

	/**
	 * Get plugin assets URL
	 */
	public function get_assets_url() {
		return $this->get_plugin_url() . 'assets/';
	}

	/**
	 * Init FatoriPay gateways together with WooCommerce to process the inner hooks correctly
	 */
	public function init_gateways() {
		foreach ( $this->get_gateways_classes() as $gateway ) {
			$reflection                         = new \ReflectionClass( $gateway );
			$gateway_obj                        = $reflection->newInstanceArgs();
			$this->gateways[ $gateway_obj->id ] = $gateway_obj;
		}
	}

	/**
	 * Load plugin textdomain file
	 */
	public function load_plugin_textdomain() {
		$domain                 = 'fatoripay-woo';
		$languages_rel_dir_path = '/../languages/';
		load_textdomain( $domain, __DIR__ . $languages_rel_dir_path . '/' . $domain . '-' . get_locale() . '.mo' );
	}

	/**
	 * Get plugin gateway classes
	 *
	 * @return array The plugin gateway classes.
	 */
	public function get_gateways_classes() {
		return apply_filters(
			'woocommerce_fatoripay_gateways_classes', array(
				\WC_FatoriPay\Gateway\All_Payments::class,
			)
		);
	}

	/**
	 * Get plugin gateways
	 *
	 * @return Gateway[string] The plugin gateways associated with its id.
	 */
	public function get_gateways() {
		return apply_filters( 'woocommerce_fatoripay_getways', $this->gateways );
	}

	/**
	 * Get gateway by id
	 *
	 * @param  string $id id.
	 *
	 * @return Gateway return order gateway
	 */
	public function get_gateway_by_id( $id ) {
		return $this->gateways[ $id ];
	}

	/**
	 * Get gateway by billing type
	 *
	 * @param string $billing_type Billing type id on FatoriPay API.
	 * @throws Billing_Type_Exception If the type wasn't registered by a gateway.
	 * @return Gateway|NULL The gateway, if id is valid. Otherwise, null.
	 */
	public function get_gateway_by_billing_type( $billing_type ) {
		foreach ( $this->gateways as $gateway ) {
			if ( $billing_type === $gateway->get_type()->get_id() ) {
				return $gateway;
			}
		}

		/* translators: %s: billing type name  */
		throw new Billing_Type_Exception( sprintf( __( 'Billing type %s wasn\'t registered.', 'fatoripay-woo' ), $billing_type ) );
	}

	/**
	 * Add plugin ticket, credit and Pix card gateways to WooCommerce
	 *
	 * @param string[] $methods WooCommerce available gateways.
	 * @return string[] Gateways including FatoriPay ones.
	 */
	public function register_gateways( $methods ) {
		return array_merge( $methods, array_values( $this->gateways ) );
	}

	/**
	 * Init the form fields
	 *
	 * @see self::get_form_fields()
	 */
	public function init_form_fields() {
		$this->get_form_fields();
	}

	/**
	 * Get the custom checkout form fields
	 *
	 * @return \WC_FatoriPay\Checkout\Form_Field\Form_Field[]
	 */
	public function get_form_fields() {
		return array(
			Card::get_instance(),
			Card_Expiration::get_instance(),
			Card_Number::get_instance(),
			Card_Security_Code::get_instance(),
			Label::get_instance(),
			One_Click_Options::get_instance(),
		);
	}

	/**
	 * Get the custom checkout form field from object type
	 *
	 * @param string $type The type identification.
	 * @return \WC_FatoriPay\Checkout\Form_Field\Form_Field|NULL The correspondent object of the type. Null, if not found it.
	 */
	public function get_form_field_object_from_type( $type ) {
		foreach ( $this->get_form_fields() as $field_type ) {
			if ( $type === $field_type->get_type() ) {
				return $field_type;
			}
		}

		return null;
	}

	/**
	 * Check checkout settings for 'woocommerce-extra-checkout-fields-for-brazil' plugin
	 */
	public function check_checkout_settings() {
		$settings = get_option( 'wcbcf_settings' );

		if ( is_array( $settings ) ) {
			if ( '0' === $settings['person_type'] ) {
				$error   = __( 'FatoriPay needs the CPF or CNPJ at checkout for the integration works.', 'fatoripay-woo' );
				$message = $error;

				$page = sanitize_text_field( wp_unslash( isset( $_GET['page'] ) ? $_GET['page'] : '' ) );
				if ( 'woocommerce-extra-checkout-fields-for-brazil' !== $page ) {
					/* translators: %s: Extra Checkout fields for Brazil settings URL  */
					$message .= ' ' . sprintf( __( '<a href="%s">Click here</a> to change checkout fields.', 'fatoripay-woo' ), admin_url( 'admin.php?page=woocommerce-extra-checkout-fields-for-brazil' ) );
				}

				echo wp_kses_post( '<div class="error"><p>' . $message . '</p></div>' );
			}
		}
	}

	/**
	 * Enqueue plugin style file
	 */
	public function enqueue_frontend_files() {
		wp_enqueue_style( 'fatoripay-woo', $this->get_plugin_url() . 'assets/fatoripay-woo.css', array(), $this->version );
		wp_enqueue_script( 'fatoripay-woo', $this->get_plugin_url() . 'assets/fatoripay-woo.js', array(), $this->version, true );
	}
}
