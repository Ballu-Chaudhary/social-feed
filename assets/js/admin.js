/**
 * Admin scripts for Social Feed plugin.
 *
 * @package SocialFeed
 */

(function ($) {
	'use strict';

	/**
	 * Accounts page module.
	 */
	var SF_Accounts = {
		$grid: null,
		selectedPlatform: null,
		oauthWindow: null,
		reconnectAccountId: null,

		init: function () {
			this.$grid = $('.sf-accounts-grid');

			if (!$('.sf-accounts-wrap').length) {
				return;
			}

			this.bindEvents();
			this.listenForOAuthMessages();
		},

		bindEvents: function () {
			var self = this;

			$(document).on('click', '.sf-connect-account-btn', function () {
				self.selectedPlatform = 'instagram';
				self.reconnectAccountId = null;
				self.startOAuth();
			});

			$(document).on('click', '.sf-reconnect-account', function () {
				var accountId = $(this).data('account-id');
				var platform = $(this).data('platform');
				self.reconnectAccount(accountId, platform);
			});

			$(document).on('click', '.sf-delete-account', function () {
				var $card = $(this).closest('.sf-account-card');
				var accountId = $(this).data('account-id');
				var feedsCount = $(this).data('feeds');
				self.deleteAccount(accountId, feedsCount, $card);
			});
		},

		startOAuth: function () {
			var self = this;

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: self.reconnectAccountId ? 'sf_reconnect_account' : 'sf_get_oauth_url',
					nonce: sfAdmin.nonce,
					platform: self.selectedPlatform,
					account_id: self.reconnectAccountId
				},
				success: function (response) {
					if (response.success && response.data.url) {
						self.openOAuthPopup(response.data.url);
					} else {
						alert(response.data.message || sfAdmin.i18n.error);
					}
				},
				error: function () {
					alert(sfAdmin.i18n.error);
				}
			});
		},

		openOAuthPopup: function (url) {
			var width = 600;
			var height = 700;
			var left = (screen.width - width) / 2;
			var top = (screen.height - height) / 2;

			this.oauthWindow = window.open(
				url,
				'sf_oauth_popup',
				'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes'
			);

			if (!this.oauthWindow) {
				alert(sfAdmin.i18n.popup_blocked || 'Please allow popups for this site.');
			}
		},

		listenForOAuthMessages: function () {
			var self = this;

			window.addEventListener('message', function (event) {
				if (event.data && event.data.type === 'sf_oauth_success') {
					self.handleOAuthSuccess(event.data);
				} else if (event.data && event.data.type === 'sf_oauth_error') {
					self.handleOAuthError(event.data);
				}
			});
		},

		handleOAuthSuccess: function (data) {
			var self = this;

			if (this.oauthWindow && !this.oauthWindow.closed) {
				this.oauthWindow.close();
			}

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_save_account',
					nonce: sfAdmin.nonce,
					platform: data.platform,
					account_name: data.account_name,
					account_id_ext: data.account_id,
					access_token: data.access_token,
					refresh_token: data.refresh_token || '',
					expires_in: data.expires_in || 0,
					profile_pic: data.profile_pic || '',
					already_saved: data.already_saved || false
				},
				success: function (response) {
					if (response.success) {
						self.refreshAccountsList();
					} else {
						alert(response.data.message || sfAdmin.i18n.error);
					}
				},
				error: function () {
					alert(sfAdmin.i18n.error);
				}
			});
		},

		handleOAuthError: function (data) {
			if (this.oauthWindow && !this.oauthWindow.closed) {
				this.oauthWindow.close();
			}

			alert(data.message || sfAdmin.i18n.error);
		},

		reconnectAccount: function (accountId, platform) {
			this.reconnectAccountId = accountId;
			this.selectedPlatform = platform || 'instagram';
			this.startOAuth();
		},

		deleteAccount: function (accountId, feedsCount, $card) {
			var self = this;

			var message = sfAdmin.i18n.confirm_delete_account || 'Are you sure you want to delete this account?';
			if (feedsCount > 0) {
				message += '\n\n' + (sfAdmin.i18n.feeds_using_account || 'This account is used by %d feed(s). They will be disconnected.').replace('%d', feedsCount);
			}

			if (!confirm(message)) {
				return;
			}

			$card.css('opacity', '0.5');

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_delete_account',
					nonce: sfAdmin.nonce,
					account_id: accountId
				},
				success: function (response) {
					if (response.success) {
						$card.slideUp(300, function () {
							$card.remove();
							if (!$('.sf-account-card').length) {
								self.showEmptyState();
							}
						});
					} else {
						$card.css('opacity', '1');
						alert(response.data.message || sfAdmin.i18n.error);
					}
				},
				error: function () {
					$card.css('opacity', '1');
					alert(sfAdmin.i18n.error);
				}
			});
		},

		showEmptyState: function () {
			var emptyState = '<div class="sf-empty-state-large sf-accounts-empty">' +
				'<div class="sf-empty-illustration">' +
				'<svg width="120" height="120" viewBox="0 0 120 120" fill="none">' +
				'<circle cx="60" cy="60" r="50" fill="#f0f0f1"/>' +
				'<circle cx="60" cy="45" r="18" fill="#c3c4c7"/>' +
				'<path d="M35 85c0-13.8 11.2-25 25-25s25 11.2 25 25" stroke="#c3c4c7" stroke-width="8" fill="none"/>' +
				'<circle cx="90" cy="85" r="18" fill="#2271b1"/>' +
				'<path d="M90 77v16M82 85h16" stroke="#fff" stroke-width="3" stroke-linecap="round"/>' +
				'</svg></div>' +
				'<h2>' + (sfAdmin.i18n.no_accounts || 'No accounts connected') + '</h2>' +
				'<p>' + (sfAdmin.i18n.connect_first || 'Connect your social media accounts to start displaying feeds on your website.') + '</p>' +
				'<button type="button" class="button button-primary button-hero sf-connect-account-btn">' +
				(sfAdmin.i18n.connect_first_account || 'Connect Your First Account') +
				'</button></div>';

			this.$grid.hide().after(emptyState);
		},

		refreshAccountsList: function () {
			location.reload();
		}
	};

	var SF_Admin = {
		/**
		 * Initialize admin functionality.
		 */
		init: function () {
			this.bindEvents();
			this.initColorPicker();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function () {
			$(document).on('click', '.sf-copy-btn', this.handleCopy);
			$(document).on('change', '.sf-status-toggle', this.handleStatusToggle);
			$(document).on('click', '.sf-clear-cache-btn', this.handleClearCache);
			$(document).on('click', '.sf-delete-feed', this.handleDeleteFeed);
			$(document).on('click', '.sf-duplicate-feed', this.handleDuplicateFeed);
			$(document).on('click', '.sf-bulk-apply-btn', this.handleBulkAction);
			$(document).on('change', '.sf-select-all', this.handleSelectAll);
		},

		/**
		 * Initialize color picker.
		 */
		initColorPicker: function () {
			if ($.fn.wpColorPicker) {
				$('.sf-color-picker').wpColorPicker();
			}
		},

		/**
		 * Copy text to clipboard.
		 *
		 * @param {Event} e Click event.
		 */
		handleCopy: function (e) {
			e.preventDefault();

			var $btn = $(this);
			var text = $btn.data('copy');

			if (!text) {
				return;
			}

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(function () {
					SF_Admin.showCopySuccess($btn);
				}).catch(function () {
					SF_Admin.fallbackCopy(text, $btn);
				});
			} else {
				SF_Admin.fallbackCopy(text, $btn);
			}
		},

		/**
		 * Fallback copy method for older browsers.
		 *
		 * @param {string} text Text to copy.
		 * @param {jQuery} $btn Button element.
		 */
		fallbackCopy: function (text, $btn) {
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();

			try {
				document.execCommand('copy');
				SF_Admin.showCopySuccess($btn);
			} catch (err) {
				console.error('Copy failed:', err);
			}

			$temp.remove();
		},

		/**
		 * Show copy success feedback.
		 *
		 * @param {jQuery} $btn Button element.
		 */
		showCopySuccess: function ($btn) {
			var $icon = $btn.find('.dashicons');
			var originalClass = $icon.attr('class');

			$icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');

			setTimeout(function () {
				$icon.attr('class', originalClass);
			}, 1500);

			SF_Admin.showNotice(sfAdmin.i18n.copied, 'success');
		},

		/**
		 * Handle feed status toggle.
		 *
		 * @param {Event} e Change event.
		 */
		handleStatusToggle: function (e) {
			var $toggle = $(this);
			var feedId = $toggle.data('feed-id');
			var status = $toggle.is(':checked') ? 'active' : 'paused';

			$toggle.prop('disabled', true);

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_update_feed_status',
					nonce: sfAdmin.nonce,
					feed_id: feedId,
					status: status
				},
				success: function (response) {
					if (response.success) {
						SF_Admin.showNotice(sfAdmin.i18n.saved, 'success');
					} else {
						$toggle.prop('checked', !$toggle.is(':checked'));
						SF_Admin.showNotice(response.data.message || sfAdmin.i18n.error, 'error');
					}
				},
				error: function () {
					$toggle.prop('checked', !$toggle.is(':checked'));
					SF_Admin.showNotice(sfAdmin.i18n.error, 'error');
				},
				complete: function () {
					$toggle.prop('disabled', false);
				}
			});
		},

		/**
		 * Handle clear cache button.
		 *
		 * @param {Event} e Click event.
		 */
		handleClearCache: function (e) {
			e.preventDefault();

			var $btn = $(this);
			$btn.prop('disabled', true).text(sfAdmin.i18n.loading);

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_clear_cache',
					nonce: sfAdmin.nonce
				},
				success: function (response) {
					if (response.success) {
						SF_Admin.showNotice(sfAdmin.i18n.cache_cleared, 'success');
					} else {
						SF_Admin.showNotice(response.data.message || sfAdmin.i18n.error, 'error');
					}
				},
				error: function () {
					SF_Admin.showNotice(sfAdmin.i18n.error, 'error');
				},
				complete: function () {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Clear Cache');
				}
			});
		},

		/**
		 * Handle delete feed.
		 *
		 * @param {Event} e Click event.
		 */
		handleDeleteFeed: function (e) {
			e.preventDefault();

			if (!confirm(sfAdmin.i18n.confirm_delete)) {
				return;
			}

			var $link = $(this);
			var feedId = $link.data('feed-id');
			var $row = $link.closest('tr');

			$row.css('opacity', '0.5');

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_delete_feed',
					nonce: sfAdmin.nonce,
					feed_id: feedId
				},
				success: function (response) {
					if (response.success) {
						$row.fadeOut(300, function () {
							$(this).remove();
							SF_Admin.checkEmptyTable();
						});
					} else {
						$row.css('opacity', '1');
						SF_Admin.showNotice(response.data.message || sfAdmin.i18n.error, 'error');
					}
				},
				error: function () {
					$row.css('opacity', '1');
					SF_Admin.showNotice(sfAdmin.i18n.error, 'error');
				}
			});
		},

		/**
		 * Handle duplicate feed.
		 *
		 * @param {Event} e Click event.
		 */
		handleDuplicateFeed: function (e) {
			e.preventDefault();

			var $link = $(this);
			var feedId = $link.data('feed-id');

			$link.text(sfAdmin.i18n.loading);

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_duplicate_feed',
					nonce: sfAdmin.nonce,
					feed_id: feedId
				},
				success: function (response) {
					if (response.success && response.data.redirect) {
						window.location.href = response.data.redirect;
					} else {
						$link.text('Duplicate');
						SF_Admin.showNotice(response.data.message || sfAdmin.i18n.error, 'error');
					}
				},
				error: function () {
					$link.text('Duplicate');
					SF_Admin.showNotice(sfAdmin.i18n.error, 'error');
				}
			});
		},

		/**
		 * Handle bulk action.
		 *
		 * @param {Event} e Click event.
		 */
		handleBulkAction: function (e) {
			e.preventDefault();

			var action = $('.sf-bulk-action').val();
			var feedIds = [];

			$('.sf-feed-checkbox:checked').each(function () {
				feedIds.push($(this).val());
			});

			if (!action) {
				SF_Admin.showNotice('Please select an action.', 'warning');
				return;
			}

			if (feedIds.length === 0) {
				SF_Admin.showNotice('Please select at least one feed.', 'warning');
				return;
			}

			if (action === 'delete' && !confirm(sfAdmin.i18n.confirm_bulk_delete)) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text(sfAdmin.i18n.loading);

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_bulk_action',
					nonce: sfAdmin.nonce,
					bulk_action: action,
					feed_ids: feedIds
				},
				success: function (response) {
					if (response.success) {
						if (action === 'delete') {
							feedIds.forEach(function (id) {
								$('tr[data-feed-id="' + id + '"]').fadeOut(300, function () {
									$(this).remove();
								});
							});
							setTimeout(function () {
								SF_Admin.checkEmptyTable();
							}, 350);
						} else {
							window.location.reload();
						}
					} else {
						SF_Admin.showNotice(response.data.message || sfAdmin.i18n.error, 'error');
					}
				},
				error: function () {
					SF_Admin.showNotice(sfAdmin.i18n.error, 'error');
				},
				complete: function () {
					$btn.prop('disabled', false).text('Apply');
				}
			});
		},

		/**
		 * Handle select all checkbox.
		 *
		 * @param {Event} e Change event.
		 */
		handleSelectAll: function (e) {
			var isChecked = $(this).is(':checked');
			$('.sf-feed-checkbox').prop('checked', isChecked);
			$('.sf-select-all').prop('checked', isChecked);
		},

		/**
		 * Check if table is empty and show empty state.
		 */
		checkEmptyTable: function () {
			var $table = $('.sf-feeds-table');
			if ($table.length && $table.find('tbody tr').length === 0) {
				window.location.reload();
			}
		},

		/**
		 * Show admin notice.
		 *
		 * @param {string} message Notice message.
		 * @param {string} type    Notice type: success, error, warning.
		 */
		showNotice: function (message, type) {
			type = type || 'info';

			var typeClass = 'notice-' + type;
			if (type === 'success') typeClass = 'notice-success';
			if (type === 'error') typeClass = 'notice-error';
			if (type === 'warning') typeClass = 'notice-warning';

			var $notice = $(
				'<div class="notice ' + typeClass + ' is-dismissible sf-admin-notice">' +
				'<p>' + message + '</p>' +
				'<button type="button" class="notice-dismiss">' +
				'<span class="screen-reader-text">Dismiss this notice.</span>' +
				'</button>' +
				'</div>'
			);

			$('.sf-admin-notice').remove();
			$('.sf-admin-title').after($notice);

			$notice.find('.notice-dismiss').on('click', function () {
				$notice.fadeOut(200, function () {
					$(this).remove();
				});
			});

			setTimeout(function () {
				$notice.fadeOut(200, function () {
					$(this).remove();
				});
			}, 5000);
		}
	};

	/**
	 * Feed Customizer Module.
	 */
	var SF_Customizer = {
		previewTimer: null,
		currentDevice: 'desktop',

		/**
		 * Initialize customizer.
		 */
		init: function () {
			this.bindEvents();
			this.initTabs();
			this.initColorPickers();
			this.loadPreview();
		},

		/**
		 * Initialize sidebar: show nav by default.
		 */
		initTabs: function () {
			var $wrap = $('.sf-customizer-wrap');
			if (!$wrap.length) return;

			/* Remove any inline styles from panels */
			$wrap.find('.sf-sidebar-panel').removeAttr('style').removeClass('sf-panel-visible');
			$wrap.find('.sf-sidebar-nav').addClass('sf-sidebar-view-active');
			$wrap.find('.sf-sidebar-panels').removeClass('sf-sidebar-panels-active');
		},

		/**
		 * Switch to a section (show its panel in sidebar).
		 *
		 * @param {jQuery} $wrap    Customizer wrap element.
		 * @param {string} section Section identifier (feed, layout, design, header, post_settings, loadmore, advanced).
		 */
		switchToTab: function ($wrap, section) {
			if (!$wrap || !$wrap.length || !section) return;

			/* Hide nav, show panels container */
			$wrap.find('.sf-sidebar-nav').removeClass('sf-sidebar-view-active');
			$wrap.find('.sf-sidebar-panels').addClass('sf-sidebar-panels-active');

			/* Hide all panels - remove class and inline style */
			$wrap.find('.sf-sidebar-panel').removeClass('sf-panel-visible').removeAttr('style');
			$wrap.find('.sf-tab-content').removeClass('active');

			/* Find target panel by id first, fallback to data-section */
			var $targetPanel = $wrap.find('#sf-panel-' + section);
			if (!$targetPanel.length) {
				$targetPanel = $wrap.find('.sf-sidebar-panel[data-section="' + section + '"]');
			}

			if ($targetPanel.length) {
				/* Show panel by adding class */
				$targetPanel.addClass('sf-panel-visible');
				
				/* Show tab content by adding active class */
				$targetPanel.find('.sf-tab-content').addClass('active');
			}

			try {
				sessionStorage.setItem('sf_customizer_tab', section);
			} catch (e) {}
		},

		/**
		 * Show sidebar nav (back from panel).
		 *
		 * @param {jQuery} $wrap Customizer wrap element.
		 */
		showSidebarNav: function ($wrap) {
			if (!$wrap || !$wrap.length) return;

			$wrap.find('.sf-sidebar-panels').removeClass('sf-sidebar-panels-active');
			$wrap.find('.sf-sidebar-panel').removeClass('sf-panel-visible').removeAttr('style');
			$wrap.find('.sf-tab-content').removeClass('active');
			$wrap.find('.sf-sidebar-nav').addClass('sf-sidebar-view-active');
		},

		/**
		 * Bind customizer events.
		 */
		bindEvents: function () {
			var self = this;

			$(document).on('click', '.sf-sidebar-toplevel-btn', this.handleToplevelClick);
			$(document).on('click', '.sf-sidebar-item', this.handleSidebarItemClick);
			$(document).on('click', '.sf-sidebar-back', this.handleSidebarBack);
			$(document).on('click', '.sf-device-btn', this.handleDeviceSwitch.bind(this));
			$(document).on('click', '.sf-refresh-preview', this.loadPreview.bind(this));
			$(document).on('click', '.sf-save-feed', this.handleSave.bind(this));

			$(document).on('click', '.sf-feed-name-edit', this.handleFeedNameEdit);
			$(document).on('blur', '.sf-feed-name-input', this.handleFeedNameBlur);
			$(document).on('keydown', '.sf-feed-name-input', function (e) {
				if (e.key === 'Enter') {
					$(this).blur();
				}
			});

			$(document).on('click', '.sf-help-btn', this.handleHelpClick);
			$(document).on('click', '.sf-embed-btn', this.handleEmbedClick);
			$(document).on('click', '.sf-embed-modal-close, .sf-embed-modal-overlay', function () {
				$('#sf-embed-modal').hide();
			});
			$(document).on('click', '#sf-embed-modal .sf-copy-btn', function () {
				var copy = $(this).data('copy');
				if (copy) {
					try {
						var decoded = $('<textarea>').html(copy).text();
						if (navigator.clipboard && navigator.clipboard.writeText) {
							navigator.clipboard.writeText(decoded);
						} else {
							var $t = $('<input>').val(decoded).appendTo('body').select();
							document.execCommand('copy');
							$t.remove();
						}
						var $icon = $(this).find('.dashicons');
						if ($icon.hasClass('dashicons-clipboard')) {
							$icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
							setTimeout(function () {
								$icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
							}, 2000);
						}
					} catch (err) {}
				}
			});

			$(document).on('click', '.sf-layout-option', function (e) {
				e.preventDefault();
				var $opt = $(this);
				var $radio = $opt.find('input[type="radio"]');
				if ($radio.length) {
					$radio.prop('checked', true);
					$('.sf-radio-card').removeClass('active');
					$opt.addClass('active');
					self.debouncePreview();
				}
			});
			$(document).on('change', '.sf-customizer-wrap input[name="layout"]', function () {
				$('.sf-customizer-wrap .sf-radio-card').removeClass('active');
				$('.sf-customizer-wrap input[name="layout"]:checked').closest('.sf-radio-card').addClass('active');
			});

			$(document).on('input change', '.sf-customizer-wrap input, .sf-customizer-wrap select, .sf-customizer-wrap textarea', function () {
				var $el = $(this);
				var name = $el.attr('name');
				if (name) {
					var $duplicates = $('.sf-customizer-wrap [name="' + name + '"]').not(this);
					if ($duplicates.length) {
						if ($el.is(':checkbox')) {
							$duplicates.prop('checked', $el.is(':checked'));
						} else if (!$el.is(':radio')) {
							$duplicates.val($el.val());
						}
					}
				}
				self.debouncePreview();
			});

			$(document).on('input', 'input[type="range"]', function () {
				var $input = $(this);
				var val = $input.val();
				var suffix = $input.attr('name').indexOf('columns') === -1 ? 'px' : '';
				$input.siblings('.sf-range-value').text(val + suffix);
			});

			$(document).on('change', '#sf_platform', this.handlePlatformChange);
			$(document).on('change', '#sf_border_style', this.handleBorderStyleChange);
			$(document).on('change', '#sf_show_header', this.handleHeaderToggle);
			$(document).on('change', '#sf_show_caption', this.handleCaptionToggle);
			$(document).on('change', 'input[name="loadmore_type"]', this.handleLoadmoreChange);
		},

		/**
		 * Initialize WordPress color pickers.
		 */
		initColorPickers: function () {
			var self = this;

			$('.sf-color-picker').wpColorPicker({
				change: function () {
					self.debouncePreview();
				},
				clear: function () {
					self.debouncePreview();
				}
			});
		},

		handleToplevelClick: function (e) {
			e.preventDefault();
			var mode = $(this).data('mode');
			var $sidebar = $(this).closest('.sf-customizer-sidebar');
			$sidebar.find('.sf-sidebar-toplevel-btn').removeClass('active');
			$(this).addClass('active');
			$sidebar.find('.sf-sidebar-sublist').hide();
			$sidebar.find('.sf-sidebar-sublist-' + mode).show();
		},

		handleSidebarItemClick: function (e) {
			e.preventDefault();
			var section = $(this).attr('data-section') || $(this).data('section');
			var $wrap = $(this).closest('.sf-customizer-wrap');
			if (!$wrap.length || !section) return;

			/* Remove active from all sidebar items */
			$wrap.find('.sf-sidebar-item').removeClass('active');
			/* Add active to clicked item */
			$(this).addClass('active');

			SF_Customizer.switchToTab($wrap, section);
		},

		handleSidebarBack: function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.sf-customizer-wrap');
			SF_Customizer.showSidebarNav($wrap);
		},

		handleFeedNameEdit: function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.sf-feed-name-wrap');
			if (!$wrap.length) $wrap = $(this).closest('.sf-customizer-topbar');
			$wrap.find('.sf-feed-name-display').hide();
			$wrap.find('.sf-feed-name-edit').hide();
			var $input = $wrap.find('.sf-feed-name-input');
			$input.show().val($wrap.find('.sf-feed-name-display').text()).focus();
		},

		handleFeedNameBlur: function () {
			var $wrap = $(this).closest('.sf-feed-name-wrap');
			if (!$wrap.length) $wrap = $(this).closest('.sf-customizer-topbar');
			var val = $(this).val().trim();
			var display = val || ($(this).attr('placeholder') || '');
			$wrap.find('.sf-feed-name-display').text(display).show();
			$wrap.find('.sf-feed-name-edit').show();
			$(this).hide();
		},

		handleHelpClick: function (e) {
			e.preventDefault();
			window.open(sfAdmin.helpUrl || 'https://wordpress.org/support/plugin/social-feed/', '_blank');
		},

		handleEmbedClick: function (e) {
			e.preventDefault();
			var feedId = $('.sf-customizer-wrap').data('feed-id');
			var shortcode = '[social_feed id="' + feedId + '"]';
			$('#sf-embed-modal .sf-generated-shortcode').text(shortcode);
			$('#sf-embed-modal .sf-copy-btn').attr('data-copy', shortcode.replace(/"/g, '&quot;'));
			$('#sf-embed-modal').show();
		},

		/**
		 * Handle device switcher.
		 */
		handleDeviceSwitch: function (e) {
			var device = $(e.currentTarget).data('device');
			this.currentDevice = device;

			$('.sf-device-btn').removeClass('active');
			$(e.currentTarget).addClass('active');

			$('.sf-preview-container').attr('data-device', device);
			this.loadPreview();
		},

		/**
		 * Handle platform change.
		 */
		handlePlatformChange: function () {
			var platform = $(this).val();

			$('#sf_account_id option').each(function () {
				var $opt = $(this);
				if ($opt.val() === '') return;

				if ($opt.data('platform') === platform) {
					$opt.show();
				} else {
					$opt.hide();
				}
			});

			$('#sf_feed_type optgroup').hide();
			$('.sf-feed-type-' + platform).show();
			$('#sf_feed_type').val($('.sf-feed-type-' + platform + ' option:first').val());
		},

		/**
		 * Handle border style change.
		 */
		handleBorderStyleChange: function () {
			if ($(this).val() === 'none') {
				$('.sf-border-options').slideUp(200);
			} else {
				$('.sf-border-options').slideDown(200);
			}
		},

		/**
		 * Handle header toggle.
		 */
		handleHeaderToggle: function () {
			if ($(this).is(':checked')) {
				$('.sf-header-options').slideDown(200);
			} else {
				$('.sf-header-options').slideUp(200);
			}
		},


		/**
		 * Handle caption toggle.
		 */
		handleCaptionToggle: function () {
			var isChecked = $(this).is(':checked');
			var $allOpts = $('.sf-caption-options');
			if (isChecked) {
				$allOpts.slideDown(200);
			} else {
				$allOpts.slideUp(200);
			}
		},

		/**
		 * Handle loadmore type change.
		 */
		handleLoadmoreChange: function () {
			var type = $('input[name="loadmore_type"]:checked').val();

			$('input[name="loadmore_type"]').closest('.sf-radio-card').removeClass('active');
			$('input[name="loadmore_type"]:checked').closest('.sf-radio-card').addClass('active');

			if (type === 'none') {
				$('.sf-loadmore-button-section').slideUp(200);
				$('.sf-loadmore-general-section').slideUp(200);
			} else if (type === 'button') {
				$('.sf-loadmore-button-section').slideDown(200);
				$('.sf-loadmore-general-section').slideDown(200);
			} else {
				$('.sf-loadmore-button-section').slideUp(200);
				$('.sf-loadmore-general-section').slideDown(200);
			}
		},

		/**
		 * Debounce preview loading.
		 */
		debouncePreview: function () {
			var self = this;

			if (this.previewTimer) {
				clearTimeout(this.previewTimer);
			}

			this.previewTimer = setTimeout(function () {
				self.loadPreview();
			}, 500);
		},

		/**
		 * Collect all settings from form.
		 */
		collectSettings: function () {
			var settings = {};

			$('.sf-customizer-wrap input, .sf-customizer-wrap select, .sf-customizer-wrap textarea').each(function () {
				var $el = $(this);
				var name = $el.attr('name');

				if (!name) return;

				if ($el.is(':checkbox')) {
					settings[name] = $el.is(':checked') ? 1 : 0;
				} else if ($el.is(':radio')) {
					if ($el.is(':checked')) {
						settings[name] = $el.val();
					}
				} else if ($el.hasClass('wp-color-picker') || $el.hasClass('sf-color-picker')) {
					try {
						settings[name] = $el.wpColorPicker('color') || $el.val();
					} catch (e) {
						settings[name] = $el.val();
					}
				} else {
					settings[name] = $el.val();
				}
			});

			return settings;
		},

		/**
		 * Load preview via AJAX.
		 */
		loadPreview: function () {
			var self = this;
			var settings = this.collectSettings();

			$('.sf-preview-loading').addClass('active');
			$('.sf-preview-content').css('visibility', 'hidden');

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_preview_feed',
					nonce: sfAdmin.nonce,
					settings: settings,
					device: this.currentDevice
				},
				success: function (response) {
					if (response.success) {
						$('.sf-preview-content').html(response.data.html);
					}
				},
				error: function () {
					$('.sf-preview-content').html('<p style="padding:20px;text-align:center;">Preview failed to load.</p>');
				},
				complete: function () {
					$('.sf-preview-loading').removeClass('active');
					$('.sf-preview-content').css('visibility', '');
				}
			});
		},

		/**
		 * Handle save feed.
		 */
		handleSave: function (e) {
			e.preventDefault();

			var self = this;
			var $btn = $(e.currentTarget);
			var feedId = $('.sf-customizer-wrap').data('feed-id') || 0;
			var settings = this.collectSettings();

			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + sfAdmin.i18n.saving);

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_save_feed',
					nonce: sfAdmin.nonce,
					feed_id: feedId,
					settings: settings
				},
				success: function (response) {
					if (response.success) {
						SF_BackNav.clearUnsaved();
						SF_Admin.showNotice(response.data.message, 'success');

						if (!feedId && response.data.feed_id) {
							$('.sf-customizer-wrap').data('feed-id', response.data.feed_id);
							$('.sf-embed-btn').show();
							if (response.data.shortcode) {
								$('#sf-embed-modal .sf-generated-shortcode').text(response.data.shortcode);
								$('#sf-embed-modal .sf-copy-btn').attr('data-copy', response.data.shortcode.replace(/"/g, '&quot;'));
							}
							if (response.data.name) {
								$('.sf-feed-name-display').text(response.data.name);
								$('.sf-feed-name-input').val(response.data.name);
							}

							window.history.replaceState(null, '', response.data.redirect);
						}
					} else {
						SF_Admin.showNotice(response.data.message || sfAdmin.i18n.error, 'error');
					}
				},
				error: function () {
					SF_Admin.showNotice(sfAdmin.i18n.error, 'error');
				},
				complete: function () {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> ' + sfAdmin.i18n.saved);

					setTimeout(function () {
						$btn.html('<span class="dashicons dashicons-saved"></span> ' + (sfAdmin.i18n.save_feed || 'Save Feed'));
					}, 2000);
				}
			});
		}
	};

	/**
	 * Settings page module.
	 */
	var SF_Settings = {
		init: function () {
			if (!$('.sf-settings-wrap').length) {
				return;
			}

			this.bindEvents();
		},

		bindEvents: function () {
			var self = this;

			$(document).on('click', '.sf-toggle-password', function () {
				var targetId = $(this).data('target');
				var $input = $('#' + targetId);
				var $icon = $(this).find('.dashicons');

				if ($input.attr('type') === 'password') {
					$input.attr('type', 'text');
					$icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
				} else {
					$input.attr('type', 'password');
					$icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
				}
			});

			$(document).on('click', '.sf-test-connection', function () {
				self.testConnection($(this));
			});

			$(document).on('submit', '#sf-settings-form', function (e) {
				e.preventDefault();
				self.saveSettings();
			});

			$(document).on('click', '.sf-clear-all-cache', function () {
				self.clearAllCache($(this));
			});

			$(document).on('click', '.sf-clear-logs', function () {
				self.clearAllLogs($(this));
			});
		},

		testConnection: function ($btn) {
			var platform = $btn.data('platform');
			var $card = $btn.closest('.sf-api-card');
			var $footer = $card.find('.sf-api-card-footer');

			$footer.find('.sf-test-result').remove();

			var settings = {};
			$card.find('input').each(function () {
				var name = $(this).attr('name');
				if (name) {
					var match = name.match(/settings\[([^\]]+)\]/);
					if (match) {
						settings[match[1]] = $(this).val();
					}
				}
			});

			$btn.addClass('testing').prop('disabled', true);

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_test_api_connection',
					nonce: sfAdmin.nonce,
					platform: platform,
					settings: settings
				},
				success: function (response) {
					var resultClass = response.success ? 'success' : 'error';
					var message = response.data.message || sfAdmin.i18n.error;
					$footer.append('<div class="sf-test-result ' + resultClass + '">' + message + '</div>');
				},
				error: function () {
					$footer.append('<div class="sf-test-result error">' + sfAdmin.i18n.error + '</div>');
				},
				complete: function () {
					$btn.removeClass('testing').prop('disabled', false);
				}
			});
		},

		saveSettings: function () {
			var $form = $('#sf-settings-form');
			var $btn = $form.find('.sf-save-settings-btn');
			var $status = $form.find('.sf-save-status');

			var formData = $form.serializeArray();
			var settings = {};

			formData.forEach(function (item) {
				var match = item.name.match(/settings\[([^\]]+)\]/);
				if (match) {
					settings[match[1]] = item.value;
				}
			});

			$btn.prop('disabled', true);
			$status.removeClass('saved error').addClass('saving').text(sfAdmin.i18n.saving || 'Saving...');

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_save_settings',
					nonce: sfAdmin.nonce,
					settings: settings
				},
				success: function (response) {
					if (response.success) {
						$status.removeClass('saving').addClass('saved').text(sfAdmin.i18n.saved || 'Saved!');

						$('.sf-api-card').each(function () {
							var $card = $(this);
							var platform = '';

							if ($card.hasClass('sf-api-instagram')) platform = 'instagram';
							if ($card.hasClass('sf-api-youtube')) platform = 'youtube';
							if ($card.hasClass('sf-api-facebook')) platform = 'facebook';

							var hasCredentials = false;
							if (platform === 'instagram') {
								hasCredentials = settings.instagram_app_id && settings.instagram_app_secret;
							} else if (platform === 'youtube') {
								hasCredentials = !!settings.youtube_api_key;
							} else if (platform === 'facebook') {
								hasCredentials = settings.facebook_app_id && settings.facebook_app_secret;
							}

							var $status = $card.find('.sf-api-status');
							if (hasCredentials) {
								$status.html('<span class="sf-status-badge sf-status-configured">' + (sfAdmin.i18n.configured || 'Configured') + '</span>');
							} else {
								$status.html('<span class="sf-status-badge sf-status-not-configured">' + (sfAdmin.i18n.not_configured || 'Not Configured') + '</span>');
							}
						});

						setTimeout(function () {
							$status.text('');
						}, 3000);
					} else {
						$status.removeClass('saving').addClass('error').text(response.data.message || sfAdmin.i18n.error);
					}
				},
				error: function () {
					$status.removeClass('saving').addClass('error').text(sfAdmin.i18n.error);
				},
				complete: function () {
					$btn.prop('disabled', false);
				}
			});
		},

		clearAllCache: function ($btn) {
			if (!confirm(sfAdmin.i18n.confirm_clear_cache || 'Are you sure you want to clear all cached data?')) {
				return;
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_clear_all_cache',
					nonce: sfAdmin.nonce
				},
				success: function (response) {
					if (response.success) {
						SF_Admin.showNotice(response.data.message, 'success');
					} else {
						SF_Admin.showNotice(response.data.message || sfAdmin.i18n.error, 'error');
					}
				},
				error: function () {
					SF_Admin.showNotice(sfAdmin.i18n.error, 'error');
				},
				complete: function () {
					$btn.prop('disabled', false);
				}
			});
		},

		clearAllLogs: function ($btn) {
			if (!confirm(sfAdmin.i18n.confirm_clear_logs || 'Are you sure you want to clear all logs?')) {
				return;
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_clear_all_logs',
					nonce: sfAdmin.nonce
				},
				success: function (response) {
					if (response.success) {
						SF_Admin.showNotice(response.data.message, 'success');
					} else {
						SF_Admin.showNotice(response.data.message || sfAdmin.i18n.error, 'error');
					}
				},
				error: function () {
					SF_Admin.showNotice(sfAdmin.i18n.error, 'error');
				},
				complete: function () {
					$btn.prop('disabled', false);
				}
			});
		}
	};

	/**
	 * License Module
	 */
	var SF_License = {
		init: function () {
			this.bindEvents();
		},

		bindEvents: function () {
			$('#sf-activate-license').on('click', this.handleActivate.bind(this));
			$('#sf-deactivate-license').on('click', this.handleDeactivate.bind(this));
			$('#sf-check-license').on('click', this.handleCheck.bind(this));
		},

		handleActivate: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var $form = $('#sf-license-form');
			var $input = $('#sf-license-key');
			var $message = $('#sf-license-message');
			var licenseKey = $input.val().trim();

			if (!licenseKey) {
				this.showMessage($message, sfAdmin.i18n.enter_license || 'Please enter a license key.', 'error');
				$input.focus();
				return;
			}

			$btn.prop('disabled', true).text(sfAdmin.i18n.activating || 'Activating...');
			$form.addClass('loading');

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_activate_license',
					nonce: sfAdmin.nonce,
					license_key: licenseKey
				},
				success: function (response) {
					if (response.success) {
						SF_License.showMessage($message, response.data.message, 'success');
						setTimeout(function () {
							window.location.reload();
						}, 1500);
					} else {
						SF_License.showMessage($message, response.data.message || 'Activation failed.', 'error');
						$btn.prop('disabled', false).text(sfAdmin.i18n.activate || 'Activate');
						$form.removeClass('loading');
					}
				},
				error: function () {
					SF_License.showMessage($message, 'An error occurred. Please try again.', 'error');
					$btn.prop('disabled', false).text(sfAdmin.i18n.activate || 'Activate');
					$form.removeClass('loading');
				}
			});
		},

		handleDeactivate: function (e) {
			e.preventDefault();

			if (!confirm(sfAdmin.i18n.confirm_deactivate || 'Are you sure you want to deactivate your license?')) {
				return;
			}

			var $btn = $(e.currentTarget);
			var $container = $('#sf-license-active');
			var $message = $('#sf-license-message');

			$btn.prop('disabled', true).text(sfAdmin.i18n.deactivating || 'Deactivating...');
			$container.addClass('loading');

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_deactivate_license',
					nonce: sfAdmin.nonce
				},
				success: function (response) {
					if (response.success) {
						SF_License.showMessage($message, response.data.message, 'success');
						setTimeout(function () {
							window.location.reload();
						}, 1500);
					} else {
						SF_License.showMessage($message, response.data.message || 'Deactivation failed.', 'error');
						$btn.prop('disabled', false).text(sfAdmin.i18n.deactivate || 'Deactivate');
						$container.removeClass('loading');
					}
				},
				error: function () {
					SF_License.showMessage($message, 'An error occurred. Please try again.', 'error');
					$btn.prop('disabled', false).text(sfAdmin.i18n.deactivate || 'Deactivate');
					$container.removeClass('loading');
				}
			});
		},

		handleCheck: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var $message = $('#sf-license-message');

			$btn.prop('disabled', true).text(sfAdmin.i18n.checking || 'Checking...');

			$.ajax({
				url: sfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'sf_check_license',
					nonce: sfAdmin.nonce
				},
				success: function (response) {
					if (response.success) {
						SF_License.showMessage($message, response.data.message || 'License status updated.', 'success');
					} else {
						SF_License.showMessage($message, response.data.message || 'Check failed.', 'error');
					}
				},
				error: function () {
					SF_License.showMessage($message, 'An error occurred. Please try again.', 'error');
				},
				complete: function () {
					$btn.prop('disabled', false).text(sfAdmin.i18n.check_license || 'Check License');
				}
			});
		},

		showMessage: function ($el, message, type) {
			$el.removeClass('success error').addClass(type).html(message).show();
		}
	};

	/**
	 * Unsaved changes handling for customizer.
	 */
	var SF_BackNav = {
		hasUnsavedChanges: false,
		beforeUnloadHandler: null,

		init: function () {
			if ($('.sf-customizer-wrap').length) {
				this.initUnsavedChanges();
			}
		},

		initUnsavedChanges: function () {
			var self = this;
			var $indicator = $('.sf-unsaved-indicator');

			$('.sf-customizer-wrap input, .sf-customizer-wrap select, .sf-customizer-wrap textarea').on('change input', function () {
				self.hasUnsavedChanges = true;
				if ($indicator.length) {
					$indicator.css('display', 'flex');
				}
			});

			this.beforeUnloadHandler = function (e) {
				if (self.hasUnsavedChanges) {
					e.preventDefault();
					e.returnValue = '';
					return '';
				}
			};
			window.addEventListener('beforeunload', this.beforeUnloadHandler);
		},

		clearUnsaved: function () {
			this.hasUnsavedChanges = false;
			$('.sf-unsaved-indicator').css('display', 'none');
			if (this.beforeUnloadHandler) {
				window.removeEventListener('beforeunload', this.beforeUnloadHandler);
				this.beforeUnloadHandler = null;
			}
		}
	};

	$(function () {
		SF_Admin.init();
		SF_Accounts.init();
		SF_Settings.init();
		SF_License.init();
		SF_BackNav.init();

		if ($('.sf-customizer-wrap').length) {
			SF_Customizer.init();
		}
	});

})(jQuery);
