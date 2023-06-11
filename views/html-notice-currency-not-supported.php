<?php
/**
 * Admin View: Notice - Currency not supported.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="error">
	<p><strong><?php _e( 'FatoriPay disabled', 'fatoripay-woo' ); ?></strong>: <?php printf( __( 'Currency <code>%s</code> is not supported. WooCommerce FatoriPay only works with Brazilian real (BRL).', 'fatoripay-woo' ), get_woocommerce_currency() ); ?>
	</p>
</div>
