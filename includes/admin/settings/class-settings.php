<?php
/**
 * Common gateways settings class
 *
 * @package FatoriPayWoo
 */

namespace WC_FatoriPay\Admin\Settings;

use WC_FatoriPay\Api\Api;
use WC_FatoriPay\Gateway\Gateway;
use WC_FatoriPay\Webhook\Endpoint;
use WC_FatoriPay\Helper\WP_List_Util;

/**
 * Common gateways settings
 */
abstract class Settings {

	/**
	 * The billing type object data
	 *
	 * @var Gateway
	 */
	public $gateway;

	/**
	 * Settings fields
	 *
	 * This plugin setting field add section to organize the settings and priority args to allow a better order customization.
	 *
	 * @var array {
	 *     Similar to WooCommerce settings fields adding `section` and `priority`.
	 *
	 *     @type string $shared   Share the same config value between all plugin gateways.
	 *     @type string $section  The section id.
	 *     @type int    $priority The field priority to show.
	 * }
	 */
	protected $fields;

	/**
	 * Field Sections
	 *
	 * The sections fields allow group field in sections. The sections render is managed by this class
	 *
	 * @var array {
	 *     Similar to WooCommerce settings fields adding `section` and `priority`.
	 *
	 *     @type string $title    The section title.
	 *     @type int    $priority The section priority to show.
	 * }
	 */
	protected $sections;

	/**
	 * The generated WooCommerce settings field list
	 *
	 * @var array
	 */
	protected $fields_array;

	/**
	 * Use this replacement because the form fields is loaded before the save action
	 *
	 * @var string
	 */
	protected $config_url_replacement = '%config_url%';

	/**
	 * Init the default field sections
	 *
	 * @param Gateway $gateway The gateway that call the logger.
	 */
	public function __construct( $gateway ) {
		$this->gateway  = $gateway;
		$this->fields   = $this->get_fields();
		$this->sections = $this->get_sections();
	}

	/**
	 * Get the FatoriPay dashbord config page URL
	 *
	 * @return string The FatoriPay config URL.
	 */
	public function get_config_url() {
		$url_components = wp_parse_url( $this->gateway->get_option( 'endpoint' ) );

		if ( '' === $url_components['path'] ) {
			return '';
		}

		return $url_components['scheme'] . '://' . $url_components['host'] . '/config/index';
	}

	/**
	 * Define the default plugin field sections
	 */
	public function get_sections() {
		return apply_filters(
			'woocommerce_fatoripay_settings_sections', array(
				'default'       => array(
					'title'    => '',
					'priority' => 0,
				),
				'gateway'       => array(
					'title'    => __( 'Gateway', 'fatoripay-woo' ),
					'priority' => 10,
				),
				'api'           => array(
					'title'    => __( 'API', 'fatoripay-woo' ),
					'priority' => 20,
				),
				'webhook'       => array(
					'title'       => __( 'Webhook', 'fatoripay-woo' ),
					'description' =>
						__( 'Configure the webhook to receive FatoriPay notifications and update orders automatically.', 'fatoripay-woo' ) .
						'<ol>' .
							/* translators: %s: FatoriPay integration settings panel URL  */
							'<li>' . sprintf( __( '<a href="%s">Click here</a> and go to section <em>Webhook</em> in the section <em>Integração</em> tab', 'fatoripay-woo' ), $this->config_url_replacement ) . '</li>' .
							'<li>' . __( 'Enable the webhook', 'fatoripay-woo' ) . '</li>' .
							/* translators: %s: Webhook endpoint URL  */
							'<li>' . sprintf( __( 'Put <code>%s</code> in URL', 'fatoripay-woo' ), Endpoint::get_instance()->get_url() ) . '</li>' .
							'<li>' . __( 'Select <em>v3</em> on <em>Versão da API</em>', 'fatoripay-woo' ) . '</li>' .
							/* translators: %s: Webhook token suggestion  */
							'<li>' . sprintf( __( 'Define an access access token (e.g., <code>%s</code>) and fill the same value in this form <em>Access Token</em> input', 'fatoripay-woo' ), wp_generate_password( 32, false ) ) . '</li>' .
							'<li>' . __( 'To process the webhook queue que field <em>Status fila de sincronização</em> must be <em>Ativa</em>', 'fatoripay-woo' ) . '</li>' .
						'</ol>',
					'priority'    => 30,
				),
				'subscriptions' => array(
					'title'    => __( 'Subscriptions', 'fatoripay-woo' ),
					'priority' => 40,
				),
				'advanced'      => array(
					'title'    => __( 'Advanced Options', 'fatoripay-woo' ),
					'priority' => 50,
				),
			),
			$this
		);
	}

