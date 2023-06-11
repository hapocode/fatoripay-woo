<?php
/**
 * Pix print link
 *
 * @package FatoriPayWoo
 */

use WC_FatoriPay\Helper\Checkout_Helper;
use WC_FatoriPay\WC_FatoriPay;

$checkout_helper = new Checkout_Helper();
$data            = $order->get_meta_data();

?>
<section class="woocommerce-order-details">
	<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Payment details', 'fatoripay-woo' ); ?></h2>

	<ul class="order_details">
		<li>
			<?php esc_html_e( 'Pay with Pix.', 'fatoripay-woo' ); ?>
		</li>
		<li class="fatoripay-pix-instructions">
			<img
				class="js-pix-qr-code"
				height="250px" width="250px"
				src="data:image/jpeg;base64,
				<?php /* phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar */ echo esc_attr( $data->encodedImage ); ?>"
				alt="QR Code Pix"
			>
			<?php
			WC_FatoriPay::get_instance()->get_template_file(
				'order/pix-thankyou-instructions.php', array(
					'show_copy_and_paste' => $show_copy_and_paste,
					'expiration_time'     => $expiration_time,
					'expiration_period'   => $expiration_period,
				)
			);
			?>
		</li>
		<?php if ( true === $show_copy_and_paste ) : ?>
		<li class="fatoripay-pix-copy-to-clipboard">
			<div>
				<p class="woocommerce-order-details__fatoripay-pix-payload"><?php echo esc_attr( $data->payload ); ?></p>
				<input class="woocommerce-order-details__fatoripay-pix-code" type="hidden" value="<?php echo esc_attr( $data->payload ); ?>">
				<button class="button woocommerce-order-details__fatoripay-pix-button" data-success-copy="<?php esc_html_e( 'Code copied to clipboard', 'fatoripay-woo' ); ?>">
					<?php esc_html_e( 'Click here to copy the Pix code', 'fatoripay-woo' ); ?>
				</button>
			</div>
		</li>
		<?php endif; ?>
	</ul>
</section>
