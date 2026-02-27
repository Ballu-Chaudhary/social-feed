<?php
/**
 * Instagram Grid Template
 *
 * @package SocialFeed
 *
 * Available variables:
 * @var array $feed     Feed data from database
 * @var array $settings Feed settings/meta
 * @var array $account  Connected account data
 * @var array $items    Feed items (posts)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$profile = array();
if ( ! empty( $items['profile'] ) ) {
	$profile = $items['profile'];
}

$posts = array();
if ( ! empty( $items['items'] ) ) {
	$posts = $items['items'];
}

$next_cursor = $items['next_cursor'] ?? '';
$has_items   = ! empty( $posts );
?>

<?php if ( ! empty( $settings['show_header'] ) && $account ) : ?>
	<header class="sf-feed__header">
		<?php if ( ! empty( $settings['show_avatar'] ) ) : ?>
			<?php
			$avatar_url = $profile['profile_picture_url'] ?? $account['profile_pic'] ?? '';
			$profile_url = 'https://www.instagram.com/' . ltrim( $account['account_name'], '@' ) . '/';
			?>
			<?php if ( $avatar_url ) : ?>
				<a href="<?php echo esc_url( $profile_url ); ?>" class="sf-feed__avatar" target="_blank" rel="noopener noreferrer">
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $account['account_name'] ); ?>" loading="lazy">
				</a>
			<?php endif; ?>
		<?php endif; ?>

		<div class="sf-feed__header-info">
			<?php if ( ! empty( $settings['show_username'] ) ) : ?>
				<?php $profile_url = 'https://www.instagram.com/' . ltrim( $account['account_name'], '@' ) . '/'; ?>
				<a href="<?php echo esc_url( $profile_url ); ?>" class="sf-feed__username" target="_blank" rel="noopener noreferrer">
					@<?php echo esc_html( $account['account_name'] ); ?>
				</a>
			<?php endif; ?>

			<?php if ( ! empty( $settings['show_followers'] ) && ! empty( $profile['followers_count'] ) ) : ?>
				<span class="sf-feed__followers">
					<?php
					printf(
						/* translators: %s: Formatted follower count */
						esc_html__( '%s followers', 'social-feed' ),
						esc_html( SF_Helpers::sf_format_number( $profile['followers_count'] ) )
					);
					?>
				</span>
			<?php endif; ?>

			<?php if ( ! empty( $settings['show_bio'] ) && ! empty( $profile['biography'] ) ) : ?>
				<p class="sf-feed__bio">
					<?php echo esc_html( SF_Helpers::sf_truncate_text( $profile['biography'], 150 ) ); ?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $settings['show_follow_button'] ) ) : ?>
			<?php $profile_url = 'https://www.instagram.com/' . ltrim( $account['account_name'], '@' ) . '/'; ?>
			<a href="<?php echo esc_url( $profile_url ); ?>" class="sf-feed__follow-btn sf-feed__follow-btn--instagram" target="_blank" rel="noopener noreferrer">
				<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073z"/></svg>
				<?php echo esc_html( $settings['follow_button_text'] ?? __( 'Follow', 'social-feed' ) ); ?>
			</a>
		<?php endif; ?>
	</header>
<?php endif; ?>

