<?php
/**
 * Admin View: Notice - WooCommerce Extra Checkout Fields for Brazil missing.
 */

if (!defined('ABSPATH')) {

	exit;

}

$plugin_slug = 'woocommerce-extra-checkout-fields-for-brazil';

if (current_user_can('install_plugins')) {

	$url = wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=' . $plugin_slug), 'install-plugin_' . $plugin_slug);

} else {

	$url = 'http://wordpress.org/plugins/' . $plugin_slug;

}

?>

<div class="error">

	<p></strong><?php printf(__( 'WooCommerce FatoriPay requires the latest version of %s to work!', 'fatoripay-woo'), '<a href="' . esc_url( $url ) . '">' . __('WooCommerce Extra Checkout Fields for Brazil', 'fatoripay-woo') . '</a>' ); ?></p>
</div>
