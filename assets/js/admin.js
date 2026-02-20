/**
 * Admin scripts for Social Feed plugin.
 *
 * @package SocialFeed
 */

(function ($) {
	'use strict';

	$(function () {
		// Admin initialization
		$('#sf-settings-app').on('click', '.sf-refresh-feed', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var platform = $btn.data('platform');
			if (!platform) return;

			$btn.prop('disabled', true);
			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_refresh_feed',
					nonce: sfAdmin.nonce,
					platform: platform
				},
				success: function (response) {
					if (response.success) {
						alert('Feed refreshed successfully.');
					} else {
						alert(response.data.message || 'Error refreshing feed.');
					}
				},
				error: function () {
					alert('Request failed.');
				},
				complete: function () {
					$btn.prop('disabled', false);
				}
			});
		});
	});

})(jQuery);