<?php if ( $has_items ) : ?>
	<div class="sf-feed__items">
		<?php foreach ( $posts as $item ) : ?>
			<?php
			$type        = $item['type'] ?? 'image';
			$is_video    = in_array( $type, array( 'video', 'reel', 'carousel' ), true );
			$is_carousel = 'carousel' === $type;
			$permalink   = $item['permalink'] ?? '#';
			$thumbnail   = $item['thumbnail'] ?? $item['media_url'] ?? '';
			$caption     = $item['caption'] ?? '';
			$likes       = $item['likes'] ?? 0;
			$comments    = $item['comments'] ?? 0;
			$timestamp   = $item['timestamp'] ?? '';

			$link_target = ! empty( $settings['open_links_new_tab'] ) ? '_blank' : '_self';
			$use_lightbox = ! empty( $settings['enable_lightbox'] );
			?>
			<article class="sf-feed__item sf-feed__item--<?php echo esc_attr( $type ); ?>" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
				<a href="<?php echo esc_url( $permalink ); ?>" 
				   class="sf-feed__item-link<?php echo $use_lightbox ? ' sf-lightbox-trigger' : ''; ?>" 
				   target="<?php echo esc_attr( $link_target ); ?>" 
				   rel="noopener noreferrer"
				   <?php if ( $use_lightbox ) : ?>
				   data-media="<?php echo esc_attr( $item['media_url'] ?? '' ); ?>"
				   data-type="<?php echo esc_attr( $type ); ?>"
				   data-caption="<?php echo esc_attr( $caption ); ?>"
				   <?php endif; ?>>

					<div class="sf-feed__item-media">
						<?php if ( $thumbnail ) : ?>
							<img src="<?php echo esc_url( $thumbnail ); ?>" 
								 alt="<?php echo esc_attr( wp_strip_all_tags( $caption ) ); ?>" 
								 loading="lazy" 
								 class="sf-feed__item-image">
						<?php endif; ?>

						<?php if ( $is_video ) : ?>
							<div class="sf-feed__item-play" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="40" height="40">
									<circle cx="12" cy="12" r="11" fill="rgba(0,0,0,0.5)"/>
									<path fill="#fff" d="M9.5 7.5v9l7-4.5z"/>
								</svg>
							</div>
						<?php endif; ?>

						<?php if ( $is_carousel ) : ?>
							<div class="sf-feed__item-carousel-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="20" height="20">
									<path fill="#fff" d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z"/>
								</svg>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $settings['show_hover_overlay'] ) ) : ?>
						<div class="sf-feed__item-overlay">
							<div class="sf-feed__item-stats">
								<span class="sf-feed__stat sf-feed__stat--likes">
									<svg viewBox="0 0 24 24" width="18" height="18">
										<path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
									</svg>
									<?php echo esc_html( SF_Helpers::sf_format_number( $likes ) ); ?>
								</span>
								<span class="sf-feed__stat sf-feed__stat--comments">
									<svg viewBox="0 0 24 24" width="18" height="18">
										<path fill="currentColor" d="M21.99 4c0-1.1-.89-2-1.99-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4-.01-18z"/>
									</svg>
									<?php echo esc_html( SF_Helpers::sf_format_number( $comments ) ); ?>
								</span>
							</div>
						</div>
					<?php endif; ?>
				</a>

				<?php if ( ! empty( $settings['show_caption'] ) && $caption ) : ?>
					<div class="sf-feed__item-caption">
						<?php
						$max_length = absint( $settings['caption_length'] ?? 100 );
						echo esc_html( SF_Helpers::sf_truncate_text( $caption, $max_length ) );
						?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $settings['show_date'] ) && $timestamp ) : ?>
					<time class="sf-feed__item-date" datetime="<?php echo esc_attr( $timestamp ); ?>">
						<?php echo esc_html( SF_Helpers::sf_time_ago( $timestamp ) ); ?>
					</time>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>

	<?php
	$load_more_type = $settings['loadmore_type'] ?? ( $settings['load_more_type'] ?? 'button' );
	$load_more_text = $settings['loadmore_text'] ?? ( $settings['load_more_text'] ?? __( 'Load More', 'social-feed' ) );
	if ( 'none' !== $load_more_type && $next_cursor ) :
		?>
		<?php if ( 'button' === $load_more_type ) : ?>
			<div class="sf-feed__load-more">
				<button type="button" class="sf-feed__load-more-btn" data-cursor="<?php echo esc_attr( $next_cursor ); ?>">
					<?php echo esc_html( $load_more_text ); ?>
				</button>
			</div>
		<?php elseif ( 'pagination' === $load_more_type ) : ?>
			<div class="sf-feed__pagination" data-cursor="<?php echo esc_attr( $next_cursor ); ?>">
				<button type="button" class="sf-feed__page-btn active" data-page="1">1</button>
				<button type="button" class="sf-feed__page-btn" data-page="2">2</button>
			</div>
		<?php else : ?>
			<div class="sf-feed__infinite-trigger" data-cursor="<?php echo esc_attr( $next_cursor ); ?>">
				<span class="sf-feed__loader" aria-label="<?php esc_attr_e( 'Loading more posts...', 'social-feed' ); ?>"></span>
			</div>
		<?php endif; ?>
	<?php endif; ?>

<?php else : ?>
	<div class="sf-feed__empty">
		<div class="sf-feed__empty-icon">
			<svg viewBox="0 0 24 24" width="48" height="48">
				<path fill="currentColor" d="M4 4h7V2H4c-1.1 0-2 .9-2 2v7h2V4zm6 9l-4 5h12l-3-4-2.03 2.71L10 13zm7-4.5c0-.83-.67-1.5-1.5-1.5S14 7.67 14 8.5s.67 1.5 1.5 1.5S17 9.33 17 8.5zM20 2h-7v2h7v7h2V4c0-1.1-.9-2-2-2zm0 18h-7v2h7c1.1 0 2-.9 2-2v-7h-2v7zM4 13H2v7c0 1.1.9 2 2 2h7v-2H4v-7z"/>
			</svg>
		</div>
		<p class="sf-feed__empty-text">
			<?php echo esc_html( $settings['empty_message'] ?? __( 'No posts to display.', 'social-feed' ) ); ?>
		</p>
	</div>
<?php endif; ?>
