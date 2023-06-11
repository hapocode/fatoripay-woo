<?php
/**
 * Checkout card expiration fields class
 *
 * @package FatoriPayWoo
 */

namespace WC_FatoriPay\Checkout\Form_Field;

use WC_FatoriPay\WC_FatoriPay;
use WC_FatoriPay\Helper\Validation_Helper;
use WC_FatoriPay\Gateway\Gateway;

/**
 * Checkout card expiration fields
 */
class Card extends Form_Field {

	/**
	 * The unique WooCommerce field type
	 *
	 * @var string
	 */
	public function get_type() {
		return 'fatoripay-card';
	}

	/**
	 * Render card fields
	 *
	 * First create a string with all fields HTML and after return or echo based in the `$args['return']` value.
	 *
	 * @see woocommerce_form_field()
	 *
	 * @param string $field The field HTML, not used.
	 * @param string $key The field key.
	 * @param array  $args The field args.
	 * @param string $value The current field value.
	 */
	public function field( $field, $key, $args, $value ) {
		$return         = $args['return'];
		$args['return'] = true;
		$field          = '';

		$field .= '<div class="fatoripay-cc-form-wrapper">';

		foreach ( $this->get_subfields( $key, $args ) as $sub_key => $args ) {
			$field .= woocommerce_form_field( $sub_key, $args, $value );
		}

		$field .= '</div>';

		if ( $return ) {
			return $field;
		}

		echo wp_kses_post( $field );
	}

	/**
	 * Get month and year fields
	 *
	 * @param string $key The field key.
	 * @param string $args The field args.
	 * @return array The subfields of expiration date.
	 */
	public function get_subfields( $key, $args ) {
		$fields_class = array( 'fatoripay-cc-form-field', 'form-row-wide' );

		return apply_filters(
			'woocommerce_fatoripay_card_subfields', array(
				$key . '_name'          => array_merge(
					$args, array(
						'type'     => 'text',
						'label'    => __( 'Name on card', 'fatoripay-woo' ),
						'required' => true,
						'class'    => $fields_class,
						'id'       => 'fatoripay-cc-name',
					)
				),
				$key . '_number'        => array_merge(
					$args, array(
						'type'     => Card_Number::get_instance()->get_type(),
						'label'    => __( 'Card number', 'fatoripay-woo' ),
						'required' => true,
						'class'    => $fields_class,
						'id'       => 'fatoripay-cc-number',
					)
				),
				$key . '_expiration'    => array_merge(
					$args, array(
						'type'  => Card_Expiration::get_instance()->get_type(),
						'label' => __( 'Expiration', 'fatoripay-woo' ),
						'class' => $fields_class,
						'id'    => 'fatoripay-cc-expiration',
					)
				),
				$key . '_security_code' => array_merge(
					$args, array(
						'type'     => Card_Security_Code::get_instance()->get_type(),
						'label'    => __( 'Security code', 'fatoripay-woo' ),
						'required' => true,
						'class'    => $fields_class,
						'id'       => 'fatoripay-cc-security-code',
					)
				),
			), $key
		);
	}

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \WC_FatoriPay\Checkout\Form_Field\Form_Field::validate()
	 */

	/**
	 * Validate card fields
	 *
	 * @param Gateway $gateway The gateway is being processed.
	 * @param array   $key The field key.
	 * @param array   $field The field args.
	 * @param array   $data The payment form posted data.
	 */
	public function validate( $gateway, $key, $field, $data ) {
		$validation_helper = new Validation_Helper();

		foreach ( $this->get_subfields( $key, $field ) as $subkey => $subfield ) {
			$field_type = WC_FatoriPay::get_instance()->get_form_field_object_from_type( $subfield['type'] );

			if ( is_null( $field_type ) ) {
				$validation_helper->validate_required( $gateway, $subkey, $subfield, $data );
				continue;
			}

			$field_type->validate( $gateway, $key, $subfield, $data );
		}
	}
}
