<?php
/**
 * Mahihub Instagram feed shortcode.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Mahihub_Feed_Shortcode
 *
 * Handles [mahihub_feed] shortcode rendering using Instagram Basic Display API.
 */
class SF_Mahihub_Feed_Shortcode {

	/**
	 * Option key where token is stored.
	 *
	 * @var string
	 */
	private $token_option = 'sf_mahihub_token';

	/**
	 * Transient cache key prefix.
	 *
	 * @var string
	 */
	private $transient_prefix = 'sf_mahihub_ig_media_';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'mahihub_feed', array( $this, 'render' ) );
	}

	/**
	 * Render shortcode output.
	 *
	 * @return string
	 */
	public function render() {
		$token = (string) get_option( $this->token_option, '' );
		$token = trim( $token );

		if ( empty( $token ) ) {
			return '<p class="sf-mahihub-feed__notice">' . esc_html__( 'Please connect your Instagram account in the Mahihub Social Feed settings.', 'social-feed' ) . '</p>';
		}

		$data = $this->get_media_data( $token );
		if ( is_wp_error( $data ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p class="sf-mahihub-feed__notice">' . esc_html( $data->get_error_message() ) . '</p>';
			}
			return '';
		}

		$items = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
		if ( empty( $items ) ) {
			return '';
		}

		ob_start();
		?>
		<style>
			.sf-mahihub-feed-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
			@media (max-width:640px){.sf-mahihub-feed-grid{grid-template-columns:1fr}}
			.sf-mahihub-feed-item{position:relative;overflow:hidden;border-radius:10px;background:#f3f4f6}
			.sf-mahihub-feed-item a{display:block;line-height:0}
			.sf-mahihub-feed-item img{width:100%;height:100%;aspect-ratio:1/1;object-fit:cover;display:block;transition:transform .2s ease}
			.sf-mahihub-feed-item a:hover img{transform:scale(1.03)}
		</style>
		<div class="sf-mahihub-feed-grid">
			<?php foreach ( $items as $item ) : ?>
				<?php
				if ( ! is_array( $item ) ) {
					continue;
				}
				$media_type    = isset( $item['media_type'] ) ? (string) $item['media_type'] : '';
				$media_url     = isset( $item['media_url'] ) ? (string) $item['media_url'] : '';
				$thumbnail_url = isset( $item['thumbnail_url'] ) ? (string) $item['thumbnail_url'] : '';
				$permalink     = isset( $item['permalink'] ) ? (string) $item['permalink'] : '';
				$caption       = isset( $item['caption'] ) ? (string) $item['caption'] : '';

				$img = ( 'VIDEO' === $media_type && ! empty( $thumbnail_url ) ) ? $thumbnail_url : $media_url;
				if ( empty( $img ) || empty( $permalink ) ) {
					continue;
				}
				?>
				<div class="sf-mahihub-feed-item">
					<a href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener noreferrer">
						<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( wp_trim_words( $caption, 12, '' ) ); ?>" loading="lazy">
					</a>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Fetch (and cache) Instagram media response.
	 *
	 * @param string $token Access token.
	 * @return array|WP_Error
	 */
	private function get_media_data( $token ) {
		$cache_key = $this->transient_prefix . md5( $token );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$url = 'https://graph.instagram.com/me/media?fields=id,caption,media_type,media_url,permalink,thumbnail_url&access_token=' . rawurlencode( $token );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		$data = json_decode( (string) $body, true );
		if ( $code < 200 || $code >= 300 ) {
			$message = __( 'Instagram API request failed.', 'social-feed' );
			if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
				$message = (string) $data['error']['message'];
			}
			return new WP_Error( 'mahihub_ig_api_error', $message );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'mahihub_ig_api_invalid_json', __( 'Instagram API returned an invalid response.', 'social-feed' ) );
		}

		set_transient( $cache_key, $data, 4 * HOUR_IN_SECONDS );
		return $data;
	}
}

