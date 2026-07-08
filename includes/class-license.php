<?php
/**
 * License / entitlement gate.
 *
 * @package EU_AI_Label
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provider-agnostic Pro entitlement check.
 *
 * Pro status can come from (in order): the EU_AI_LABEL_PRO override constant
 * (handy for local dev / CI), the Freemius SDK when bundled and configured, or
 * the `eu_ai_label_is_pro` filter (a custom license endpoint, another Merchant
 * of Record, or tests). It reads only locally cached state — Freemius performs
 * its own network calls, caching, and offline grace period — so is_pro() is
 * cheap to call on every render and fails open if the licensing backend is
 * unreachable.
 */
final class EU_AI_Label_License {

	/**
	 * Maximum number of labeled images on the free plan (Pro is unlimited).
	 *
	 * Any stored label consumes a slot, including "No AI": the cap is on the
	 * declaration, not on whether a badge renders. Existing labels always keep
	 * working (and stay editable) — the cap only blocks labeling NEW images,
	 * so a Pro downgrade never silently removes disclosures.
	 *
	 * @var int
	 */
	const FREE_LABEL_LIMIT = 10;

	/**
	 * Whether the current site is entitled to Pro features.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		$is_pro = false;

		if ( defined( 'EU_AI_LABEL_PRO' ) ) {
			$is_pro = (bool) EU_AI_LABEL_PRO;
		} elseif ( function_exists( 'eu_ai_label_fs' ) ) {
			// can_use_premium_code() is true for paying customers, active
			// trials, and the developer's own install; it reads cached
			// license state without a network round-trip.
			$is_pro = (bool) eu_ai_label_fs()->can_use_premium_code();
		}

		/**
		 * Filter the resolved Pro entitlement.
		 *
		 * Lets a custom licensing backend, another provider, or tests override
		 * the result.
		 *
		 * @param bool $is_pro Whether Pro features are unlocked.
		 */
		return (bool) apply_filters( 'eu_ai_label_is_pro', $is_pro );
	}

	/**
	 * Convenience inverse of is_pro().
	 *
	 * @return bool
	 */
	public static function is_free() {
		return ! self::is_pro();
	}
}
