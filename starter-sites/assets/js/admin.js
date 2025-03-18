"use strict";

jQuery( function ($) {

	$( document ).on('click', '.starter-sites-review-notice .notice-dismiss', function () {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'starter_sites_review_notice_dismiss',
				'wpss-review-nonce-name': starter_sites_review_notice.wpss_review_nonce
			}
		});
	});

});