	/**
	 * Shared fields between to billing types checkout
	 *
	 * @link https://docs.woocommerce.com/document/settings-api/
	 *
	 * @return array
	 */
	protected function get_fields() {
		$fields = array(
			'enabled'                 => array(
				'title'    => __( 'Enable/Disable', 'fatoripay-woo' ),
				'type'     => 'checkbox',
				/* translators: %s: billing type name  */
				'label'    => sprintf( __( 'Enable FatoriPay %s', 'fatoripay-woo' ), $this->gateway->get_type()->get_name() ),
				'default'  => 'no',
				'section'  => 'default',
				'priority' => 0,
			),
			'title'                   => array(
				'title'       => __( 'Title', 'fatoripay-woo' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'fatoripay-woo' ),
				/* translators: %s: billing type name  */
				'default'     => $this->gateway->get_type()->get_name(),
				'desc_tip'    => true,
				'section'     => 'default',
				'priority'    => 10,
			),
			'description'             => array(
				'title'       => __( 'Description', 'fatoripay-woo' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'fatoripay-woo' ),
				'default'     => __( 'Pay your order using FatoriPay.', 'fatoripay-woo' ),
				'desc_tip'    => true,
				'section'     => 'default',
				'priority'    => 20,
			),
			'awaiting_payment_status' => array(
				'title'       => __( 'Open payment order status', 'fatoripay-woo' ),
				'type'        => 'select',
				'description' => __( 'Status that the order will be saved when the customer makes a purchase and the order is not yet paid. <code>On hold</code> reduces stock, sends an email to the customer and to the shopkeeper. <code>Pending payment</code> does not reduce stock or send an email. This option is shared with other FatoriPay payment methods.', 'fatoripay-woo' ),
				'shared'      => true,
				'section'     => 'default',
				'priority'    => 30,
				'options'     => array(
					'pending' => __( 'Pending payment', 'fatoripay-woo' ),
					'on-hold' => __( 'On hold', 'fatoripay-woo' ),
				),
			),
			'endpoint'                => array(
				'title'       => __( 'Endpoint', 'fatoripay-woo' ),
				'type'        => 'text',
				'description' => __( 'The API endpoint. The default values are <code>https://sandbox.fatoripay.com/api/v3</code> for tests and <code>https://www.fatoripay.com/api/v3</code> for production.', 'fatoripay-woo' ),
				'default'     => 'https://www.fatoripay.com/api/v3',
				'shared'      => true,
				'section'     => 'api',
				'priority'    => 10,
			),
			'api_key'                 => array(
				'title'       => __( 'API Key', 'fatoripay-woo' ),
				'type'        => 'text',
				/* translators: %s: FatoriPay integration settings panel URL  */
				'description' => sprintf( __( 'The API Key used to connect with FatoriPay. <a href="%s">Click here</a> to get it.', 'fatoripay-woo' ), $this->config_url_replacement ),
				'default'     => '',
				'shared'      => true,
				'section'     => 'api',
				'priority'    => 20,
			),
			'notification'            => array(
				'title'       => __( 'Notification between FatoriPay and customer', 'fatoripay-woo' ),
				'type'        => 'checkbox',
				'label'       => sprintf( __( 'Enable Notification', 'fatoripay-woo' ), $this->gateway->get_type()->get_name() ),
				/* translators: %s: FatoriPay integration settings panel URL  */
				'description' => __( 'Allow FatoriPay to send email and SMS about the purchase and notify him periodically while the purchase is not paid.', 'fatoripay-woo' ),
				'default'     => 'no',
				'shared'      => true,
				'section'     => 'api',
				'priority'    => 30,
			),
			'webhook_access_token'    => array(
				'title'       => __( 'Access Token', 'fatoripay-woo' ),
				'type'        => 'text',
				/* translators: %s: FatoriPay integration settings panel URL  */
				'description' => sprintf( __( 'The token filled in the FatoriPay webhook settings.', 'fatoripay-woo' ), $this->config_url_replacement ),
				'default'     => '',
				'shared'      => true,
				'section'     => 'webhook',
				'priority'    => 10,
			),
			'debug'                   => array(
				'title'       => __( 'Debug log', 'fatoripay-woo' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'fatoripay-woo' ),
				'default'     => 'no',
				/* translators: %s: log page link */
				'description' => sprintf( __( 'Log FatoriPay API and webhook communication, inside %s.', 'fatoripay-woo' ), $this->get_log_view() ),
				'section'     => 'advanced',
				'priority'    => 20,
			),
		);

		$shared_message = apply_filters( 'woocommerce_fatoripay_shared_option_message', __( 'This option is shared with another FatoriPay gateway.', 'fatoripay-woo' ) );
		foreach ( $fields as &$field ) {
			if ( isset( $field['shared'] ) && true === $field['shared'] ) {
				$field['description'] .= ' ' . $shared_message;
			}
		}

		return apply_filters( 'woocommerce_fatoripay_settings_fields', $fields, $this );
	}

