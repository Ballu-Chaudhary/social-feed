/**
 * Frontend scripts for Social Feed plugin.
 *
 * @package SocialFeed
 */

(function ($) {
	'use strict';

	$(function () {
		// Carousel scroll behavior
		$('.sf-feed__carousel').each(function () {
			var $carousel = $(this);
			var track = $carousel.find('.sf-feed__carousel-track');
			if (track.length) {
				$carousel.css('cursor', 'grab');
				$carousel.on('mousedown', function (e) {
					var startX = e.pageX - track.offset().left;
					var scrollLeft = track.scrollLeft();
					$carousel.css('cursor', 'grabbing');
					$(document).on('mousemove.sf-carousel', function (e) {
						var x = e.pageX - track.offset().left;
						var walk = (x - startX) * 1.5;
						track.scrollLeft(scrollLeft - walk);
					});
				});
				$(document).on('mouseup', function () {
					$(document).off('mousemove.sf-carousel');
					$carousel.css('cursor', 'grab');
				});
			}
		});
	});

})(jQuery);
