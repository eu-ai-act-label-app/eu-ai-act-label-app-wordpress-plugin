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

				<div class="eu-ai-label-card">
					<h2><?php echo esc_html__( 'Plan', 'eu-ai-label' ); ?></h2>
					<p>
						<strong>
							<?php
							if ( $is_woocommerce ) {
								echo esc_html__( 'Woo Marketplace — all premium features active', 'eu-ai-label' );
							} else {
								echo esc_html( $is_pro ? __( 'Pro — Label Studio and adaptive badge active', 'eu-ai-label' ) : __( 'Free', 'eu-ai-label' ) );
							}
							?>
						</strong>
					</p>
					<p class="description">
						<?php
						if ( $is_woocommerce ) {
							echo esc_html__( 'Your Woo Marketplace edition includes product-editor controls, Label Studio, adaptive badges, bulk labeling, alteration details, and the audit log.', 'eu-ai-label' );
						} else {
							echo esc_html__( 'The free plugin labels unlimited images with the always-visible, WCAG AA badge. Pro adds Label Studio, an adaptive badge, bulk labeling, alteration details with a badge tooltip, and a tamper-evident audit log.', 'eu-ai-label' );
						}
						?>
					</p>
				</div>
			</div>
		<?php
	}
}
