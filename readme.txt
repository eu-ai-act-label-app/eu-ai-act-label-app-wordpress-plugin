=== EU AI Label ===
Contributors: lukjak, freemius
Tags: ai, transparency, media-library, images, eu-ai-act
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add visible, localized AI labels to your Media Library images, in the spirit of EU AI Act Article 50 transparency obligations.

== Description ==

EU AI Label lets you flag images in your WordPress Media Library as AI generated or AI modified. Labeled images are wrapped on the front end with a clean badge overlay so visitors can see at a glance which visuals involved AI.

Built for EU WooCommerce stores that want a simple, honest way to disclose AI-generated product imagery — no C2PA, no cryptography, no external services. Just a clear visual mark plus attachment metadata.

**Features**

* Per-image labeling directly in the Media Library, for unlimited images.
* Three-state taxonomy: AI generated, AI modified, or No AI (no badge).
* Server-side-first rendering. Featured/gallery images, in-content images, Elementor AJAX widgets, and Media Library-backed static Elementor backgrounds are covered; lightweight helpers support dynamic/background and Pro tooltip behavior.
* An accessibility-first badge: always visible (no hover needed), high-contrast on any photo, `role="img"` with a screen-reader label, and a readable minimum size. The wording and pill shape mirror the EU standardized AI labels.
* Fully localized badge text — English, Polish, German, French, Spanish, Italian, and Dutch — following the site or user locale.

Labeling is manual and per-image by design: you stay in control of exactly what is disclosed.

This plugin is part of the euailabel.app AI Act toolkit for e-commerce.

