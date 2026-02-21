/**
 * Social Feed Gutenberg Block
 *
 * @package SocialFeed
 */

(function (wp) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ServerSideRender = wp.serverSideRender;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var Button = wp.components.Button;
	var Placeholder = wp.components.Placeholder;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;

	var i18n = sfBlockData.i18n || {};
	var feeds = sfBlockData.feeds || [];

	var socialFeedIcon = createElement(
		'svg',
		{
			width: 24,
			height: 24,
			viewBox: '0 0 24 24',
			fill: 'none',
			xmlns: 'http://www.w3.org/2000/svg'
		},
		createElement('path', {
			d: 'M4 4h7V2H4c-1.1 0-2 .9-2 2v7h2V4zm6 9l-4 5h12l-3-4-2.03 2.71L10 13zm7-4.5c0-.83-.67-1.5-1.5-1.5S14 7.67 14 8.5s.67 1.5 1.5 1.5S17 9.33 17 8.5zM20 2h-7v2h7v7h2V4c0-1.1-.9-2-2-2zm0 18h-7v2h7c1.1 0 2-.9 2-2v-7h-2v7zM4 13H2v7c0 1.1.9 2 2 2h7v-2H4v-7z',
			fill: 'currentColor'
		})
	);

	registerBlockType('social-feed/feed', {
		title: i18n.title || 'Social Feed',
		description: i18n.description || 'Display a social media feed on your site.',
		icon: socialFeedIcon,
		category: 'widgets',
		keywords: ['instagram', 'facebook', 'youtube', 'social', 'feed'],
		supports: {
			html: false,
			align: ['wide', 'full']
		},

		attributes: {
			feedId: {
				type: 'number',
				default: 0
			},
			title: {
				type: 'string',
				default: ''
			}
		},

		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var feedId = attributes.feedId;
			var title = attributes.title;

			var feedOptions = feeds.map(function (feed) {
				return {
					value: feed.value,
					label: feed.label
				};
			});

			var inspectorControls = createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{
						title: i18n.select_feed || 'Select a Feed',
						initialOpen: true
					},
					createElement(SelectControl, {
						label: i18n.feed_id || 'Feed ID',
						value: feedId,
						options: feedOptions,
						onChange: function (value) {
							setAttributes({ feedId: parseInt(value, 10) || 0 });
						}
					}),
					createElement(TextControl, {
						label: i18n.custom_title || 'Custom Title (optional)',
						value: title,
						onChange: function (value) {
							setAttributes({ title: value });
						},
						help: 'Override the feed title for this instance.'
					}),
					createElement(
						'div',
						{ className: 'sf-block-actions' },
						createElement(
							Button,
							{
								variant: 'secondary',
								href: sfBlockData.createUrl,
								target: '_blank'
							},
							i18n.create_feed || 'Create a Feed'
						),
						createElement(
							Button,
							{
								variant: 'link',
								href: sfBlockData.adminUrl,
								target: '_blank'
							},
							i18n.manage_feeds || 'Manage Feeds'
						)
					)
				)
			);

			var blockContent;

			if (!feedId) {
				if (feeds.length <= 1) {
					blockContent = createElement(
						Placeholder,
						{
							icon: socialFeedIcon,
							label: i18n.title || 'Social Feed',
							instructions: i18n.no_feeds || 'No feeds created yet.'
						},
						createElement(
							Button,
							{
								variant: 'primary',
								href: sfBlockData.createUrl,
								target: '_blank'
							},
							i18n.create_feed || 'Create a Feed'
						)
					);
				} else {
					blockContent = createElement(
						Placeholder,
						{
							icon: socialFeedIcon,
							label: i18n.title || 'Social Feed',
							instructions: i18n.no_feed || 'Please select a feed to display.'
						},
						createElement(SelectControl, {
							value: feedId,
							options: feedOptions,
							onChange: function (value) {
								setAttributes({ feedId: parseInt(value, 10) || 0 });
							}
						})
					);
				}
			} else {
				blockContent = createElement(ServerSideRender, {
					block: 'social-feed/feed',
					attributes: attributes
				});
			}

			return createElement(
				Fragment,
				null,
				inspectorControls,
				createElement(
					'div',
					{ className: 'sf-block-wrapper' },
					blockContent
				)
			);
		},

		save: function () {
			return null;
		}
	});

})(window.wp);
