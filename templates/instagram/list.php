<?php
/**
 * Instagram list layout template.
 *
 * @package SocialFeed
 *
 * @var array $posts Post data.
 * @var array $atts  Shortcode attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$renderer = new SF_Renderer();
?>
<div class="sf-feed__list">
	<?php foreach ( $posts as $post ) : ?>
		<?php echo $renderer->render_post_item( $post, 'instagram' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endforeach; ?>
</div>
