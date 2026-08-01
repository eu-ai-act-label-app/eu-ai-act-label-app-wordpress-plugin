<?php
/**
 * Freemius SDK customizations for the SDK-rendered screens (pricing,
 * checkout, opt-in): brand skin, EUR default, real yearly prices, icon.
 *
 * Loaded from eu-ai-label.php immediately after the Freemius bootstrap —
 * the pricing template applies these filters when the page renders, so
 * registering them on a later hook (e.g. `plugins_loaded`) could miss it.
 *
 * @package EU_AI_Label
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'eu_ai_label_fs' ) ) {
	return;
}

/*
 * Show the real per-cycle price ("€49.99 / year") instead of the
 * "€4.17 / mo, billed annually" framing the pricing app defaults to.
 */
eu_ai_label_fs()->add_filter( 'pricing/show_annual_in_monthly', '__return_false' );

/**
 * Default the pricing page and in-dashboard checkout to EUR.
 *
 * The plugin targets EU stores; USD stays selectable in the currency
 * dropdown.
 *
 * @return string
 */
function eu_ai_label_fs_default_currency() {
	return 'eur';
}
eu_ai_label_fs()->add_filter( 'default_currency', 'eu_ai_label_fs_default_currency' );

/**
 * Brand skin for the bundled pricing app.
 *
 * The SDK expects an absolute filesystem path (NOT a URL) — it converts it
 * via `fs_asset_url()` and enqueues it on the pricing page.
 *
 * @return string
 */
function eu_ai_label_fs_pricing_css_path() {
	return EU_AI_LABEL_DIR . 'assets/admin/pricing.css';
}
eu_ai_label_fs()->add_filter( 'pricing/css_path', 'eu_ai_label_fs_pricing_css_path' );

/**
 * Serve the bundled brand icon to Freemius-rendered screens, which
 * otherwise fall back to a generic plug icon.
 *
 * Absolute filesystem path; the SDK resolves it to a URL itself.
 *
 * @return string
 */
function eu_ai_label_fs_plugin_icon() {
	return EU_AI_LABEL_DIR . 'assets/img/icon-256.png';
}
eu_ai_label_fs()->add_filter( 'plugin_icon', 'eu_ai_label_fs_plugin_icon' );

/**
 * Enqueue the "limited for early adopters" badge styles on the Lifetime
 * billing toggle. Self-expiring: nothing loads from 2026-08-01 (site timezone).
 *
 * This is a marketing overlay only — actually retiring or repricing the
 * lifetime option on that date happens in the Freemius Developer Dashboard.
 * Genuinely remove it there; EU consumer rules bar fake limited offers.
 *
 * Added as page-specific inline CSS (not part of pricing.css) because the
 * badge is temporary and needs the PHP date gate. The hook suffix is the
 * SDK's pricing submenu under Settings: `{slug}-pricing`.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function eu_ai_label_fs_lifetime_promo_styles( $hook_suffix ) {
	if (
		'settings_page_eu-ai-label-pricing' !== $hook_suffix
		|| current_datetime()->format( 'Y-m-d' ) >= '2026-08-01'
	) {
		return;
	}

	$css = '#fs_pricing_wrapper #fs_pricing_app .fs-billing-cycles li.fs-period--lifetime::after {
		content: "Early adopters \\00b7 ends Aug 1";
		background: #f0b90b;
		border-radius: 999px;
		color: #131b2c;
		display: inline-block;
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.04em;
		line-height: 1.6;
		margin-left: 8px;
		padding: 2px 8px;
		text-transform: uppercase;
		vertical-align: middle;
	}';

	wp_register_style( 'eu-ai-label-lifetime-promo', false, array(), EU_AI_LABEL_VERSION );
	wp_enqueue_style( 'eu-ai-label-lifetime-promo' );
	wp_add_inline_style( 'eu-ai-label-lifetime-promo', $css );
}
add_action( 'admin_enqueue_scripts', 'eu_ai_label_fs_lifetime_promo_styles' );

/*
 * Checkout social proof — enable once the product has enough reviews and
 * the refund policy is finalized in the Freemius dashboard. Adds the
 * money-back badge and the reviews section to the in-dashboard checkout.
 *
 * eu_ai_label_fs()->add_filter(
 *     'checkout/parameters',
 *     function ( $params ) {
 *         return array_merge(
 *             (array) $params,
 *             array(
 *                 'show_refund_badge' => true,
 *                 'show_reviews'      => true,
 *             )
 *         );
 *     }
 * );
 */

/*
 * Replace the in-dashboard pricing page with the external pricing page on
 * euailabel.app. Left disabled: the in-dashboard page keeps buyers inside
 * wp-admin (less friction); flip this on only if the marketing-site page
 * converts measurably better.
 *
 * eu_ai_label_fs()->add_filter(
 *     'pricing_url',
 *     function () {
 *         return 'https://euailabel.app/pricing/?utm_source=wp-admin&utm_medium=plugin';
 *     }
 * );
 */
