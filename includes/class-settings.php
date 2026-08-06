<?php
/**
 * Admin page for the plugin (status + guidance).
 *
 * The free badge ships with a fixed WCAG-AA style. Pro features can add their
 * own settings tabs, including Label Studio appearance controls.
 *
 * @package EU_AI_Label
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Settings → EU AI Label status page.
 */
class EU_AI_Label_Settings {

	/**
	 * Option name for shared appearance settings.
	 *
	 * @var string
	 */
	const OPTION = 'eu_ai_label_options';

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE = 'eu-ai-label';

	/**
	 * WordPress.org review form for the plugin.
	 *
	 * @var string
	 */
	const REVIEW_URL = 'https://wordpress.org/support/plugin/eu-ai-label/reviews/#new-post';

	/**
	 * Default appearance options.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'position'      => 'bottom-left',
			'bg_color'      => '#1d1d1b',
			'text_color'    => '#ffffff',
			'size'          => 'm',
			'show_icon'     => 1,
			'border_radius' => 999,
			'icon'          => 'none',
		);
	}

	/**
	 * Retrieve defaults-merged options.
	 *
	 * @return array
	 */
	public static function get_options() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::get_defaults() );
	}

	/**
	 * Retrieve the URL used by the review CTA.
	 *
	 * The WordPress.org form only fits the wp.org channel. The Woo Marketplace
	 * edition is not listed there, so it gets no CTA unless
	 * `eu_ai_label_review_url` supplies its marketplace URL.
	 *
	 * @return string Review-form URL, or an empty string to hide the CTA.
	 */
	public static function get_review_url() {
		$url = ( defined( 'EU_AI_LABEL_DISTRIBUTION' ) && 'woocommerce' === EU_AI_LABEL_DISTRIBUTION )
			? ''
			: self::REVIEW_URL;

		/**
		 * Filter the destination of the settings-page review CTA.
		 *
		 * @param string $url Review-form URL. An empty string hides the CTA.
		 */
		return (string) apply_filters( 'eu_ai_label_review_url', $url );
	}

	/**
	 * Retrieve the URL of the plans and pricing screen.
	 *
	 * Only a distribution that sells an upgrade supplies one — the Woo
	 * Marketplace edition already ships every premium feature, and a Pro
	 * install has nothing left to buy. Prices are never rendered here: the
	 * seller's own screen reads them live, so they cannot drift.
	 *
	 * @return string Pricing-page URL, or an empty string when there is
	 *                nothing to upgrade to.
	 */
	public static function get_pricing_url() {
		/**
		 * Filter the destination of the settings-page upgrade CTA.
		 *
		 * The active distribution bootstrap fills this in.
		 *
		 * @param string $url Pricing-page URL. An empty string hides the CTA.
		 */
		return (string) apply_filters( 'eu_ai_label_pricing_url', '' );
	}

	/**
	 * Feature lists behind the plan comparison.
	 *
	 * @return array{free:array<int,string>,pro:array<int,string>}
	 */
	public static function get_plan_features() {
		return array(
			'free' => array(
				__( 'Unlimited per-image labeling from the Media Library', 'eu-ai-label' ),
				__( 'Always-visible badge that meets WCAG AA on any image', 'eu-ai-label' ),
				__( 'Badge text localized in 7 languages', 'eu-ai-label' ),
				__( 'Featured, gallery, in-content, WooCommerce, and Elementor images', 'eu-ai-label' ),
			),
			'pro'  => array(
				__( 'Adaptive badge that adjusts to light or dark images', 'eu-ai-label' ),
				__( 'Label Studio: badge colors, corner radius, and an optional icon', 'eu-ai-label' ),
				__( 'Bulk labeling from the Media Library list and grid views', 'eu-ai-label' ),
				__( '“How it was altered” details shown in a badge tooltip', 'eu-ai-label' ),
				__( 'Tamper-evident audit log sealed with a hash chain', 'eu-ai-label' ),
			),
		);
	}

	/**
	 * Retrieve the first-month-free promotion for eligible installations.
	 *
	 * `requires_opt_in` marks a URL that connects the account rather than
	 * opening checkout, so the CTA can say so; `coupon` is the code to show
	 * once checkout is reachable.
	 *
	 * @return array{url:string,requires_opt_in:bool,coupon:string}
	 */
	public static function get_promotion() {
		$defaults = array(
			'url'             => '',
			'requires_opt_in' => false,
			'coupon'          => '',
		);

		/**
		 * Filter the first-month-free promotion state.
		 *
		 * The active distribution bootstrap fills this in; an empty URL
		 * hides the CTA.
		 *
		 * @param array{url:string,requires_opt_in:bool,coupon:string} $promotion Promotion state.
		 */
		return wp_parse_args( (array) apply_filters( 'eu_ai_label_promotion', $defaults ), $defaults );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue the shared admin stylesheet on the plugin settings page.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}

		EU_AI_Label_Media_Meta::register_admin_style();
		wp_enqueue_style( 'eu-ai-label-admin' );
	}

	/**
	 * Add the submenu under Settings.
	 *
	 * @return void
	 */
	public function add_page() {
		add_options_page(
			__( 'EU AI Label', 'eu-ai-label' ),
			__( 'EU AI Label', 'eu-ai-label' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registered settings tabs (slug => label).
	 *
	 * Pro features append their own tabs (e.g. the audit log) via the
	 * `eu_ai_label_settings_tabs` filter; each non-default tab renders through
	 * the `eu_ai_label_settings_tab_{slug}` action.
	 *
	 * @return array<string,string>
	 */
	private function tabs() {
		/**
		 * Filter the settings-page tabs.
		 *
		 * @param array<string,string> $tabs Tab slug => tab label.
		 */
		return (array) apply_filters(
			'eu_ai_label_settings_tabs',
			array( 'status' => __( 'Status', 'eu-ai-label' ) )
		);
	}

	/**
	 * Render the settings page (tab router).
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = $this->tabs();

		// Read-only tab navigation; no state is changed from this request.
		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'status'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $current ] ) ) {
			$current = 'status';
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'EU AI Label', 'eu-ai-label' ); ?></h1>

			<?php if ( count( $tabs ) > 1 ) : ?>
				<nav class="nav-tab-wrapper">
					<?php
					foreach ( $tabs as $slug => $label ) {
						$url = add_query_arg(
							array(
								'page' => self::PAGE,
								'tab'  => $slug,
							),
							admin_url( 'options-general.php' )
						);
						printf(
							'<a class="nav-tab%1$s" href="%2$s">%3$s</a>',
							esc_attr( $slug === $current ? ' nav-tab-active' : '' ),
							esc_url( $url ),
							esc_html( $label )
						);
					}
					?>
				</nav>
			<?php endif; ?>

			<?php
			if ( 'status' !== $current ) {
				/**
				 * Render a non-default settings tab.
				 *
				 * @since 0.1.0
				 */
				do_action( "eu_ai_label_settings_tab_{$current}" );
				echo '</div>';
				return;
			}

			$this->render_status_tab();
			?>
		</div>
		<?php
	}

	/**
	 * Count labeled attachments per status.
	 *
	 * @return array<string,int> Status => attachment count (statuses with no
	 *                           rows are present with 0).
	 */
	public static function get_status_counts() {
		global $wpdb;

		$counts = array_fill_keys( EU_AI_Label_Plugin::allowed_statuses(), 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one aggregate read on a settings screen; core offers no grouped meta count.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS status, COUNT(*) AS total
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'attachment'
				WHERE pm.meta_key = %s
				GROUP BY pm.meta_value",
				EU_AI_Label_Plugin::META_KEY
			)
		);

		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row->status ] ) ) {
				$counts[ $row->status ] = (int) $row->total;
			}
		}

		return $counts;
	}

	/**
	 * Render the default status tab.
	 *
	 * @return void
	 */
	private function render_status_tab() {
		$is_pro         = class_exists( 'EU_AI_Label_License' ) && EU_AI_Label_License::is_pro();
		$is_woocommerce = defined( 'EU_AI_LABEL_DISTRIBUTION' ) && 'woocommerce' === EU_AI_LABEL_DISTRIBUTION;
		$counts         = self::get_status_counts();
		$plan_features  = self::get_plan_features();
		$pricing_url    = self::get_pricing_url();
		$review_url     = self::get_review_url();
		$promotion      = self::get_promotion();
		$promotion_url  = (string) $promotion['url'];
		$promo_count    = ( $promotion_url ? 1 : 0 ) + ( $review_url ? 1 : 0 );
		?>
			<p><?php echo esc_html__( 'Adds visible, localized AI transparency labels to your images, in the spirit of EU AI Act Article 50.', 'eu-ai-label' ); ?></p>

			<div class="eu-ai-label-cards">
				<div class="eu-ai-label-card">
					<h2><?php echo esc_html__( 'Badge preview', 'eu-ai-label' ); ?></h2>
					<div class="eu-ai-label-preview">
						<div class="eu-ai-label-preview-tile eu-ai-label-preview-tile--light">
							<span class="eu-ai-label-preview-badge"><?php echo esc_html( EU_AI_Label_Renderer::badge_text( EU_AI_Label_Plugin::STATUS_AI_GENERATED ) ); ?></span>
						</div>
						<div class="eu-ai-label-preview-tile eu-ai-label-preview-tile--dark">
							<span class="eu-ai-label-preview-badge<?php echo $is_pro ? ' eu-ai-label-preview-badge--light' : ''; ?>"><?php echo esc_html( EU_AI_Label_Renderer::badge_text( EU_AI_Label_Plugin::STATUS_AI_EDITED ) ); ?></span>
						</div>
					</div>
					<p class="description">
						<?php
						if ( $is_pro ) {
							echo esc_html__( 'Pro Label Studio controls the badge colors, corners, and optional icon. With default colors, adaptive contrast can still switch to a light chip on dark images.', 'eu-ai-label' );
						} else {
							echo esc_html__( 'The badge style is fixed and meets WCAG AA contrast on any image. Its text follows the visitor’s language automatically.', 'eu-ai-label' );
						}
						?>
					</p>
				</div>

				<div class="eu-ai-label-card">
					<h2><?php echo esc_html__( 'Labeled images', 'eu-ai-label' ); ?></h2>
					<div class="eu-ai-label-counts">
						<div>
							<?php echo wp_kses_post( EU_AI_Label_Media_Meta::status_chip( EU_AI_Label_Plugin::STATUS_AI_GENERATED ) ); ?>
							<span class="eu-ai-label-count"><?php echo esc_html( number_format_i18n( $counts[ EU_AI_Label_Plugin::STATUS_AI_GENERATED ] ) ); ?></span>
						</div>
						<div>
							<?php echo wp_kses_post( EU_AI_Label_Media_Meta::status_chip( EU_AI_Label_Plugin::STATUS_AI_EDITED ) ); ?>
							<span class="eu-ai-label-count"><?php echo esc_html( number_format_i18n( $counts[ EU_AI_Label_Plugin::STATUS_AI_EDITED ] ) ); ?></span>
						</div>
						<div>
							<?php echo wp_kses_post( EU_AI_Label_Media_Meta::status_chip( EU_AI_Label_Plugin::STATUS_AI_UNDISCLOSED ) ); ?>
							<span class="eu-ai-label-count"><?php echo esc_html( number_format_i18n( $counts[ EU_AI_Label_Plugin::STATUS_AI_UNDISCLOSED ] ) ); ?></span>
						</div>
						<div>
							<?php echo wp_kses_post( EU_AI_Label_Media_Meta::status_chip( EU_AI_Label_Plugin::STATUS_NO_AI ) ); ?>
							<span class="eu-ai-label-count"><?php echo esc_html( number_format_i18n( $counts[ EU_AI_Label_Plugin::STATUS_NO_AI ] ) ); ?></span>
						</div>
					</div>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">
							<?php echo esc_html__( 'Go to the Media Library', 'eu-ai-label' ); ?>
						</a>
					</p>
				</div>

				<div class="eu-ai-label-card">
					<h2><?php echo esc_html__( 'How to label an image', 'eu-ai-label' ); ?></h2>
					<ol>
						<li><?php echo esc_html__( 'Open the Media Library and select an image.', 'eu-ai-label' ); ?></li>
						<li><?php echo esc_html__( 'Set its “AI label” to AI generated or AI modified.', 'eu-ai-label' ); ?></li>
						<li><?php echo esc_html__( 'Done — the badge appears automatically wherever the image is shown on the front end.', 'eu-ai-label' ); ?></li>
					</ol>
				</div>

				<div class="eu-ai-label-card eu-ai-label-card--plan">
					<h2><?php echo esc_html__( 'Plan', 'eu-ai-label' ); ?></h2>
					<div class="eu-ai-label-plans">
						<div class="eu-ai-label-plan<?php echo $is_pro ? '' : ' eu-ai-label-plan--current'; ?>">
							<h3>
								<?php echo esc_html__( 'Free', 'eu-ai-label' ); ?>
								<?php if ( ! $is_pro ) : ?>
									<span class="eu-ai-label-plan__badge"><?php echo esc_html__( 'Current plan', 'eu-ai-label' ); ?></span>
								<?php endif; ?>
							</h3>
							<p class="description"><?php echo esc_html__( 'A complete disclosure tool — nothing is locked or limited.', 'eu-ai-label' ); ?></p>
							<ul class="eu-ai-label-plan__features">
								<?php foreach ( $plan_features['free'] as $eu_ai_label_feature ) : ?>
									<li><?php echo esc_html( $eu_ai_label_feature ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>

						<div class="eu-ai-label-plan<?php echo $is_pro ? ' eu-ai-label-plan--current' : ''; ?>">
							<h3>
								<?php echo esc_html( $is_woocommerce ? __( 'Woo Marketplace edition', 'eu-ai-label' ) : __( 'Pro', 'eu-ai-label' ) ); ?>
								<?php if ( $is_pro ) : ?>
									<span class="eu-ai-label-plan__badge"><?php echo esc_html__( 'Current plan', 'eu-ai-label' ); ?></span>
								<?php endif; ?>
							</h3>
							<p class="description"><?php echo esc_html__( 'Everything in Free, plus:', 'eu-ai-label' ); ?></p>
							<ul class="eu-ai-label-plan__features">
								<?php foreach ( $plan_features['pro'] as $eu_ai_label_feature ) : ?>
									<li><?php echo esc_html( $eu_ai_label_feature ); ?></li>
								<?php endforeach; ?>
								<?php if ( $is_woocommerce ) : ?>
									<li><?php echo esc_html__( 'AI label controls in the WooCommerce product editor', 'eu-ai-label' ); ?></li>
								<?php endif; ?>
							</ul>
							<?php if ( $pricing_url ) : ?>
								<p class="eu-ai-label-plan__cta">
									<a class="button button-primary" href="<?php echo esc_url( $pricing_url ); ?>">
										<?php echo esc_html__( 'See plans and pricing', 'eu-ai-label' ); ?>
									</a>
								</p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<?php if ( $promo_count > 0 ) : ?>
			<div class="eu-ai-label-promotions<?php echo 1 === $promo_count ? ' eu-ai-label-promotions--single' : ''; ?>">
				<?php if ( $promotion_url ) : ?>
					<div class="eu-ai-label-promotion eu-ai-label-promotion--offer">
						<span class="dashicons dashicons-awards" aria-hidden="true"></span>
						<div class="eu-ai-label-promotion__content">
							<h2><?php echo esc_html__( 'Get your first month of Pro free', 'eu-ai-label' ); ?></h2>
							<?php if ( $promotion['requires_opt_in'] ) : ?>
								<p><?php echo esc_html__( 'Opt in with your email first to unlock this offer.', 'eu-ai-label' ); ?></p>
							<?php else : ?>
								<p>
									<?php echo esc_html__( 'Your coupon:', 'eu-ai-label' ); ?>
									<code class="eu-ai-label-coupon"><?php echo esc_html( $promotion['coupon'] ); ?></code>
								</p>
							<?php endif; ?>
						</div>
						<a class="button button-primary" href="<?php echo esc_url( $promotion_url ); ?>">
							<?php echo esc_html( $promotion['requires_opt_in'] ? __( 'Opt in to continue', 'eu-ai-label' ) : __( 'Apply coupon', 'eu-ai-label' ) ); ?>
						</a>
					</div>
				<?php endif; ?>

				<?php if ( $review_url ) : ?>
					<div class="eu-ai-label-promotion eu-ai-label-promotion--review">
						<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
						<div class="eu-ai-label-promotion__content">
							<h2><?php echo esc_html__( 'Enjoying EU AI Label?', 'eu-ai-label' ); ?></h2>
							<p><?php echo esc_html__( 'A short review helps more WordPress users discover the plugin.', 'eu-ai-label' ); ?></p>
						</div>
						<a class="button button-secondary" href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html__( 'Leave a review', 'eu-ai-label' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		<?php
	}
}
