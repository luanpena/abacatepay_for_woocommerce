/**
 * AbacatePay Checkout Script
 */

jQuery(function($) {
	'use strict';

	// Handle payment method selection
	$('body').on('change', 'input[name="payment_method"]', function() {
		if ('abacatepay' === $(this).val()) {
			$('.payment_method_abacatepay').show();
		} else {
			$('.payment_method_abacatepay').hide();
		}
	});

	// Trigger initial state
	$('input[name="payment_method"]:checked').trigger('change');

	// Handle form submission
	$('form.checkout').on('checkout_place_order_abacatepay', function() {
		// Validate form
		if (!validateCheckoutForm()) {
			return false;
		}

		return true;
	});

	/**
	 * Validate checkout form
	 */
	function validateCheckoutForm() {
		var isValid = true;

		// Add custom validation here if needed

		return isValid;
	}

	// Handle AJAX responses
	$(document).on('ajaxComplete', function(event, xhr, settings) {
		if (settings.url.indexOf('wc-ajax=checkout') !== -1) {
			var response = xhr.responseJSON;

			if (response && response.result === 'success') {
				// Payment processing started
				console.log('Payment processing started');
			} else if (response && response.result === 'failure') {
				// Payment failed
				console.log('Payment failed:', response.messages);
			}
		}
	});
});
