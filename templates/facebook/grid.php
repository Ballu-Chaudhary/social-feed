<?php
/**
 * Facebook grid layout template.
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
$columns  = isset( $atts['columns'] ) ? absint( $atts['columns'] ) : 3;
?>
<div class="sf-feed__grid sf-feed__grid--cols-<?php echo esc_attr( $columns ); ?>">
	<?php foreach ( $posts as $post ) : ?>
		<?php echo $renderer->render_post_item( $post, 'facebook' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endforeach; ?>
</div>
