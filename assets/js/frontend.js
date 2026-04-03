( function( $ ) {
	'use strict';

	function refreshBanner() {
		var $banner = $( '[data-wc-cart-discount-progress]' );

		if ( ! $banner.length || typeof WCCartDiscountProgress === 'undefined' ) {
			return;
		}

		$.post( WCCartDiscountProgress.ajaxUrl, {
			action: 'wc_cart_discount_progress_banner',
			nonce: WCCartDiscountProgress.nonce
		} ).done( function( response ) {
			if ( response && response.success && response.data && response.data.markup ) {
				$banner.replaceWith( response.data.markup );
			}
		} );
	}

	$( document.body ).on( 'updated_checkout updated_cart_totals wc_fragments_loaded wc_fragments_refreshed', refreshBanner );
} )( jQuery );
