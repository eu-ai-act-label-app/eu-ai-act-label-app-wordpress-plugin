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
 * Pro status can come from the Woo Marketplace distribution, the
 * EU_AI_LABEL_PRO override constant, or the `eu_ai_label_is_pro` filter used
 * by another distribution provider and tests.
 */
final class EU_AI_Label_License {

	/**
	 * Whether the current site is entitled to Pro features.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		$is_pro = false;

		if ( defined( 'EU_AI_LABEL_DISTRIBUTION' ) && 'woocommerce' === EU_AI_LABEL_DISTRIBUTION ) {
			$is_pro = true;
		} elseif ( defined( 'EU_AI_LABEL_PRO' ) ) {
			$is_pro = (bool) EU_AI_LABEL_PRO;
		}

		/**
		 * Filter the resolved Pro entitlement.
		 *
		 * Lets a distribution provider or tests override the result.
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
