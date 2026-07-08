<?php

/**
 * Plugin Name:       EU AI Label
 * Plugin URI:        https://euailabel.app/
 * Description:       Adds visible, localized AI labels to images in the Media Library, in the spirit of EU AI Act Article 50 transparency obligations.
 * Version:           0.2.2
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            EU AI Label
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eu-ai-label
 * Domain Path:       /languages
 *
 * @package EU_AI_Label
 */
defined( 'ABSPATH' ) || exit;
define( 'EU_AI_LABEL_VERSION', '0.2.2' );
define( 'EU_AI_LABEL_FILE', __FILE__ );
define( 'EU_AI_LABEL_DIR', plugin_dir_path( __FILE__ ) );
define( 'EU_AI_LABEL_URL', plugin_dir_url( __FILE__ ) );
define( 'EU_AI_LABEL_BASENAME', plugin_basename( __FILE__ ) );
/*
 * Freemius bootstrap — licensing + Pro updates + Merchant of Record.
 *
 * The plugin ID and public key are safe to embed (public by design). Only
 * initializes when the SDK is bundled at vendor/freemius/start.php (see
 * vendor/freemius/README.md) and is skipped under the PHPUnit suite. The
 * Freemius SECRET key (sk_...) is never stored here — it is used solely when
 * deploying builds to Freemius. Values may be overridden per-environment via
 * the EU_AI_LABEL_FS_ID / EU_AI_LABEL_FS_PUBLIC_KEY constants.
 */
if ( !function_exists( 'eu_ai_label_fs' ) && !defined( 'WP_TESTS_DOMAIN' ) && file_exists( EU_AI_LABEL_DIR . 'vendor/freemius/start.php' ) ) {
    /**
     * Lazily create and return the shared Freemius instance.
     *
     * @return Freemius
     */
    function eu_ai_label_fs() {
        global $eu_ai_label_fs;
        if ( !isset( $eu_ai_label_fs ) ) {
            require_once EU_AI_LABEL_DIR . 'vendor/freemius/start.php';
            $eu_ai_label_fs = fs_dynamic_init( array(
                'id'               => ( defined( 'EU_AI_LABEL_FS_ID' ) ? EU_AI_LABEL_FS_ID : '33411' ),
                'slug'             => 'eu-ai-label',
                'premium_slug'     => 'eu-ai-label-pro',
                'type'             => 'plugin',
                'public_key'       => ( defined( 'EU_AI_LABEL_FS_PUBLIC_KEY' ) ? EU_AI_LABEL_FS_PUBLIC_KEY : 'pk_6886f48d2f1543266420c80961ce0' ),
                'is_premium'       => false,
                'premium_suffix'   => 'Pro',
                'has_addons'       => false,
                'has_paid_plans'   => true,
                'menu'             => array(
                    'slug'   => 'eu-ai-label',
                    'parent' => array(
                        'slug' => 'options-general.php',
                    ),
                ),
                'is_live'          => true,
                'is_org_compliant' => true,
            ) );
        }
        return $eu_ai_label_fs;
    }

    eu_ai_label_fs();
    do_action( 'eu_ai_label_fs_loaded' );
    /**
     * Remove plugin options when the plugin is uninstalled.
     *
     * Hooked on Freemius's `after_uninstall` action rather than shipping an
     * uninstall.php file. WordPress runs uninstall.php *instead of* registered
     * uninstall hooks, which would suppress Freemius's own uninstall handling.
     * With no uninstall.php present, WordPress loads this main file on uninstall
     * (registering this callback) and fires Freemius's uninstall hook, which in
     * turn fires `after_uninstall`.
     *
     * Attachment meta (`_eu_ai_label_status`, `_eu_ai_label_details`) and the
     * Pro audit-log table are intentionally left intact so labels and their
     * change history survive a reinstall.
     *
     * @return void
     */
    function eu_ai_label_uninstall_cleanup() {
        delete_option( 'eu_ai_label_options' );
        if ( is_multisite() ) {
            $eu_ai_label_site_ids = get_sites( array(
                'fields' => 'ids',
                'number' => 0,
            ) );
            foreach ( $eu_ai_label_site_ids as $eu_ai_label_site_id ) {
                switch_to_blog( (int) $eu_ai_label_site_id );
                delete_option( 'eu_ai_label_options' );
                restore_current_blog();
            }
        }
    }

    eu_ai_label_fs()->add_action( 'after_uninstall', 'eu_ai_label_uninstall_cleanup' );
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