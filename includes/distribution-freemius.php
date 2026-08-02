<?php

/**
 * Freemius distribution bootstrap.
 *
 * @package EU_AI_Label
 */
defined( 'ABSPATH' ) || exit;
if ( function_exists( 'eu_ai_label_fs' ) || defined( 'WP_TESTS_DOMAIN' ) || !file_exists( EU_AI_LABEL_DIR . 'vendor/freemius/start.php' ) ) {
    return;
}
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

/**
 * Resolve entitlement from locally cached Freemius license state.
 *
 * @param bool $is_pro Previously resolved entitlement.
 * @return bool
 */
function eu_ai_label_freemius_is_pro(  $is_pro  ) {
    return ( eu_ai_label_fs()->can_use_premium_code() ? true : $is_pro );
}

eu_ai_label_fs();
do_action( 'eu_ai_label_fs_loaded' );
add_filter( 'eu_ai_label_is_pro', 'eu_ai_label_freemius_is_pro', 5 );
require_once EU_AI_LABEL_DIR . 'includes/freemius-customizations.php';
eu_ai_label_fs()->add_action( 'after_uninstall', 'eu_ai_label_uninstall_cleanup' );