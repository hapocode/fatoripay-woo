<?php
/**
 * Unsupported WooCommerce Subscriptions plugin feature: manual renewal.
 *
 * @package FatoriPayWoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page = sanitize_text_field( wp_unslash( isset( $_GET['page'] ) ? $_GET['page'] : '' ) );
$tab  = sanitize_text_field( wp_unslash( isset( $_GET['tab'] ) ? $_GET['tab'] : '' ) );
?>

<div class="notice notice-error">
	<p>
		<span class="dashicons-before dashicons-dismiss components-dropdown"></span><strong><?php esc_html_e( 'The FatoriPay WooCommerce plugin does not support the manual renewal feature.', 'fatoripay-woo' ); ?></strong>
	<?php
	if ( 'wc-settings' === $page && 'subscriptions' === $tab ) {
		?>
		<?php esc_html_e( 'Please, review this setting.', 'fatoripay-woo' ); ?>
		<?php
	} else {
		$button_action = self_admin_url( 'admin.php?page=wc-settings&tab=subscriptions' );
		$button_label  = __( 'Click here to review WooCommerce Subscriptions settings', 'fatoripay-woo' );
		?>
		<a href="<?php echo esc_url( $button_action ); ?>" class="button button-primary"><?php echo esc_html( $button_label ); ?></a>
		<?php
	}
	?>
	</p>
</div>