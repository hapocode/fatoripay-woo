(function( $ ) {
	'use strict';

	$( function() {

		let fatoripay_barcode = $('#bank_slip_barcode');

		if (fatoripay_barcode.length) {

			JsBarcode('#bank_slip_barcode', fatoripay_bank_slip.bank_slip_barcode, {
				fontSize: 40,
				background: "#000000",
				lineColor: "#ffffff",
				margin: 40,
			}).init();

		  $('#bank_slip_barcode').css('max-width', '100%');

		} // end if;

	});

}( jQuery ));
