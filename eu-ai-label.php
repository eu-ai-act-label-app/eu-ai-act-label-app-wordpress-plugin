<?php
/**
 * Plugin Name:       EU AI Label
 * Plugin URI:        https://euailabel.app/
 * Description:       Adds visible, localized AI labels to images in the Media Library, in the spirit of EU AI Act Article 50 transparency obligations.
 * Version:           0.6.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            EU AI Label
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eu-ai-label
 * Domain Path:       /languages
 *
 * @package EU_AI_Label
 */

defined( 'ABSPATH' ) || exit;

define( 'EU_AI_LABEL_VERSION', '0.6.0' );
define( 'EU_AI_LABEL_FILE', __FILE__ );
define( 'EU_AI_LABEL_DIR', plugin_dir_path( __FILE__ ) );
define( 'EU_AI_LABEL_URL', plugin_dir_url( __FILE__ ) );
define( 'EU_AI_LABEL_BASENAME', plugin_basename( __FILE__ ) );

require_once EU_AI_LABEL_DIR . 'distribution.php';

/**
 * Remove plugin options when the plugin is uninstalled.
 *
 * Attachment metadata and the Pro audit log remain intact so disclosure
 * records survive a reinstall.
 *
 * @return void
 */
function eu_ai_label_uninstall_cleanup() {
	delete_option( 'eu_ai_label_options' );

	if ( is_multisite() ) {
		$eu_ai_label_site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $eu_ai_label_site_ids as $eu_ai_label_site_id ) {
			switch_to_blog( (int) $eu_ai_label_site_id );
			delete_option( 'eu_ai_label_options' );
			restore_current_blog();
		}
	}
}

$eu_ai_label_distribution_bootstrap = EU_AI_LABEL_DIR . 'includes/distribution-' . sanitize_key( EU_AI_LABEL_DISTRIBUTION ) . '.php';
if ( file_exists( $eu_ai_label_distribution_bootstrap ) ) {
	require_once $eu_ai_label_distribution_bootstrap;
}
unset( $eu_ai_label_distribution_bootstrap );

if ( 'woocommerce' === EU_AI_LABEL_DISTRIBUTION ) {
	register_uninstall_hook( __FILE__, 'eu_ai_label_uninstall_cleanup' );
}

require_once EU_AI_LABEL_DIR . 'includes/class-plugin.php';

/**
 * Retrieve the main plugin instance.
 *
 * @return EU_AI_Label_Plugin
 */
function eu_ai_label() {
	return EU_AI_Label_Plugin::instance();
}

// Bootstrap.
eu_ai_label();

/**
 * Activation hook. Ensures default options exist.
 *
 * @return void
 */
function eu_ai_label_activate() {
	require_once EU_AI_LABEL_DIR . 'includes/class-settings.php';

	if ( false === get_option( 'eu_ai_label_options', false ) ) {
		add_option( 'eu_ai_label_options', EU_AI_Label_Settings::get_defaults() );
	}
}
register_activation_hook( __FILE__, 'eu_ai_label_activate' );
