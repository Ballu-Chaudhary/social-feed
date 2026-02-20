<?php
/**
 * Feed HTML output for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Renderer
 */
class SF_Renderer {

	/**
	 * Render feed HTML.
	 *
	 * @param string $platform Platform name (instagram, youtube, facebook).
	 * @param string $layout   Layout type (grid, list, carousel).
	 * @param array  $posts    Array of post data.
	 * @param array  $atts     Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( $platform, $layout, $posts, $atts = array() ) {
		$atts = wp_parse_args( $atts, array( 'columns' => 3 ) );

		$template_path = $this->get_template_path( $platform, $layout );
		if ( ! file_exists( $template_path ) ) {
			$template_path = $this->get_template_path( 'instagram', 'grid' );
		}

		ob_start();
		include $template_path;
		return ob_get_clean();
	}

	/**
	 * Get template file path.
	 *
	 * @param string $platform Platform name.
	 * @param string $layout   Layout type.
	 * @return string
	 */
	public function get_template_path( $platform, $layout ) {
		return SF_PLUGIN_PATH . 'templates/' . sanitize_key( $platform ) . '/' . sanitize_key( $layout ) . '.php';
	}

	/**
	 * Render a single post item.
	 *
	 * @param array  $post  Post data.
	 * @param string $platform Platform name.
	 * @return string
	 */
	public function render_post_item( $post, $platform = '' ) {
		$permalink     = isset( $post['permalink'] ) ? esc_url( $post['permalink'] ) : '#';
		$thumbnail_url = isset( $post['thumbnail_url'] ) ? esc_url( $post['thumbnail_url'] ) : '';
		$content       = isset( $post['content'] ) ? SF_Helpers::truncate( $post['content'], 150 ) : '';
		$author_name   = isset( $post['author_name'] ) ? esc_html( $post['author_name'] ) : '';
		$created_at    = isset( $post['created_at'] ) ? SF_Helpers::format_date( $post['created_at'] ) : '';

		ob_start();
		?>
		<article class="sf-post sf-post--<?php echo esc_attr( $platform ); ?>">
			<a href="<?php echo esc_url( $permalink ); ?>" class="sf-post__link" target="_blank" rel="noopener noreferrer">
				<?php if ( $thumbnail_url ) : ?>
					<div class="sf-post__media">
						<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="" loading="lazy" />
					</div>
				<?php endif; ?>
				<div class="sf-post__content">
					<?php if ( $content ) : ?>
						<p class="sf-post__text"><?php echo esc_html( $content ); ?></p>
					<?php endif; ?>
					<?php if ( $author_name || $created_at ) : ?>
						<div class="sf-post__meta">
							<?php if ( $author_name ) : ?>
								<span class="sf-post__author"><?php echo esc_html( $author_name ); ?></span>
							<?php endif; ?>
							<?php if ( $created_at ) : ?>
								<span class="sf-post__date"><?php echo esc_html( $created_at ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</a>
		</article>
		<?php
		return ob_get_clean();
	}
}
