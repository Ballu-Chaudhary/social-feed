<?php
/**
 * License Admin Page for Social Feed Pro.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_License_Page
 *
 * Renders the license management admin page.
 */
class SF_License_Page {

	/**
	 * Render the license page.
	 */
	public static function render() {
		$license_data = SF_License::get_license_data();
		$has_license  = ! empty( $license_data['status'] ) && 'valid' === $license_data['status'];
		$is_expired   = ! empty( $license_data['expires'] ) && 'lifetime' !== $license_data['expires'] && strtotime( $license_data['expires'] ) < time();

		?>
		<div class="wrap sf-license-page">
			<h1><?php esc_html_e( 'License', 'social-feed' ); ?></h1>

			<div class="sf-license-container">
				<?php if ( $has_license && ! $is_expired ) : ?>
					<?php self::render_active_license( $license_data ); ?>
				<?php elseif ( $has_license && $is_expired ) : ?>
					<?php self::render_expired_license( $license_data ); ?>
				<?php else : ?>
					<?php self::render_activation_form(); ?>
					<?php self::render_pricing_cards(); ?>
				<?php endif; ?>
			</div>
		</div>

		<style>
			.sf-license-page { max-width: 1200px; }

			.sf-license-container {
				margin-top: 20px;
			}

			/* Activation Form */
			.sf-license-form {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 30px;
				max-width: 600px;
				margin-bottom: 40px;
			}

			.sf-license-form h2 {
				margin: 0 0 10px;
				font-size: 18px;
			}

			.sf-license-form p {
				color: #646970;
				margin: 0 0 20px;
			}

			.sf-license-input-wrap {
				display: flex;
				gap: 10px;
			}

			.sf-license-input-wrap input[type="text"] {
				flex: 1;
				padding: 8px 12px;
				font-size: 14px;
			}

			.sf-license-input-wrap .button {
				padding: 0 20px;
				height: auto;
			}

			.sf-license-message {
				margin-top: 15px;
				padding: 10px 15px;
				border-radius: 4px;
			}

			.sf-license-message.success {
				background: #d4edda;
				border: 1px solid #c3e6cb;
				color: #155724;
			}

			.sf-license-message.error {
				background: #f8d7da;
				border: 1px solid #f5c6cb;
				color: #721c24;
			}

			/* Active License */
			.sf-license-active {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 30px;
				max-width: 600px;
			}

			.sf-license-active h2 {
				margin: 0 0 20px;
				display: flex;
				align-items: center;
				gap: 10px;
			}

			.sf-license-active h2 .dashicons {
				color: #46b450;
				font-size: 24px;
			}

			.sf-license-details {
				display: grid;
				grid-template-columns: repeat(2, 1fr);
				gap: 15px;
				margin-bottom: 25px;
			}

			.sf-license-detail {
				background: #f6f7f7;
				padding: 15px;
				border-radius: 4px;
			}

			.sf-license-detail label {
				display: block;
				font-size: 11px;
				text-transform: uppercase;
				color: #646970;
				margin-bottom: 5px;
			}

			.sf-license-detail span {
				font-size: 15px;
				font-weight: 500;
				color: #1d2327;
			}

			.sf-license-key-display {
				background: #f6f7f7;
				padding: 10px 15px;
				border-radius: 4px;
				font-family: monospace;
				margin-bottom: 20px;
			}

			.sf-license-actions {
				display: flex;
				gap: 10px;
			}

			/* Expired License */
			.sf-license-expired {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-left: 4px solid #dc3232;
				border-radius: 4px;
				padding: 30px;
				max-width: 600px;
			}

			.sf-license-expired h2 {
				margin: 0 0 15px;
				color: #dc3232;
				display: flex;
				align-items: center;
				gap: 10px;
			}

			.sf-license-expired p {
				margin: 0 0 20px;
				color: #646970;
			}

			/* Pricing Cards */
			.sf-pricing-section {
				margin-top: 40px;
			}

			.sf-pricing-section h2 {
				margin: 0 0 10px;
				font-size: 24px;
			}

			.sf-pricing-section > p {
				color: #646970;
				margin: 0 0 30px;
			}

			.sf-pricing-cards {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
				gap: 20px;
			}

			.sf-pricing-card {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 8px;
				padding: 25px;
				text-align: center;
				transition: box-shadow 0.2s, transform 0.2s;
			}

			.sf-pricing-card:hover {
				box-shadow: 0 4px 12px rgba(0,0,0,0.1);
				transform: translateY(-2px);
			}

			.sf-pricing-card.featured {
				border-color: #2271b1;
				box-shadow: 0 0 0 1px #2271b1;
			}

			.sf-pricing-card.featured .sf-pricing-badge {
				background: #2271b1;
				color: #fff;
				padding: 4px 12px;
				border-radius: 20px;
				font-size: 11px;
				text-transform: uppercase;
				display: inline-block;
				margin-bottom: 15px;
			}

			.sf-pricing-card h3 {
				margin: 0 0 10px;
				font-size: 20px;
			}

			.sf-pricing-card .price {
				font-size: 36px;
				font-weight: 700;
				color: #1d2327;
				margin-bottom: 5px;
			}

			.sf-pricing-card .price span {
				font-size: 14px;
				font-weight: 400;
				color: #646970;
			}

			.sf-pricing-card .billing {
				color: #646970;
				font-size: 13px;
				margin-bottom: 20px;
			}

			.sf-pricing-card ul {
				list-style: none;
				padding: 0;
				margin: 0 0 25px;
				text-align: left;
			}

			.sf-pricing-card ul li {
				padding: 8px 0;
				border-bottom: 1px solid #f0f0f0;
				font-size: 13px;
			}

			.sf-pricing-card ul li:last-child {
				border-bottom: none;
			}

			.sf-pricing-card ul li::before {
				content: "✓";
				color: #46b450;
				font-weight: bold;
				margin-right: 8px;
			}

			.sf-pricing-card .button {
				width: 100%;
				justify-content: center;
			}

			/* Loading state */
			.sf-license-form.loading .button,
			.sf-license-active.loading .button {
				pointer-events: none;
				opacity: 0.7;
			}
		</style>
		<?php
	}

