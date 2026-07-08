<?php
/**
 * Front-end badge renderer.
 *
 * @package EU_AI_Label
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wraps attachment-backed images with a localized AI badge overlay.
 *
 * Two rendering paths give full coverage:
 *  - wp_get_attachment_image (featured images, galleries via the function,
 *    WooCommerce product thumbnails, and any theme/plugin call).
 *  - the_content (in-content Image blocks, gallery blocks, and classic-editor
 *    images), matched by the wp-image-{ID} class WordPress bakes into them.
 */
class EU_AI_Label_Renderer {

	/**
	 * Whether the badge stylesheet has already been enqueued this request.
	 *
	 * @var bool
	 */
	private $style_enqueued = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'wp_get_attachment_image', array( $this, 'wrap_image' ), 10, 5 );
		// Priority 20 runs after do_blocks (9), do_shortcode (11), and wpautop (10),
		// so block/shortcode/classic images are all present and untouched afterward.
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_style' ) );
	}

	/**
	 * Register (but do not force-enqueue) the badge stylesheet.
	 *
	 * @return void
	 */
	public function register_style() {
		$path = EU_AI_LABEL_DIR . 'assets/css/badge.css';

		// In debug/dev, bust the browser cache on every edit by versioning
		// the stylesheet with its file mtime; production keeps the stable
		// plugin version so shared caches/CDNs stay effective.
		$version = EU_AI_LABEL_VERSION;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $path ) ) {
			$version = (string) filemtime( $path );
		}

		wp_register_style(
			'eu-ai-label-badge',
			EU_AI_LABEL_URL . 'assets/css/badge.css',
			array(),
			$version
		);
	}

	/**
	 * Localized badge text for a given status.
	 *
	 * @param string $status Enum status.
	 * @return string
	 */
	public static function badge_text( $status ) {
		switch ( $status ) {
			case EU_AI_Label_Plugin::STATUS_AI_GENERATED:
				return __( 'AI generated', 'eu-ai-label' );
			case EU_AI_Label_Plugin::STATUS_AI_EDITED:
				return __( 'AI modified', 'eu-ai-label' );
			default:
				return '';
		}
	}

	/**
	 * Filter callback: wrap the image HTML when the attachment carries a badge status.
	 *
	 * @param string       $html          Original <img> markup.
	 * @param int          $attachment_id Attachment ID.
	 * @param string|int[] $size          Requested size (unused).
	 * @param bool         $icon          Whether an icon was requested (unused).
	 * @param array        $attr          Image attributes (unused).
	 * @return string
	 */
	public function wrap_image( $html, $attachment_id, $size = '', $icon = false, $attr = array() ) {
		if ( empty( $html ) ) {
			return $html;
		}

		// Admin screens (Media Library list thumbnails, editors) never load the
		// badge stylesheet, so the wrap would spill raw badge text into the UI.
		// Ajax is exempt: some themes render front-end content via admin-ajax.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $html;
		}

		$status = get_post_meta( (int) $attachment_id, EU_AI_Label_Plugin::META_KEY, true );

		// no_ai and missing meta render nothing: zero overhead, original markup untouched.
		if ( ! EU_AI_Label_Plugin::status_has_badge( $status ) ) {
			return $html;
		}

		$text = self::badge_text( $status );
		if ( '' === $text ) {
			return $html;
		}

		// Ensure the stylesheet loads only when a badge is actually emitted.
		if ( ! $this->style_enqueued && ! is_admin() ) {
			wp_enqueue_style( 'eu-ai-label-badge' );
			$this->style_enqueued = true;
		}

		return sprintf(
			'<figure class="eu-ai-label-wrap eu-ai-label-pos--bottom-left">%1$s%2$s</figure>',
			$html,
			$this->badge_markup( $status, $text, (int) $attachment_id )
		);
	}

	/**
	 * Wrap attachment-backed images inside post content with the badge.
	 *
	 * Matches images by the `wp-image-{ID}` class that WordPress adds to
	 * media-library images (block editor, gallery block, and classic editor),
	 * so in-content images get the same disclosure as featured/gallery images.
	 *
	 * @param string $content Post content HTML.
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		// Same admin guard as wrap_image: no stylesheet there, so no wrapping.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $content;
		}

		// Fast path: only attachment-backed images carry a wp-image-{ID} class.
		if ( false === strpos( $content, 'wp-image-' ) || ! class_exists( 'DOMDocument' ) ) {
			return $content;
		}

		$dom          = new DOMDocument( '1.0', 'UTF-8' );
		$libxml_state = libxml_use_internal_errors( true );
		// The XML encoding PI forces UTF-8 parsing so multibyte text is not mangled.
		$loaded = $dom->loadHTML(
			'<?xml encoding="UTF-8" ?><eu-ai-root>' . $content . '</eu-ai-root>',
			LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_state );

		if ( ! $loaded ) {
			return $content;
		}

		$root = $dom->getElementsByTagName( 'eu-ai-root' )->item( 0 );
		if ( null === $root ) {
			return $content;
		}

		$changed = false;

		// Snapshot the list first: the tree is mutated inside the loop.
		foreach ( iterator_to_array( $dom->getElementsByTagName( 'img' ) ) as $img ) {
			if ( $this->within_wrap( $img ) ) {
				continue;
			}

			$class = $img->getAttribute( 'class' );
			if ( ! preg_match( '/(?:^|\s)wp-image-(\d+)(?:\s|$)/', $class, $matches ) ) {
				continue;
			}

			$status = get_post_meta( (int) $matches[1], EU_AI_Label_Plugin::META_KEY, true );
			if ( ! EU_AI_Label_Plugin::status_has_badge( $status ) ) {
				continue;
			}

			$text = self::badge_text( $status );
			if ( '' === $text ) {
				continue;
			}

			$parent = $img->parentNode;
			if ( null === $parent ) {
				continue;
			}

			// Wrap only the <img> in an inline span (valid inside <p>/<figure>),
			// then append the badge as a positioned sibling overlay.
			$wrap = $dom->createElement( 'span' );
			$wrap->setAttribute( 'class', 'eu-ai-label-wrap eu-ai-label-pos--bottom-left' );
			$parent->replaceChild( $wrap, $img );
			$wrap->appendChild( $img );

			$fragment = $dom->createDocumentFragment();
			// badge_markup() is well-formed XML, so appendXML preserves SVG case.
			if ( $fragment->appendXML( $this->badge_markup( $status, $text, (int) $matches[1] ) ) ) {
				$wrap->appendChild( $fragment );
				$changed = true;
			}
		}

		if ( ! $changed ) {
			return $content;
		}

		if ( ! $this->style_enqueued && ! is_admin() ) {
			wp_enqueue_style( 'eu-ai-label-badge' );
			$this->style_enqueued = true;
		}

		$out = '';
		foreach ( $root->childNodes as $node ) {
			$out .= $dom->saveHTML( $node );
		}

		return $out;
	}

	/**
	 * Whether a node already sits inside an EU AI Label wrapper.
	 *
	 * Prevents double-wrapping images that a shortcode already rendered through
	 * wp_get_attachment_image (e.g. the classic [gallery]).
	 *
	 * @param DOMNode $node Node to test.
	 * @return bool
	 */
	private function within_wrap( DOMNode $node ) {
		for ( $parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode ) {
			$class = $parent->getAttribute( 'class' );
			if ( '' !== $class && false !== strpos( $class, 'eu-ai-label-wrap' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the accessible badge markup shared by both rendering paths.
	 *
	 * Exposed to assistive tech as a single labelled image (role="img" +
	 * aria-label) so screen readers announce the disclosure once; the
	 * duplicated visible text is hidden from the accessibility tree. The text
	 * is always rendered (no hover/click) and the style is fixed for contrast.
	 * The output is well-formed XML so it can be parsed by DOMDocument.
	 *
	 * @param string $status        Enum status.
	 * @param string $text          Localized badge text.
	 * @param int    $attachment_id Attachment ID (used by the Pro variant filter).
	 * @return string
	 */
	private function badge_markup( $status, $text, $attachment_id = 0 ) {
		$is_pro = EU_AI_Label_License::is_pro();

		$attrs = array(
			'class'      => 'eu-ai-label-badge eu-ai-label--' . sanitize_html_class( $status ),
			'role'       => 'img',
			'aria-label' => $text,
		);

		if ( $is_pro ) {
			/**
			 * Filter the badge variant on Pro sites.
			 *
			 * The Pro add-on hooks this to return an auto-contrast "adaptive"
			 * variant computed from the underlying image. Free sites never run
			 * this and always use the fixed, WCAG-safe default. Returning
			 * 'default' (or an empty string) emits no extra class.
			 *
			 * @param string $variant       Variant slug.
			 * @param int    $attachment_id Attachment ID.
			 */
			$variant = (string) apply_filters( 'eu_ai_label_badge_variant', 'default', (int) $attachment_id );
			if ( '' !== $variant && 'default' !== $variant ) {
				$attrs['class'] .= ' eu-ai-label--variant-' . sanitize_html_class( $variant );
			}

			/**
			 * Filter the badge span attributes on Pro sites.
			 *
			 * The Pro alteration-details feature adds `tabindex` and
			 * `aria-describedby` here so the badge can anchor an accessible
			 * tooltip. Free sites always emit the fixed attribute set.
			 *
			 * @param array<string,string> $attrs         Attribute name => value map.
			 * @param int                  $attachment_id Attachment ID.
			 * @param string               $status        Enum status.
			 */
			$attrs = (array) apply_filters( 'eu_ai_label_badge_attrs', $attrs, (int) $attachment_id, $status );
		}

		$attr_html = '';
		foreach ( $attrs as $name => $value ) {
			$name = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $name ) );
			if ( '' === $name || ! is_scalar( $value ) ) {
				continue;
			}
			$attr_html .= sprintf( ' %s="%s"', $name, esc_attr( (string) $value ) );
		}

		$markup = sprintf(
			'<span%1$s><span class="eu-ai-label-text" aria-hidden="true">%2$s</span></span>',
			$attr_html,
			esc_html( $text )
		);

		if ( $is_pro ) {
			/**
			 * Filter the final badge markup on Pro sites.
			 *
			 * The Pro alteration-details feature appends a `role="tooltip"`
			 * sibling span here. The returned markup MUST stay well-formed XML:
			 * the content path inserts it via DOMDocumentFragment::appendXML().
			 *
			 * @param string $markup        Badge markup.
			 * @param int    $attachment_id Attachment ID.
			 * @param string $status        Enum status.
			 */
			$markup = (string) apply_filters( 'eu_ai_label_badge_markup', $markup, (int) $attachment_id, $status );
		}

		return $markup;
	}
}
