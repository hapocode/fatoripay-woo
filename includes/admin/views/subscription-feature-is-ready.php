<?php
/**
 * Subscription feature is ready notice.
 *
 * @package FatoriPayWoo
 */

use WC_FatoriPay\Helper\Subscriptions_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$subscriptions_helper = new Subscriptions_Helper();
?>
<div class="subscription-feature-is-ready">
	<p>
		<?php esc_html_e( 'The plugin is able to process subscription payments.', 'fatoripay-woo' ); ?>
	</p>
	<code>
		<span class="dashicons-before dashicons-warning"></span>
		<?php
			/* translators: %s: billing cycles */
			echo esc_html( sprintf( __( 'We currently support the following billing cycles: %s.', 'fatoripay-woo' ), $subscriptions_helper->get_supported_billing_periods_string() ) );
		?>
	</code>
</div>
