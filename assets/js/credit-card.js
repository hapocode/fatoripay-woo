/* global fatoripay_wc_credit_card_params */
/*jshint devel: true */
(function( $ ) {
	'use strict';

	$( function() {
		/**
		 * Call Tokenezation
		 */
		const payment = new FatoriPay_.PaymentMethod();

		formMasks();

		$('form.checkout').on('change', '#fatoripay_card_number', function() {

			getInstallments();

			formHandler($('form.checkout'));

		}) // end if;

		$('form.checkout').on('change', '#fatoripay_card_cvv', function() {

			formHandler($('form.checkout'));

		});

		$('form.checkout').on('change', '#fatoripay_card_expiry', function() {

			formHandler($('form.checkout'));

		});

		$('form.checkout').on('change', '#fatoripay_card_name', function() {

			formHandler($('form.checkout'));

		});

		$('body').on('checkout_error', function () {

			$('.fatoripay-token').remove();

		});

		$('form.checkout, form#order_review').on('change', '#fatoripay-credit-card-fields input', function() {

			$('.fatoripay-token').remove();

		});

		/**
		 * Form Handler.
		 *
		 * @param  {object} form
		 * @return {bool}
		 */
		function formHandler(form) {

			if (!$('#payment_method_fatoripay-credit-card').is(':checked')) {

				return true;

			} // end if;

			let $form = $(form);

			let cardExpiry = $form.find('#fatoripay_card_expiry').val();

			let card_number = $form.find('#fatoripay_card_number').val().replace(' ', '');

			let card_cvv = $form.find('#fatoripay_card_cvv').val();

			if (cardExpiry === 'undefined' || card_number === 'undefined' || card_cvv === 'undefined') {

				return false;

			} // end if;

				//credit card data
				let card_data = {
					creditCard: card_number,
					cvv: card_cvv,
					expiration: {
							month: cardExpiry.substring(0, 2),
							year: cardExpiry.substring(5)
					}
				};

				/**
				 * callback function for payment.getDirectToken function
				 *
				 * @param {object} err Error
				 * @param {string} directToken Payment method token.
				 * @returns
				 */
				const fatoripay_callback = function(err, directToken) {

					if (err) {

						if (fatoripay_wc_credit_card_params.sandbox) {

							//console.log('ERROR: ' + err);

						} // end if;

						return false;

					} // end if;

					$form.find('#fatoripay_card_token').val(directToken);

				};

				payment.getDirectToken(card_data, fatoripay_callback);

		} // end formHandler;

		/**
		 * Field mask in the credit card form.
		 *
		 * @returns void.
		 */
		function formMasks() {

			if (fatoripay_wc_credit_card_params !== 'undefined') {

				$.each(fatoripay_wc_credit_card_params.masks, function (field, mask) {

					$('[name=' + field + ']').mask(mask);

				});

			} // end if;

		} // end formMasks;

		/**
		 *
		 */
		function getInstallments() {

			let installments_params = {
				card_number: $('[name="fatoripay_card_number"]').val(),
				amount: fatoripay_wc_credit_card_params.order_total,
				country: $('[name="billing_country"]').val()
			};

			$.ajax({
				type: 'POST',
				url: fatoripay_wc_credit_card_params.ajaxurl,
				data: installments_params,
				dataType: 'json',
				processData: true,
				success: function (response) {

					if (response.success) {

						let instalmments_select = $('[name="fatoripay_card_installments"]');

						instalmments_select.empty();

						Object.entries(response.data.installments).forEach(([key, value]) => {

							instalmments_select.append(value);

						});

						instalmments_select.attr('type', 'select')

						$('#fatoripay_card_brand').val(response.data.brand);

					} // end if;

				}

			});

		}

	});

}( jQuery ));
