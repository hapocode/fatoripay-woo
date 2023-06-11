(function( $ ) {
	'use strict';

	$(function() {

    $('form.checkout').on('click', '#fatoripay_wallet_pagseguro_label', function() {

      $('#fatoripay_wallet_pagseguro_img').addClass('wallet-options-img-disable');

      $('#fatoripay_wallet_paypal_img').removeClass('wallet-options-img-disable');

		});

    $('form.checkout').on('click', '#fatoripay_wallet_paypal_label', function() {

      $('#fatoripay_wallet_paypal_img').addClass('wallet-options-img-disable');

      $('#fatoripay_wallet_pagseguro_img').removeClass('wallet-options-img-disable');

		});

  });

}( jQuery ));
