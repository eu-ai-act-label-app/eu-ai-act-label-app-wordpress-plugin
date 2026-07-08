<?php
/**
 * Main plugin bootstrap.
 *
 * @package EU_AI_Label
 */

defined( 'ABSPATH' ) || exit;

/**
 * Singleton that wires up the plugin components.
 */
final class EU_AI_Label_Plugin {

	/**
	 * Meta key used to store the AI label status on an attachment.
	 *
	 * @var string
	 */
	const META_KEY = '_eu_ai_label_status';

	/**
	 * Enum: fully AI-generated image.
	 *
	 * @var string
	 */
	const STATUS_AI_GENERATED = 'ai_generated';

	/**
	 * Enum: real photo modified by AI.
	 *
	 * @var string
	 */
	const STATUS_AI_EDITED = 'ai_edited';

	/**
	 * Enum: no AI involvement (default / no badge).
	 *
	 * @var string
	 */
	const STATUS_NO_AI = 'no_ai';

	/**
	 * Singleton instance.
	 *
	 * @var EU_AI_Label_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return EU_AI_Label_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	/**
	 * Constructor is private to enforce the singleton.
	 */
	private function __construct() {}

	/**
	 * All allowed enum values for the label status.
	 *
	 * @return string[]
	 */
	public static function allowed_statuses() {
		return array(
			self::STATUS_AI_GENERATED,
			self::STATUS_AI_EDITED,
			self::STATUS_NO_AI,
		);
	}

	/**
	 * Whether a status value is a valid enum member.
	 *
	 * @param mixed $status Candidate value.
	 * @return bool
	 */
	public static function is_valid_status( $status ) {
		return is_string( $status ) && in_array( $status, self::allowed_statuses(), true );
	}

	/**
	 * Whether a status should render a badge.
	 *
	 * `no_ai` and missing meta are treated identically: no badge.
	 *
	 * @param mixed $status Candidate value.
	 * @return bool
	 */
	public static function status_has_badge( $status ) {
		return in_array( $status, array( self::STATUS_AI_GENERATED, self::STATUS_AI_EDITED ), true );
	}

	/**
	 * Load dependencies and register hooks.
	 *
	 * @return void
	 */
	private function boot() {
		require_once EU_AI_LABEL_DIR . 'includes/class-license.php';
		require_once EU_AI_LABEL_DIR . 'includes/class-i18n.php';
		require_once EU_AI_LABEL_DIR . 'includes/class-media-meta.php';
		require_once EU_AI_LABEL_DIR . 'includes/class-renderer.php';
		require_once EU_AI_LABEL_DIR . 'includes/class-settings.php';

		( new EU_AI_Label_I18n() )->register();
		( new EU_AI_Label_Media_Meta() )->register();
		( new EU_AI_Label_Renderer() )->register();
		( new EU_AI_Label_Settings() )->register();

		/*
		 * Pro features (adaptive auto-contrast badge, etc.) load only for
		 * entitled sites. The `__premium_only` folder is stripped by Freemius
		 * when it builds the free version, so the file is absent there and this
		 * is a no-op on free installs.
		 */
		if ( EU_AI_Label_License::is_pro() && file_exists( EU_AI_LABEL_DIR . 'includes/pro__premium_only/class-pro.php' ) ) {
			require_once EU_AI_LABEL_DIR . 'includes/pro__premium_only/class-pro.php';
			( new EU_AI_Label_Pro() )->register();
		}
	}
}
