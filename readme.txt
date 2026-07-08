=== EU AI Label ===
Contributors: lukjaki, freemius
Tags: ai, transparency, media-library, images, eu-ai-act
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add visible, localized AI labels to your Media Library images, in the spirit of EU AI Act Article 50 transparency obligations.

== Description ==

EU AI Label lets you flag images in your WordPress Media Library as AI generated or AI modified. Labeled images are wrapped on the front end with a clean badge overlay so visitors can see at a glance which visuals involved AI.

Built for EU WooCommerce stores that want a simple, honest way to disclose AI-generated product imagery — no C2PA, no cryptography, no external services. Just a clear visual mark plus attachment metadata.

**Features**

* Per-image labeling directly in the Media Library (up to 10 labeled images on the free plan).
* Three-state taxonomy: AI generated, AI modified, or No AI (no badge).
* Server-side rendering — no front-end JavaScript required. Both featured/gallery images (via `wp_get_attachment_image`) and in-content images (Image blocks, galleries, and classic-editor images) are covered.
* An accessibility-first badge: always visible (no hover needed), high-contrast on any photo, `role="img"` with a screen-reader label, and a readable minimum size. The wording and pill shape mirror the EU standardized AI labels.
* Fully localized badge text — English, Polish, German, French, Spanish, Italian, and Dutch — following the site or user locale.

Labeling is manual and per-image by design: you stay in control of exactly what is disclosed.

This plugin is part of the euailabel.app AI Act toolkit for e-commerce.

**EU AI Label Pro**

A Pro upgrade adds, for stores that need more:

* Unlimited labeled images (the free plan covers up to 10).
* An adaptive badge that auto-adjusts to light or dark images.
* Bulk labeling from the Media Library (list and grid views).
* Optional "how it was altered" details shown in a badge tooltip (e.g. background removed, color modified).
* A tamper-evident audit log of every label change (who, what, when), sealed with a hash chain.

The free plugin is a complete disclosure tool for up to 10 images; Pro removes the cap and adds the refinements above.

== Installation ==

1. Upload the `eu-ai-label` folder to `/wp-content/plugins/`, or install through the Plugins screen.
2. Activate the plugin through the *Plugins* screen in WordPress.
3. Open any image in the Media Library and set its *AI label* to AI generated or AI modified.
4. Labeled images get the badge automatically on the front end. There is nothing else to configure.

== Frequently Asked Questions ==

= Does this detect AI images automatically? =

No. Labeling is manual and per-image, by design. You decide what is disclosed.

= How many images can I label for free? =

The free plan labels up to 10 images — enough to disclose AI imagery on a small site and to evaluate the plugin. Pro removes the limit. Labels you have already applied always keep working and stay editable, even if you later drop from Pro back to free.

= Can I change the badge text or its appearance? =

No. The badge ships with a single, fixed, accessibility-first style, and its text is localized automatically. This is deliberate: a consistent, high-contrast disclosure keeps the label reliable across languages and themes, and cannot be accidentally styled into something illegible.

= Which images get the badge? =

Any image attached from your Media Library that you have labeled — whether it is a featured image, appears in a gallery, or is placed in post content as an Image block or classic-editor image. Externally hotlinked images and CSS background images have no attachment record, so they cannot be labeled.

= Is this legal advice or a guarantee of compliance? =

No. EU AI Label is a tool to help you disclose AI-generated imagery clearly. It does not constitute legal advice, and using it does not by itself guarantee compliance with the EU AI Act or any other law. Consult a qualified professional about your obligations.

= What happens to labels when I uninstall? =

Plugin settings are removed on uninstall. Per-image labels stored in attachment metadata are preserved so they survive a reinstall.

== Screenshots ==

1. The AI transparency badge rendered on a front-end image (with the optional Pro alteration tooltip).
2. Setting the AI label and alteration details on an attachment in the Media Library.

== Changelog ==

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
