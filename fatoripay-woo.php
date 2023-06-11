<?php
/**
 * Plugin Name:     FatoriPay for WooCommerce
 * Plugin URI:      https://fatoripay.com.br
 * Description:     Pagamentos com cartão de crédito, boleto e pix.
 * Author:          FatoriPay
 * Author URI:      https://fatoripay.com.br
 * Text Domain:     fatoripay-woo
 * Domain Path:     /lang
 * Version:         1.0
 *
 * @package         FatoriPayWoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'autoload.php';

add_action( 'plugins_loaded', array( \WC_FatoriPay\WC_FatoriPay::class, 'get_instance' ) );
