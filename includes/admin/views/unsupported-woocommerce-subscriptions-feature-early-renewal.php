<?php
/**
 * Unsupported WooCommerce Subscriptions plugin feature: early renewal.
 *
 * @package FatoriPayWoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="notice notice-warning">
	<p>
		<span class="dashicons-before dashicons-warning"></span><strong><?php esc_html_e( 'The FatoriPay WooCommerce plugin does not support the early renewal feature.', 'fatoripay-woo' ); ?></strong>
		<?php esc_html_e( 'That\'s why it was automatically deactivated in the client\'s subscription management screen.', 'fatoripay-woo' ); ?>
	</p>
</div>