	/**
	 * Get fields to be showed in settings page
	 *
	 * @return array The fields in the WooCommerce checkout settings format
	 */
	public function fields() {
		if ( ! empty( $this->fields_array ) ) {
			return $this->fields_array;
		}

		$this->sort_sections();
		$this->add_fields_to_sections();
		$this->create_fields_array();

		return $this->fields_array;
	}

	/**
	 * Sort sections by priority
	 */
	public function sort_sections() {
		$this->sections = $this->sort_by_priority( $this->sections );
	}

	/**
	 * Sort the section fields by priority
	 *
	 * @param array $fields The fields list.
	 * @return array The fields list sorted
	 */
	public function sort_section_fields( $fields ) {
		return $this->sort_by_priority( $fields );
	}

	/**
	 * Sort an array by the priority key
	 *
	 * @global $wp_version string Check WP version for legacy code.
	 *
	 * @param array $list The array list.
	 * @return array The array sorted.
	 */
	private function sort_by_priority( $list ) {
		global $wp_version;

		$orderby       = array(
			'priority' => 'ASC',
		);
		$order         = 'ASC';
		$preserve_keys = true;

		// Legacy code support.
		if ( version_compare( $wp_version, '4.7.0', '<' ) ) {
			$util = new WP_List_Util( $list );
			return $util->sort( $orderby, $order, $preserve_keys );
		}

		return wp_list_sort( $list, $orderby, $order, $preserve_keys );
	}

	/**
	 * Add all fields to its respective section
	 */
	public function add_fields_to_sections() {
		foreach ( $this->sections as &$section ) {
			$section['fields'] = array();
		}

		foreach ( $this->fields as $id => $args ) {
			if ( empty( $args['section'] ) ) {
				$args['section'] = 'default';
			}

			if ( empty( $args['priority'] ) ) {
				$args['priority'] = 0;
			}

			$args['id']                                     = $id;
			$this->sections[ $args['section'] ]['fields'][] = $args;
		}
	}

	/**
	 * Prepare the fields array to be processed by WooCommerce
	 */
	public function create_fields_array() {
		$fields = array();

		foreach ( $this->sections as $id => $section ) {
			if ( ! empty( $section['title'] ) ) {
				$fields[] = $this->title_field( $section );
			}

			$section_fields = $this->sort_by_priority( $section['fields'] );
			$fields         = array_merge( $fields, array_combine( array_column( $section_fields, 'id' ), $section_fields ) );
		}

		$this->fields_array = $fields;
	}

	/**
	 * Create a WooCommerce settings title field
	 *
	 * @param array $section The section data.
	 * @return string[] The settings title field
	 */
	public function title_field( $section ) {
		$field         = $section;
		$field['type'] = 'title';
		return $field;
	}

	/**
	 * Replace config URL replacement to real value in fields that need
	 *
	 * @return array The fields array updated
	 */
	public function replace_config_url() {
		$config_url = $this->get_config_url();

		foreach ( $this->fields_array as $key => $field ) {
			if ( isset( $field['description'] ) && false !== strpos( $field['description'], $this->config_url_replacement ) ) {
				$this->fields_array[ $key ]['description'] = str_replace( $this->config_url_replacement, $config_url, $field['description'] );
			}
		}

		return $this->fields_array;
	}

	/**
	 * Get log view
	 *
	 * @return string The HTML with where get the log
	 */
	protected function get_log_view() {
		return '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->gateway->id ) . '-' . sanitize_file_name( wp_hash( $this->gateway->id ) ) . '.log' ) ) . '">' . __( 'Status &gt; Logs', 'fatoripay-woo' ) . '</a>';
	}

	/**
	 * Is tokenization available?
	 *
	 * @return bool True if tokenization is available for this account. Otherwise, false.
	 */
	public function is_tokenization_available() {
		$api      = new Api( $this->gateway );
		$response = $api->credit_card()->tokenize( array() );
		if ( is_array( $response->errors ) && 0 < count( $response->errors ) ) {
			// invalid_customer = tokenization enabled / forbidden = tokenization disabled.
			return 'invalid_customer' === $response->errors[0]->code;
		}
		return false;
	}
}
