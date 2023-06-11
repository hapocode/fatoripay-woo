<?php
/**
 * Ticket print link
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
		<?php if ( property_exists( $data, 'installment' ) ) : ?>
			<li>
				<?php esc_html_e( 'Your tickets are ready.', 'fatoripay-woo' ); ?>
			</li>
			<?php
			$installments       = array_reverse( $data->installments->data );
			$installments_count = count( $installments );
			$count              = 1;
			foreach ( $installments as $installment ) :
				$ticket_url = $installment->bankSlipUrl; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
				?>
				<li>
					<?php
					/* translators: %1$s: count installments %2$s: total installments  */
					echo esc_html( sprintf( __( 'Installment %1$s of %2$s.', 'fatoripay-woo' ), $count, $installments_count ) );
					?>
					<br>
					<a class="button woocommerce-order-details__fatoripay-ticket-button" href="<?php echo esc_url( $ticket_url ); ?>" target="_blank">
						<?php esc_html_e( 'Access ticket', 'fatoripay-woo' ); ?>
					</a>
				</li>
					<?php
					$count++;
			endforeach;
		else :
			$ticket_url = $data->bankSlipUrl; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
			?>
			<li>
				<?php esc_html_e( 'Your ticket is ready.', 'fatoripay-woo' ); ?>
				<div>
					<a class="button woocommerce-order-details__fatoripay-ticket-button" href="<?php echo esc_url( $ticket_url ); ?>" target="_blank">
						<?php esc_html_e( 'Access ticket', 'fatoripay-woo' ); ?>
					</a>
				</div>
			</li>
		<?php endif; ?>
	</ul>
</section>
