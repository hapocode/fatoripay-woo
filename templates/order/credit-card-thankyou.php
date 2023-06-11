<?php
/**
 * Credit card status after checkout
 *
 * @package FatoriPayWoo
 */

use WC_FatoriPay\Helper\Checkout_Helper;

$checkout_helper = new Checkout_Helper();
$data            = $order->get_meta_data();

?>
<section class="woocommerce-order-details">
	<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Payment details', 'fatoripay-woo' ); ?></h2>

	<ul class="order_details">
		<li>
			<?php
				/* translators: %s: the order status  */
				echo wp_kses_post( sprintf( __( 'Status: <strong>%s</strong>', 'fatoripay-woo' ), $checkout_helper->convert_status( $data->status ) ) );
			?>
		</li>
	</ul>
</section>
