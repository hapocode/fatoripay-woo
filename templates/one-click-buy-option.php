<?php
/**
 * One click buy options
 *
 * @package FatoriPayWoo
 */

use WC_FatoriPay\WC_FatoriPay;

$brand_image = WC_FatoriPay::get_instance()->get_assets_url() . 'images/' . strtolower( $card['creditCardBrand'] ) . '.png';

?>
<div class="number">
	<span class="bullets">
		<?php
		for ( $i = 0; $i < 12; $i++ ) :
			echo '&bull;';

			if ( 3 === $i % 4 ) :
				echo ' ';
				endif;
			endfor;
		?>
	</span>

	<?php echo esc_html( $card['creditCardNumber'] ); ?>
</div>

<img class="brand" alt="<?php echo esc_attr( $card['creditCardBrand'] ); ?>" src="<?php echo esc_url( $brand_image ); ?>" />