**Need to audit images across an entire website?** [AI Act Icon](https://aiacticon.com/?utm_source=wordpress.org&utm_medium=plugin-readme&utm_campaign=eu-ai-label) discovers public website images and where they appear, then helps you classify them and maintain an audit history.

EU AI Label is an independent tool that helps with transparency disclosures in the spirit of the EU AI Act (Regulation (EU) 2024/1689). It is not affiliated with, endorsed by, or an official labeling scheme of the European Union or any EU institution.

**EU AI Label Pro**

A Pro upgrade adds, for stores that need more:

* An adaptive badge that auto-adjusts to light or dark images.
* Label Studio controls for background/text colors, border radius, and an optional Sparkles, AI Pen, or Robot icon.
* Bulk labeling from the Media Library (list and grid views).
* Optional "how it was altered" details shown in a badge tooltip (e.g. background removed, color modified).
* A tamper-evident audit log of every label change (who, what, when), sealed with a hash chain.

Label Studio is available under Settings → EU AI Label → Label Studio. Its live preview shows color, corner-radius, and icon changes immediately and reports the resulting WCAG AA text-contrast ratio before the style is saved. Badge wording remains localized and cannot be replaced. The compact “AI” mark stays circular and icon-free, while custom colors override the adaptive light-chip palette for consistent branding.

The free plugin is a complete, unrestricted disclosure tool; Pro adds the refinements above. All Pro functionality lives in a separately distributed build — nothing in the free plugin is locked or limited.

== Installation ==

1. Upload the `eu-ai-label` folder to `/wp-content/plugins/`, or install through the Plugins screen.
2. Activate the plugin through the *Plugins* screen in WordPress.
3. Open any image in the Media Library and set its *AI label* to AI generated or AI modified.
4. Labeled images get the badge automatically on the front end. There is nothing else to configure.

== Frequently Asked Questions ==

= Does this detect AI images automatically? =

No. Labeling is manual and per-image, by design. You decide what is disclosed.

= Is the free version limited? =

No. The free plugin is fully functional: you can label as many images as you like, and every feature it ships is available to everyone. Pro is a separate build that adds extras (Label Studio, adaptive badge, bulk labeling, alteration tooltips, and an audit log). Labels always keep working and stay editable, even if you drop from Pro back to free.

= Can I change the badge text or its appearance? =

The free badge uses a fixed, accessibility-first style. Pro includes Label Studio for changing background and text colors, border radius, and an optional icon, with a live WCAG contrast warning. Badge text remains fixed and localized automatically.

= Which images get the badge? =

Any labeled Media Library image used as a featured image, in a gallery/carousel, in post content, or as a static Elementor background. Externally hotlinked images and raw CSS background URLs without a Media Library attachment ID cannot be labeled. Elementor background slideshows are skipped because one fixed label cannot accurately describe a changing image.

= Is this legal advice or a guarantee of compliance? =

No. EU AI Label is a tool to help you disclose AI-generated imagery clearly. It does not constitute legal advice, and using it does not by itself guarantee compliance with the EU AI Act or any other law. Consult a qualified professional about your obligations.

= What happens to labels when I uninstall? =

Plugin settings are removed on uninstall. Per-image labels stored in attachment metadata are preserved so they survive a reinstall.

== Screenshots ==

1. The AI transparency badge rendered on a front-end image (with the optional Pro alteration tooltip).
2. Setting the AI label and alteration details on an attachment in the Media Library.
3. Pro Label Studio with color controls, border radius, icon choices, live preview, and WCAG contrast feedback.

== Changelog ==

= 0.5.0 =
* Added: Pro Label Studio with live preview, background/text colors, border radius, optional Sparkles/AI Pen/Robot icons, and a WCAG contrast warning.
* Improved: Elementor support for AJAX-loaded widgets, custom galleries/carousels, and Media Library-backed static background images.
* Fixed: Elementor ratio-card thumbnails no longer disappear when the label wrapper is inserted.
* Fixed: Pro alteration tooltips inside linked thumbnails keep a single focus target and no longer block image links.
* Added: WooCommerce product-editor controls for labeling featured and gallery images.
* Improved: WooCommerce controls now use a native Product Data tab with live status feedback.
* Added: a premium-only Woo Marketplace build with all Pro features and no external licensing runtime.
* Added: HPOS compatibility declaration and testing against WooCommerce 10.8 and 10.9.
* Changed: the minimum supported PHP version is now 7.4.

= 0.4.1 =
* Fixed: temporary Freemius pricing-page CSS now loads through WordPress enqueue APIs.
* Added: a related AI Act Icon website-audit product link.

= 0.4.0 =
* Added: a new "AI (without details)" label for images where AI involvement is disclosed but the type of change is not. It renders as a compact, localized "AI" circle badge.

= 0.3.0 =
* Changed: removed the free-plan 10-image limit — the free plugin now labels unlimited images and is fully functional, per WordPress.org guidelines.

= 0.2.3 =
* New: redesigned upgrade page — real yearly prices instead of per-month framing, prices in EUR by default (USD still selectable), and the plugin icon on all Freemius screens.

= 0.2.2 =
* Changed: the bundled Freemius SDK moved from includes/freemius to vendor/freemius, the wordpress.org convention for third-party libraries.

= 0.2.1 =
* Fixed: removed the Author URI plugin header (duplicated the Plugin URI, flagged by wp.org Plugin Check).

= 0.2.0 =
* New: "AI label" column with status chips in the Media Library list view.
* New: redesigned settings page — live badge preview, labeled-image counts, clearer plan overview.
* New: free plan labels up to 10 images; Pro is unlimited. Existing labels always keep working.
* Improved: audit log (Pro) shows label changes as status chips.
* Fixed: admin thumbnails are no longer wrapped with front-end badge markup (stray badge text under Media Library thumbnails).

= 0.1.0 =
* Initial release: per-image AI labeling, localized accessibility-first front-end badge covering featured, gallery, and in-content images.

== Upgrade Notice ==

= 0.2.0 =
Media Library label column, redesigned settings page, and a 10-image free-plan cap (existing labels keep working).

= 0.1.0 =
Initial release.
