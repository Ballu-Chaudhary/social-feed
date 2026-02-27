/**
 * Frontend JavaScript for Social Feed
 *
 * @package SocialFeed
 */

(function ($) {
	'use strict';

	/**
	 * Social Feed Frontend Module
	 */
	var SF_Frontend = {
		/**
		 * Initialize all functionality.
		 */
		init: function () {
			this.bindEvents();
			this.initLightbox();
			this.initInfiniteScroll();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function () {
			$(document).on('click', '.sf-feed__load-more-btn', this.handleLoadMore.bind(this));
			$(document).on('click', '.sf-feed__page-btn', this.handlePagination.bind(this));
			$(document).on('click', '.sf-lightbox-trigger', this.handleLightboxOpen.bind(this));
			$(document).on('click', '.sf-lightbox__close', this.handleLightboxClose.bind(this));
			$(document).on('click', '.sf-lightbox__nav', this.handleLightboxNav.bind(this));
			$(document).on('click', '.sf-lightbox', this.handleLightboxOverlayClick.bind(this));
			$(document).on('keydown', this.handleKeyboard.bind(this));
		},

		/**
		 * Handle Load More button click.
		 *
		 * @param {Event} e Click event.
		 */
		handleLoadMore: function (e) {
			e.preventDefault();

			var $btn = $(e.currentTarget);
			var $feed = $btn.closest('.sf-feed');
			var feedId = $feed.data('feed-id');
			var cursor = $btn.data('cursor');

			if ($btn.hasClass('loading') || !cursor) {
				return;
			}

			$btn.addClass('loading').prop('disabled', true);
			var originalText = $btn.text();
			$btn.html('<span class="sf-feed__loader" style="width:20px;height:20px;display:inline-block;vertical-align:middle;margin-right:8px;"></span>' + (sfFrontend.i18n.loading || 'Loading...'));

			this.fetchMoreItems(feedId, cursor, function (response) {
				$btn.removeClass('loading').prop('disabled', false);

				if (response.success && response.data.html) {
					var $items = $feed.find('.sf-feed__items');
					$items.append(response.data.html);

					if (response.data.next_cursor) {
						$btn.data('cursor', response.data.next_cursor).text(originalText);
					} else {
						$btn.parent().html('<p class="sf-feed__no-more">' + (sfFrontend.i18n.no_more || 'No more posts') + '</p>');
					}

					$(document).trigger('sf:items_loaded', [$feed, response.data]);
				} else {
					$btn.text(originalText);
					console.error('Social Feed: Load more failed');
				}
			});
		},

		/**
		 * Handle pagination button click.
		 *
		 * @param {Event} e Click event.
		 */
		handlePagination: function (e) {
			e.preventDefault();

			var $btn  = $(e.currentTarget);
			var $feed = $btn.closest('.sf-feed');
			var $wrap = $btn.closest('.sf-feed__pagination');
			var feedId = $feed.data('feed-id');
			var page   = parseInt($btn.data('page'), 10);
			var cursor = $wrap.data('cursor');

			if ($btn.hasClass('active') || $btn.hasClass('loading') || !cursor) {
				return;
			}

			$wrap.find('.sf-feed__page-btn').prop('disabled', true);
			$btn.addClass('loading');

			this.fetchMoreItems(feedId, cursor + ':page:' + page, function (response) {
				$wrap.find('.sf-feed__page-btn').prop('disabled', false);
				$btn.removeClass('loading');

				if (response.success && response.data.html) {
					var $items = $feed.find('.sf-feed__items');
					$items.html(response.data.html);

					$wrap.find('.sf-feed__page-btn').removeClass('active');
					$btn.addClass('active');

					if (response.data.next_cursor) {
						$wrap.data('cursor', response.data.next_cursor);
					}

					if (response.data.total_pages) {
						var totalPages = response.data.total_pages;
						var currentCount = $wrap.find('.sf-feed__page-btn').length;
						for (var p = currentCount + 1; p <= totalPages; p++) {
							$wrap.append('<button type="button" class="sf-feed__page-btn" data-page="' + p + '">' + p + '</button>');
						}
					}

					$feed[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
					$(document).trigger('sf:items_loaded', [$feed, response.data]);
				}
			});
		},

		/**
		 * Fetch more items via AJAX.
		 *
		 * @param {number}   feedId   Feed ID.
		 * @param {string}   cursor   Pagination cursor.
		 * @param {Function} callback Callback function.
		 */
		fetchMoreItems: function (feedId, cursor, callback) {
			$.ajax({
				url: sfFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_load_more',
					nonce: sfFrontend.nonce,
					feed_id: feedId,
					cursor: cursor
				},
				success: callback,
				error: function () {
					callback({ success: false });
				}
			});
		},

		/**
		 * Initialize infinite scroll.
		 */
		initInfiniteScroll: function () {
			var self = this;

			$('.sf-feed[data-infinite="true"]').each(function () {
				var $feed = $(this);
				var $trigger = $feed.find('.sf-feed__infinite-trigger');

				if (!$trigger.length) {
					return;
				}

				self.setupIntersectionObserver($feed, $trigger);
			});
		},

		/**
		 * Set up Intersection Observer for infinite scroll.
		 *
		 * @param {jQuery} $feed    Feed element.
		 * @param {jQuery} $trigger Trigger element.
		 */
		setupIntersectionObserver: function ($feed, $trigger) {
			var self = this;
			var loading = false;

			if (!('IntersectionObserver' in window)) {
				return;
			}

			var observer = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting && !loading) {
						var cursor = $trigger.data('cursor');
						var feedId = $feed.data('feed-id');

						if (!cursor) {
							observer.disconnect();
							$trigger.remove();
							return;
						}

						loading = true;
						$trigger.find('.sf-feed__loader').show();

						self.fetchMoreItems(feedId, cursor, function (response) {
							loading = false;

							if (response.success && response.data.html) {
								var $items = $feed.find('.sf-feed__items');
								$items.append(response.data.html);

								if (response.data.next_cursor) {
									$trigger.data('cursor', response.data.next_cursor);
								} else {
									observer.disconnect();
									$trigger.remove();
								}

								$(document).trigger('sf:items_loaded', [$feed, response.data]);
							} else {
								$trigger.find('.sf-feed__loader').hide();
							}
						});
					}
				});
			}, {
				rootMargin: '200px'
			});

			observer.observe($trigger[0]);
		},

		/**
		 * Initialize lightbox.
		 */
		initLightbox: function () {
			if (!$('.sf-feed--lightbox').length) {
				return;
			}

			if (!$('#sf-lightbox').length) {
				this.createLightboxHTML();
			}
		},

		/**
		 * Create lightbox HTML structure.
		 */
		createLightboxHTML: function () {
			var html = '<div id="sf-lightbox" class="sf-lightbox" role="dialog" aria-modal="true" aria-label="' + (sfFrontend.i18n.lightbox || 'Image lightbox') + '">' +
				'<button type="button" class="sf-lightbox__close" aria-label="' + (sfFrontend.i18n.close || 'Close') + '">' +
				'<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>' +
				'</button>' +
				'<button type="button" class="sf-lightbox__nav sf-lightbox__nav--prev" aria-label="' + (sfFrontend.i18n.prev || 'Previous') + '">' +
				'<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>' +
				'</button>' +
				'<button type="button" class="sf-lightbox__nav sf-lightbox__nav--next" aria-label="' + (sfFrontend.i18n.next || 'Next') + '">' +
				'<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>' +
				'</button>' +
				'<div class="sf-lightbox__content"></div>' +
				'<div class="sf-lightbox__caption"></div>' +
				'</div>';

			$('body').append(html);
		},

		/**
		 * Handle lightbox open.
		 *
		 * @param {Event} e Click event.
		 */
		handleLightboxOpen: function (e) {
			e.preventDefault();

			var $link = $(e.currentTarget);
			var $feed = $link.closest('.sf-feed');
			var $item = $link.closest('.sf-feed__item');

			var media = $link.data('media') || $link.attr('href');
			var type = $link.data('type') || 'image';
			var caption = $link.data('caption') || '';

			this.currentFeed = $feed;
			this.currentItems = $feed.find('.sf-lightbox-trigger');
			this.currentIndex = this.currentItems.index($link);

			this.openLightbox(media, type, caption);
		},

		/**
		 * Open lightbox with media.
		 *
		 * @param {string} media   Media URL.
		 * @param {string} type    Media type.
		 * @param {string} caption Caption text.
		 */
		openLightbox: function (media, type, caption) {
			var $lightbox = $('#sf-lightbox');
			var $content = $lightbox.find('.sf-lightbox__content');
			var $caption = $lightbox.find('.sf-lightbox__caption');

			$content.empty();

			if (type === 'video' || type === 'reel') {
				$content.html('<video class="sf-lightbox__media" src="' + media + '" controls autoplay playsinline></video>');
			} else {
				$content.html('<img class="sf-lightbox__media" src="' + media + '" alt="">');
			}

			if (caption) {
				$caption.text(caption).show();
			} else {
				$caption.hide();
			}

			this.updateNavButtons();

			$lightbox.addClass('active');
			$('body').css('overflow', 'hidden');

			$lightbox.find('.sf-lightbox__close').focus();
		},

		/**
		 * Handle lightbox close.
		 */
		handleLightboxClose: function () {
			var $lightbox = $('#sf-lightbox');
			$lightbox.removeClass('active');
			$('body').css('overflow', '');

			var $content = $lightbox.find('.sf-lightbox__content');
			var $video = $content.find('video');

			if ($video.length) {
				$video[0].pause();
			}

			$content.empty();
		},

		/**
		 * Handle lightbox overlay click.
		 *
		 * @param {Event} e Click event.
		 */
		handleLightboxOverlayClick: function (e) {
			if ($(e.target).hasClass('sf-lightbox')) {
				this.handleLightboxClose();
			}
		},

		/**
		 * Handle lightbox navigation.
		 *
		 * @param {Event} e Click event.
		 */
		handleLightboxNav: function (e) {
			e.stopPropagation();

			var $btn = $(e.currentTarget);
			var direction = $btn.hasClass('sf-lightbox__nav--prev') ? -1 : 1;

			this.navigateLightbox(direction);
		},

		/**
		 * Navigate lightbox.
		 *
		 * @param {number} direction Direction (-1 or 1).
		 */
		navigateLightbox: function (direction) {
			if (!this.currentItems || !this.currentItems.length) {
				return;
			}

			this.currentIndex += direction;

			if (this.currentIndex < 0) {
				this.currentIndex = this.currentItems.length - 1;
			} else if (this.currentIndex >= this.currentItems.length) {
				this.currentIndex = 0;
			}

			var $item = this.currentItems.eq(this.currentIndex);
			var media = $item.data('media') || $item.attr('href');
			var type = $item.data('type') || 'image';
			var caption = $item.data('caption') || '';

			this.openLightbox(media, type, caption);
		},

		/**
		 * Update navigation buttons visibility.
		 */
		updateNavButtons: function () {
			var $lightbox = $('#sf-lightbox');
			var $prevBtn = $lightbox.find('.sf-lightbox__nav--prev');
			var $nextBtn = $lightbox.find('.sf-lightbox__nav--next');

			if (!this.currentItems || this.currentItems.length <= 1) {
				$prevBtn.hide();
				$nextBtn.hide();
			} else {
				$prevBtn.show();
				$nextBtn.show();
			}
		},

		/**
		 * Handle keyboard events.
		 *
		 * @param {Event} e Keydown event.
		 */
		handleKeyboard: function (e) {
			var $lightbox = $('#sf-lightbox');

			if (!$lightbox.hasClass('active')) {
				return;
			}

			switch (e.key) {
				case 'Escape':
					this.handleLightboxClose();
					break;
				case 'ArrowLeft':
					this.navigateLightbox(-1);
					break;
				case 'ArrowRight':
					this.navigateLightbox(1);
					break;
			}
		}
	};

	$(function () {
		SF_Frontend.init();
	});

	window.SF_Frontend = SF_Frontend;

})(jQuery);
