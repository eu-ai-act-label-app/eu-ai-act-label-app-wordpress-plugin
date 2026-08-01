<?php
/**
 * Internationalization loader.
 *
 * @package EU_AI_Label
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the plugin text domain so translations follow determine_locale().
 */
class EU_AI_Label_I18n {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Register the bundled language directory with WordPress.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required for bundled translations outside WordPress.org language packs.
		load_plugin_textdomain(
			'eu-ai-label',
			false,
			dirname( EU_AI_LABEL_BASENAME ) . '/languages'
		);
	}
}
