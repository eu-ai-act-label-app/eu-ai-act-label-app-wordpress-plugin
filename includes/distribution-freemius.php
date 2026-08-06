<?php

/**
 * Freemius distribution bootstrap.
 *
 * @package EU_AI_Label
 */
defined( 'ABSPATH' ) || exit;
/**
 * Coupon offered to opted-in free users.
 */
define( 'EU_AI_LABEL_PROMO_COUPON', 'FIRSTMONTHFREE' );
if ( !function_exists( 'eu_ai_label_freemius_pricing_url' ) ) {
    /**
     * Point the settings-page upgrade CTA at the SDK pricing screen.
     *
     * A Pro install has nothing left to buy, so it keeps the empty default.
     *
     * @param string $url Previously resolved URL.
     * @return string
     */
    function eu_ai_label_freemius_pricing_url(  $url  ) {
        global $eu_ai_label_fs;
        if ( class_exists( 'EU_AI_Label_License' ) && EU_AI_Label_License::is_pro() ) {
            return $url;
        }
        if ( !isset( $eu_ai_label_fs ) || !is_object( $eu_ai_label_fs ) || !method_exists( $eu_ai_label_fs, 'pricing_url' ) ) {
            return $url;
        }
        return (string) $eu_ai_label_fs->pricing_url();
    }

    /**
     * Resolve the first-month-free offer for this install.
     *
     * Users who have not connected an account are sent to opt-in first; once
     * connected, free installs go to monthly checkout with the coupon applied.
     * Eligibility tracks the connection itself, not usage-tracking consent —
     * opting out of tracking later does not withdraw the offer.
     *
     * @param array $promotion Previously resolved promotion state.
     * @return array
     */
    function eu_ai_label_freemius_promotion(  $promotion  ) {
        global $eu_ai_label_fs;
        $promotion = (array) $promotion;
        if ( class_exists( 'EU_AI_Label_License' ) && EU_AI_Label_License::is_pro() ) {
            return $promotion;
        }
        if ( !isset( $eu_ai_label_fs ) || !is_object( $eu_ai_label_fs ) ) {
            return $promotion;
        }
        $is_opted_in = method_exists( $eu_ai_label_fs, 'is_registered' ) && $eu_ai_label_fs->is_registered();
        if ( !$is_opted_in ) {
            /*
             * Freemius::connect_again() — the only handler behind the
             * reconnect URL — returns early unless the install is anonymous
             * (opt-in skipped) or pending activation. Any other state has to
             * use the plain activation URL, or the button would reload the
             * settings page unchanged.
             */
            $needs_reconnect = method_exists( $eu_ai_label_fs, 'is_anonymous' ) && $eu_ai_label_fs->is_anonymous() || method_exists( $eu_ai_label_fs, 'is_pending_activation' ) && $eu_ai_label_fs->is_pending_activation();
            if ( $needs_reconnect && method_exists( $eu_ai_label_fs, 'get_reconnect_url' ) ) {
                $promotion['url'] = $eu_ai_label_fs->get_reconnect_url();
            } elseif ( method_exists( $eu_ai_label_fs, 'get_activation_url' ) ) {
                $promotion['url'] = $eu_ai_label_fs->get_activation_url();
            }
            $promotion['requires_opt_in'] = !empty( $promotion['url'] );
        } elseif ( method_exists( $eu_ai_label_fs, 'is_paying' ) && !$eu_ai_label_fs->is_paying() && method_exists( $eu_ai_label_fs, 'checkout_url' ) ) {
            $promotion['url'] = $eu_ai_label_fs->checkout_url( 'monthly', false, array(
                'coupon' => EU_AI_LABEL_PROMO_COUPON,
            ) );
            $promotion['coupon'] = EU_AI_LABEL_PROMO_COUPON;
        }
        return $promotion;
    }

}
/*
 * Registered before the SDK bootstrap below: both callbacks only probe the
 * shared instance through method_exists(), so they stay correct — and
 * testable — when the SDK is absent.
 */
add_filter( 'eu_ai_label_pricing_url', 'eu_ai_label_freemius_pricing_url' );
add_filter( 'eu_ai_label_promotion', 'eu_ai_label_freemius_promotion' );
if ( defined( 'WP_TESTS_DOMAIN' ) || !file_exists( EU_AI_LABEL_DIR . 'vendor/freemius/start.php' ) ) {
    return;
}
if ( !function_exists( 'eu_ai_label_fs' ) ) {
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