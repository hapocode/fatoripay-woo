(function( $ ) {
	'use strict';

	$(function() {

        const urlParams = new URLSearchParams(window.location.search);

        if (urlParams.has('code')) {
            const code = urlParams.get('code');
            const state = urlParams.get('state');

            const data = {
                action: 'wc_fatoripay_oauth',
                code: code,
                state: state
            };

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: data,
				dataType: 'json',
				processData: true,
				success: function (response) {
                    // remove code and state from url
                    let url = new URL(window.location.href);
                    url.searchParams.delete("code")
                    console.log(url)
                    alert('Recarregue a página para ver as alterações.')
                }
			});
        }

    });

})( jQuery );