	/**
	 * Render activation form.
	 */
	private static function render_activation_form() {
		?>
		<div class="sf-license-form" id="sf-license-form">
			<h2><?php esc_html_e( 'Activate Your License', 'social-feed' ); ?></h2>
			<p><?php esc_html_e( 'Enter your license key to unlock Pro features and receive automatic updates.', 'social-feed' ); ?></p>

			<div class="sf-license-input-wrap">
				<input type="text" id="sf-license-key" placeholder="<?php esc_attr_e( 'Enter your license key', 'social-feed' ); ?>" />
				<button type="button" class="button button-primary" id="sf-activate-license">
					<?php esc_html_e( 'Activate', 'social-feed' ); ?>
				</button>
			</div>

			<div class="sf-license-message" id="sf-license-message" style="display: none;"></div>

			<p style="margin-top: 20px; font-size: 13px;">
				<?php
				printf(
					/* translators: %s: Store URL */
					esc_html__( 'Don\'t have a license? %s', 'social-feed' ),
					'<a href="' . esc_url( SF_License::STORE_URL . '/pricing/' ) . '" target="_blank">' . esc_html__( 'Purchase one here', 'social-feed' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render active license info.
	 *
	 * @param array $license_data License data.
	 */
	private static function render_active_license( $license_data ) {
		$plan_names = array(
			'1' => __( 'Personal', 'social-feed' ),
			'2' => __( 'Plus', 'social-feed' ),
			'3' => __( 'Agency', 'social-feed' ),
			'4' => __( 'Bundle', 'social-feed' ),
		);

		$plan_id   = $license_data['plan'] ?? '1';
		$plan_name = $plan_names[ $plan_id ] ?? __( 'Personal', 'social-feed' );

		$expires = $license_data['expires'] ?? '';
		if ( 'lifetime' === $expires ) {
			$expires_text = __( 'Lifetime', 'social-feed' );
		} elseif ( $expires ) {
			$expires_text = date_i18n( get_option( 'date_format' ), strtotime( $expires ) );
		} else {
			$expires_text = __( 'Unknown', 'social-feed' );
		}

		$site_count    = $license_data['site_count'] ?? 1;
		$license_limit = $license_data['license_limit'] ?? 1;
		$sites_text    = 0 === $license_limit ? __( 'Unlimited', 'social-feed' ) : sprintf( '%d / %d', $site_count, $license_limit );

		$masked_key = self::mask_license_key( SF_Helpers::sf_decrypt( $license_data['key'] ?? '' ) );

		?>
		<div class="sf-license-active" id="sf-license-active">
			<h2>
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'License Active', 'social-feed' ); ?>
			</h2>

			<div class="sf-license-key-display">
				<?php echo esc_html( $masked_key ); ?>
			</div>

			<div class="sf-license-details">
				<div class="sf-license-detail">
					<label><?php esc_html_e( 'Plan', 'social-feed' ); ?></label>
					<span><?php echo esc_html( $plan_name ); ?></span>
				</div>
				<div class="sf-license-detail">
					<label><?php esc_html_e( 'Expires', 'social-feed' ); ?></label>
					<span><?php echo esc_html( $expires_text ); ?></span>
				</div>
				<div class="sf-license-detail">
					<label><?php esc_html_e( 'Sites', 'social-feed' ); ?></label>
					<span><?php echo esc_html( $sites_text ); ?></span>
				</div>
				<div class="sf-license-detail">
					<label><?php esc_html_e( 'Status', 'social-feed' ); ?></label>
					<span style="color: #46b450;"><?php esc_html_e( 'Valid', 'social-feed' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $license_data['customer_email'] ) ) : ?>
				<p style="color: #646970; font-size: 13px; margin-bottom: 20px;">
					<?php
					printf(
						/* translators: %s: Customer email */
						esc_html__( 'Licensed to: %s', 'social-feed' ),
						esc_html( $license_data['customer_email'] )
					);
					?>
				</p>
			<?php endif; ?>

			<div class="sf-license-actions">
				<button type="button" class="button" id="sf-check-license">
					<?php esc_html_e( 'Check License', 'social-feed' ); ?>
				</button>
				<button type="button" class="button" id="sf-deactivate-license" style="color: #d63638;">
					<?php esc_html_e( 'Deactivate', 'social-feed' ); ?>
				</button>
			</div>

			<div class="sf-license-message" id="sf-license-message" style="display: none;"></div>
		</div>
		<?php
	}

	/**
	 * Render expired license notice.
	 *
	 * @param array $license_data License data.
	 */
	private static function render_expired_license( $license_data ) {
		$expires      = strtotime( $license_data['expires'] ?? '' );
		$days_expired = ceil( ( time() - $expires ) / DAY_IN_SECONDS );
		$masked_key   = self::mask_license_key( SF_Helpers::sf_decrypt( $license_data['key'] ?? '' ) );
		$license_key  = SF_Helpers::sf_decrypt( $license_data['key'] ?? '' );

		?>
		<div class="sf-license-expired">
			<h2>
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'License Expired', 'social-feed' ); ?>
			</h2>

			<p>
				<?php
				printf(
					/* translators: %d: Days since expiry */
					esc_html(
						_n(
							'Your license expired %d day ago. Renew now to continue receiving updates and support.',
							'Your license expired %d days ago. Renew now to continue receiving updates and support.',
							$days_expired,
							'social-feed'
						)
					),
					absint( $days_expired )
				);
				?>
			</p>

			<div class="sf-license-key-display">
				<?php echo esc_html( $masked_key ); ?>
			</div>

			<div class="sf-license-actions">
				<a href="<?php echo esc_url( SF_License::STORE_URL . '/checkout/?edd_license_key=' . urlencode( $license_key ) ); ?>" class="button button-primary" target="_blank">
					<?php esc_html_e( 'Renew License', 'social-feed' ); ?>
				</a>
				<button type="button" class="button" id="sf-deactivate-license">
					<?php esc_html_e( 'Deactivate', 'social-feed' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render pricing cards.
	 */
	private static function render_pricing_cards() {
		?>
		<div class="sf-pricing-section">
			<h2><?php esc_html_e( 'Upgrade to Pro', 'social-feed' ); ?></h2>
			<p><?php esc_html_e( 'Unlock powerful features with a Pro license.', 'social-feed' ); ?></p>

			<div class="sf-pricing-cards">
				<!-- Personal -->
				<div class="sf-pricing-card">
					<h3><?php esc_html_e( 'Personal', 'social-feed' ); ?></h3>
					<div class="price">$49<span>/year</span></div>
					<div class="billing"><?php esc_html_e( '1 Site License', 'social-feed' ); ?></div>
					<ul>
						<li><?php esc_html_e( 'Instagram Feeds', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'Hashtag Feeds', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'Grid & List Layouts', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'Popup Display', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'Custom CSS', 'social-feed' ); ?></li>
						<li><?php esc_html_e( '1 Year Updates & Support', 'social-feed' ); ?></li>
					</ul>
					<a href="<?php echo esc_url( SF_License::STORE_URL . '/checkout/?add-to-cart=123&price_id=1' ); ?>" class="button button-secondary" target="_blank">
						<?php esc_html_e( 'Get Personal', 'social-feed' ); ?>
					</a>
				</div>

				<!-- Plus -->
				<div class="sf-pricing-card featured">
					<span class="sf-pricing-badge"><?php esc_html_e( 'Most Popular', 'social-feed' ); ?></span>
					<h3><?php esc_html_e( 'Plus', 'social-feed' ); ?></h3>
					<div class="price">$99<span>/year</span></div>
					<div class="billing"><?php esc_html_e( '5 Site License', 'social-feed' ); ?></div>
					<ul>
						<li><?php esc_html_e( 'Everything in Personal', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'YouTube Integration', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'Masonry & Carousel Layouts', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'Lightbox Popup', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'Content Moderation', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'Feed Analytics', 'social-feed' ); ?></li>
					</ul>
					<a href="<?php echo esc_url( SF_License::STORE_URL . '/checkout/?add-to-cart=123&price_id=2' ); ?>" class="button button-primary" target="_blank">
						<?php esc_html_e( 'Get Plus', 'social-feed' ); ?>
					</a>
				</div>

				<!-- Agency -->
				<div class="sf-pricing-card">
					<h3><?php esc_html_e( 'Agency', 'social-feed' ); ?></h3>
					<div class="price">$199<span>/year</span></div>
					<div class="billing"><?php esc_html_e( 'Unlimited Sites', 'social-feed' ); ?></div>
					<ul>
						<li><?php esc_html_e( 'Everything in Plus', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'Facebook Integration', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'White Label Branding', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'Priority Support', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'Multisite Support', 'social-feed' ); ?></li>
						<li><?php esc_html_e( 'Developer Access', 'social-feed' ); ?></li>
					</ul>
					<a href="<?php echo esc_url( SF_License::STORE_URL . '/checkout/?add-to-cart=123&price_id=3' ); ?>" class="button button-secondary" target="_blank">
						<?php esc_html_e( 'Get Agency', 'social-feed' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Mask license key for display.
	 *
	 * @param string $key License key.
	 * @return string Masked key.
	 */
	private static function mask_license_key( $key ) {
		if ( strlen( $key ) < 10 ) {
			return $key;
		}

		return substr( $key, 0, 4 ) . str_repeat( '*', strlen( $key ) - 8 ) . substr( $key, -4 );
	}
}
